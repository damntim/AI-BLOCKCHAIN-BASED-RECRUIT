const express = require('express');
require('dotenv').config();
const h = require('./handlers');

const app = express();
app.use(express.json());

app.get('/health', (_req, res) =>
    res.json({ status: 'ok', canister: process.env.CANISTER_ID || 'not-set' })
);

// Each entry: [path, handler]
// POST routes — write operations
// GET-style queries sent as POST with body for simplicity (PHP curl)
const routes = [
    // User
    ['/user/register',       (b) => h.registerUser(b.userId, b.identityHash)],
    ['/user/face-verified',  (b) => h.setFaceVerified(b.userId)],
    ['/user/get',            (b) => h.getUser(b.userId)],

    // Credentials
    ['/credential/store',    (b) => h.storeCredential(b.userId, b.docType, b.fileHash)],
    ['/credential/verify',   (b) => h.verifyCredential(b.userId, b.docType, b.fileHash)],

    // Credential verification audit (ICP-GUIDE #2)
    ['/credential/verify-audit',     (b) => h.storeCredentialVerification(b.userId, b.credHash, b.aiMatchScore, b.userEdited)],
    ['/credential/verify-audit/get', (b) => h.getCredentialVerification(b.userId, b.credHash)],

    // Company
    ['/company/approve',     (b) => h.approveCompany(b.companyId, b.companyHash)],
    ['/company/is-approved', (b) => h.isCompanyApproved(b.companyId)],

    // Jobs
    ['/job/seal',            (b) => h.sealJob(b.jobId, b.jobHash, b.positions, b.deadline)],
    ['/job/get',             (b) => h.getJob(b.jobId)],

    // Exam config (ICP-GUIDE #1)
    ['/job/exam-config',     (b) => h.storeExamConfig(b.jobId, b.numQuestions, b.timeLimitMin, b.openEnded, b.closedEnded)],
    ['/job/exam-config/get', (b) => h.getExamConfig(b.jobId)],

    // Exam schedule (ICP-GUIDE #3)
    ['/job/exam-schedule',       (b) => h.storeExamSchedule(b.jobId, b.startEpoch, b.endEpoch, b.extendedEpoch ?? 0)],
    ['/job/exam-schedule/get',   (b) => h.getExamSchedule(b.jobId)],

    // Interview schedule (ICP-GUIDE #4)
    ['/job/interview-schedule',      (b) => h.storeInterviewSchedule(b.jobId, b.startEpoch, b.candidateCount)],
    ['/job/interview-schedule/get',  (b) => h.getInterviewSchedule(b.jobId)],

    // Applications
    ['/application/log',     (b) => h.logApplication(b.userId, b.jobId)],
    ['/application/check',   (b) => h.hasApplied(b.userId, b.jobId)],

    // Exam results
    ['/exam/record',         (b) => h.recordExam(b.userId, b.jobId, b.score, b.antiCheatHash, b.cheatScore, b.outcome)],
    ['/exam/get',            (b) => h.getExamResult(b.userId, b.jobId)],

    // Interview results
    ['/interview/record',    (b) => h.recordInterview(b.userId, b.jobId, b.score, b.transcriptHash)],
    ['/interview/get',       (b) => h.getInterview(b.userId, b.jobId)],

    // Hiring
    ['/hiring/declare',      (b) => h.declareWinners(b.jobId, b.winnerIds, b.finalScores)],
    ['/result/job',          (b) => h.getHiringResult(b.jobId)],
    ['/result/is-winner',    (b) => h.isWinner(b.userId, b.jobId)],

    // Job offer (ICP-GUIDE #5)
    ['/offer/store',         (b) => h.storeOffer(b.jobId, b.userId, b.overallRank, b.offerAccepted)],
    ['/offer/get',           (b) => h.getOffer(b.jobId, b.userId)],

    // Audit log (ICP-GUIDE #6)
    ['/audit/job',           (b) => h.getJobAuditLog(b.jobId)],
];

routes.forEach(([path, fn]) => {
    app.post(path, async (req, res) => {
        try {
            const result = await fn(req.body);
            res.json(result);
        } catch (err) {
            console.error(`[ICP Gateway] ${path} error:`, err.message);
            res.status(500).json({ success: false, error: err.message });
        }
    });
});

const port = process.env.PORT || 3001;
app.listen(port, () =>
    console.log(`ICP Gateway running on port ${port} | Canister: ${process.env.CANISTER_ID || 'not-set'}`)
);
