from fastapi import APIRouter
from pydantic import BaseModel
from typing import Optional
import json, os
from llm import llm_chat_retry, load_prompt, load_fixture

router = APIRouter()


class InterviewStartPayload(BaseModel):
    job: dict
    candidate: dict


class InterviewNextPayload(BaseModel):
    jobId: int
    job: Optional[dict] = {}
    transcript: list[dict]
    answer: str
    questionNum: Optional[int] = 1


class InterviewEvalPayload(BaseModel):
    jobId: int
    job: Optional[dict] = {}
    transcript: list[dict]
    behavioral: Optional[dict] = {}
    violations: Optional[list] = []


@router.post("/interview/start")
def interview_start(payload: InterviewStartPayload):
    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        return json.loads(load_fixture("interview_start"))

    job = payload.job
    candidate = payload.candidate
    system_prompt = load_prompt("interview_start_v1")
    user_prompt = (
        f"Job Title: {job.get('title','')}\n"
        f"Department: {job.get('department','')}\n"
        f"Required Skills: {job.get('required_skills','')}\n"
        f"Experience Level: {job.get('experience_level','')}\n"
        f"Job Description: {job.get('description','')[:500]}\n\n"
        f"Candidate:\n{json.dumps(candidate)}"
    )
    raw = llm_chat_retry(
        [{"role": "system", "content": system_prompt},
         {"role": "user", "content": user_prompt}],
        temperature=0.7, json_mode=True
    )
    return json.loads(raw)


@router.post("/interview/next")
def interview_next(payload: InterviewNextPayload):
    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        q_num = payload.questionNum or 1
        if q_num >= 8:
            return {"done": True, "question": None, "time_seconds": 0}
        return {
            "done": False,
            "question": "Can you give me a specific example of a challenging situation you faced and how you resolved it?",
            "time_seconds": 120
        }

    job = payload.job or {}
    q_num = payload.questionNum or 1
    system_prompt = load_prompt("interview_next_v1")

    # Build a clean transcript summary (last 6 exchanges to stay within context)
    recent = payload.transcript[-12:] if len(payload.transcript) > 12 else payload.transcript
    transcript_text = "\n".join(
        f"[{'AI' if m.get('role')=='ai' else 'Candidate'}]: {m.get('text','')}"
        for m in recent
    )

    user_prompt = (
        f"Job Title: {job.get('title', 'Not specified')}\n"
        f"Department: {job.get('department', '')}\n"
        f"Required Skills: {job.get('required_skills', '')}\n"
        f"Experience Level: {job.get('experience_level', '')}\n"
        f"Job Description (excerpt): {str(job.get('description', ''))[:400]}\n\n"
        f"Interview so far ({q_num} questions asked):\n{transcript_text}\n\n"
        f"Candidate's latest answer:\n{payload.answer}\n\n"
        f"Questions asked so far: {q_num}. Maximum questions: 8."
    )

    raw = llm_chat_retry(
        [{"role": "system", "content": system_prompt},
         {"role": "user", "content": user_prompt}],
        temperature=0.7, json_mode=True
    )
    result = json.loads(raw)

    # Ensure required fields present with safe defaults
    return {
        "done":         bool(result.get("done", False)),
        "question":     result.get("question") or result.get("next_question"),
        "time_seconds": int(result.get("time_seconds", 120)),
    }


@router.post("/interview/evaluate")
def interview_evaluate(payload: InterviewEvalPayload):
    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        v_count = len(payload.violations or [])
        return {
            "technical_score":       72.0,
            "communication_score":   75.0,
            "behavioral_score":      70.0,
            "problem_solving_score": 68.0,
            "professionalism_score": 80.0,
            "confidence_score":      float(payload.behavioral.get("confidence", 60)),
            "anomaly_score":         max(0.0, 100.0 - v_count * 15),
            "attention_score":       float(payload.behavioral.get("engagement", 60)),
            "summary":               "Candidate demonstrated solid technical knowledge with adequate communication skills.",
            "report":                "Full evaluation pending LLM processing.",
            "recommendation":        "Consider for next stage",
        }

    job = payload.job or {}
    behavioral = payload.behavioral or {}
    violations = payload.violations or []

    system_prompt = load_prompt("interview_evaluate_v1")

    # Build transcript text (full for evaluation)
    transcript_text = "\n".join(
        f"[{'AI Interviewer' if m.get('role')=='ai' else 'Candidate'} Q{m.get('q_num','')}]: {m.get('text','')}"
        for m in payload.transcript
    )

    # Summarise violations
    violation_summary = []
    for v in violations:
        violation_summary.append(f"- {v.get('type','?')}: {v.get('msg','')} ({v.get('reason','')})")
    violation_text = "\n".join(violation_summary) if violation_summary else "None"

    user_prompt = (
        f"Job Title: {job.get('title', 'Not specified')}\n"
        f"Department: {job.get('department', '')}\n"
        f"Required Skills: {job.get('required_skills', '')}\n"
        f"Experience Level: {job.get('experience_level', '')}\n\n"
        f"Full Interview Transcript:\n{transcript_text}\n\n"
        f"Behavioral Analytics:\n"
        f"  Confidence level: {behavioral.get('confidence', 50)}/100\n"
        f"  Engagement level: {behavioral.get('engagement', 60)}/100\n"
        f"  Speaking pace: {behavioral.get('pace', 'unknown')}\n"
        f"  Eye contact quality: {behavioral.get('eyeContact', 'unknown')}\n"
        f"  Hesitation count: {behavioral.get('hesitationCount', 0)}\n"
        f"  Avg response time: {behavioral.get('avgResponseSec', 0)}s\n\n"
        f"Proctoring Violations ({len(violations)} total):\n{violation_text}"
    )

    raw = llm_chat_retry(
        [{"role": "system", "content": system_prompt},
         {"role": "user", "content": user_prompt}],
        temperature=0.1, json_mode=True
    )
    result = json.loads(raw)

    # Normalise keys and ensure all required fields
    return {
        "technical_score":       float(result.get("technical_score",       65)),
        "communication_score":   float(result.get("communication_score",   65)),
        "behavioral_score":      float(result.get("behavioral_score",      65)),
        "problem_solving_score": float(result.get("problem_solving_score", 65)),
        "professionalism_score": float(result.get("professionalism_score", 65)),
        "confidence_score":      float(result.get("confidence_score",      50)),
        "anomaly_score":         float(result.get("anomaly_score", max(0, 100 - len(violations) * 15))),
        "attention_score":       float(result.get("attention_score",       60)),
        "summary":               result.get("summary", ""),
        "report":                result.get("report", result.get("summary", "")),
        "recommendation":        result.get("recommendation", ""),
        "strengths":             result.get("strengths", []),
        "areas_for_improvement": result.get("areas_for_improvement", []),
    }
