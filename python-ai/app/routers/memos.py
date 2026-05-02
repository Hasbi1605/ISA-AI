from fastapi import APIRouter, Depends, HTTPException
from fastapi.responses import Response
from pydantic import BaseModel, Field

from app.api_shared import verify_token
from app.services.memo_generation import generate_memo_docx

router = APIRouter(prefix="/api/memos", tags=["Memos"])


class GenerateMemoRequest(BaseModel):
    memo_type: str = Field(..., min_length=1, max_length=60)
    title: str = Field(..., min_length=1, max_length=160)
    context: str = Field(..., min_length=1, max_length=12000)


@router.post("/generate-body", dependencies=[Depends(verify_token)])
async def generate_memo_body(request: GenerateMemoRequest):
    try:
        draft = generate_memo_docx(
            memo_type=request.memo_type,
            title=request.title,
            context=request.context,
        )
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc

    return Response(
        content=draft.content,
        media_type="application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        headers={
            "Content-Disposition": f'attachment; filename="{draft.filename}"',
            "X-Memo-Searchable-Text": draft.searchable_text[:500],
            "X-Content-Type-Options": "nosniff",
            "Cache-Control": "no-store",
        },
    )
