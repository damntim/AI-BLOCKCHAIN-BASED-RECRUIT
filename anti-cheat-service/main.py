from fastapi import FastAPI, WebSocket, WebSocketDisconnect
from dotenv import load_dotenv
import json, os

load_dotenv()

from ws_handler import handle_session
from scorer import CheatScorer

app = FastAPI(title="RecruitChain Anti-Cheat Service")

@app.get("/health")
def health():
    return {"status": "ok"}

@app.websocket("/session/{session_id}")
async def ws_session(websocket: WebSocket, session_id: str):
    await websocket.accept()
    await handle_session(websocket, session_id)

@app.get("/report/{session_id}")
def get_report(session_id: str):
    return {"session_id": session_id, "status": "report_placeholder", "cheat_score": 0}

@app.post("/override/{session_id}")
def override_session(session_id: str):
    return {"success": True, "session_id": session_id}
