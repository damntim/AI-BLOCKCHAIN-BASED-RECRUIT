import asyncio, json
from scorer import CheatScorer

sessions: dict[str, CheatScorer] = {}

async def handle_session(websocket, session_id: str):
    scorer = CheatScorer(session_id)
    sessions[session_id] = scorer

    try:
        while True:
            message = await websocket.receive_text()
            data = json.loads(message)
            msg_type = data.get("type")

            if msg_type == "event":
                event_name = data.get("event")
                scorer.add_event(event_name, data.get("timestamp"))
            elif msg_type == "face_scan":
                scorer.add_face_scan(
                    face_count=data.get("face_count", 0),
                    match_score=data.get("match_score", 1.0),
                    gaze=data.get("gaze", "STRAIGHT"),
                )

            action = scorer.get_action()
            if action:
                await websocket.send_text(json.dumps({
                    "action": action,
                    "cheat_score": scorer.score,
                    "message": scorer.get_message(action),
                }))
    except Exception:
        pass
    finally:
        scorer.finalize()
        sessions.pop(session_id, None)
