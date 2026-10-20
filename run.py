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
    uvicorn.run(
        "app.main:app",
        host=args.host,
        port=args.port,
        reload=args.dev,
    )
