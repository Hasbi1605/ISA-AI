import os

from dotenv import load_dotenv
from fastapi import FastAPI

from app.api_shared import HealthResponse, build_health_payload
from app.routers import documents

# Load .env from the project root (python-ai/.env)
load_dotenv(os.path.join(os.path.dirname(os.path.dirname(__file__)), ".env"))

app = FastAPI(title="ISTA AI Document Microservice", version="1.2.0")
app.include_router(documents.router)


@app.get("/api/health", response_model=HealthResponse)
async def health_check():
    return build_health_payload()
