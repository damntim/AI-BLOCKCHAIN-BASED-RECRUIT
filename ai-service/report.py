from fastapi import APIRouter
from pydantic import BaseModel
import json, os
from llm import llm_chat_retry, load_prompt, load_fixture

router = APIRouter()

class ReportPayload(BaseModel):
    job: dict
    candidates: list[dict]

@router.post("/report/final")
def report_final(payload: ReportPayload):
    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        return {"ranked_candidates": payload.candidates, "summary": "Mock report generated."}

    system_prompt = load_prompt("report_final_v1")
    user_prompt = f"Job:\n{json.dumps(payload.job)}\n\nCandidates:\n{json.dumps(payload.candidates)}"
    raw = llm_chat_retry(
        [{"role": "system", "content": system_prompt}, {"role": "user", "content": user_prompt}],
        temperature=0.2, json_mode=True
    )
    return json.loads(raw)
