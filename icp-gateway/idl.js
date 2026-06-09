// IDL factory for recruitment canister
// Generated from icp/recruitment.did — update when the canister interface changes.
//
// The factory signature ({ IDL }) matches what Actor.createActor() injects at runtime.
// All record/type definitions must live inside the factory so they use the injected IDL.

const idlFactory = ({ IDL }) => {
    const UserRecord = IDL.Record({
        userId:       IDL.Nat,
        identityHash: IDL.Text,
        registeredAt: IDL.Int,
        faceVerified: IDL.Bool,
    });

    const JobRecord = IDL.Record({
        jobId:     IDL.Nat,
        jobHash:   IDL.Text,
        positions: IDL.Nat,
        deadline:  IDL.Int,
        postedAt:  IDL.Int,
    });

    const ExamRecord = IDL.Record({
        userId:        IDL.Nat,
        jobId:         IDL.Nat,
        score:         IDL.Nat,
        antiCheatHash: IDL.Text,
        cheatScore:    IDL.Nat,
        outcome:       IDL.Text,
        submittedAt:   IDL.Int,
    });

    const InterviewRecord = IDL.Record({
        userId:         IDL.Nat,
        jobId:          IDL.Nat,
        score:          IDL.Nat,
        transcriptHash: IDL.Text,
        completedAt:    IDL.Int,
    });

    const HiringResult = IDL.Record({
        jobId:       IDL.Nat,
        winnerIds:   IDL.Vec(IDL.Nat),
        finalScores: IDL.Vec(IDL.Nat),
        declaredAt:  IDL.Int,
    });

    const ExamConfig = IDL.Record({
        jobId:        IDL.Nat,
        numQuestions: IDL.Nat,
        timeLimitMin: IDL.Nat,
        openEnded:    IDL.Nat,
        closedEnded:  IDL.Nat,
        sealedAt:     IDL.Int,
    });

    const CredentialVerification = IDL.Record({
        userId:       IDL.Nat,
        credHash:     IDL.Text,
        aiMatchScore: IDL.Nat,
        userEdited:   IDL.Bool,
        storedAt:     IDL.Int,
    });

    const ExamSchedule = IDL.Record({
        jobId:         IDL.Nat,
        startEpoch:    IDL.Int,
        endEpoch:      IDL.Int,
        extendedEpoch: IDL.Int,
        sealedAt:      IDL.Int,
    });

    const InterviewSchedule = IDL.Record({
        jobId:          IDL.Nat,
        startEpoch:     IDL.Int,
        candidateCount: IDL.Nat,
        sealedAt:       IDL.Int,
    });

    const JobOffer = IDL.Record({
        jobId:         IDL.Nat,
        userId:        IDL.Nat,
        overallRank:   IDL.Nat,
        offerAccepted: IDL.Bool,
        recordedAt:    IDL.Int,
    });

    const AuditEntry = IDL.Record({
        eventType: IDL.Text,
        actor_id:  IDL.Nat,
        payload:   IDL.Text,
        timestamp: IDL.Int,
    });

    const StorageStats = IDL.Record({
        users:         IDL.Nat,
        credentials:   IDL.Nat,
        companies:     IDL.Nat,
        jobs:          IDL.Nat,
        applications:  IDL.Nat,
        examResults:   IDL.Nat,
        interviews:    IDL.Nat,
        hiringResults: IDL.Nat,
        examConfigs:   IDL.Nat,
        credVerif:     IDL.Nat,
        examSchedules: IDL.Nat,
        ivSchedules:   IDL.Nat,
        jobOffers:     IDL.Nat,
        auditLog:      IDL.Nat,
    });

    return IDL.Service({
        // User
        registerUser:    IDL.Func([IDL.Nat, IDL.Text], [IDL.Bool],              []),
        setFaceVerified: IDL.Func([IDL.Nat],            [IDL.Bool],              []),
        getUser:         IDL.Func([IDL.Nat],            [IDL.Opt(UserRecord)],   ['query']),

        // Credentials
        storeCredential:  IDL.Func([IDL.Nat, IDL.Text, IDL.Text], [IDL.Bool], []),
        verifyCredential: IDL.Func([IDL.Nat, IDL.Text, IDL.Text], [IDL.Bool], ['query']),

        // Company
        approveCompany:    IDL.Func([IDL.Nat, IDL.Text], [IDL.Bool], []),
        isCompanyApproved: IDL.Func([IDL.Nat],            [IDL.Bool], ['query']),

        // Jobs
        sealJob: IDL.Func([IDL.Nat, IDL.Text, IDL.Nat, IDL.Int], [IDL.Bool],         []),
        getJob:  IDL.Func([IDL.Nat],                               [IDL.Opt(JobRecord)], ['query']),

        // Applications
        logApplication: IDL.Func([IDL.Nat, IDL.Nat], [IDL.Bool], []),
        hasApplied:     IDL.Func([IDL.Nat, IDL.Nat], [IDL.Bool], ['query']),

        // Exams
        recordExam:    IDL.Func([IDL.Nat, IDL.Nat, IDL.Nat, IDL.Text, IDL.Nat, IDL.Text], [IDL.Bool],            []),
        getExamResult: IDL.Func([IDL.Nat, IDL.Nat],                                         [IDL.Opt(ExamRecord)], ['query']),

        // Interviews
        recordInterview: IDL.Func([IDL.Nat, IDL.Nat, IDL.Nat, IDL.Text], [IDL.Bool],                 []),
        getInterview:    IDL.Func([IDL.Nat, IDL.Nat],                      [IDL.Opt(InterviewRecord)], ['query']),

        // Hiring
        declareWinners:  IDL.Func([IDL.Nat, IDL.Vec(IDL.Nat), IDL.Vec(IDL.Nat)], [IDL.Bool],               []),
        getHiringResult: IDL.Func([IDL.Nat],                                       [IDL.Opt(HiringResult)], ['query']),
        isWinner:        IDL.Func([IDL.Nat, IDL.Nat],                              [IDL.Bool],               ['query']),

        // Exam config
        storeExamConfig: IDL.Func([IDL.Nat, IDL.Nat, IDL.Nat, IDL.Nat, IDL.Nat], [IDL.Bool],             []),
        getExamConfig:   IDL.Func([IDL.Nat],                                        [IDL.Opt(ExamConfig)], ['query']),

        // Credential verification audit
        storeCredentialVerification: IDL.Func([IDL.Nat, IDL.Text, IDL.Nat, IDL.Bool], [IDL.Bool],                       []),
        getCredentialVerification:   IDL.Func([IDL.Nat, IDL.Text],                     [IDL.Opt(CredentialVerification)], ['query']),

        // Exam schedule
        storeExamSchedule: IDL.Func([IDL.Nat, IDL.Int, IDL.Int, IDL.Int], [IDL.Bool],              []),
        getExamSchedule:   IDL.Func([IDL.Nat],                              [IDL.Opt(ExamSchedule)], ['query']),

        // Interview schedule
        storeInterviewSchedule: IDL.Func([IDL.Nat, IDL.Int, IDL.Nat], [IDL.Bool],                   []),
        getInterviewSchedule:   IDL.Func([IDL.Nat],                     [IDL.Opt(InterviewSchedule)], ['query']),

        // Job offer
        storeOffer: IDL.Func([IDL.Nat, IDL.Nat, IDL.Nat, IDL.Bool], [IDL.Bool],         []),
        getOffer:   IDL.Func([IDL.Nat, IDL.Nat],                      [IDL.Opt(JobOffer)], ['query']),

        // Audit + stats
        getJobAuditLog:  IDL.Func([IDL.Nat], [IDL.Vec(AuditEntry)], ['query']),
        getStorageStats: IDL.Func([],          [StorageStats],         ['query']),
    });
};

module.exports = { idlFactory };
