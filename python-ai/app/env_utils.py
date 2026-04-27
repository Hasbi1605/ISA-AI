import os
from typing import Optional


def normalize_env_value(value: Optional[str]) -> Optional[str]:
    """Trim whitespace and unwrap matching quotes from env values."""
    if value is None:
        return None

    normalized = value.strip()
    if len(normalized) >= 2 and normalized[0] == normalized[-1] and normalized[0] in {"'", '"'}:
        normalized = normalized[1:-1]

    return normalized


def get_env(name: str, default: Optional[str] = None) -> Optional[str]:
    value = normalize_env_value(os.getenv(name))
    if value is None or value == "":
        return default
    return value


def get_env_int(name: str, default: int) -> int:
    value = get_env(name)
    if value is None:
        return default
    return int(value)


def get_env_float(name: str, default: float) -> float:
    value = get_env(name)
    if value is None:
        return default
    return float(value)


def get_env_bool(name: str, default: bool) -> bool:
    value = get_env(name)
    if value is None:
        return default

    normalized = value.lower()
    if normalized in {"1", "true", "yes", "on"}:
        return True
    if normalized in {"0", "false", "no", "off"}:
        return False
    return default
