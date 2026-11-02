#!/usr/bin/env python3
"""Точка входа: запуск сайта мехового ателье «Дом Быта».

Использование:
    python run.py [--port 8000] [--host 0.0.0.0]
"""
import argparse

import uvicorn

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Сайт мехового ателье")
    parser.add_argument("--host", default="0.0.0.0")
    parser.add_argument("--port", type=int, default=8000)
    parser.add_argument("--dev", action="store_true", help="авто-перезагрузка при изменениях")
    args = parser.parse_args()

    # Показать секретный адрес админки (админка скрыта от посторонних)
    try:
        from app.main import ADMIN_BASE
        print("=" * 56, flush=True)
        print("  Секретный адрес админки: " + ADMIN_BASE, flush=True)
        print("  Сохраните его: обычный /admin недоступен (404).", flush=True)
        print("  Свой адрес можно задать переменной окружения", flush=True)
        print("  ATELLE_ADMIN_PATH (например, ATELLE_ADMIN_PATH=kabinet7)", flush=True)
        print("=" * 56, flush=True)
    except Exception as e:  # noqa: BLE001
        print("Не удалось показать адрес админки:", e, flush=True)

    uvicorn.run(
        "app.main:app",
        host=args.host,
        port=args.port,
        reload=args.dev,
    )
