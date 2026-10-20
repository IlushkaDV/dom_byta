"""Простая, но безопасная авторизация для админ-панели.

Используется PBKDF2 (hashlib) + подписанные сессии на itsdangerous-подобной
самодельной схеме (hmac + sha256), без внешних зависимостей кроме stdlib.
"""
import hashlib
import hmac
import os
import secrets
import time

SECRET_FILE = os.path.join(os.path.dirname(__file__), "..", "data", ".secret_key")
SESSION_TTL = 60 * 60 * 12  # 12 часов

_ITERATIONS = 200_000


def _secret_key() -> bytes:
    if os.path.exists(SECRET_FILE):
        with open(SECRET_FILE, "rb") as f:
            key = f.read().strip()
            if key:
                return key
    key = secrets.token_bytes(32)
    os.makedirs(os.path.dirname(SECRET_FILE), exist_ok=True)
    with open(SECRET_FILE, "wb") as f:
        f.write(key)
    os.chmod(SECRET_FILE, 0o600)
    return key


def hash_password(password: str) -> str:
    salt = secrets.token_bytes(16)
    dk = hashlib.pbkdf2_hmac("sha256", password.encode(), salt, _ITERATIONS)
    return f"pbkdf2${_ITERATIONS}${salt.hex()}${dk.hex()}"


def verify_password(password: str, stored: str) -> bool:
    try:
        _, iters, salt_hex, hash_hex = stored.split("$")
        dk = hashlib.pbkdf2_hmac("sha256", password.encode(), bytes.fromhex(salt_hex), int(iters))
        return hmac.compare_digest(dk.hex(), hash_hex)
    except Exception:
        return False


def make_session_token(login: str) -> str:
    payload = f"{login}|{int(time.time()) + SESSION_TTL}"
    sig = hmac.new(_secret_key(), payload.encode(), hashlib.sha256).hexdigest()
    return f"{payload}.{sig}"


def check_session_token(token: str) -> str | None:
    """Возвращает login, если токен валиден и не истёк, иначе None."""
    try:
        payload, sig = token.rsplit(".", 1)
        expected = hmac.new(_secret_key(), payload.encode(), hashlib.sha256).hexdigest()
        if not hmac.compare_digest(sig, expected):
            return None
        login, exp = payload.rsplit("|", 1)
        if int(exp) < time.time():
            return None
        return login
    except Exception:
        return None
