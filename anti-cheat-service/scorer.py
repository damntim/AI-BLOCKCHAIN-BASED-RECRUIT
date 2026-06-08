from datetime import datetime

WEIGHTS = {
    "FACE_MISMATCH": 30,
    "MULTIPLE_FACES": 25,
    "FACE_MISSING_30S": 20,
    "TAB_SWITCH": 15,
    "FULLSCREEN_EXIT": 15,
    "DEV_TOOLS_OPEN": 12,
    "PASTE_ATTEMPT": 10,
    "SCREEN_RECORDING": 10,
    "KEYSTROKE_ANOMALY": 8,
    "GAZE_OFF_CAMERA_3X": 7,
    "IDLE_60S": 5,
    "RIGHT_CLICK": 3,
}

class CheatScorer:
    def __init__(self, session_id: str):
        self.session_id = session_id
        self.score = 0
        self.events = []
        self.last_action = None

    def add_event(self, event_name: str, timestamp: str = None):
        points = WEIGHTS.get(event_name, 0)
        self.score = min(100, self.score + points)
        self.events.append({
            "event": event_name,
            "points": points,
            "cumulative": self.score,
            "at": timestamp or datetime.utcnow().isoformat(),
        })

    def add_face_scan(self, face_count: int, match_score: float, gaze: str):
        if face_count == 0:
            self.add_event("FACE_MISSING_30S")
        elif face_count > 1:
            self.add_event("MULTIPLE_FACES")
        if match_score > 0.45:
            self.add_event("FACE_MISMATCH")
        if gaze != "STRAIGHT":
            self.add_event("GAZE_OFF_CAMERA_3X")

    def get_action(self) -> str | None:
        if self.score <= 20:
            action = "CLEAN"
        elif self.score <= 40:
            action = "FLAG"
        elif self.score <= 60:
            action = "WARN"
        elif self.score <= 80:
            action = "PAUSE"
        else:
            action = "TERMINATE"

        if action != self.last_action and action != "CLEAN":
            self.last_action = action
            return action
        return None

    def get_message(self, action: str) -> str:
        return {
            "FLAG": "Suspicious activity detected.",
            "WARN": "Please stay focused on the exam.",
            "PAUSE": "Session paused. Please verify your identity.",
            "TERMINATE": "Session voided: integrity violation detected.",
        }.get(action, "")

    def finalize(self):
        pass  # Could persist to DB here
