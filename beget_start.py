#!/usr/bin/env python3
"""Точка входа для shared-хостинга Beget (Python-приложения).

Beget запускает файл, указанный в поле "Файл с скриптом", и сам управляет
процессом и портом. Здесь: читаем переменные окружения (PORT/ATELLE_ADMIN_PATH),
показываем секретный адрес админки в логе и стартуем uvicorn.
"""
import os

import uvicorn

if __name__ == "__main__":
    # Beget передаёт выделенный порт через окружение; по умолчанию 8000 —
    # на случай ручного запуска ("python beget_start.py" в SSH).
    port = int(os.environ.get("PORT") or os.environ.get("PORT_1") or 8000)

    try:
        from app.main import ADMIN_BASE
        print("=" * 56, flush=True)
        print("  Секретный адрес админки: " + ADMIN_BASE, flush=True)
        print("  Вход: https://ваш-домен.ru" + ADMIN_BASE + "/login", flush=True)
        print("  Обычный /admin недоступен (404).", flush=True)
        print("=" * 56, flush=True)
    except Exception as e:  # noqa: BLE001
        print("Не удалось показать адрес админки:", e, flush=True)

    # reload=False обязательно: на shared-хостинге перезагрузка не нужна
    uvicorn.run(
        "app.main:app",
        host="0.0.0.0",
        port=port,
        reload=False,
    )
