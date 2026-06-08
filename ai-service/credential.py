from fastapi import APIRouter, HTTPException
from pydantic import BaseModel
import base64, json, os, re
import requests
from llm import llm_chat_retry
from pdf_utils import extract_text_from_pdf

router = APIRouter()

_GVK = "AIzaSyCMJW4lIPucHUg4QB00QMTFtjuvTllpYNQ"
_GVU = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent"

CERT_SYSTEM = """You are a senior forensic document analyst, credential verification specialist, academic records examiner, and anti-fraud investigator.

A user has uploaded a credential, diploma, certificate, transcript, license, award, professional qualification, or educational document.

WARNING:
An incorrect assessment can cause serious legal, employment, immigration, academic, financial, and security consequences. Therefore, you must examine the document with extreme care.

MANDATORY REVIEW PROCESS:

1. Read the entire document carefully.
2. Read it again from top to bottom.
3. Re-check all extracted fields before producing a result.
4. Compare all visible text, dates, names, logos, seals, signatures, certificate numbers, and security features for consistency.
5. Examine the document multiple times before issuing any credibility assessment.
6. Look specifically for evidence of digital manipulation, AI-generated content, edited text, altered photographs, or fabricated credential elements.
7. Never assume authenticity simply because a document looks professional.
8. Never declare a document authentic or fraudulent with certainty unless supported by strong visual evidence.
9. Base every score only on observable evidence present in the document.

Your tasks:

1. Extract the qualification title exactly as written on the document.

2. Extract the institution name exactly as shown.

3. Extract the year awarded if present.

4. Extract certificate number, registration number, serial number, reference number, or verification number if visible.

5. Compare the user-provided title against the document title.

6. Calculate a match_score from 0-100.

7. Assess the document's credibility and authenticity by examining:

AUTHENTICITY INDICATORS:

* Official seals
* Official stamps
* Watermarks
* Institutional logos
* QR codes
* Verification URLs
* Certificate numbers
* Registration numbers
* Security features
* Authorized signatures
* Holograms (if visible)

DIGITAL MANIPULATION INDICATORS:

* Evidence of Photoshop editing
* Text inserted onto an existing document
* Different image compression around specific text areas
* Signs that names, dates, grades, or certificate numbers were replaced
* Edited or replaced photographs
* Cloned visual elements
* Visible editing boundaries
* Artificial overlays
* Erased content
* Reconstructed sections

AI-GENERATION INDICATORS:

* Unrealistic logos
* Distorted seals
* Artificial signatures
* Nonsensical text
* Hallucinated institution information
* Inconsistent document structures
* Common AI image generation artefacts

CREDENTIAL CONSISTENCY:

* Qualification title appears appropriate for the issuing institution
* Dates are logically consistent
* Names remain consistent throughout the document
* Certificate identifiers remain internally consistent
* Institution branding appears consistent
* Security features appear logically placed

INSTITUTION REVIEW:

* Institution name is clearly visible
* Institution branding appears coherent
* Verification information is present if expected
* Institution identity appears plausible based on document contents

8. Generate a credibility_score from 0-100.

Scoring Guidance:

* 90-100: Strong credibility indicators and no meaningful signs of manipulation.
* 70-89: Generally credible but limited verification evidence.
* 50-69: Mixed evidence requiring independent verification.
* 30-49: Significant concerns requiring investigation.
* 0-29: Strong evidence of manipulation, fabrication, or unreadable content.

9. Produce trust_flags describing observations that increase confidence.

10. Produce risk_flags describing observations that reduce confidence.

IMPORTANT:

* If a feature is not visible, state "Not visible" rather than assuming it is absent.
* Distinguish observations from conclusions.
* Do not invent missing information.
* Do not lower credibility solely because of scanning artefacts, photocopy quality, image compression, document age, rotation, shadows, lighting conditions, or minor formatting differences.
* Only flag concerns when there is actual visual evidence suggesting manipulation or fabrication.
* If image quality prevents reliable analysis, reduce confidence accordingly.
* If verification cannot be completed visually, recommend external verification.

Respond ONLY with valid JSON:

{
"suggested_title": "<exact title from document or best extraction>",
"institution": "<institution name>",
"year": <year as integer or null>,
"certificate_number": "<number or null>",
"credibility_score": <0-100>,
"authenticity_assessment": "<High|Medium|Low|Uncertain>",
"document_quality": "<Excellent|Good|Fair|Poor>",
"trust_flags": ["<observation>"],
"risk_flags": ["<concern>"],
"notes": "<two to three sentence summary of the document, its authenticity indicators, and any concerns>"
}

If the document is unreadable, not a credential, heavily obscured, or lacks sufficient information:

{
"suggested_title": "",
"institution": "",
"year": null,
"certificate_number": null,
"credibility_score": 0,
"authenticity_assessment": "Uncertain",
"document_quality": "Poor",
"trust_flags": [],
"risk_flags": ["Unable to verify document contents"],
"notes": "Document is unreadable or does not appear to be a credential."
}
"""


IMAGE_MIMES = {"image/jpeg", "image/png", "image/webp", "image/gif"}


def _detect_mime(data: bytes) -> str:
    if data[:4] == b"%PDF":
        return "application/pdf"
    if data[:8] == b"\x89PNG\r\n\x1a\n":
        return "image/png"
    if data[:2] == b"\xff\xd8":
        return "image/jpeg"
    if data[:4] in (b"RIFF", b"WEBP"):
        return "image/webp"
    return "application/octet-stream"


def _user_context(payload: "CertAnalysePayload") -> str:
    parts = [f"- Degree/qualification title: {payload.user_title or '(not provided)'}",
             f"- Institution: {payload.user_institution or '(not provided)'}",
             f"- Country: {payload.user_country or '(not provided)'}",
             f"- Year completed: {payload.user_year or '(not provided)'}"]
    return "User-provided information:\n" + "\n".join(parts)


def _gemini_vision(image_b64: str, image_mime: str, payload: "CertAnalysePayload") -> dict:
    body = {
        "contents": [{
            "parts": [
                {"text": CERT_SYSTEM + "\n\n" + _user_context(payload)},
                {"inline_data": {"mime_type": image_mime, "data": image_b64}},
            ]
        }],
        "generationConfig": {"temperature": 0.1},
    }
    resp = requests.post(_GVU, params={"key": _GVK}, json=body, timeout=60)
    resp.raise_for_status()
    text = resp.json()["candidates"][0]["content"]["parts"][0]["text"]
    text = re.sub(r"^```[a-z]*\n?", "", text.strip(), flags=re.IGNORECASE)
    text = re.sub(r"\n?```$", "", text.strip())
    result = json.loads(text)
    result["credibility_score"] = max(0, min(100, int(result.get("credibility_score", 0))))
    return result


class CertAnalysePayload(BaseModel):
    cert_base64: str
    user_title: str = ""
    user_institution: str = ""
    user_country: str = ""
    user_year: str = ""


@router.post("/cert/analyse")
def cert_analyse(payload: CertAnalysePayload):
    try:
        raw_bytes = base64.b64decode(payload.cert_base64)
    except Exception as e:
        raise HTTPException(status_code=422, detail=f"Invalid base64: {e}")

    mime = _detect_mime(raw_bytes)

    if os.getenv("LLM_ENABLED", "true").lower() != "true":
        return {
            "suggested_title": payload.user_title or "Sample Diploma",
            "institution": payload.user_institution or "Sample University",
            "year": int(payload.user_year) if payload.user_year else 2023,
            "certificate_number": None,
            "credibility_score": 85,
            "authenticity_assessment": "High",
            "document_quality": "Good",
            "trust_flags": ["LLM disabled — fixture response"],
            "risk_flags": [],
            "notes": "LLM disabled — fixture response.",
        }

    if mime in IMAGE_MIMES:
        return _gemini_vision(payload.cert_base64, mime, payload)

    # PDF path
    try:
        text = extract_text_from_pdf(raw_bytes).strip()
    except Exception as e:
        raise HTTPException(status_code=422, detail=f"Could not parse PDF: {e}")

    if len(text) >= 10:
        # Selectable text — use text LLM
        raw = llm_chat_retry(
            [
                {"role": "system", "content": CERT_SYSTEM},
                {"role": "user", "content": _user_context(payload) + f"\n\nDocument text:\n{text[:4000]}"},
            ],
            temperature=0.1,
            json_mode=True,
        )
        try:
            result = json.loads(raw)
            result["credibility_score"] = max(0, min(100, int(result.get("credibility_score", 0))))
            return result
        except Exception:
            return {"suggested_title": "", "institution": "", "year": None, "certificate_number": None,
                    "credibility_score": 0, "authenticity_assessment": "Uncertain", "document_quality": "Poor",
                    "trust_flags": [], "risk_flags": ["Could not parse AI response"], "notes": "Could not parse AI response."}

    # Scanned PDF — render first page to PNG, send to Gemini vision
    try:
        import fitz
        doc = fitz.open(stream=raw_bytes, filetype="pdf")
        pix = doc[0].get_pixmap(dpi=150)
        png_b64 = base64.b64encode(pix.tobytes("png")).decode()
    except Exception as e:
        raise HTTPException(status_code=422, detail=f"Could not render PDF page: {e}")

    return _gemini_vision(png_b64, "image/png", payload)
