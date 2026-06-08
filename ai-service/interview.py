from fastapi import APIRouter
from pydantic import BaseModel
import json, os
from llm import llm_chat_retry, load_prompt, load_fixture

router = APIRouter()

class InterviewStartPayload(BaseModel):
    job: dict
    candidate: dict

class InterviewNextPayload(BaseModel):
    jobId: int
    transcript: list[dict]
    answer: str

class InterviewEvalPayload(BaseModel):
    jobId: int
    transcript: list[dict]

@router.post("/interview/start")
def interview_start(payload: InterviewStartPayload):
    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        return {"question": "Tell me about your experience and what interests you about this role."}

    system_prompt = load_prompt("interview_start_v1")
    user_prompt = f"Job:\n{json.dumps(payload.job)}\n\nCandidate:\n{json.dumps(payload.candidate)}"
    raw = llm_chat_retry(
        [{"role": "system", "content": system_prompt}, {"role": "user", "content": user_prompt}],
        temperature=0.7, json_mode=True
    )
    return json.loads(raw)

@router.post("/interview/next")
def interview_next(payload: InterviewNextPayload):
    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        return {"question": "Can you elaborate on that? Give me a specific example."}

    system_prompt = load_prompt("interview_next_v1")
    user_prompt = f"Transcript:\n{json.dumps(payload.transcript)}\n\nLatest answer:\n{payload.answer}"
    raw = llm_chat_retry(
        [{"role": "system", "content": system_prompt}, {"role": "user", "content": user_prompt}],
        temperature=0.7, json_mode=True
    )
    return json.loads(raw)

@router.post("/interview/evaluate")
def interview_evaluate(payload: InterviewEvalPayload):
    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        return {"technical_score": 72, "communication_score": 75, "behavioral_score": 70, "problem_solving_score": 68, "professionalism_score": 80}

    system_prompt = load_prompt("interview_evaluate_v1")
    user_prompt = f"Transcript:\n{json.dumps(payload.transcript)}"
    raw = llm_chat_retry(
        [{"role": "system", "content": system_prompt}, {"role": "user", "content": user_prompt}],
        temperature=0.1, json_mode=True
    )
    return json.loads(raw)
