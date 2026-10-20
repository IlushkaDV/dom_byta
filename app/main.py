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

    # Четыре направления ателье -> вкладки с ценами. Каждая реальная категория
    # прейскуранта попадает ровно в одну вкладку (без дублей).
    from .db import DEFAULT_DIRECTIONS, DEFAULT_STEPS
    groups = {
        "meh": ["Пошив меха", "Пошив меха с перекроем", "Ремонт меха"],
        "kozha": ["Пошив кожи", "Ремонт кожи и дублёнок"],
        "poshiv": ["Пошив верхней одежды", "Пошив лёгкого платья"],
        "remont": ["Ремонт верхней одежды", "Ремонт лёгкой одежды"],
    }
    by_cat: dict[str, list] = {}
    for srv in services:
        by_cat.setdefault(srv["category"], []).append(srv)
    used = {c for lst in groups.values() for c in lst}
    leftover = [srv for cat, items in by_cat.items() if cat not in used for srv in items]
    if leftover:
        groups.setdefault("remont", [])
        groups["remont"] = groups["remont"]  # существующие категории уже включены
    price_tabs = []
    for d in DEFAULT_DIRECTIONS:
        items = [srv for cat in groups.get(d["tab"], []) for srv in by_cat.get(cat, [])]
        if d["tab"] == "remont" and leftover:
            items = items + leftover
        if items:
            price_tabs.append({"dir": d, "items": items})

    # Карточки направлений: фото из настроек (загружаются в админке), при пустой
    # настройке — первое фото из галереи той же категории; если и там пусто —
    # любое фото галереи, чтобы карточка не оставалась графической заглушкой.
    directions = []
    gal_imgs = [g["before_image_path"] or g["image_path"] for g in gallery]
    gal_imgs += [g["after_image_path"] for g in gallery]
    fallback_img = next((p for p in gal_imgs if p), "")
    for d in DEFAULT_DIRECTIONS:
        dd = dict(d)
        img = (s.get(d["image_key"]) or "").strip()
        if not img:
            with db.db() as conn:
                row = conn.execute(
                    "SELECT image_path, before_image_path FROM gallery_items "
                    "WHERE category=? ORDER BY id DESC LIMIT 1", (d["title"],)).fetchone()
            if row:
                img = row["image_path"] or row["before_image_path"] or ""
        if not img:
            img = fallback_img
        dd["image"] = img
        directions.append(dd)

    return templates.TemplateResponse(request, "index.html", context={
        "s": s, "services": services,
        "advantages": advantages, "gallery": gallery, "reviews": reviews,
        "directions": directions, "steps": DEFAULT_STEPS,
        "price_tabs": price_tabs,
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
    return templates.TemplateResponse(request, "admin/login.html")


@app.post("/admin/login")
def login(request: Request, login_name: str = Form(...), password: str = Form(...)):
    with db.db() as conn:
        row = conn.execute("SELECT * FROM admins WHERE login=?", (login_name,)).fetchone()
    if not row or not verify_password(password, row["password_hash"]):
        return templates.TemplateResponse(
            request, "admin/login.html",
            context={"error": "Неверный логин или пароль"},
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
    return templates.TemplateResponse(request, "admin/dashboard.html", context={
        "admin": admin, "stats": stats, "recent_orders": recent_orders,
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
    return templates.TemplateResponse(request, "admin/orders.html", context={
        "admin": admin, "orders": [dict(r) for r in rows],
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
    return templates.TemplateResponse(request, "admin/services.html", context={
        "admin": admin, "services": rows,
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


# ---------- админка: условия и надбавки (карточки + прейскурант) ----------

@app.get("/admin/advantages", response_class=HTMLResponse)
def admin_advantages(request: Request, admin: str = Depends(require_admin)):
    s = db.get_all_settings()
    with db.db() as conn:
        rows = [dict(r) for r in conn.execute("SELECT * FROM advantages ORDER BY id")]
    surcharge_keys = [(f"prices_surcharge_{i}", f"Надбавка {i}") for i in range(1, 10)]
    note_keys = [("prices_note_1", "Примечание к ценам 1"), ("prices_note_2", "Примечание к ценам 2")]
    return templates.TemplateResponse(request, "admin/advantages.html", context={
        "admin": admin, "advantages": rows, "s": s,
        "text_fields": note_keys + surcharge_keys,
    })


@app.post("/admin/advantages/save")
def admin_advantage_save(
    admin: str = Depends(require_admin),
    id: int = Form(0), icon: str = Form(""), title: str = Form(...), text: str = Form(""),
):
    if not title.strip():
        raise HTTPException(400, "Название обязательно")
    with db.db() as conn:
        if id:
            conn.execute("UPDATE advantages SET icon=?, title=?, text=? WHERE id=?",
                         (icon.strip(), title.strip(), text.strip(), id))
        else:
            conn.execute("INSERT INTO advantages(icon, title, text) VALUES(?,?,?)",
                         (icon.strip(), title.strip(), text.strip()))
    return redirect("/admin/advantages")


@app.post("/admin/advantages/{adv_id}/delete")
def admin_advantage_delete(adv_id: int, admin: str = Depends(require_admin)):
    with db.db() as conn:
        conn.execute("DELETE FROM advantages WHERE id=?", (adv_id,))
    return redirect("/admin/advantages")


@app.post("/admin/advantages/text")
async def admin_advantage_text_save(
    request: Request, admin: str = Depends(require_admin),
):
    form = await request.form()
    for k in form.keys():
        if k.startswith("prices_note_") or k.startswith("prices_surcharge_"):
            db.set_setting(k, str(form[k]).strip())
    return redirect("/admin/advantages")


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
    return templates.TemplateResponse(request, "admin/gallery.html", context={
        "admin": admin, "items": rows,
    })


async def _pick_upload(request_data, name: str) -> str | None:
    """Сохранить загруженное фото; None — если файл не выбран (пустой input)."""
    val = (request_data or {}).get(name)
    if val is None or isinstance(val, str):
        return None
    filename = getattr(val, "filename", "") or ""
    if not filename:
        return None
    ext = os.path.splitext(filename)[1].lower()
    if ext not in ALLOWED_IMAGE_EXT:
        return None
    fname = f"{secrets.token_hex(8)}{ext}"
    path = os.path.join(UPLOAD_DIR, fname)
    with open(path, "wb") as out:
        shutil.copyfileobj(val.file, out)
    return f"/static/uploads/{fname}"


def _remove_upload(path: str | None) -> None:
    """Удалить сохранённый файл загрузки (по URL-пути из настроек/БД)."""
    if not path or not path.startswith("/static/uploads/"):
        return
    try:
        os.remove(os.path.join(BASE_DIR, "static", path[len("/static/"):]))
    except OSError:
        pass


@app.post("/admin/gallery/save")
async def admin_gallery_save(
    request: Request,
    admin: str = Depends(require_admin),
):
    form = await request.form()
    title = (form.get("title") or "").strip()
    if not title:
        raise HTTPException(400, "Название обязательно")
    id = int(form.get("id") or 0)
    before_text = form.get("before_text") or ""
    after_text = form.get("after_text") or ""
    price = form.get("price") or ""
    category = (form.get("category") or "").strip()
    img_path = await _pick_upload(form, "image")
    before_path = await _pick_upload(form, "before_image")
    after_path = await _pick_upload(form, "after_image")
    with db.db() as conn:
        if id:
            row = conn.execute("SELECT * FROM gallery_items WHERE id=?", (id,)).fetchone()
            if not row:
                raise HTTPException(404, "Работа не найдена")
            new_img = img_path if img_path is not None else row["image_path"]
            new_before = before_path if before_path is not None else row["before_image_path"]
            new_after = after_path if after_path is not None else row["after_image_path"]
            # обратная совместимость: одно «общее» фото подставляется в пустые слоты до/после
            if not new_before and not new_after and new_img:
                new_before, new_after = new_img, new_img
            conn.execute(
                "UPDATE gallery_items SET title=?, before_text=?, after_text=?, price=?, "
                "image_path=?, before_image_path=?, after_image_path=?, category=? WHERE id=?",
                (title, before_text, after_text, price, new_img, new_before, new_after, category, id))
        else:
            b = before_path or ""
            a = after_path or ""
            if not b and not a and img_path:
                b = a = img_path
            conn.execute(
                "INSERT INTO gallery_items(title, before_text, after_text, price, image_path, "
                "before_image_path, after_image_path, category) VALUES(?,?,?,?,?,?,?,?)",
                (title, before_text, after_text, price, img_path or "", b, a, category))
    # Автозаполнение текстов «До»/«После», если поля оставлены пустыми
    with db.db() as conn:
        for r in conn.execute("SELECT id, title, before_text, after_text FROM gallery_items").fetchall():
            bt = (r["before_text"] or "").strip()
            at = (r["after_text"] or "").strip()
            nbt, nat = _clean_work_texts(bt, at, r["title"])
            if (nbt, nat) != (bt, at):
                conn.execute("UPDATE gallery_items SET before_text=?, after_text=? WHERE id=?",
                             (nbt, nat, r["id"]))
    return redirect("/admin/gallery")


def _clean_work_texts(before: str, after: str, title: str) -> tuple[str, str]:
    """Нормализуем подписи работ в галерее.

    1. Срезаем дублирующиеся префиксы «До:» / «После:» из текстов — на витрине
       они и так показаны плашками-метками.
    2. Текст после, начинающийся со срока («Готово через…», «Срок — …»),
       помечаем меткой «Срок:» вместо бессмысленного «После».
    3. Пустые поля заполняем подсказками по названию работы.
    """
    import re
    before = re.sub(r"^\s*до\s*[::]\s*", "", before, flags=re.I).strip()
    after = re.sub(r"^\s*после\s*[::]\s*", "", after, flags=re.I).strip()
    if after and not after.startswith("Срок"):
        m = re.match(r"^готово\s+через\s+(.+)$", after, flags=re.I)
        if m:
            after = f"Срок — {m.group(1)}"
        elif re.match(r"^срок\b", after, flags=re.I):
            after = "Срок" + after[4:]
    return before, after


@app.post("/admin/gallery/migrate")
def admin_gallery_migrate(admin: str = Depends(require_admin)):
    """Разово перенести старые одиночные фото в слоты «До»/«После»."""
    moved = 0
    with db.db() as conn:
        rows = conn.execute(
            "SELECT id, image_path, before_image_path, after_image_path FROM gallery_items"
        ).fetchall()
        for r in rows:
            b, a = r["before_image_path"] or "", r["after_image_path"] or ""
            img = r["image_path"] or ""
            if img and (not b or not a):
                nb = b or img
                na = a or img
                if (nb, na) != (b, a):
                    conn.execute("UPDATE gallery_items SET before_image_path=?, after_image_path=? WHERE id=?",
                                 (nb, na, r["id"]))
                    moved += 1
    return redirect("/admin/gallery")


@app.post("/admin/gallery/{item_id}/delete")
def admin_gallery_delete(item_id: int, admin: str = Depends(require_admin)):
    with db.db() as conn:
        row = conn.execute("SELECT image_path, before_image_path, after_image_path FROM gallery_items WHERE id=?",
                           (item_id,)).fetchone()
        conn.execute("DELETE FROM gallery_items WHERE id=?", (item_id,))
    if row:
        for path in (row["image_path"], row["before_image_path"], row["after_image_path"]):
            if path:
                p = os.path.join(BASE_DIR, "..", path.lstrip("/"))
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
    return templates.TemplateResponse(request, "admin/reviews.html", context={
        "admin": admin, "reviews": rows,
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
    ("tagline", "Короткий слоган (meta-описание)"),
    ("hero_kicker", "Надзаголовок на первом экране"),
    ("hero_title", "Заголовок на первом экране"),
    ("hero_subtitle", "Подзаголовок на первом экране"),
    ("about_text", "Текст раздела «О нас» (резервный)"),
    ("manager_title", "Заголовок блока «Лицо ателье»"),
    ("manager_quote", "Цитата заведующей (блок «Лицо ателье»)"),
    ("manager_photo", "Фото заведующей (блок «Лицо ателье»)"),
    ("manager_signature", "Рукописная подпись заведующей (фото подписи на белом листе)"),
    ("hero_image", "Фоновое фото первого экрана (желательно ≥1600px)"),
    ("dir_img_meh", "Фото карточки «Меховое ателье» (блок «Что мы делаем»)"),
    ("dir_img_kozha", "Фото карточки «Кожа и дублёнки»"),
    ("dir_img_poshiv", "Фото карточки «Верхняя одежда и лёгкое платье»"),
    ("dir_img_remont", "Фото карточки «Ремонт и трикотаж»"),
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

# настройки-картинки: сохраняются через загрузку файла, а не текстовым полем
IMAGE_SETTINGS = {"manager_photo", "manager_signature", "hero_image",
                  "dir_img_meh", "dir_img_kozha", "dir_img_poshiv", "dir_img_remont"}


def _trace_signature(path: str) -> None:
    """Для загруженного фото подписи: убираем белый фон (делаем прозрачным),
    поджигаем чёрнила и обрезаем пустые поля. При неудаче молча пропускаем."""
    try:
        from PIL import Image, ImageChops
        src_path = os.path.normpath(os.path.join(BASE_DIR, path.lstrip("/")))
        img = Image.open(src_path).convert("RGB")
        w, h = img.size
        if w < 60 or h < 40:
            return
        gray = img.convert("L")
        # бинаризация по порогу: тёмное = чернила
        lut = []
        for v in range(256):
            a = max(0, min(255, int((185 - v) * 3)))
            lut.extend([a])
        alpha = gray.point(lut)
        rgba = img.convert("RGBA")
        rgba.putalpha(alpha)
        # цвет чернил — графитовый
        r, g, b, a = rgba.split()
        ink = Image.new("RGB", (w, h), (38, 38, 46))
        out_img = Image.merge("RGBA", (*ink.split(), a))
        # авто-обрезка по содержимому
        bbox = a.getbbox()
        if bbox and bbox[2] - bbox[0] > 40 and bbox[3] - bbox[1] > 20:
            pad = 12
            x0 = max(0, bbox[0] - pad); y0 = max(0, bbox[1] - pad)
            x1 = min(w, bbox[2] + pad); y1 = min(h, bbox[3] + pad)
            out_img = out_img.crop((x0, y0, x1, y1))
        out = src_path.rsplit(".", 1)[0] + "-ink.png"
        out_img.save(out)
        db.set_setting("manager_signature", path.rsplit(".", 1)[0] + "-ink.png")
    except Exception:
        pass


@app.get("/admin/settings", response_class=HTMLResponse)
def admin_settings(request: Request, admin: str = Depends(require_admin)):
    s = db.get_all_settings()
    return templates.TemplateResponse(request, "admin/settings.html", context={
        "admin": admin, "s": s, "fields": SETTING_FIELDS, "image_keys": IMAGE_SETTINGS,
    })


@app.post("/admin/settings")
async def admin_settings_save(request: Request, admin: str = Depends(require_admin)):
    form = await request.form()
    for key, _label in SETTING_FIELDS:
        if key in IMAGE_SETTINGS:
            new_path = await _pick_upload(form, key)
            if new_path:
                old = db.get_all_settings().get(key, "")
                db.set_setting(key, new_path)
                _remove_upload(old)  # чистим старый файл
                if key == "manager_signature":
                    _trace_signature(new_path)
            remove_flag = str(form.get(f"{key}_remove") or "").strip()
            if remove_flag:
                old = db.get_all_settings().get(key, "")
                db.set_setting(key, "")
                _remove_upload(old)
                if key == "manager_signature":
                    db.set_setting("manager_signature_mask", "")
        elif key in form:
            db.set_setting(key, str(form[key]).strip())
    return redirect("/admin/settings?saved=1")


# ---------- админка: смена пароля ----------

@app.get("/admin/password", response_class=HTMLResponse)
def admin_password_page(request: Request, admin: str = Depends(require_admin)):
    return templates.TemplateResponse(request, "admin/password.html", context={"admin": admin})


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
