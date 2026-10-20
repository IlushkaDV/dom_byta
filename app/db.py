"""Слой работы с базой данных (SQLite) для сайта мехового ателье."""
import sqlite3
from contextlib import contextmanager
from pathlib import Path

DB_PATH = Path(__file__).resolve().parent.parent / "data" / "atele.db"


def _connect() -> sqlite3.Connection:
    DB_PATH.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON")
    return conn


@contextmanager
def db():
    conn = _connect()
    try:
        yield conn
        conn.commit()
    finally:
        conn.close()


SCHEMA = """
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS services (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    title       TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    price       TEXT NOT NULL DEFAULT '',
    category    TEXT NOT NULL DEFAULT 'Пошив',
    sort_order  INTEGER NOT NULL DEFAULT 0,
    is_active   INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS gallery_items (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    title      TEXT NOT NULL,
    image_path TEXT NOT NULL DEFAULT '',
    before_image_path TEXT NOT NULL DEFAULT '',
    after_image_path  TEXT NOT NULL DEFAULT '',
    before_text TEXT NOT NULL DEFAULT '',
    after_text  TEXT NOT NULL DEFAULT '',
    price      TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS advantages (
    id    INTEGER PRIMARY KEY AUTOINCREMENT,
    icon  TEXT NOT NULL DEFAULT '✦',
    title TEXT NOT NULL,
    text  TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS reviews (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    author     TEXT NOT NULL,
    rating     INTEGER NOT NULL DEFAULT 5,
    text       TEXT NOT NULL,
    is_public  INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS orders (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    name         TEXT NOT NULL,
    phone        TEXT NOT NULL,
    email        TEXT NOT NULL DEFAULT '',
    service      TEXT NOT NULL DEFAULT '',
    message      TEXT NOT NULL DEFAULT '',
    status       TEXT NOT NULL DEFAULT 'new',
    created_at   TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS admins (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    login         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL
);
"""


DEFAULT_SETTINGS = {
    "site_name": "Меховое ателье «Дом Быта»",
    "tagline": "Авторский пошив и реставрация меховых изделий в Хабаровске",
    "hero_title": "Ателье по пошиву одежды",
    "hero_subtitle": (
        "Дом Быта поможет подчеркнуть Вашу индивидуальность при помощи "
        "авторских меховых изделий высочайшего качества."
    ),
    "about_text": (
        "Наше ателье по пошиву одежды специализируется на создании уникальных "
        "авторских изделий из меха и прочих материалов, а также на реставрации, "
        "подгонке и ремонте готовой одежды (замена молний, пуговиц и замков, "
        "вышивка, штопка, замена подкладки и т.д.).\n\n"
        "В ателье «Дом Быта» Вас ждет первоклассное обслуживание. Наши опытные "
        "специалисты смогут справиться с задачей любой сложности в кратчайшие "
        "сроки. Доверив ремонт или пошив одежды профессионалам, Вы можете не "
        "сомневаться в отличном результате.\n\n"
        "Мы с радостью вернём Вашим любимым вещам былой великолепный внешний вид "
        "или создадим для Вас новые платья, пальто, шубы или куртки по индивидуальным "
        "лекалам. Ждём Вас в одном из лучших ателье Хабаровска!"
    ),
    "manager_name": "Щербакова Юлия Борисовна",
    "manager_role": "Заведующая ателье",
    "manager_note": "Занимается контролем качества исполнения услуг",
    "address": "г. Хабаровск, ул. Шеронова, 92, ТЦ «Дом Быта», 6 этаж",
    "phone": "+7 (4212) 32-72-82",
    "phone_tc": "+7 (4212) 32-84-10",
    "email": "khhouse@mail.ru",
    "hours_week": "ПН–ПТ: 10:00 – 19:00",
    "hours_weekend": "СБ: 10:00 – 17:00",
}

DEFAULT_SERVICES = [
    ("Пошив шубы по индивидуальным меркам", "Создание авторской шубы из меха клиента или из наших материалов. Индивидуальные лекала, подбор фурнитуры.", "от 12 000 ₽", "Пошив"),
    ("Пошив пальто и куртки", "Пошив демисезонных пальто, плащей и курток по вашим меркам из любых тканей.", "от 8 000 ₽", "Пошив"),
    ("Пошив платья на заказ", "Вечерние, коктейльные и повседневные платья по индивидуальным лекалам.", "от 6 000 ₽", "Пошив"),
    ("Реставрация и перешив меховых изделий", "Обновление старых шуб и дублёнок: перекрас, замена подкладки, укорачивание, моделирование.", "от 5 000 ₽", "Реставрация"),
    ("Подгонка изделия по фигуре", "Точная посадка готовой одежды: ушивка, расшивка, подгонка длины рукавов и изделия.", "от 1 500 ₽", "Ремонт"),
    ("Замена молний, пуговиц и замков", "Ремонт фурнитуры на любой одежде, включая меховые изделия.", "от 800 ₽", "Ремонт"),
    ("Замена подкладки", "Полная или частичная замена подкладки на пальто, шубах и куртках.", "от 3 000 ₽", "Ремонт"),
    ("Вышивка и штопка", "Художественная вышивка, деликатная штопка, устранение дефектов ткани и меха.", "от 2 000 ₽", "Ремонт"),
    ("Услуги портного на дому", "Выезд мастера для снятия мерок и мелкого ремонта — уточняйте по телефону.", "по договорённости", "Другое"),
]

DEFAULT_ADVANTAGES = [
    ("💎", "Высокое качество сервиса", "Первоклассное обслуживание на каждом этапе — от снятия мерок до выдачи готового изделия."),
    ("⚡", "Быстрый пошив", "Даже самые сложные изделия готовы в кратчайшие сроки без потери качества."),
    ("💰", "Разумные цены", "Объективная и оптимальная стоимость услуг ателье в Хабаровске."),
    ("🧵", "Профессиональный подход", "Современное оборудование и квалифицированные мастера с большим опытом."),
    ("🏬", "ТЦ «Дом Быта»", "Высокая концентрация бытовых услуг в одном месте: химчистка, ремонт обуви, парикмахерская."),
    ("📍", "Центр Хабаровска", "Удобное расположение в центральной части города и комфортный график работы."),
]

DEFAULT_GALLERY = [
    ("Реставрация шубы: замена подкладки и фурнитуры", "До: потёртая подкладка и заклинивающая молния. После: как новая вещь.", "Готово через 5 дней", "2 000 ₽"),
    ("Перешив старинной шубы в современную модель", "До: изделие 90-х годов, устаревший фасон. После: стильная шуба по актуальным лекалам.", "Срок — 3 недели", "от 15 000 ₽"),
    ("Пошив вечернего платья на заказ", "Индивидуальные лекала, ручная отделка, идеальная посадка по фигуре.", "Срок — 2 недели", "от 6 000 ₽"),
]


def init_db() -> None:
    with db() as conn:
        conn.executescript(SCHEMA)

        # Миграция: поля для фото «до/после» в галерее
        cols = {r["name"] for r in conn.execute("PRAGMA table_info(gallery_items)")}
        if "before_image_path" not in cols:
            conn.execute("ALTER TABLE gallery_items ADD COLUMN before_image_path TEXT NOT NULL DEFAULT ''")
        if "after_image_path" not in cols:
            conn.execute("ALTER TABLE gallery_items ADD COLUMN after_image_path TEXT NOT NULL DEFAULT ''")

        # Настройки — заполняем только отсутствующие ключи
        existing = {r["key"] for r in conn.execute("SELECT key FROM settings")}
        for k, v in DEFAULT_SETTINGS.items():
            if k not in existing:
                conn.execute("INSERT INTO settings(key, value) VALUES(?, ?)", (k, v))

        # Услуги и преимущества — засеваем при пустой таблице
        if conn.execute("SELECT COUNT(*) c FROM services").fetchone()["c"] == 0:
            for i, (t, d, p, c) in enumerate(DEFAULT_SERVICES):
                conn.execute(
                    "INSERT INTO services(title, description, price, category, sort_order) VALUES(?,?,?,?,?)",
                    (t, d, p, c, i),
                )
        if conn.execute("SELECT COUNT(*) c FROM advantages").fetchone()["c"] == 0:
            for icon, t, x in DEFAULT_ADVANTAGES:
                conn.execute("INSERT INTO advantages(icon, title, text) VALUES(?,?,?)", (icon, t, x))
        if conn.execute("SELECT COUNT(*) c FROM gallery_items").fetchone()["c"] == 0:
            for t, b, a, p in DEFAULT_GALLERY:
                conn.execute(
                    "INSERT INTO gallery_items(title, before_text, after_text, price) VALUES(?,?,?,?)",
                    (t, b, a, p),
                )

        # Админ по умолчанию
        from .auth import hash_password
        if conn.execute("SELECT COUNT(*) c FROM admins").fetchone()["c"] == 0:
            conn.execute(
                "INSERT INTO admins(login, password_hash) VALUES(?, ?)",
                ("admin", hash_password("admin123")),
            )


# ---------- helpers ----------

def get_all_settings() -> dict:
    with db() as conn:
        return {r["key"]: r["value"] for r in conn.execute("SELECT key, value FROM settings")}


def set_setting(key: str, value: str) -> None:
    with db() as conn:
        conn.execute(
            "INSERT INTO settings(key, value) VALUES(?, ?) "
            "ON CONFLICT(key) DO UPDATE SET value=excluded.value",
            (key, value),
        )
