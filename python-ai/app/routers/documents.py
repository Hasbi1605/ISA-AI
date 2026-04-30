import os
import shutil
import uuid

from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile
from fastapi.responses import Response
from pydantic import BaseModel

from app.api_shared import verify_token
from app.config_loader import (
    get_summarize_final_prompt,
    get_summarize_partial_prompt,
    get_summarize_single_prompt,
)
from app.document_runner import run_document_process
from app.services.document_conversion import convert_document_file
from app.services.document_content import extract_document_content_html
from app.services.document_export import export_content
from app.services.table_extraction import extract_tables_from_file

router = APIRouter(prefix="/api/documents", tags=["Documents"])


def delete_document_vectors(*args, **kwargs):
    from app.services.rag_ingest import delete_document_vectors as _delete_document_vectors

    return _delete_document_vectors(*args, **kwargs)


def get_document_chunks_for_summarization(*args, **kwargs):
    from app.services.rag_summarization import (
        get_document_chunks_for_summarization as _get_document_chunks_for_summarization,
    )

    return _get_document_chunks_for_summarization(*args, **kwargs)


@router.post("/process", dependencies=[Depends(verify_token)])
async def upload_document(
    file: UploadFile = File(...),
    user_id: str = Form(...),
):
    """
    Endpoint for uploading and processing a document into vector embeddings.

    Heavy ingest runs in a subprocess so OCR / ML libraries do not stay resident
    in the lightweight API worker after the request completes.
    """
    temp_dir = "temp_files"
    os.makedirs(temp_dir, exist_ok=True)

    file_id = str(uuid.uuid4())
    temp_file_path = os.path.join(temp_dir, f"{file_id}_{file.filename}")

    try:
        with open(temp_file_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)

        success, message = run_document_process(temp_file_path, file.filename, user_id)

        if os.path.exists(temp_file_path):
            os.remove(temp_file_path)

        if success:
            return {"status": "success", "message": message, "filename": file.filename}

        raise HTTPException(status_code=500, detail=message)

    except HTTPException:
        if os.path.exists(temp_file_path):
            os.remove(temp_file_path)
        raise
    except Exception as e:
        if os.path.exists(temp_file_path):
            os.remove(temp_file_path)
        raise HTTPException(status_code=500, detail=str(e))


@router.delete("/{filename}", dependencies=[Depends(verify_token)])
async def delete_document(filename: str):
    success, message = delete_document_vectors(filename)
    if success:
        return {"status": "success", "message": message}
    raise HTTPException(status_code=500, detail=message)


class SummarizeRequest(BaseModel):
    filename: str
    user_id: str


class ExportRequest(BaseModel):
    content_html: str
    target_format: str
    file_name: str | None = None


def _render_prompt(template: str, **kwargs) -> str:
    rendered = template.format(**kwargs)
    if not rendered.strip():
        raise RuntimeError("Prompt summarization kosong setelah dirender")
    return rendered


def _render_prompt_or_http_exception(template: str, **kwargs) -> str:
    try:
        return _render_prompt(template, **kwargs)
    except (RuntimeError, KeyError, IndexError) as exc:
        raise HTTPException(status_code=500, detail=f"Gagal merender prompt: {exc}") from exc


@router.post("/extract-tables", dependencies=[Depends(verify_token)])
async def extract_tables_endpoint(file: UploadFile = File(...)):
    temp_dir = "temp_files"
    os.makedirs(temp_dir, exist_ok=True)

    file_id = str(uuid.uuid4())
    temp_file_path = os.path.join(temp_dir, f"{file_id}_{file.filename}")

    try:
        with open(temp_file_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)

        tables = extract_tables_from_file(temp_file_path)

        return {
            "status": "success",
            "filename": file.filename,
            "tables": tables,
        }
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    finally:
        if os.path.exists(temp_file_path):
            os.remove(temp_file_path)


@router.post("/extract-content", dependencies=[Depends(verify_token)])
async def extract_content_endpoint(file: UploadFile = File(...)):
    temp_dir = "temp_files"
    os.makedirs(temp_dir, exist_ok=True)

    file_id = str(uuid.uuid4())
    temp_file_path = os.path.join(temp_dir, f"{file_id}_{file.filename}")

    try:
        with open(temp_file_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)

        content_html = extract_document_content_html(temp_file_path, filename=file.filename)

        return {
            "status": "success",
            "filename": file.filename,
            "content_html": content_html,
        }
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    finally:
        if os.path.exists(temp_file_path):
            os.remove(temp_file_path)


@router.post("/export", dependencies=[Depends(verify_token)])
async def export_document_endpoint(request: ExportRequest):
    try:
        artifact = export_content(
            request.content_html,
            request.target_format,
            file_name=request.file_name,
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc

    headers = {
        "Content-Disposition": f'attachment; filename="{artifact.filename}"',
        "X-Content-Type-Options": "nosniff",
        "Cache-Control": "no-store",
    }

    return Response(content=artifact.content, media_type=artifact.mime_type, headers=headers)


@router.post("/convert", dependencies=[Depends(verify_token)])
async def convert_document_endpoint(
    file: UploadFile = File(...),
    target_format: str = Form(...),
    file_name: str | None = Form(None),
):
    temp_dir = "temp_files"
    os.makedirs(temp_dir, exist_ok=True)

    file_id = str(uuid.uuid4())
    temp_file_path = os.path.join(temp_dir, f"{file_id}_{file.filename}")

    try:
        with open(temp_file_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)

        artifact = convert_document_file(
            temp_file_path,
            target_format,
            filename=file.filename,
            file_name=file_name,
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc
    finally:
        if os.path.exists(temp_file_path):
            os.remove(temp_file_path)

    headers = {
        "Content-Disposition": f'attachment; filename="{artifact.filename}"',
        "X-Content-Type-Options": "nosniff",
        "Cache-Control": "no-store",
    }

    return Response(content=artifact.content, media_type=artifact.mime_type, headers=headers)


@router.post("/summarize", dependencies=[Depends(verify_token)])
async def summarize_document_endpoint(request: SummarizeRequest):
    from app.llm_manager import get_llm_stream

    if not request.filename:
        raise HTTPException(status_code=400, detail="filename is required")

    if not request.user_id:
        raise HTTPException(status_code=400, detail="user_id is required for authorization")

    success, batches, total_chunks = get_document_chunks_for_summarization(
        request.filename,
        user_id=request.user_id,
        max_tokens=8000,
    )

    if not success:
        raise HTTPException(status_code=403, detail="Dokumen tidak ditemukan atau Anda tidak memiliki akses.")

    if len(batches) == 1:
        summarize_prompt = _render_prompt_or_http_exception(
            get_summarize_single_prompt(),
            document=batches[0] or "",
        )

        messages = [{"role": "user", "content": summarize_prompt}]

        full_response = ""
        for chunk in get_llm_stream(messages):
            full_response += chunk

        if "[MODEL:" in full_response:
            full_response = full_response.split("]", 1)[1] if "]" in full_response else full_response

        return {
            "status": "success",
            "summary": full_response,
            "filename": request.filename,
            "mode": "single",
            "total_chunks": total_chunks,
        }

    partial_summaries = []
    for i, batch in enumerate(batches):
        partial_prompt = _render_prompt_or_http_exception(
            get_summarize_partial_prompt(),
            batch=batch or "",
            part_number=i + 1,
            total_parts=len(batches),
        )

        batch_messages = [{"role": "user", "content": partial_prompt}]
        partial_response = ""
        for chunk in get_llm_stream(batch_messages):
            partial_response += chunk

        if "[MODEL:" in partial_response:
            parts = partial_response.split("]", 1)
            if len(parts) > 1:
                partial_response = parts[1]

        partial_summaries.append(partial_response.strip())

    combined_summaries = "\n\n".join([f"Ringkasan Bagian {i + 1}:\n{s}" for i, s in enumerate(partial_summaries)])

    final_prompt = _render_prompt_or_http_exception(
        get_summarize_final_prompt(),
        combined_summaries=combined_summaries,
    )

    final_messages = [{"role": "user", "content": final_prompt}]

    full_response = ""
    for chunk in get_llm_stream(final_messages):
        full_response += chunk

    if "[MODEL:" in full_response:
        full_response = full_response.split("]", 1)[1] if "]" in full_response else full_response

    return {
        "status": "success",
        "summary": full_response,
        "filename": request.filename,
        "mode": "hierarchical",
        "total_chunks": total_chunks,
        "batches_processed": len(batches),
        "note": "Dokumen terlalu besar, menggunakan summarization bertahap",
    }
