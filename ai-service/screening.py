from fastapi import APIRouter
from pydantic import BaseModel
import json, os
from llm import llm_chat_retry, load_prompt, load_fixture

router = APIRouter()

class ScreeningPayload(BaseModel):
    job: dict
    candidates: list[dict]

@router.post("/screening/run")
def screening_run(payload: ScreeningPayload):
    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        return json.loads(load_fixture("screening"))

    system_prompt = load_prompt("screening_v1")
    user_prompt = f"Job:\n{json.dumps(payload.job)}\n\nCandidates:\n{json.dumps(payload.candidates)}"
    raw = llm_chat_retry(
        [{"role": "system", "content": system_prompt}, {"role": "user", "content": user_prompt}],
        temperature=0.1, json_mode=True
    )
    return json.loads(raw)
