import socket

from fastapi import Header, HTTPException
from pydantic import BaseModel

from app.env_utils import get_env


DEFAULT_AI_SERVICE_TOKEN = "your_internal_api_secret"
UNSAFE_AI_SERVICE_TOKENS = {
    DEFAULT_AI_SERVICE_TOKEN,
    "change_me_internal_api_secret",
    "CHANGE_ME",
}


def get_internal_service_token() -> str | None:
    token = get_env("AI_SERVICE_TOKEN")

    if token is None or token in UNSAFE_AI_SERVICE_TOKENS:
        return None

    return token


def verify_token(authorization: str | None = Header(None)):
    """Simple token-based security for internal service communication."""
    token = get_internal_service_token()

    if token is None:
        raise HTTPException(status_code=503, detail="AI Service token is not configured.")

    if not authorization or authorization != f"Bearer {token}":
        raise HTTPException(status_code=401, detail="Unauthorized access to AI Service.")


class HealthResponse(BaseModel):
    status: str
    host: str


def build_health_payload() -> dict:
    return {
        "status": "ok",
        "host": socket.gethostname(),
    }
