"""Меховое ателье «Дом Быта» — сайт + админ-панель (FastAPI)."""
import os
import secrets
import shutil

from fastapi import FastAPI, Form, Request, UploadFile, File, Depends, HTTPException
from fastapi.responses import HTMLResponse, RedirectResponse, JSONResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates

from . import db
from .auth import hash_password, verify_password, make_session_token, check_session_token

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOAD_DIR = os.path.join(BASE_DIR, "static", "uploads")
os.makedirs(UPLOAD_DIR, exist_ok=True)

app = FastAPI(title="Меховое ателье «Дом Быта»")
app.mount("/static", StaticFiles(directory=os.path.join(BASE_DIR, "static")), name="static")
templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))

db.init_db()


# ---------- helpers ----------

def get_current_admin(request: Request) -> str | None:
    token = request.cookies.get("atele_admin_session")
    if not token:
        return None
    return check_session_token(token)


def require_admin(request: Request) -> str:
    login = get_current_admin(request)
    if not login:
        raise HTTPException(status_code=303, headers={"Location": "/admin/login"})
    return login


def redirect(url: str, flash: str | None = None) -> RedirectResponse:
    resp = RedirectResponse(url, status_code=303)
    if flash:
        resp.set_cookie("atele_flash", flash, max_age=10, httponly=True)
    return resp


@app.middleware("http")
async def flash_middleware(request: Request, call_next):
    response = await call_next(request)
    flash = request.cookies.get("atele_flash")
    if flash:
        response.delete_cookie("atele_flash")
        response.headers["x-flash"] = flash
    return response


# ---------- публичная часть ----------

@app.get("/", response_class=HTMLResponse)
def index(request: Request):
    s = db.get_all_settings()
    with db.db() as conn:
        services = [dict(r) for r in conn.execute(
            "SELECT * FROM services WHERE is_active=1 ORDER BY sort_order, id")]
        advantages = [dict(r) for r in conn.execute("SELECT * FROM advantages ORDER BY id")]
        gallery = [dict(r) for r in conn.execute(
            "SELECT * FROM gallery_items ORDER BY id DESC LIMIT 6")]
        reviews = [dict(r) for r in conn.execute(
            "SELECT * FROM reviews WHERE is_public=1 ORDER BY created_at DESC LIMIT 6")]
    categories = {}
    for srv in services:
        categories.setdefault(srv["category"], []).append(srv)
    return templates.TemplateResponse("index.html", {
        "request": request, "s": s, "services": services,
        "categories": categories, "advantages": advantages,
        "gallery": gallery, "reviews": reviews,
    })


@app.post("/order")
def create_order(
    name: str = Form(...), phone: str = Form(...), email: str = Form(""),
    service: str = Form(""), message: str = Form(""),
):
    name, phone = name.strip(), phone.strip()
    if not name or not phone:
        return redirect("/?sent=error")
    with db.db() as conn:
        conn.execute(
            "INSERT INTO orders(name, phone, email, service, message) VALUES(?,?,?,?,?)",
            (name, phone, email.strip(), service.strip(), message.strip()),
        )
    return redirect("/?sent=ok#order")


# ---------- админка: авторизация ----------

@app.get("/admin/login", response_class=HTMLResponse)
def login_page(request: Request):
    if get_current_admin(request):
        return RedirectResponse("/admin", status_code=303)
    return templates.TemplateResponse("admin/login.html", {"request": request})


@app.post("/admin/login")
def login(request: Request, login_name: str = Form(...), password: str = Form(...)):
    with db.db() as conn:
        row = conn.execute("SELECT * FROM admins WHERE login=?", (login_name,)).fetchone()
    if not row or not verify_password(password, row["password_hash"]):
        return templates.TemplateResponse(
            "admin/login.html", {"request": request, "error": "Неверный логин или пароль"},
            status_code=401)
    resp = RedirectResponse("/admin", status_code=303)
    resp.set_cookie("atele_admin_session", make_session_token(login_name),
                    httponly=True, samesite="lax", max_age=60 * 60 * 12)
    return resp


@app.get("/admin/logout")
def logout():
    resp = RedirectResponse("/admin/login", status_code=303)
    resp.delete_cookie("atele_admin_session")
    return resp


# ---------- админка: дашборд ----------

@app.get("/admin", response_class=HTMLResponse)
def admin_dashboard(request: Request, admin: str = Depends(require_admin)):
    with db.db() as conn:
        stats = {
            "orders_new": conn.execute("SELECT COUNT(*) c FROM orders WHERE status='new'").fetchone()["c"],
            "orders_total": conn.execute("SELECT COUNT(*) c FROM orders").fetchone()["c"],
            "services": conn.execute("SELECT COUNT(*) c FROM services WHERE is_active=1").fetchone()["c"],
            "gallery": conn.execute("SELECT COUNT(*) c FROM gallery_items").fetchone()["c"],
            "reviews": conn.execute("SELECT COUNT(*) c FROM reviews WHERE is_public=1").fetchone()["c"],
        }
        recent_orders = [dict(r) for r in conn.execute(
            "SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")]
    return templates.TemplateResponse("admin/dashboard.html", {
        "request": request, "admin": admin, "stats": stats, "recent_orders": recent_orders,
    })


# ---------- админка: заявки ----------

ORDERS_STATUS_LABELS = {"new": "Новая", "in_progress": "В работе", "done": "Завершена", "cancelled": "Отклонена"}


@app.get("/admin/orders", response_class=HTMLResponse)
def admin_orders(request: Request, admin: str = Depends(require_admin), status: str = ""):
    with db.db() as conn:
        if status in ORDERS_STATUS_LABELS:
            rows = conn.execute("SELECT * FROM orders WHERE status=? ORDER BY created_at DESC", (status,)).fetchall()
        else:
            rows = conn.execute("SELECT * FROM orders ORDER BY created_at DESC").fetchall()
    return templates.TemplateResponse("admin/orders.html", {
        "request": request, "admin": admin, "orders": [dict(r) for r in rows],
        "status_labels": ORDERS_STATUS_LABELS, "cur_status": status,
    })


@app.post("/admin/orders/{order_id}/status")
def admin_order_status(order_id: int, status: str = Form(...), admin: str = Depends(require_admin)):
    if status not in ORDERS_STATUS_LABELS:
        raise HTTPException(400)
    with db.db() as conn:
        conn.execute("UPDATE orders SET status=? WHERE id=?", (status, order_id))
    return redirect("/admin/orders")


@app.post("/admin/orders/{order_id}/delete")
def admin_order_delete(order_id: int, admin: str = Depends(require_admin)):
    with db.db() as conn:
        conn.execute("DELETE FROM orders WHERE id=?", (order_id,))
    return redirect("/admin/orders")


# ---------- админка: услуги ----------

@app.get("/admin/services", response_class=HTMLResponse)
def admin_services(request: Request, admin: str = Depends(require_admin)):
    with db.db() as conn:
        rows = [dict(r) for r in conn.execute("SELECT * FROM services ORDER BY sort_order, id")]
    return templates.TemplateResponse("admin/services.html", {
        "request": request, "admin": admin, "services": rows,
        "edit_id": None, "edit": None,
    })


def _service_form(id_: int | None, title: str, description: str, price: str, category: str, sort_order: int, is_active: bool):
    with db.db() as conn:
        if id_:
            conn.execute(
                "UPDATE services SET title=?, description=?, price=?, category=?, sort_order=?, is_active=? WHERE id=?",
                (title, description, price, category, sort_order, int(is_active), id_))
        else:
            conn.execute(
                "INSERT INTO services(title, description, price, category, sort_order, is_active) VALUES(?,?,?,?,?,?)",
                (title, description, price, category, sort_order, int(is_active)))


@app.post("/admin/services/save")
def admin_service_save(
    admin: str = Depends(require_admin),
    id: int = Form(0), title: str = Form(...), description: str = Form(""),
    price: str = Form(""), category: str = Form("Пошив"),
    sort_order: int = Form(0), is_active: str | None = Form(None),
):
    if not title.strip():
        raise HTTPException(400, "Название обязательно")
    _service_form(id or None, title.strip(), description.strip(), price.strip(),
                  category.strip(), sort_order, bool(is_active))
    return redirect("/admin/services")


@app.post("/admin/services/{service_id}/delete")
def admin_service_delete(service_id: int, admin: str = Depends(require_admin)):
    with db.db() as conn:
        conn.execute("DELETE FROM services WHERE id=?", (service_id,))
    return redirect("/admin/services")


# ---------- админка: галерея ----------

ALLOWED_IMAGE_EXT = {".jpg", ".jpeg", ".png", ".webp", ".gif", ".avif"}


def _save_upload(file: UploadFile) -> str | None:
    if not file or not file.filename:
        return None
    ext = os.path.splitext(file.filename)[1].lower()
    if ext not in ALLOWED_IMAGE_EXT:
        return None
    fname = f"{secrets.token_hex(8)}{ext}"
    path = os.path.join(UPLOAD_DIR, fname)
    with open(path, "wb") as out:
        shutil.copyfileobj(file.file, out)
    return f"/static/uploads/{fname}"


@app.get("/admin/gallery", response_class=HTMLResponse)
def admin_gallery(request: Request, admin: str = Depends(require_admin)):
    with db.db() as conn:
        rows = [dict(r) for r in conn.execute("SELECT * FROM gallery_items ORDER BY id DESC")]
    return templates.TemplateResponse("admin/gallery.html", {
        "request": request, "admin": admin, "items": rows,
    })


@app.post("/admin/gallery/save")
def admin_gallery_save(
    admin: str = Depends(require_admin),
    id: int = Form(0), title: str = Form(...), before_text: str = Form(""),
    after_text: str = Form(""), price: str = Form(""), image: UploadFile | None = File(None),
):
    if not title.strip():
        raise HTTPException(400, "Название обязательно")
    img_path = _save_upload(image) if image else None
    with db.db() as conn:
        if id:
            if img_path:
                conn.execute("UPDATE gallery_items SET title=?, before_text=?, after_text=?, price=?, image_path=? WHERE id=?",
                             (title, before_text, after_text, price, img_path, id))
            else:
                conn.execute("UPDATE gallery_items SET title=?, before_text=?, after_text=?, price=? WHERE id=?",
                             (title, before_text, after_text, price, id))
        else:
            conn.execute(
                "INSERT INTO gallery_items(title, before_text, after_text, price, image_path) VALUES(?,?,?,?,?)",
                (title, before_text, after_text, price, img_path or ""))
    return redirect("/admin/gallery")


@app.post("/admin/gallery/{item_id}/delete")
def admin_gallery_delete(item_id: int, admin: str = Depends(require_admin)):
    with db.db() as conn:
        row = conn.execute("SELECT image_path FROM gallery_items WHERE id=?", (item_id,)).fetchone()
        conn.execute("DELETE FROM gallery_items WHERE id=?", (item_id,))
    if row and row["image_path"]:
        p = os.path.join(BASE_DIR, "..", row["image_path"].lstrip("/"))
        try:
            os.remove(p)
        except OSError:
            pass
    return redirect("/admin/gallery")


# ---------- админка: отзывы ----------

@app.get("/admin/reviews", response_class=HTMLResponse)
def admin_reviews(request: Request, admin: str = Depends(require_admin)):
    with db.db() as conn:
        rows = [dict(r) for r in conn.execute("SELECT * FROM reviews ORDER BY created_at DESC")]
    return templates.TemplateResponse("admin/reviews.html", {
        "request": request, "admin": admin, "reviews": rows,
    })


@app.post("/admin/reviews/save")
def admin_review_save(
    admin: str = Depends(require_admin),
    id: int = Form(0), author: str = Form(...), rating: int = Form(5),
    text: str = Form(...), is_public: str | None = Form(None),
):
    if not author.strip() or not text.strip():
        raise HTTPException(400, "Автор и текст обязательны")
    rating = max(1, min(5, rating))
    with db.db() as conn:
        if id:
            conn.execute("UPDATE reviews SET author=?, rating=?, text=?, is_public=? WHERE id=?",
                         (author, rating, text, int(bool(is_public)), id))
        else:
            conn.execute("INSERT INTO reviews(author, rating, text, is_public) VALUES(?,?,?,?)",
                         (author, rating, text, int(bool(is_public))))
    return redirect("/admin/reviews")


@app.post("/admin/reviews/{review_id}/delete")
def admin_review_delete(review_id: int, admin: str = Depends(require_admin)):
    with db.db() as conn:
        conn.execute("DELETE FROM reviews WHERE id=?", (review_id,))
    return redirect("/admin/reviews")


# ---------- админка: настройки ----------

SETTING_FIELDS = [
    ("site_name", "Название сайта"),
    ("tagline", "Короткий слоган"),
    ("hero_title", "Заголовок на первом экране"),
    ("hero_subtitle", "Подзаголовок на первом экране"),
    ("about_text", "Текст раздела «О нас»"),
    ("manager_name", "Имя руководителя"),
    ("manager_role", "Должность руководителя"),
    ("manager_note", "Примечание о руководителе"),
    ("address", "Адрес"),
    ("phone", "Телефон ателье"),
    ("phone_tc", "Телефон ТЦ"),
    ("email", "E-mail"),
    ("hours_week", "График (будни)"),
    ("hours_weekend", "График (выходные)"),
]


@app.get("/admin/settings", response_class=HTMLResponse)
def admin_settings(request: Request, admin: str = Depends(require_admin)):
    s = db.get_all_settings()
    return templates.TemplateResponse("admin/settings.html", {
        "request": request, "admin": admin, "s": s, "fields": SETTING_FIELDS,
    })


@app.post("/admin/settings")
async def admin_settings_save(request: Request, admin: str = Depends(require_admin)):
    form = await request.form()
    for key, _label in SETTING_FIELDS:
        if key in form:
            db.set_setting(key, str(form[key]).strip())
    return redirect("/admin/settings?saved=1")


# ---------- админка: смена пароля ----------

@app.get("/admin/password", response_class=HTMLResponse)
def admin_password_page(request: Request, admin: str = Depends(require_admin)):
    return templates.TemplateResponse("admin/password.html", {"request": request, "admin": admin})


@app.post("/admin/password")
def admin_password_change(
    old: str = Form(...), new: str = Form(...), confirm: str = Form(...),
    admin: str = Depends(require_admin),
):
    if new != confirm or len(new) < 6:
        return redirect("/admin/password?err=short")
    with db.db() as conn:
        row = conn.execute("SELECT * FROM admins WHERE login=?", (admin,)).fetchone()
        if not row or not verify_password(old, row["password_hash"]):
            return redirect("/admin/password?err=old")
        conn.execute("UPDATE admins SET password_hash=? WHERE id=?", (row["id"], hash_password(new)))
    return redirect("/admin/password?ok=1")


@app.exception_handler(HTTPException)
async def http_exc_handler(request: Request, exc: HTTPException):
    if exc.status_code == 303:
        return RedirectResponse(exc.headers.get("Location", "/"), status_code=303)
    return JSONResponse({"detail": exc.detail}, status_code=exc.status_code)
