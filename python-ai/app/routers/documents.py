from fastapi import APIRouter, UploadFile, File, Depends, HTTPException, Header
import os
import shutil
import uuid
from typing import Dict, List
from app.services.rag_service import process_document, delete_document_vectors, summarize_document

router = APIRouter(prefix="/api/documents", tags=["Documents"])

# Security
AI_SERVICE_TOKEN = os.getenv("AI_SERVICE_TOKEN", "your_internal_api_secret")

def verify_token(authorization: str = Header(None)):
    """Simple token-based security for internal service communication."""
    if not authorization or authorization != f"Bearer {AI_SERVICE_TOKEN}":
        raise HTTPException(status_code=401, detail="Unauthorized access to AI Service.")

@router.post("/process", dependencies=[Depends(verify_token)])
async def upload_document(file: UploadFile = File(...)):
    """
    Endpoint for uploading and processing a document into vector embeddings.
    """
    temp_dir = "temp_files"
    os.makedirs(temp_dir, exist_ok=True)
    
    file_id = str(uuid.uuid4())
    temp_file_path = os.path.join(temp_dir, f"{file_id}_{file.filename}")
    
    try:
        # Save temp file
        with open(temp_file_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)
            
        success, message = process_document(temp_file_path, file.filename)
        
        # Cleanup
        if os.path.exists(temp_file_path):
            os.remove(temp_file_path)
            
        if success:
            return {"status": "success", "message": message, "filename": file.filename}
        else:
            raise HTTPException(status_code=500, detail=message)
            
    except Exception as e:
        if os.path.exists(temp_file_path):
            os.remove(temp_file_path)
        raise HTTPException(status_code=500, detail=str(e))

@router.delete("/{filename}", dependencies=[Depends(verify_token)])
async def delete_document(filename: str):
    """
    Endpoint for deleting document vector embeddings.
    """
    success, message = delete_document_vectors(filename)
    if success:
        return {"status": "success", "message": message}
    else:
        raise HTTPException(status_code=500, detail=message)


@router.post("/summarize", dependencies=[Depends(verify_token)])
async def summarize_document_endpoint(filename: str = ""):
    """
    Endpoint for summarizing a document.
    Input: filename (query parameter)
    Output: summary text from LLM
    """
    if not filename:
        raise HTTPException(status_code=400, detail="filename parameter is required")
    
    success, result = summarize_document(filename)
    
    if not success:
        raise HTTPException(status_code=500, detail=result)
    
    # Now send the context to LLM for summarization
    from app.llm_manager import get_llm_stream
    
    summarize_prompt = f"""Buatkan ringkasan yang jelas dan padat dari dokumen berikut. 
Ringkasan harus mencakup poin-poin utama dan informasi penting.

Dokumen:
{result}

---

Buat ringkasan dalam Bahasa Indonesia (maksimal 500 kata):"""
    
    messages = [{"role": "user", "content": summarize_prompt}]
    
    # Collect the full response
    full_response = ""
    for chunk in get_llm_stream(messages):
        full_response += chunk
    
    # Remove any model prefix
    if "[MODEL:" in full_response:
        full_response = full_response.split("]", 1)[1] if "]" in full_response else full_response
    
    return {"status": "success", "summary": full_response, "filename": filename}
