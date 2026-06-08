from fastapi import APIRouter
from pydantic import BaseModel
import base64, json, os
from llm import llm_chat_retry

router = APIRouter()


class EnvCheckPayload(BaseModel):
    image_b64: str = ""  # base64-encoded JPEG (no data URI prefix)


@router.post("/environment/check")
def environment_check(payload: EnvCheckPayload):
    """
    Analyse a webcam frame to verify the exam environment.
    Falls back gracefully if LLM is disabled or image cannot be processed.
    """
    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        return _grant("LLM disabled — environment check skipped.")

    if not payload.image_b64 or len(payload.image_b64) < 100:
        return _grant("No image provided.")

    # Build a vision-capable message if the model supports it,
    # otherwise use a heuristic text prompt (Ollama vision models vary)
    try:
        messages = [
            {
                "role": "system",
                "content": (
                    "You are an exam proctoring assistant. Analyse the provided webcam image "
                    "and assess the candidate's exam environment. "
                    "Return a JSON object with these boolean fields:\n"
                    "  seated: true if the candidate appears to be seated at a desk/table\n"
                    "  background: true if the background is plain/neutral with no other people visible\n"
                    "  lighting: true if the room is well-lit and the face is clearly visible\n"
                    "  alone: true if only one person is visible\n"
                    "  feedback: short human-readable explanation (1-2 sentences)\n"
                    "If you cannot determine a factor from the image, default it to true. "
                    "Only return the JSON object, no other text."
                ),
            },
            {
                "role": "user",
                "content": [
                    {
                        "type": "image_url",
                        "image_url": {
                            "url": f"data:image/jpeg;base64,{payload.image_b64}",
                        },
                    },
                    {
                        "type": "text",
                        "text": "Analyse this webcam frame for exam environment compliance.",
                    },
                ],
            },
        ]
        raw = llm_chat_retry(messages, temperature=0.0, json_mode=True)
        result = json.loads(raw)
        return {
            "seated":     bool(result.get("seated", True)),
            "background": bool(result.get("background", True)),
            "lighting":   bool(result.get("lighting", True)),
            "alone":      bool(result.get("alone", True)),
            "feedback":   result.get("feedback", "Environment checked."),
        }
    except Exception:
        return _grant("Environment analysis unavailable. Proceeding with self-certification.")


def _grant(feedback: str) -> dict:
    return {
        "seated": True, "background": True,
        "lighting": True, "alone": True,
        "feedback": feedback,
    }
