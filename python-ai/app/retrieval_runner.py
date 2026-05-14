import json
import logging
import os
import subprocess
import sys
from typing import Any, Dict, List, Tuple

from app.env_utils import get_env_bool, get_env_int

logger = logging.getLogger(__name__)


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


def _run_retrieval_search_inprocess(
    query: str,
    filenames: List[str] | None = None,
    top_k: int = 5,
    user_id: str | None = None,
    document_ids: List[str] | None = None,
) -> Tuple[List[Dict[str, Any]], bool]:
    from app.services.rag_retrieval import search_relevant_chunks

    chunks, success = search_relevant_chunks(
        query,
        filenames,
        top_k=top_k,
        user_id=user_id,
        document_ids=document_ids,
    )
    return list(chunks or []), bool(success)


def run_retrieval_search(
    query: str,
    filenames: List[str] | None = None,
    top_k: int = 5,
    user_id: str | None = None,
    document_ids: List[str] | None = None,
) -> Tuple[List[Dict[str, Any]], bool]:
    timeout_seconds = get_env_int("DOCUMENT_RETRIEVAL_SUBPROCESS_TIMEOUT", 180)
    app_dir = os.path.dirname(os.path.dirname(__file__))
    filenames_json = json.dumps(filenames or [], ensure_ascii=False)
    use_subprocess = get_env_bool("DOCUMENT_RETRIEVAL_USE_SUBPROCESS", False)

    if not use_subprocess:
        try:
            return _run_retrieval_search_inprocess(
                query, filenames, top_k=top_k, user_id=user_id, document_ids=document_ids
            )
        except Exception:
            logger.exception("In-process retrieval failed; falling back to subprocess")

    try:
        document_ids_json = json.dumps([str(d) for d in document_ids] if document_ids else [], ensure_ascii=False)
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
                document_ids_json,
            ],
            cwd=app_dir,
            capture_output=True,
            text=True,
            timeout=timeout_seconds,
            check=False,
        )
    except subprocess.TimeoutExpired:
        logger.error("Retrieval subprocess timed out after %s seconds", timeout_seconds)
        return [], False

    try:
        payload = _parse_search_payload(completed.stdout)
        chunks = payload.get("chunks") or []
        return list(chunks), bool(payload["success"])
    except Exception:
        return [], False
