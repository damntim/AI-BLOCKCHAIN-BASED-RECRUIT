/**
 * ICP Gateway handlers — thin wrappers around real canister calls.
 *
 * Type mapping:  JS number / string → BigInt for nat/int Candid types.
 *                Opt<T> canister return → array of 0 or 1 elements → unwrap to null or object.
 *
 * Every function is async so the Express router can await it uniformly.
 */

const { getActor, resetActor } = require('./agent');

// Candid Opt<T> returns [] (none) or [value] (some)
function unwrapOpt(opt) {
    return Array.isArray(opt) && opt.length > 0 ? opt[0] : null;
}

// Canister calls can fail if the replica restarts; reset the actor so the
// next request rebuilds it rather than reusing a broken connection.
async function call(fn) {
    try {
        const actor = await getActor();
        return await fn(actor);
    } catch (err) {
        if (err.message && err.message.includes('fetch')) resetActor();
        throw err;
    }
}

// ── User ──────────────────────────────────────────────────────────────────────

async function registerUser(userId, identityHash) {
    const ok = await call(a => a.registerUser(BigInt(userId), String(identityHash)));
    return { success: Boolean(ok), txId: `icp-user-${userId}-${Date.now()}` };
}

async function setFaceVerified(userId) {
    const ok = await call(a => a.setFaceVerified(BigInt(userId)));
    return { success: Boolean(ok) };
}

async function getUser(userId) {
    const raw = await call(a => a.getUser(BigInt(userId)));
    const data = unwrapOpt(raw);
    return { success: true, data: data ? normUser(data) : null };
}

function normUser(u) {
    return {
        userId:       Number(u.userId),
        identityHash: u.identityHash,
        registeredAt: Number(u.registeredAt),
        faceVerified: Boolean(u.faceVerified),
    };
}

// ── Credentials ───────────────────────────────────────────────────────────────

async function storeCredential(userId, docType, fileHash) {
    const ok = await call(a => a.storeCredential(BigInt(userId), String(docType), String(fileHash)));
    return { success: Boolean(ok) };
}

async function verifyCredential(userId, docType, fileHash) {
    const ok = await call(a => a.verifyCredential(BigInt(userId), String(docType), String(fileHash)));
    return { success: true, verified: Boolean(ok) };
}

// ── Company ───────────────────────────────────────────────────────────────────

async function approveCompany(companyId, companyHash) {
    const ok = await call(a => a.approveCompany(BigInt(companyId), String(companyHash)));
    return { success: Boolean(ok), txId: `icp-company-${companyId}-${Date.now()}` };
}

async function isCompanyApproved(companyId) {
    const ok = await call(a => a.isCompanyApproved(BigInt(companyId)));
    return { success: true, approved: Boolean(ok) };
}

// ── Jobs ──────────────────────────────────────────────────────────────────────

async function sealJob(jobId, jobHash, positions, deadline) {
    const ok = await call(a => a.sealJob(
        BigInt(jobId),
        String(jobHash),
        BigInt(positions),
        BigInt(deadline),   // Int (epoch seconds)
    ));
    return { success: Boolean(ok), txId: `icp-job-${jobId}-${Date.now()}` };
}

async function getJob(jobId) {
    const raw  = await call(a => a.getJob(BigInt(jobId)));
    const data = unwrapOpt(raw);
    return { success: true, data: data ? normJob(data) : null };
}

function normJob(j) {
    return {
        jobId:     Number(j.jobId),
        jobHash:   j.jobHash,
        positions: Number(j.positions),
        deadline:  Number(j.deadline),
        postedAt:  Number(j.postedAt),
    };
}

// ── Applications ──────────────────────────────────────────────────────────────

async function logApplication(userId, jobId) {
    const ok = await call(a => a.logApplication(BigInt(userId), BigInt(jobId)));
    return { success: Boolean(ok) };
}

async function hasApplied(userId, jobId) {
    const ok = await call(a => a.hasApplied(BigInt(userId), BigInt(jobId)));
    return { success: true, applied: Boolean(ok) };
}

// ── Exams ─────────────────────────────────────────────────────────────────────

async function recordExam(userId, jobId, score, antiCheatHash, cheatScore, outcome) {
    const ok = await call(a => a.recordExam(
        BigInt(userId),
        BigInt(jobId),
        BigInt(Math.round(score)),
        String(antiCheatHash),
        BigInt(Math.round(cheatScore)),
        String(outcome),
    ));
    return { success: Boolean(ok), txId: `icp-exam-${userId}-${jobId}-${Date.now()}` };
}

async function getExamResult(userId, jobId) {
    const raw  = await call(a => a.getExamResult(BigInt(userId), BigInt(jobId)));
    const data = unwrapOpt(raw);
    return { success: true, data: data ? normExam(data) : null };
}

function normExam(e) {
    return {
        userId:        Number(e.userId),
        jobId:         Number(e.jobId),
        score:         Number(e.score),
        antiCheatHash: e.antiCheatHash,
        cheatScore:    Number(e.cheatScore),
        outcome:       e.outcome,
        submittedAt:   Number(e.submittedAt),
    };
}

// ── Interviews ────────────────────────────────────────────────────────────────

async function recordInterview(userId, jobId, score, transcriptHash) {
    const ok = await call(a => a.recordInterview(
        BigInt(userId),
        BigInt(jobId),
        BigInt(Math.round(score)),
        String(transcriptHash),
    ));
    return { success: Boolean(ok), txId: `icp-iv-${userId}-${jobId}-${Date.now()}` };
}

async function getInterview(userId, jobId) {
    const raw  = await call(a => a.getInterview(BigInt(userId), BigInt(jobId)));
    const data = unwrapOpt(raw);
    return { success: true, data: data ? normIv(data) : null };
}

function normIv(iv) {
    return {
        userId:         Number(iv.userId),
        jobId:          Number(iv.jobId),
        score:          Number(iv.score),
        transcriptHash: iv.transcriptHash,
        completedAt:    Number(iv.completedAt),
    };
}

// ── Hiring results ────────────────────────────────────────────────────────────

async function declareWinners(jobId, winnerIds, finalScores) {
    const ok = await call(a => a.declareWinners(
        BigInt(jobId),
        (winnerIds  || []).map(id  => BigInt(id)),
        (finalScores || []).map(s  => BigInt(Math.round(s))),
    ));
    return { success: Boolean(ok), txId: `icp-hire-${jobId}-${Date.now()}` };
}

async function getHiringResult(jobId) {
    const raw  = await call(a => a.getHiringResult(BigInt(jobId)));
    const data = unwrapOpt(raw);
    if (!data) return { success: true, data: null };

    const winnerIds = data.winnerIds.map(Number);
    // Recompute the same SHA-256 hash the PHP side stores so the verifier can compare
    const crypto = require('crypto');
    const winnersHash = crypto.createHash('sha256')
        .update(JSON.stringify(winnerIds))
        .digest('hex');

    return {
        success: true,
        data: {
            jobId:       Number(data.jobId),
            winnerIds,
            finalScores: data.finalScores.map(Number),
            declaredAt:  Number(data.declaredAt),
            winnersHash,
        },
    };
}

async function isWinner(userId, jobId) {
    const ok = await call(a => a.isWinner(BigInt(userId), BigInt(jobId)));
    return { success: true, winner: Boolean(ok) };
}

// ── Exam config ───────────────────────────────────────────────────────────────

async function storeExamConfig(jobId, numQuestions, timeLimitMin, openEnded, closedEnded) {
    const ok = await call(a => a.storeExamConfig(
        BigInt(jobId),
        BigInt(numQuestions),
        BigInt(timeLimitMin),
        BigInt(openEnded),
        BigInt(closedEnded),
    ));
    return { success: Boolean(ok), txId: `icp-examcfg-${jobId}-${Date.now()}` };
}

async function getExamConfig(jobId) {
    const raw  = await call(a => a.getExamConfig(BigInt(jobId)));
    const data = unwrapOpt(raw);
    return {
        success: true,
        data: data ? {
            jobId:        Number(data.jobId),
            numQuestions: Number(data.numQuestions),
            timeLimitMin: Number(data.timeLimitMin),
            openEnded:    Number(data.openEnded),
            closedEnded:  Number(data.closedEnded),
            sealedAt:     Number(data.sealedAt),
        } : null,
    };
}

// ── Credential verification audit ─────────────────────────────────────────────

async function storeCredentialVerification(userId, credHash, aiMatchScore, userEdited) {
    const ok = await call(a => a.storeCredentialVerification(
        BigInt(userId),
        String(credHash),
        BigInt(Math.round(aiMatchScore)),
        Boolean(userEdited),
    ));
    return { success: Boolean(ok) };
}

async function getCredentialVerification(userId, credHash) {
    const raw  = await call(a => a.getCredentialVerification(BigInt(userId), String(credHash)));
    const data = unwrapOpt(raw);
    return {
        success: true,
        data: data ? {
            userId:       Number(data.userId),
            credHash:     data.credHash,
            aiMatchScore: Number(data.aiMatchScore),
            userEdited:   Boolean(data.userEdited),
            storedAt:     Number(data.storedAt),
        } : null,
    };
}

// ── Exam schedule ─────────────────────────────────────────────────────────────

async function storeExamSchedule(jobId, startEpoch, endEpoch, extendedEpoch) {
    const ok = await call(a => a.storeExamSchedule(
        BigInt(jobId),
        BigInt(startEpoch),
        BigInt(endEpoch),
        BigInt(extendedEpoch || endEpoch),
    ));
    return { success: Boolean(ok), txId: `icp-examsched-${jobId}-${Date.now()}` };
}

async function getExamSchedule(jobId) {
    const raw  = await call(a => a.getExamSchedule(BigInt(jobId)));
    const data = unwrapOpt(raw);
    return {
        success: true,
        data: data ? {
            jobId:         Number(data.jobId),
            startEpoch:    Number(data.startEpoch),
            endEpoch:      Number(data.endEpoch),
            extendedEpoch: Number(data.extendedEpoch),
            sealedAt:      Number(data.sealedAt),
        } : null,
    };
}

// ── Interview schedule ────────────────────────────────────────────────────────

async function storeInterviewSchedule(jobId, startEpoch, candidateCount) {
    const ok = await call(a => a.storeInterviewSchedule(
        BigInt(jobId),
        BigInt(startEpoch),
        BigInt(candidateCount),
    ));
    return { success: Boolean(ok), txId: `icp-ivsched-${jobId}-${Date.now()}` };
}

async function getInterviewSchedule(jobId) {
    const raw  = await call(a => a.getInterviewSchedule(BigInt(jobId)));
    const data = unwrapOpt(raw);
    return {
        success: true,
        data: data ? {
            jobId:          Number(data.jobId),
            startEpoch:     Number(data.startEpoch),
            candidateCount: Number(data.candidateCount),
            sealedAt:       Number(data.sealedAt),
        } : null,
    };
}

// ── Job offer ─────────────────────────────────────────────────────────────────

async function storeOffer(jobId, userId, overallRank, offerAccepted) {
    const ok = await call(a => a.storeOffer(
        BigInt(jobId),
        BigInt(userId),
        BigInt(overallRank),
        Boolean(offerAccepted),
    ));
    return { success: Boolean(ok), txId: `icp-offer-${jobId}-${userId}-${Date.now()}` };
}

async function getOffer(jobId, userId) {
    const raw  = await call(a => a.getOffer(BigInt(jobId), BigInt(userId)));
    const data = unwrapOpt(raw);
    return {
        success: true,
        data: data ? {
            jobId:         Number(data.jobId),
            userId:        Number(data.userId),
            overallRank:   Number(data.overallRank),
            offerAccepted: Boolean(data.offerAccepted),
            recordedAt:    Number(data.recordedAt),
        } : null,
    };
}

// ── Audit log + stats ─────────────────────────────────────────────────────────

async function getJobAuditLog(jobId) {
    const entries = await call(a => a.getJobAuditLog(BigInt(jobId)));
    return {
        success: true,
        entries: (entries || []).map(e => ({
            eventType: e.eventType,
            actorId:   Number(e.actor_id),
            payload:   e.payload,
            timestamp: Number(e.timestamp),
        })),
    };
}

async function getStorageStats() {
    const s = await call(a => a.getStorageStats());
    return {
        success: true,
        data: s ? {
            users:         Number(s.users),
            credentials:   Number(s.credentials),
            companies:     Number(s.companies),
            jobs:          Number(s.jobs),
            applications:  Number(s.applications),
            examResults:   Number(s.examResults),
            interviews:    Number(s.interviews),
            hiringResults: Number(s.hiringResults),
            examConfigs:   Number(s.examConfigs),
            credVerif:     Number(s.credVerif),
            examSchedules: Number(s.examSchedules),
            ivSchedules:   Number(s.ivSchedules),
            jobOffers:     Number(s.jobOffers),
            auditLog:      Number(s.auditLog),
        } : null,
    };
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
    // exam config
    storeExamConfig, getExamConfig,
    // credential verification
    storeCredentialVerification, getCredentialVerification,
    // exam schedule
    storeExamSchedule, getExamSchedule,
    // interview schedule
    storeInterviewSchedule, getInterviewSchedule,
    // job offer
    storeOffer, getOffer,
    // audit + stats
    getJobAuditLog, getStorageStats,
};
