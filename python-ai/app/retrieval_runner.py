import json
import os
import subprocess
import sys
from typing import Any, Dict, List, Tuple

from app.env_utils import get_env_int


def _parse_search_payload(stdout: str) -> Dict[str, Any]:
    lines = [line.strip() for line in stdout.splitlines() if line.strip()]
    for line in reversed(lines):
        try:
            payload = json.loads(line)
        except json.JSONDecodeError:
            continue

        if isinstance(payload, dict) and "success" in payload and "chunks" in payload:
            return payload

    raise ValueError("Subprocess did not return a valid retrieval JSON payload.")


def run_retrieval_search(
    query: str,
    filenames: List[str] | None = None,
    top_k: int = 5,
    user_id: str | None = None,
) -> Tuple[List[Dict[str, Any]], bool]:
    timeout_seconds = get_env_int("DOCUMENT_RETRIEVAL_SUBPROCESS_TIMEOUT", 180)
    app_dir = os.path.dirname(os.path.dirname(__file__))
    filenames_json = json.dumps(filenames or [], ensure_ascii=False)

    completed = subprocess.run(
        [
            sys.executable,
            "-m",
            "app.retrieval_tasks",
            "search",
            query,
            filenames_json,
            str(top_k),
            user_id or "",
        ],
        cwd=app_dir,
        capture_output=True,
        text=True,
        timeout=timeout_seconds,
        check=False,
    )

    try:
        payload = _parse_search_payload(completed.stdout)
        chunks = payload.get("chunks") or []
        return list(chunks), bool(payload["success"])
    except Exception:
        return [], False
