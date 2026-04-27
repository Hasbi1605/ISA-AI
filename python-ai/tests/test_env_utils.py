import os
import sys

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))


def test_normalize_env_value_strips_matching_quotes_and_whitespace():
    from app.env_utils import normalize_env_value

    assert normalize_env_value('  "quoted-value"  ') == "quoted-value"
    assert normalize_env_value("  'quoted-value'  ") == "quoted-value"


def test_get_env_int_parses_quoted_numbers(monkeypatch):
    from app.env_utils import get_env_int

    monkeypatch.setenv("TEST_TIMEOUT", ' "15" ')

    assert get_env_int("TEST_TIMEOUT", 5) == 15


def test_get_env_bool_parses_quoted_boolean_strings(monkeypatch):
    from app.env_utils import get_env_bool

    monkeypatch.setenv("TEST_FLAG", " 'true' ")

    assert get_env_bool("TEST_FLAG", False) is True


def test_llm_manager_strips_quotes_from_default_system_prompt_env(monkeypatch):
    import app.llm_manager as manager

    monkeypatch.setattr(manager, "CONFIG_AVAILABLE", False)
    monkeypatch.setenv("DEFAULT_SYSTEM_PROMPT", '  "Prompt override dari env"  ')

    assert manager._get_default_system_prompt_fallback() == "Prompt override dari env"
