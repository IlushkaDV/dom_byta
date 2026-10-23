# dom_byta

Сайт мехового ателье — см. инструкцию ниже.

# Сайт мехового ателье «Дом Быта»

Современный сайт-витрина с анимациями + админ-панель для управления контентом.
Бэкенд: Python + FastAPI, база данных: SQLite (файл `data/atele.db`).

## Страницы

| Адрес | Что там |
|---|---|
| `http://localhost:8000/` | Витрина: главная, услуги и цены, галерея работ, отзывы, форма заявки |
| `http://localhost:8000/admin-kabinet7/login` | Админ-панель (секретный путь, см. ниже) |

## Адрес админки (важно!)

Обычный `/admin` **не существует** — он отдаёт 404, чтобы сканеры и посторонние не видели страницу входа.
Админка доступна только по секретному пути, который задаётся переменной окружения **`ATELLE_ADMIN_PATH`**:

```bash
# Linux/macOS
export ATELLE_ADMIN_PATH=admin-kabinet7
python run.py
# вход: http://localhost:8000/admin-kabinet7/login

# Windows (cmd)
set ATELLE_ADMIN_PATH=admin-kabinet7
python run.py

# Windows (PowerShell)
$env:ATELLE_ADMIN_PATH="admin-kabinet7"; python run.py
```

Если переменная не задана, сервер сам генерирует случайный путь вида `/admin-a1b2c3d4`,
сохраняет его в файл `data/.admin_path` и печатает в консоль при запуске — адрес виден там же.

На боевом сервере (systemd) пропишите в юните:
```
Environment="ATELLE_ADMIN_PATH=admin-kabinet7"
```

**Логин по умолчанию:** `admin`, пароль `admin123` (или значение переменной `ADMIN_PASSWORD` при первом запуске).
Обязательно смените пароль после первого входа (раздел «Смена пароля»).

В админке: дашборд, заявки с формы, услуги и цены, галерея, отзывы, настройки сайта (телефон, адрес, часы работы, соцсети).

---

## Быстрый запуск (локально)

Нужен **Python 3.10+**.

```bash
# 1. Клонировать репозиторий
git clone https://github.com/<ваш_аккаунт>/<ваш_репозиторий>.git
cd <ваш_репозиторий>

# 2. Создать виртуальное окружение и установить зависимости
python3 -m venv .venv
source .venv/bin/activate        # Windows: .venv\Scripts\activate
pip install -r requirements.txt

# 3. Запустить
python run.py
```

Открыть в браузере: **http://localhost:8000**

При первом запуске база `data/atele.db` создаётся автоматически и наполняется
демо-контентом (услуги, отзывы, примеры работ) — ничего отдельно инициализировать не нужно.

### Параметры запуска

```bash
python run.py --port 8080            # другой порт
python run.py --host 0.0.0.0         # доступ из локальной сети (по IP машины)
python run.py --dev                  # авто-перезагрузка при изменении кода
```

---

## Запуск на боевом сервере (VPS, Ubuntu/Debian)

```bash
sudo apt update && sudo apt install -y python3-venv
git clone https://github.com/<ваш_аккаунт>/<ваш_репозиторий>.git /opt/atele
cd /opt/atele
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
```

Сервис systemd — файл `/etc/systemd/system/atele.service`:

```ini
[Unit]
Description=Atele site
After=network.target

[Service]
WorkingDirectory=/opt/atele
ExecStart=/opt/atele/.venv/bin/python run.py --host 127.0.0.1 --port 8000
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now atele
```

Далее поставьте nginx как reverse proxy на порт 8000 и сертификат Let's Encrypt:

```bash
sudo apt install -y nginx certbot python3-certbot-nginx
sudo certbot --nginx -d db-center.ru
```

Пример конфига nginx (`/etc/nginx/sites-available/atele`):

```nginx
server {
    listen 80;
    server_name db-center.ru;
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## Структура проекта

```
run.py              # точка входа
requirements.txt    # зависимости
app/
  main.py           # маршруты витрины и админки
  db.py             # схема БД + начальные данные
  auth.py           # вход в админку, хэширование паролей, сессии
  templates/        # HTML-шаблоны (Jinja2)
  static/           # CSS, JS (анимации), изображения
data/
  atele.db          # база данных (SQLite) — бэкавьте этот файл
```

## Полезно знать

- Все данные (заявки, услуги, фото, отзывы, настройки) хранятся в одном файле
  `data/atele.db`. Для бэкапа достаточно его скопировать.
- Чтобы перенести контент со старого сайта, отредактируйте разделы через админку.
- Секретный ключ сессий генерируется автоматически в `data/.secret_key`.
