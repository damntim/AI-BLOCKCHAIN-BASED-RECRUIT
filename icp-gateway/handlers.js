/**
 * ICP Gateway handlers — each function maps to one canister method.
 *
 * When the canister is deployed, replace the stub bodies with real actor calls:
 *
 *   const { actor } = require('./agent');
 *   async function registerUser(userId, identityHash) {
 *     const ok = await actor.registerUser(BigInt(userId), identityHash);
 *     return { success: ok, txId: `icp-user-${userId}-${Date.now()}` };
 *   }
 */

// ── User ─────────────────────────────────────────────────────────────────────

async function registerUser(userId, identityHash) {
    return { success: true, txId: `icp-user-${userId}-${Date.now()}` };
}

async function setFaceVerified(userId) {
    return { success: true };
}

async function getUser(userId) {
    return { success: true, data: null };
}

// ── Credentials ───────────────────────────────────────────────────────────────

async function storeCredential(userId, docType, fileHash) {
    return { success: true };
}

async function verifyCredential(userId, docType, fileHash) {
    return { success: true, verified: true };
}

// ── Company ───────────────────────────────────────────────────────────────────

async function approveCompany(companyId, companyHash) {
    return { success: true, txId: `icp-company-${companyId}-${Date.now()}` };
}

async function isCompanyApproved(companyId) {
    return { success: true, approved: true };
}

// ── Jobs ──────────────────────────────────────────────────────────────────────

async function sealJob(jobId, jobHash, positions, deadline) {
    return { success: true, txId: `icp-job-${jobId}-${Date.now()}` };
}

async function getJob(jobId) {
    return { success: true, data: null };
}

// ── Applications ──────────────────────────────────────────────────────────────

async function logApplication(userId, jobId) {
    return { success: true };
}

async function hasApplied(userId, jobId) {
    return { success: true, applied: false };
}

// ── Exams ─────────────────────────────────────────────────────────────────────

async function recordExam(userId, jobId, score, antiCheatHash, cheatScore, outcome) {
    return { success: true, txId: `icp-exam-${userId}-${jobId}-${Date.now()}` };
}

async function getExamResult(userId, jobId) {
    return { success: true, data: null };
}

// ── Interviews ────────────────────────────────────────────────────────────────

async function recordInterview(userId, jobId, score, transcriptHash) {
    return { success: true, txId: `icp-iv-${userId}-${jobId}-${Date.now()}` };
}

async function getInterview(userId, jobId) {
    return { success: true, data: null };
}

// ── Hiring results ────────────────────────────────────────────────────────────

async function declareWinners(jobId, winnerIds, finalScores) {
    return { success: true, txId: `icp-hire-${jobId}-${Date.now()}` };
}

async function getHiringResult(jobId) {
    return { success: true, data: null };
}

async function isWinner(userId, jobId) {
    return { success: true, winner: false };
}

// ── NEW: Exam config (ICP-GUIDE.md #1) ───────────────────────────────────────

async function storeExamConfig(jobId, numQuestions, timeLimitMin, openEnded, closedEnded) {
    return { success: true, txId: `icp-examcfg-${jobId}-${Date.now()}` };
}

async function getExamConfig(jobId) {
    return { success: true, data: null };
}

// ── NEW: Credential verification (ICP-GUIDE.md #2) ───────────────────────────

async function storeCredentialVerification(userId, credHash, aiMatchScore, userEdited) {
    return { success: true };
}

async function getCredentialVerification(userId, credHash) {
    return { success: true, data: null };
}

// ── NEW: Exam schedule (ICP-GUIDE.md #3) ─────────────────────────────────────

async function storeExamSchedule(jobId, startEpoch, endEpoch, extendedEpoch) {
    return { success: true, txId: `icp-examsched-${jobId}-${Date.now()}` };
}

async function getExamSchedule(jobId) {
    return { success: true, data: null };
}

// ── NEW: Interview schedule (ICP-GUIDE.md #4) ─────────────────────────────────

async function storeInterviewSchedule(jobId, startEpoch, candidateCount) {
    return { success: true, txId: `icp-ivsched-${jobId}-${Date.now()}` };
}

async function getInterviewSchedule(jobId) {
    return { success: true, data: null };
}

// ── NEW: Job offer (ICP-GUIDE.md #5) ─────────────────────────────────────────

async function storeOffer(jobId, userId, overallRank, offerAccepted) {
    return { success: true, txId: `icp-offer-${jobId}-${userId}-${Date.now()}` };
}

async function getOffer(jobId, userId) {
    return { success: true, data: null };
}

// ── NEW: Audit log (ICP-GUIDE.md #6) ─────────────────────────────────────────

async function getJobAuditLog(jobId) {
    return { success: true, entries: [] };
}

// ─────────────────────────────────────────────────────────────────────────────

module.exports = {
    // user
    registerUser, setFaceVerified, getUser,
    // credentials
    storeCredential, verifyCredential,
    // company
    approveCompany, isCompanyApproved,
    // jobs
    sealJob, getJob,
    // applications
    logApplication, hasApplied,
    // exams
    recordExam, getExamResult,
    // interviews
    recordInterview, getInterview,
    // hiring
    declareWinners, getHiringResult, isWinner,
    // new
    storeExamConfig, getExamConfig,
    storeCredentialVerification, getCredentialVerification,
    storeExamSchedule, getExamSchedule,
    storeInterviewSchedule, getInterviewSchedule,
    storeOffer, getOffer,
    getJobAuditLog,
};
