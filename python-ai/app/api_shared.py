import socket

from fastapi import Header, HTTPException
from pydantic import BaseModel

from app.env_utils import get_env


AI_SERVICE_TOKEN = get_env("AI_SERVICE_TOKEN", "your_internal_api_secret")


def verify_token(authorization: str = Header(None)):
    """Simple token-based security for internal service communication."""
    if not authorization or authorization != f"Bearer {AI_SERVICE_TOKEN}":
        raise HTTPException(status_code=401, detail="Unauthorized access to AI Service.")


class HealthResponse(BaseModel):
    status: str
    host: str


def build_health_payload() -> dict:
    return {
        "status": "ok",
        "host": socket.gethostname(),
    }
