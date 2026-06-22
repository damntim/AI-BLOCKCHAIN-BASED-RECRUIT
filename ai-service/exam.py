from fastapi import APIRouter, UploadFile, File
from pydantic import BaseModel
import json, os, io
from llm import llm_chat_retry, load_prompt, load_fixture
from pdf_utils import extract_text_from_pdf

router = APIRouter()


_CLOSED_TYPES = {"mcq", "true_false", "fill_blank", "matching"}
_OPEN_TYPES   = {"written", "scenario", "practical", "case_study"}

_TIME_PER_TYPE = {
    "mcq": 2, "true_false": 1, "fill_blank": 2, "matching": 3,
    "scenario": 8, "practical": 12, "case_study": 15, "written": 10,
}


class ExamGeneratePayload(BaseModel):
    job: dict
    num_questions: int = 15
    closed_ended: int = 10
    open_ended: int = 5
    sample_doc_text: str = ""


class ExamScorePayload(BaseModel):
    questions: list[dict]
    answers: list


def _extract_pdf_text(data: bytes) -> str:
    try:
        return extract_text_from_pdf(data)
    except Exception:
        return ""


def _extract_docx_text(data: bytes) -> str:
    try:
        from docx import Document
        doc = Document(io.BytesIO(data))
        return "\n".join(p.text for p in doc.paragraphs)
    except Exception:
        return ""


@router.post("/exam/generate")
def exam_generate(payload: ExamGeneratePayload):

    # =====================================================================
    # TESTING MODE — returns 5 hardcoded questions instantly, no LLM call
    # To go live: comment out the block below (from "return {" to the "}")
    # =====================================================================
    return {
        "questions": [
            {
                "text": "What is 2 + 2?",
                "type": "mcq",
                "options": ["3", "4", "5", "6"],
                "correct_answer": 1,
                "difficulty": "easy",
                "points": 2,
            },
            {
                "text": "The sky is blue.",
                "type": "true_false",
                "correct_answer": True,
                "difficulty": "easy",
                "points": 1,
            },
            {
                "text": "Water boils at ___ degrees Celsius.",
                "type": "fill_blank",
                "correct_answer": "100",
                "difficulty": "easy",
                "points": 2,
            },
         
        ],
        "total_minutes": 8,
        "total_points": 5,
        "_mode": "testing",
    }
    # =====================================================================
    # END TESTING MODE
    # =====================================================================


    # =====================================================================
    # DEPLOYMENT MODE — uncomment everything below when going live
    # =====================================================================

    # if os.getenv("LLM_ENABLED", "true").lower() != "true":
    #     return json.loads(load_fixture("exam_generate"))

    # # Normalise question counts
    # closed = max(0, payload.closed_ended)
    # opened = max(0, payload.open_ended)
    # total  = payload.num_questions
    # if closed + opened != total:
    #     closed = total - opened
    #     if closed < 0:
    #         opened = 0
    #         closed = total

    # system_prompt = load_prompt("exam_generate_v1")
    # context = ""
    # if payload.sample_doc_text:
    #     context = f"\n\nReference Document Context (base at least 60% of questions on this):\n{payload.sample_doc_text[:6000]}"

    # user_prompt = (
    #     f"Job details:\n{json.dumps(payload.job, indent=2)}\n\n"
    #     f"closed_ended_count: {closed}\n"
    #     f"open_ended_count: {opened}\n"
    #     f"Total questions required: {total}"
    #     f"{context}"
    # )

    # raw = llm_chat_retry(
    #     [{"role": "system", "content": system_prompt}, {"role": "user", "content": user_prompt}],
    #     temperature=0.3, json_mode=True
    # )
    # result = json.loads(raw)

    # questions = result.get("questions", [])
    # closed_qs = [q for q in questions if q.get("type") in _CLOSED_TYPES]
    # open_qs   = [q for q in questions if q.get("type") in _OPEN_TYPES]
    # final = closed_qs[:closed] + open_qs[:opened]

    # total_minutes = sum(_TIME_PER_TYPE.get(q.get("type", "mcq"), 3) for q in final)

    # result["questions"]    = final
    # result["total_minutes"] = total_minutes
    # result["total_points"]  = sum(q.get("points", 2) for q in final)
    # return result


@router.post("/exam/score")
def exam_score(payload: ExamScorePayload):
    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        return json.loads(load_fixture("exam_score"))

    system_prompt = load_prompt("exam_score_v1")
    user_prompt = f"Questions:\n{json.dumps(payload.questions)}\n\nAnswers:\n{json.dumps(payload.answers)}"
    raw = llm_chat_retry(
        [{"role": "system", "content": system_prompt}, {"role": "user", "content": user_prompt}],
        temperature=0.1, json_mode=True
    )
    return json.loads(raw)


@router.post("/exam/extract-doc")
async def exam_extract_doc(file: UploadFile = File(...)):
    """Extract text from a company-uploaded sample document (PDF/DOCX/TXT)."""
    data = await file.read()
    filename = (file.filename or "").lower()

    if filename.endswith(".pdf"):
        text = _extract_pdf_text(data)
    elif filename.endswith(".docx"):
        text = _extract_docx_text(data)
    else:
        try:
            text = data.decode("utf-8", errors="replace")
        except Exception:
            text = ""

    return {"text": text, "length": len(text)}