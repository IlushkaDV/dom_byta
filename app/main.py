"""Меховое ателье «Дом Быта» — сайт + админ-панель (FastAPI)."""
import hashlib
import hmac
import os
import secrets
import shutil
from datetime import datetime

from fastapi import FastAPI, Form, Request, UploadFile, File, Depends, HTTPException
from fastapi.responses import HTMLResponse, RedirectResponse, JSONResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates

from . import db
from .auth import (hash_password, verify_password, make_session_token,
                   check_session_token, secret_bytes as _secret_bytes)

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
UPLOAD_DIR = os.path.join(BASE_DIR, "static", "uploads")
os.makedirs(UPLOAD_DIR, exist_ok=True)

app = FastAPI(title="Ателье", docs_url=None, redoc_url=None, openapi_url=None)
app.mount("/static", StaticFiles(directory=os.path.join(BASE_DIR, "static")), name="static")
templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))

db.init_db()

# ---------- базовые настройки безопасности ----------

MAX_UPLOAD_BYTES = 15 * 1024 * 1024  # 15 МБ на файл


def _security_headers(resp):
    resp.headers.setdefault("X-Content-Type-Options", "nosniff")
    resp.headers.setdefault("X-Frame-Options", "DENY")
    resp.headers.setdefault("Referrer-Policy", "strict-origin-when-cross-origin")
    resp.headers.setdefault(
        "Permissions-Policy", "camera=(), microphone=(), geolocation=()")
    # CSP: только свои ресурсы; inline-скрипты/стили сайта включены через
    # 'unsafe-inline' (используются в шаблонах), внешние — шрифты Google и карты Яндекса.
    csp = ("default-src 'self'; "
           "script-src 'self' 'unsafe-inline' https://api-maps.yandex.ru; "
           "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
           "font-src 'self' https://fonts.gstatic.com; "
           "img-src 'self' data: blob: https://*.yandex.ru https://*.ytimg.ru; "
           "frame-src 'self' https://yandex.ru https://maps.yandex.ru; "
           "connect-src 'self' https://api-maps.yandex.ru; "
           "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'")
    resp.headers.setdefault("Content-Security-Policy", csp)
    return resp


# ---------- защита от подбора пароля (rate limiting) ----------

import time as _time
from collections import defaultdict as _defaultdict

_LOGIN_WINDOW = 15 * 60       # окно наблюдения, сек
_LOGIN_MAX_FAILS = 8          # максимум неудачных попыток за окно
_login_attempts: dict = _defaultdict(list)


def _login_blocked(ip: str) -> bool:
    now = _time.time()
    attempts = [t for t in _login_attempts[ip] if now - t < _LOGIN_WINDOW]
    _login_attempts[ip] = attempts
    return len(attempts) >= _LOGIN_MAX_FAILS


def _login_fail(ip: str) -> None:
    _login_attempts[ip].append(_time.time())
    # защита от разрастания памяти: чистим старые записи
    if len(_login_attempts) > 5000:
        cutoff = _time.time() - _LOGIN_WINDOW
        for k in list(_login_attempts.keys()):
            v = [t for t in _login_attempts[k] if t > cutoff]
            if v:
                _login_attempts[k] = v
            else:
                del _login_attempts[k]


def _login_ok(ip: str) -> None:
    _login_attempts.pop(ip, None)


def _client_ip(request: Request) -> str:
    # если сайт стоит за nginx — берём реальный IP из заголовка
    fwd = request.headers.get("x-forwarded-for", "")
    if fwd:
        return fwd.split(",")[0].strip()
    return request.client.host if request.client else "?"


# ---------- CSRF-защита админки ----------
# Сессия на cookie + SameSite=Lax, но дополнительно проверяем токен формы.

def _csrf_token(admin_login: str) -> str:
    payload = f"csrf|{admin_login}"
    sig = hmac.new(_secret_bytes(), payload.encode(), hashlib.sha256).hexdigest()
    return sig[:32]


def _check_csrf(request: Request, form_token: str | None) -> bool:
    admin = get_current_admin(request)
    if not admin:
        return False
    return hmac.compare_digest(form_token or "", _csrf_token(admin))


def require_csrf(request: Request, csrf_check: str | None = Form(None, alias="_csrf")) -> None:
    if not _check_csrf(request, csrf_check):
        raise HTTPException(400, "Сессия устарела, обновите страницу")


# ---------- скрытый адрес админки ----------
# Админка доступна только по секретному пути /admin-XXXXXX. Обычный /admin...
# отдаёт 404 — сканеры и случайные посетители не видят страницу входа.
# Секрет задаётся переменной окружения ATELLE_ADMIN_PATH (например "kabinet7"),
# иначе генерируется случайно и сохраняется в data/.admin_path — адрес
# печатается в консоли при запуске сервера.

_DATA_DIR = os.path.abspath(os.path.join(BASE_DIR, "..", "data"))
_ADMIN_PATH_FILE = os.path.join(_DATA_DIR, ".admin_path")


def _load_admin_secret() -> str:
    env = os.environ.get("ATELLE_ADMIN_PATH", "").strip().strip("/")
    if env:
        return env
    try:
        with open(_ADMIN_PATH_FILE, "r", encoding="utf-8") as f:
            saved = f.read().strip()
        if saved:
            return saved
    except OSError:
        pass
    secret = "admin-" + secrets.token_hex(4)
    os.makedirs(_DATA_DIR, exist_ok=True)
    with open(_ADMIN_PATH_FILE, "w", encoding="utf-8") as f:
        f.write(secret)
    try:
        os.chmod(_ADMIN_PATH_FILE, 0o600)
    except OSError:
        pass
    return secret


ADMIN_SECRET = _load_admin_secret()
ADMIN_BASE = "/" + ADMIN_SECRET  # например /admin-k7f3a9c2

templates.env.globals["admin_base"] = ADMIN_BASE  # доступно во всех шаблонах как {{ admin_base }}


def _is_admin_url(path: str) -> bool:
    """Путь относится к админке: точный ADMIN_BASE или ADMIN_BASE/что-то."""
    p = path.rstrip("/") or "/"
    base = ADMIN_BASE.rstrip("/")
    return p == base or p.startswith(base + "/")


# ---------- helpers ----------

def get_current_admin(request: Request) -> str | None:
    token = request.cookies.get("atele_admin_session")
    if not token:
        return None
    return check_session_token(token)


def require_admin(request: Request) -> str:
    login = get_current_admin(request)
    if not login:
        raise HTTPException(status_code=303, headers={"Location": ADMIN_BASE + "/login"})
    return login


def redirect(url: str, flash: str | None = None) -> RedirectResponse:
    resp = RedirectResponse(url, status_code=303)
    if flash:
        resp.set_cookie("atele_flash", flash, max_age=10, httponly=True)
    return resp


@app.middleware("http")
async def flash_middleware(request: Request, call_next):
    # Скрытая админка: обычный /admin... не существует, только секретный путь.
    path = request.url.path
    if (path == "/admin" or path.startswith("/admin/")) and not _is_admin_url(path):
        return _security_headers(
            JSONResponse({"detail": "Not Found"}, status_code=404))
    # CSRF-токен для админ-форм (доступен в шаблонах как request.state.csrf)
    request.state.csrf = ""
    adm = get_current_admin(request)
    if adm:
        request.state.csrf = _csrf_token(adm)
    try:
        response = await call_next(request)
    except HTTPException as exc:
        response = JSONResponse({"detail": exc.detail}, status_code=exc.status_code,
                                headers=dict(exc.headers or {}))
    flash = request.cookies.get("atele_flash")
    if flash:
        response.delete_cookie("atele_flash")
        response.headers["x-flash"] = flash
    return _security_headers(response)


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
    request: Request,
    name: str = Form(...), phone: str = Form(...), email: str = Form(""),
    service: str = Form(""), message: str = Form(""),
    visit_date: str = Form(""), visit_time: str = Form(""),
    consent: str = Form(""),
):
    name, phone = name.strip(), phone.strip()
    if not name or not phone:
        return redirect("/?sent=error#order")
    if not consent:  # без согласия на обработку ПД заявку не принимаем (152-ФЗ)
        return redirect("/?sent=noconsent#order")
    ip = _client_ip(request)
    with db.db() as conn:
        conn.execute(
            "INSERT INTO orders(name, phone, email, service, message, "
            "visit_date, visit_time, pd_consent_at) VALUES(?,?,?,?,?,?,?,?)",
            (name, phone, email.strip(), service.strip(), message.strip(),
             visit_date.strip(), visit_time.strip(),
             f"{datetime.now().strftime('%Y-%m-%d %H:%M')} с IP {ip}"),
        )
    return redirect("/?sent=ok#order")


# ---------- админка: авторизация ----------

@app.get(ADMIN_BASE + "/login", response_class=HTMLResponse)
def login_page(request: Request):
    if get_current_admin(request):
        return RedirectResponse(f"{ADMIN_BASE}", status_code=303)
    return templates.TemplateResponse(request, "admin/login.html")


@app.post(ADMIN_BASE + "/login")
def login(request: Request, login_name: str = Form(...), password: str = Form(...)):
    ip = _client_ip(request)
    if _login_blocked(ip):
        return templates.TemplateResponse(
            request, "admin/login.html",
            context={"error": "Слишком много неудачных попыток. Подождите 15 минут."},
            status_code=429)
    with db.db() as conn:
        row = conn.execute("SELECT * FROM admins WHERE login=?", (login_name,)).fetchone()
    if not row or not verify_password(password, row["password_hash"]):
        _login_fail(ip)
        return templates.TemplateResponse(
            request, "admin/login.html",
            context={"error": "Неверный логин или пароль"},
            status_code=401)
    _login_ok(ip)
    resp = RedirectResponse(f"{ADMIN_BASE}", status_code=303)
    resp.set_cookie("atele_admin_session", make_session_token(login_name),
                    httponly=True, secure=request.url.scheme == "https",
                    samesite="lax", max_age=60 * 60 * 12)
    return resp


# ---------- страница «Политика обработки персональных данных» (152-ФЗ) ----------

POLICY_TITLE = "Политика обработки персональных данных"


@app.get("/privacy", response_class=HTMLResponse)
def privacy(request: Request):
    s = db.get_all_settings()
    return templates.TemplateResponse(request, "privacy.html", context={
        "s": s, "policy_updated": "01.10.2026", "POLICY_TITLE": POLICY_TITLE,
        "admin": get_current_admin(request),
    })


@app.get(ADMIN_BASE + "/logout", response_class=HTMLResponse)
def logout():
    resp = RedirectResponse(f"{ADMIN_BASE}/login", status_code=303)
    resp.delete_cookie("atele_admin_session")
    return resp


# ---------- админка: дашборд ----------

@app.get(ADMIN_BASE, response_class=HTMLResponse)
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


@app.get(ADMIN_BASE + "/orders", response_class=HTMLResponse)
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


@app.post(ADMIN_BASE + "/orders/{order_id}/status")
def admin_order_status(order_id: int, request: Request, status: str = Form(...), admin: str = Depends(require_admin), _: None = Depends(require_csrf)):
    if status not in ORDERS_STATUS_LABELS:
        raise HTTPException(400)
    with db.db() as conn:
        conn.execute("UPDATE orders SET status=? WHERE id=?", (status, order_id))
    return redirect(f"{ADMIN_BASE}/orders")


@app.post(ADMIN_BASE + "/orders/{order_id}/delete")
def admin_order_delete(order_id: int, request: Request, admin: str = Depends(require_admin), _csrf_ok: None = Depends(require_csrf)):
    with db.db() as conn:
        conn.execute("DELETE FROM orders WHERE id=?", (order_id,))
    return redirect(f"{ADMIN_BASE}/orders")


# ---------- админка: услуги ----------

@app.get(ADMIN_BASE + "/services", response_class=HTMLResponse)
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


@app.post(ADMIN_BASE + "/services/save")
def admin_service_save(
    request: Request,
    admin: str = Depends(require_admin),
    id: int = Form(0), title: str = Form(...), description: str = Form(""),
    price: str = Form(""), category: str = Form("Пошив"),
    sort_order: int = Form(0), is_active: str | None = Form(None),
    _csrf_ok: None = Depends(require_csrf),
):
    if not title.strip():
        raise HTTPException(400, "Название обязательно")
    _service_form(id or None, title.strip(), description.strip(), price.strip(),
                  category.strip(), sort_order, bool(is_active))
    return redirect(f"{ADMIN_BASE}/services")


@app.post(ADMIN_BASE + "/services/{service_id}/delete")
def admin_service_delete(service_id: int, request: Request, admin: str = Depends(require_admin), _csrf_ok: None = Depends(require_csrf)):
    with db.db() as conn:
        conn.execute("DELETE FROM services WHERE id=?", (service_id,))
    return redirect(f"{ADMIN_BASE}/services")


# ---------- админка: условия и надбавки (карточки + прейскурант) ----------

@app.get(ADMIN_BASE + "/advantages", response_class=HTMLResponse)
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


@app.post(ADMIN_BASE + "/advantages/save")
def admin_advantage_save(
    request: Request,
    admin: str = Depends(require_admin),
    id: int = Form(0), icon: str = Form(""), title: str = Form(...), text: str = Form(""),
    _csrf_ok: None = Depends(require_csrf),
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
    return redirect(f"{ADMIN_BASE}/advantages")


@app.post(ADMIN_BASE + "/advantages/{adv_id}/delete")
def admin_advantage_delete(adv_id: int, request: Request, admin: str = Depends(require_admin), _csrf_ok: None = Depends(require_csrf)):
    with db.db() as conn:
        conn.execute("DELETE FROM advantages WHERE id=?", (adv_id,))
    return redirect(f"{ADMIN_BASE}/advantages")


@app.post(ADMIN_BASE + "/advantages/text")
async def admin_advantage_text_save(
    request: Request, admin: str = Depends(require_admin),
    _csrf_ok: None = Depends(require_csrf),
):
    form = await request.form()
    for k in form.keys():
        if k.startswith("prices_note_") or k.startswith("prices_surcharge_"):
            db.set_setting(k, str(form[k]).strip())
    return redirect(f"{ADMIN_BASE}/advantages")


# ---------- админка: галерея ----------

ALLOWED_IMAGE_EXT = {".jpg", ".jpeg", ".png", ".webp", ".gif", ".avif"}


def _validate_image_bytes(raw: bytes, ext: str) -> bool:
    """Проверить, что содержимое файла действительно является картинкой.

    Дополнительно запрещаем SVG (защита от XSS через встроенные скрипты)."""
    if ext == ".svg":
        return False
    if not raw or len(raw) > MAX_UPLOAD_BYTES:
        return False
    try:
        import io
        from PIL import Image
        img = Image.open(io.BytesIO(raw))
        img.verify()
        return True
    except Exception:
        return False


def _save_upload(file: UploadFile) -> str | None:
    if not file or not file.filename:
        return None
    ext = os.path.splitext(file.filename)[1].lower()
    if ext not in ALLOWED_IMAGE_EXT:
        return None
    raw = file.file.read()
    if not _validate_image_bytes(raw, ext):
        return None
    fname = f"{secrets.token_hex(8)}{ext}"
    path = os.path.join(UPLOAD_DIR, fname)
    with open(path, "wb") as out:
        out.write(raw)
    return f"/static/uploads/{fname}"


@app.get(ADMIN_BASE + "/gallery", response_class=HTMLResponse)
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
    raw = await val.read()
    if not _validate_image_bytes(raw, ext):
        return None
    fname = f"{secrets.token_hex(8)}{ext}"
    path = os.path.join(UPLOAD_DIR, fname)
    with open(path, "wb") as out:
        out.write(raw)
    return f"/static/uploads/{fname}"


def _remove_upload(path: str | None) -> None:
    """Удалить сохранённый файл загрузки (по URL-пути из настроек/БД)."""
    if not path or not path.startswith("/static/uploads/"):
        return
    try:
        os.remove(os.path.join(BASE_DIR, "static", path[len("/static/"):]))
    except OSError:
        pass


@app.post(ADMIN_BASE + "/gallery/save")
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
    return redirect(f"{ADMIN_BASE}/gallery")


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


@app.post(ADMIN_BASE + "/gallery/migrate")
def admin_gallery_migrate(request: Request, admin: str = Depends(require_admin), _csrf_ok: None = Depends(require_csrf)):
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
    return redirect(f"{ADMIN_BASE}/gallery")


@app.post(ADMIN_BASE + "/gallery/{item_id}/delete")
def admin_gallery_delete(item_id: int, request: Request, admin: str = Depends(require_admin), _csrf_ok: None = Depends(require_csrf)):
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
    return redirect(f"{ADMIN_BASE}/gallery")


# ---------- админка: отзывы ----------

@app.get(ADMIN_BASE + "/reviews", response_class=HTMLResponse)
def admin_reviews(request: Request, admin: str = Depends(require_admin)):
    with db.db() as conn:
        rows = [dict(r) for r in conn.execute("SELECT * FROM reviews ORDER BY created_at DESC")]
    return templates.TemplateResponse(request, "admin/reviews.html", context={
        "admin": admin, "reviews": rows,
    })


@app.post(ADMIN_BASE + "/reviews/save")
def admin_review_save(
    request: Request,
    admin: str = Depends(require_admin),
    id: int = Form(0), author: str = Form(...), rating: int = Form(5),
    text: str = Form(...), is_public: str | None = Form(None),
    _csrf_ok: None = Depends(require_csrf),
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
    return redirect(f"{ADMIN_BASE}/reviews")


@app.post(ADMIN_BASE + "/reviews/{review_id}/delete")
def admin_review_delete(review_id: int, request: Request, admin: str = Depends(require_admin), _csrf_ok: None = Depends(require_csrf)):
    with db.db() as conn:
        conn.execute("DELETE FROM reviews WHERE id=?", (review_id,))
    return redirect(f"{ADMIN_BASE}/reviews")


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
    ("manager_signature", "Рукописная подпись заведующей (PNG с прозрачным фоном)"),
    ("hero_image", "Фоновое фото первого экрана (десктоп, горизонтальное ≥1600px)"),
    ("hero_image_mobile", "Фото первого экрана для телефонов (вертикальное, 9:16 — необязательно)"),
    ("logo_image", "Логотип в шапке и подвале (PNG/SVG с прозрачным фоном)" ),
    ("favicon_image", "Фавикон — иконка вкладки браузера (квадратный PNG, 512×512)"),
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
IMAGE_SETTINGS = {"manager_photo", "manager_signature", "hero_image", "hero_image_mobile",
                  "dir_img_meh", "dir_img_kozha", "dir_img_poshiv", "dir_img_remont",
                  "logo_image", "favicon_image"}


def _make_favicon(path: str) -> None:
    """Из квадратного исходника делаем набор PNG 16/32/48/192 и ICO 32 в /static/favicon/."""
    try:
        from PIL import Image
        src_path = os.path.normpath(os.path.join(BASE_DIR, path.lstrip("/")))
        img = Image.open(src_path).convert("RGBA")
        w, h = img.size
        side = min(w, h)
        img = img.crop(((w - side) // 2, (h - side) // 2,
                        (w - side) // 2 + side, (h - side) // 2 + side))
        fav_dir = os.path.join(BASE_DIR, "static", "favicon")
        os.makedirs(fav_dir, exist_ok=True)
        sizes = [16, 32, 48, 192]
        for sz in sizes:
            img.resize((sz, sz), Image.LANCZOS).save(os.path.join(fav_dir, f"favicon-{sz}.png"))
        img.resize((256, 256), Image.LANCZOS).save(
            os.path.join(fav_dir, "favicon.ico"), sizes=[(16, 16), (32, 32), (48, 48)])
    except Exception:
        pass


def _trim_signature(path: str) -> None:
    """Для загруженной подписи (PNG с прозрачным фоном): оставляем файл как есть,
    только обрезаем пустые прозрачные поля. При неудаче молча пропускаем."""
    try:
        from PIL import Image
        src_path = os.path.normpath(os.path.join(BASE_DIR, path.lstrip("/")))
        img = Image.open(src_path)
        if img.mode != "RGBA":
            return  # не PNG с альфа-каналом — используем как есть
        bbox = img.getchannel("A").getbbox()
        if bbox and bbox[2] - bbox[0] > 40 and bbox[3] - bbox[1] > 20:
            pad = 8
            w, h = img.size
            x0 = max(0, bbox[0] - pad); y0 = max(0, bbox[1] - pad)
            x1 = min(w, bbox[2] + pad); y1 = min(h, bbox[3] + pad)
            img.crop((x0, y0, x1, y1)).save(src_path)
    except Exception:
        pass


@app.get(ADMIN_BASE + "/settings", response_class=HTMLResponse)
def admin_settings(request: Request, admin: str = Depends(require_admin)):
    s = db.get_all_settings()
    return templates.TemplateResponse(request, "admin/settings.html", context={
        "admin": admin, "s": s, "fields": SETTING_FIELDS, "image_keys": IMAGE_SETTINGS,
    })


@app.post(ADMIN_BASE + "/settings")
async def admin_settings_save(request: Request, admin: str = Depends(require_admin), _csrf_ok: None = Depends(require_csrf)):
    form = await request.form()
    for key, _label in SETTING_FIELDS:
        if key in IMAGE_SETTINGS:
            new_path = await _pick_upload(form, key)
            if new_path:
                old = db.get_all_settings().get(key, "")
                db.set_setting(key, new_path)
                _remove_upload(old)  # чистим старый файл
                if key == "manager_signature":
                    _trim_signature(new_path)
                if key == "favicon_image" and new_path.lower().endswith((".png", ".jpg", ".jpeg", ".webp")):
                    _make_favicon(new_path)
            remove_flag = str(form.get(f"{key}_remove") or "").strip()
            if remove_flag:
                old = db.get_all_settings().get(key, "")
                db.set_setting(key, "")
                _remove_upload(old)
        elif key in form:
            db.set_setting(key, str(form[key]).strip())
    return redirect(f"{ADMIN_BASE}/settings?saved=1")


# ---------- админка: смена пароля ----------

@app.get(ADMIN_BASE + "/password", response_class=HTMLResponse)
def admin_password_page(request: Request, admin: str = Depends(require_admin)):
    return templates.TemplateResponse(request, "admin/password.html", context={"admin": admin})


@app.post(ADMIN_BASE + "/password")
def admin_password_change(
    request: Request,
    old: str = Form(...), new: str = Form(...), confirm: str = Form(...),
    admin: str = Depends(require_admin),
    _csrf_ok: None = Depends(require_csrf),
):
    if new != confirm or len(new) < 6:
        return redirect(f"{ADMIN_BASE}/password?err=short")
    with db.db() as conn:
        row = conn.execute("SELECT * FROM admins WHERE login=?", (admin,)).fetchone()
        if not row or not verify_password(old, row["password_hash"]):
            return redirect(f"{ADMIN_BASE}/password?err=old")
        conn.execute("UPDATE admins SET password_hash=? WHERE id=?", (row["id"], hash_password(new)))
    return redirect(f"{ADMIN_BASE}/password?ok=1")


@app.exception_handler(HTTPException)
async def http_exc_handler(request: Request, exc: HTTPException):
    if exc.status_code == 303:
        return RedirectResponse(exc.headers.get("Location", "/"), status_code=303)
    return JSONResponse({"detail": exc.detail}, status_code=exc.status_code)
