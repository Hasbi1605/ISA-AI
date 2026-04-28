from app.chat_api import ChatRequest, HealthResponse, app, chat_stream, health_check


if __name__ == "__main__":
    import uvicorn

    uvicorn.run(app, host="0.0.0.0", port=8001)
