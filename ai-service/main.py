from fastapi import FastAPI
from dotenv import load_dotenv
import os

load_dotenv()

from screening import router as screening_router
from exam import router as exam_router
from interview import router as interview_router
from report import router as report_router
from cv import router as cv_router
from credential import router as credential_router
from eligibility import router as eligibility_router
from environment import router as environment_router

app = FastAPI(title="RecruitChain AI Service")

app.include_router(screening_router)
app.include_router(exam_router)
app.include_router(interview_router)
app.include_router(report_router)
app.include_router(cv_router)
app.include_router(credential_router)
app.include_router(eligibility_router)
app.include_router(environment_router)

@app.get("/health")
def health():
    return {
        "status": "ok",
        "llm_enabled": os.getenv("LLM_ENABLED", "true"),
        "model": os.getenv("LLM_MODEL", "unknown"),
    }
