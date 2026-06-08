from fastapi import APIRouter
from pydantic import BaseModel
import json, os
from llm import llm_chat_retry, load_prompt, load_fixture

router = APIRouter()

class EligibilityPayload(BaseModel):
    job: dict
    candidate: dict   # full profile: education, experience, certificates, languages, cv_text, skills, etc.

@router.post("/eligibility/check")
def eligibility_check(payload: EligibilityPayload):
    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        # Fixture fallback
        try:
            return json.loads(load_fixture("eligibility_check"))
        except Exception:
            return {
                "eligible": True,
                "confidence": "low",
                "verdict": "LLM disabled — eligibility could not be assessed.",
                "reasoning": "The AI service is running in offline mode. Eligibility analysis is not available.",
                "strengths": [],
                "gaps": [],
                "education_match": True,
                "experience_match": True,
                "skills_match": True,
            }

    system_prompt = load_prompt("eligibility_v1")

    # Build a clean, readable candidate summary for the LLM
    c = payload.candidate
    edu_summary = []
    for e in c.get("education", []):
        entry = e.get("degree_title", "")
        if e.get("institution"):
            entry += f" — {e['institution']}"
        if e.get("year_completed"):
            entry += f" ({e['year_completed']})"
        edu_summary.append(entry)

    exp_summary = []
    for e in c.get("experience", []):
        entry = f"{e.get('job_title','?')} at {e.get('company_name','?')}"
        start = e.get("start_date", "")[:7] if e.get("start_date") else "?"
        end   = "Present" if e.get("is_current") else (e.get("end_date", "")[:7] if e.get("end_date") else "?")
        entry += f" ({start} – {end})"
        exp_summary.append(entry)

    cert_summary = [
        f"{e.get('title','?')} from {e.get('issuer','?')}" + (f" ({e['year_issued']})" if e.get("year_issued") else "")
        for e in c.get("certificates", [])
    ]

    lang_summary = [
        f"{e.get('language','?')} (reading:{e.get('reading','?')}, writing:{e.get('writing','?')}, speaking:{e.get('speaking','?')})"
        for e in c.get("languages", [])
    ]

    candidate_text = {
        "name": c.get("name", ""),
        "education": edu_summary,
        "experience": exp_summary,
        "certificates": cert_summary,
        "languages": lang_summary,
        "skills": c.get("skills", ""),
        "cv_text": (c.get("cv_text", "") or "")[:4000],  # cap CV text to avoid token overflow
    }

    job = payload.job
    job_text = {
        "title": job.get("title", ""),
        "description": (job.get("description", "") or "")[:2000],
        "required_skills": job.get("required_skills", []),
        "eligible_educations": job.get("eligible_educations", []),
        "required_certs": job.get("required_certs", []),
        "min_experience": job.get("min_experience", 0),
        "responsibilities": job.get("responsibilities", []),
    }

    user_prompt = (
        f"Job Requirements:\n{json.dumps(job_text, indent=2)}\n\n"
        f"Candidate Profile:\n{json.dumps(candidate_text, indent=2)}"
    )

    raw = llm_chat_retry(
        [
            {"role": "system", "content": system_prompt},
            {"role": "user",   "content": user_prompt},
        ],
        temperature=0.1,
        json_mode=True,
    )
    return json.loads(raw)
