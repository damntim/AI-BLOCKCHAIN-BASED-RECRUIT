from fastapi import APIRouter, UploadFile, File, HTTPException
import json, os, io
from llm import llm_chat_retry, load_fixture
from pdf_utils import extract_text_from_pdf

router = APIRouter()

CV_OVERVIEW_SYSTEM = """You are an expert HR analyst. A job seeker has uploaded their CV.
Extract key information and provide a concise, honest overview.

Respond ONLY with valid JSON matching this exact schema — no text before or after:
{
  "extractable": true,
  "name": "<full name found in CV or empty string>",
  "summary": "<2-3 sentence professional summary of the candidate>",
  "skills": ["<skill1>", "<skill2>", "..."],
  "experience_years": <integer estimate of total years of experience>,
  "education": "<highest qualification and institution>",
  "strengths": ["<strength1>", "<strength2>", "<strength3>"],
  "improvement_areas": ["<area1>", "<area2>"],
  "overall_impression": "<1 sentence honest overall impression>"
}

If the document is not a proper CV (e.g. blank, image-only, unreadable, or completely unrelated content),
respond with exactly:
{
  "extractable": false,
  "reason": "<brief explanation of why it could not be read>"
}"""


def extract_text_from_pdf_safe(data: bytes) -> str:
    try:
        return extract_text_from_pdf(data)
    except Exception as e:
        raise HTTPException(status_code=422, detail=f"Could not parse PDF: {e}")


@router.post("/cv/extract")
async def cv_extract(file: UploadFile = File(...)):
    """Return raw extracted text from a PDF — used by the screening worker."""
    data = await file.read()
    text = extract_text_from_pdf_safe(data)
    return {"text": text, "extractable": len(text.strip()) >= 50}


@router.post("/cv/overview")
async def cv_overview(file: UploadFile = File(...)):
    if not file.filename or not file.filename.lower().endswith(".pdf"):
        raise HTTPException(status_code=400, detail="Only PDF files are accepted.")

    data = await file.read()
    cv_text = extract_text_from_pdf(data)

    if not cv_text or len(cv_text.strip()) < 50:
        return {"extractable": False, "reason": "The CV appears to be empty or unreadable (possibly a scanned image or blank document)."}

    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        fixture = load_fixture("cv_overview")
        return json.loads(fixture)

    raw = llm_chat_retry(
        [
            {"role": "system", "content": CV_OVERVIEW_SYSTEM},
            {"role": "user", "content": f"CV Content:\n\n{cv_text[:6000]}"},
        ],
        temperature=0.2,
        json_mode=True,
    )
    try:
        result = json.loads(raw)
        if "extractable" not in result:
            result["extractable"] = True
        return result
    except Exception:
        return {"extractable": False, "reason": "AI could not parse the CV. Please upload a proper PDF CV."}
