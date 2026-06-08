import Time   "mo:base/Time";
import HashMap "mo:base/HashMap";
import Text    "mo:base/Text";
import Nat     "mo:base/Nat";
import Int     "mo:base/Int";
import Hash    "mo:base/Hash";
import Array   "mo:base/Array";
import Iter    "mo:base/Iter";

persistent actor Recruitment {

  // ── Types ──────────────────────────────────────────────────────────────────

  type UserId = Nat;
  type JobId  = Nat;

  type UserRecord = {
    userId       : UserId;
    identityHash : Text;
    registeredAt : Int;
    faceVerified : Bool;
  };

  type JobRecord = {
    jobId     : JobId;
    jobHash   : Text;
    positions : Nat;
    deadline  : Int;
    postedAt  : Int;
  };

  type ExamRecord = {
    userId        : UserId;
    jobId         : JobId;
    score         : Nat;
    antiCheatHash : Text;
    cheatScore    : Nat;
    outcome       : Text;  // "CLEAN" | "FLAGGED" | "TERMINATED"
    submittedAt   : Int;
  };

  type InterviewRecord = {
    userId         : UserId;
    jobId          : JobId;
    score          : Nat;
    transcriptHash : Text;
    completedAt    : Int;
  };

  type HiringResult = {
    jobId       : JobId;
    winnerIds   : [UserId];
    finalScores : [Nat];
    declaredAt  : Int;
  };

  type ExamConfig = {
    jobId        : JobId;
    numQuestions : Nat;
    timeLimitMin : Nat;
    openEnded    : Nat;
    closedEnded  : Nat;
    sealedAt     : Int;
  };

  type CredentialVerification = {
    userId       : UserId;
    credHash     : Text;
    aiMatchScore : Nat;  // 0-100, multiplied by 100 (e.g. 9250 = 92.50%)
    userEdited   : Bool;
    storedAt     : Int;
  };

  type ExamSchedule = {
    jobId          : JobId;
    startEpoch     : Int;
    endEpoch       : Int;
    extendedEpoch  : Int;  // 0 = not extended
    sealedAt       : Int;
  };

  type InterviewSchedule = {
    jobId          : JobId;
    startEpoch     : Int;
    candidateCount : Nat;
    sealedAt       : Int;
  };

  type JobOffer = {
    jobId         : JobId;
    userId        : UserId;
    overallRank   : Nat;
    offerAccepted : Bool;
    recordedAt    : Int;
  };

  // AuditEntry captures every significant on-chain event for a job
  type AuditEntry = {
    eventType  : Text;   // "JOB_SEALED" | "EXAM_CONFIG" | "EXAM_SCHEDULE" | "APPLICATION" |
                         // "EXAM_RESULT" | "INTERVIEW_SCHEDULE" | "INTERVIEW_RESULT" |
                         // "OFFER" | "WINNERS_DECLARED"
    actor_id   : Nat;    // userId or 0 for job-level events
    payload    : Text;   // JSON-like summary string
    timestamp  : Int;
  };

  // ── Helper functions ───────────────────────────────────────────────────────

  func natHash(n: Nat): Hash.Hash { Text.hash(Nat.toText(n)) };
  func compositeKey(a: Nat, b: Nat): Text { Nat.toText(a) # ":" # Nat.toText(b) };
  func credKey(userId: Nat, docType: Text): Text { Nat.toText(userId) # ":" # docType };

  // ── Stable state (survives upgrades) ──────────────────────────────────────

  var usersEntries         : [(Nat, UserRecord)]              = [];
  var credentialsEntries   : [(Text, Text)]                   = [];
  var companiesEntries     : [(Nat, Text)]                    = [];
  var jobsEntries          : [(Nat, JobRecord)]               = [];
  var applicationsEntries  : [(Text, Int)]                    = [];
  var examResultsEntries   : [(Text, ExamRecord)]             = [];
  var interviewsEntries    : [(Text, InterviewRecord)]        = [];
  var hiringResultsEntries : [(Nat, HiringResult)]            = [];
  var examConfigsEntries   : [(Nat, ExamConfig)]              = [];
  var credVerifEntries     : [(Text, CredentialVerification)] = [];
  var examSchedulesEntries : [(Nat, ExamSchedule)]            = [];
  var ivSchedulesEntries   : [(Nat, InterviewSchedule)]       = [];
  var jobOffersEntries     : [(Text, JobOffer)]               = [];
  var auditLogEntries      : [(Text, AuditEntry)]             = [];

  // ── Working HashMaps (rebuilt from stable arrays after upgrade) ────────────

  transient var users         = HashMap.fromIter<Nat,  UserRecord>            (usersEntries.vals(),         16, Nat.equal,  natHash);
  transient var credentials   = HashMap.fromIter<Text, Text>                  (credentialsEntries.vals(),   64, Text.equal, Text.hash);
  transient var companies     = HashMap.fromIter<Nat,  Text>                  (companiesEntries.vals(),     16, Nat.equal,  natHash);
  transient var jobs          = HashMap.fromIter<Nat,  JobRecord>             (jobsEntries.vals(),          16, Nat.equal,  natHash);
  transient var applications  = HashMap.fromIter<Text, Int>                   (applicationsEntries.vals(),  64, Text.equal, Text.hash);
  transient var examResults   = HashMap.fromIter<Text, ExamRecord>            (examResultsEntries.vals(),   64, Text.equal, Text.hash);
  transient var interviews    = HashMap.fromIter<Text, InterviewRecord>       (interviewsEntries.vals(),    64, Text.equal, Text.hash);
  transient var hiringResults = HashMap.fromIter<Nat,  HiringResult>          (hiringResultsEntries.vals(), 16, Nat.equal,  natHash);
  transient var examConfigs   = HashMap.fromIter<Nat,  ExamConfig>            (examConfigsEntries.vals(),   16, Nat.equal,  natHash);
  transient var credVerif     = HashMap.fromIter<Text, CredentialVerification>(credVerifEntries.vals(),     64, Text.equal, Text.hash);
  transient var examSchedules = HashMap.fromIter<Nat,  ExamSchedule>          (examSchedulesEntries.vals(), 16, Nat.equal,  natHash);
  transient var ivSchedules   = HashMap.fromIter<Nat,  InterviewSchedule>     (ivSchedulesEntries.vals(),   16, Nat.equal,  natHash);
  transient var jobOffers     = HashMap.fromIter<Text, JobOffer>              (jobOffersEntries.vals(),     64, Text.equal, Text.hash);
  transient var auditLog      = HashMap.fromIter<Text, AuditEntry>            (auditLogEntries.vals(),     256, Text.equal, Text.hash);

  // Audit log key: jobId + sequential counter stored as size proxy
  func auditKey(jobId: Nat): Text {
    Nat.toText(jobId) # ":" # Nat.toText(auditLog.size())
  };

  func appendAudit(jobId: Nat, eventType: Text, actorId: Nat, payload: Text) {
    let entry: AuditEntry = {
      eventType; actor_id = actorId; payload; timestamp = Time.now()
    };
    auditLog.put(auditKey(jobId), entry);
  };

  // ── Upgrade hooks — persist HashMaps to stable arrays ─────────────────────

  system func preupgrade() {
    usersEntries         := Iter.toArray(users.entries());
    credentialsEntries   := Iter.toArray(credentials.entries());
    companiesEntries     := Iter.toArray(companies.entries());
    jobsEntries          := Iter.toArray(jobs.entries());
    applicationsEntries  := Iter.toArray(applications.entries());
    examResultsEntries   := Iter.toArray(examResults.entries());
    interviewsEntries    := Iter.toArray(interviews.entries());
    hiringResultsEntries := Iter.toArray(hiringResults.entries());
    examConfigsEntries   := Iter.toArray(examConfigs.entries());
    credVerifEntries     := Iter.toArray(credVerif.entries());
    examSchedulesEntries := Iter.toArray(examSchedules.entries());
    ivSchedulesEntries   := Iter.toArray(ivSchedules.entries());
    jobOffersEntries     := Iter.toArray(jobOffers.entries());
    auditLogEntries      := Iter.toArray(auditLog.entries());
  };

  system func postupgrade() {
    users         := HashMap.fromIter<Nat,  UserRecord>            (usersEntries.vals(),         16, Nat.equal,  natHash);
    credentials   := HashMap.fromIter<Text, Text>                  (credentialsEntries.vals(),   64, Text.equal, Text.hash);
    companies     := HashMap.fromIter<Nat,  Text>                  (companiesEntries.vals(),     16, Nat.equal,  natHash);
    jobs          := HashMap.fromIter<Nat,  JobRecord>             (jobsEntries.vals(),          16, Nat.equal,  natHash);
    applications  := HashMap.fromIter<Text, Int>                   (applicationsEntries.vals(),  64, Text.equal, Text.hash);
    examResults   := HashMap.fromIter<Text, ExamRecord>            (examResultsEntries.vals(),   64, Text.equal, Text.hash);
    interviews    := HashMap.fromIter<Text, InterviewRecord>       (interviewsEntries.vals(),    64, Text.equal, Text.hash);
    hiringResults := HashMap.fromIter<Nat,  HiringResult>          (hiringResultsEntries.vals(), 16, Nat.equal,  natHash);
    examConfigs   := HashMap.fromIter<Nat,  ExamConfig>            (examConfigsEntries.vals(),   16, Nat.equal,  natHash);
    credVerif     := HashMap.fromIter<Text, CredentialVerification>(credVerifEntries.vals(),     64, Text.equal, Text.hash);
    examSchedules := HashMap.fromIter<Nat,  ExamSchedule>          (examSchedulesEntries.vals(), 16, Nat.equal,  natHash);
    ivSchedules   := HashMap.fromIter<Nat,  InterviewSchedule>     (ivSchedulesEntries.vals(),   16, Nat.equal,  natHash);
    jobOffers     := HashMap.fromIter<Text, JobOffer>              (jobOffersEntries.vals(),     64, Text.equal, Text.hash);
    auditLog      := HashMap.fromIter<Text, AuditEntry>            (auditLogEntries.vals(),     256, Text.equal, Text.hash);
  };

  // ── User methods ───────────────────────────────────────────────────────────

  public func registerUser(userId: Nat, identityHash: Text) : async Bool {
    users.put(userId, { userId; identityHash; registeredAt = Time.now(); faceVerified = false });
    true
  };

  public func setFaceVerified(userId: Nat) : async Bool {
    switch (users.get(userId)) {
      case null false;
      case (?u) { users.put(userId, { u with faceVerified = true }); true };
    }
  };

  public query func getUser(userId: Nat) : async ?UserRecord { users.get(userId) };

  // ── Credential methods ─────────────────────────────────────────────────────

  public func storeCredential(userId: Nat, docType: Text, fileHash: Text) : async Bool {
    credentials.put(credKey(userId, docType), fileHash); true
  };

  public query func verifyCredential(userId: Nat, docType: Text, fileHash: Text) : async Bool {
    switch (credentials.get(credKey(userId, docType))) {
      case null false;
      case (?stored) stored == fileHash;
    }
  };

  // ── Company methods ────────────────────────────────────────────────────────

  public func approveCompany(companyId: Nat, companyHash: Text) : async Bool {
    companies.put(companyId, companyHash); true
  };

  public query func isCompanyApproved(companyId: Nat) : async Bool {
    switch (companies.get(companyId)) { case null false; case (_) true }
  };

  // ── Job methods ────────────────────────────────────────────────────────────

  public func sealJob(jobId: Nat, jobHash: Text, positions: Nat, deadline: Int) : async Bool {
    jobs.put(jobId, { jobId; jobHash; positions; deadline; postedAt = Time.now() });
    appendAudit(jobId, "JOB_SEALED", 0,
      "hash=" # jobHash # " positions=" # Nat.toText(positions));
    true
  };

  public query func getJob(jobId: Nat) : async ?JobRecord { jobs.get(jobId) };

  // ── Application methods ────────────────────────────────────────────────────

  public func logApplication(userId: Nat, jobId: Nat) : async Bool {
    applications.put(compositeKey(userId, jobId), Time.now());
    appendAudit(jobId, "APPLICATION", userId, "userId=" # Nat.toText(userId));
    true
  };

  public query func hasApplied(userId: Nat, jobId: Nat) : async Bool {
    switch (applications.get(compositeKey(userId, jobId))) { case null false; case (_) true }
  };

  // ── Exam methods ───────────────────────────────────────────────────────────

  public func recordExam(userId: Nat, jobId: Nat, score: Nat, antiCheatHash: Text, cheatScore: Nat, outcome: Text) : async Bool {
    examResults.put(compositeKey(userId, jobId), {
      userId; jobId; score; antiCheatHash; cheatScore; outcome; submittedAt = Time.now()
    });
    appendAudit(jobId, "EXAM_RESULT", userId,
      "score=" # Nat.toText(score) # " cheat=" # Nat.toText(cheatScore) # " outcome=" # outcome);
    true
  };

  public query func getExamResult(userId: Nat, jobId: Nat) : async ?ExamRecord {
    examResults.get(compositeKey(userId, jobId))
  };

  // ── Interview methods ──────────────────────────────────────────────────────

  public func recordInterview(userId: Nat, jobId: Nat, score: Nat, transcriptHash: Text) : async Bool {
    interviews.put(compositeKey(userId, jobId), {
      userId; jobId; score; transcriptHash; completedAt = Time.now()
    });
    appendAudit(jobId, "INTERVIEW_RESULT", userId,
      "score=" # Nat.toText(score) # " transcriptHash=" # transcriptHash);
    true
  };

  public query func getInterview(userId: Nat, jobId: Nat) : async ?InterviewRecord {
    interviews.get(compositeKey(userId, jobId))
  };

  // ── Hiring result methods ──────────────────────────────────────────────────

  public func declareWinners(jobId: Nat, winnerIds: [Nat], finalScores: [Nat]) : async Bool {
    hiringResults.put(jobId, { jobId; winnerIds; finalScores; declaredAt = Time.now() });
    appendAudit(jobId, "WINNERS_DECLARED", 0,
      "count=" # Nat.toText(winnerIds.size()));
    true
  };

  public query func getHiringResult(jobId: Nat) : async ?HiringResult {
    hiringResults.get(jobId)
  };

  public query func isWinner(userId: Nat, jobId: Nat) : async Bool {
    switch (hiringResults.get(jobId)) {
      case null false;
      case (?r) {
        switch (Array.find<Nat>(r.winnerIds, func(id) { id == userId })) {
          case null false; case (_) true;
        }
      };
    }
  };

  // ── Exam config ───────────────────────────────────────────────────────────

  public func storeExamConfig(
    jobId        : Nat,
    numQuestions : Nat,
    timeLimitMin : Nat,
    openEnded    : Nat,
    closedEnded  : Nat
  ) : async Bool {
    examConfigs.put(jobId, {
      jobId; numQuestions; timeLimitMin; openEnded; closedEnded; sealedAt = Time.now()
    });
    appendAudit(jobId, "EXAM_CONFIG", 0,
      "q=" # Nat.toText(numQuestions) # " min=" # Nat.toText(timeLimitMin) #
      " open=" # Nat.toText(openEnded) # " closed=" # Nat.toText(closedEnded));
    true
  };

  public query func getExamConfig(jobId: Nat) : async ?ExamConfig {
    examConfigs.get(jobId)
  };

  // ── Credential verification audit ────────────────────────────────────────

  public func storeCredentialVerification(
    userId       : Nat,
    credHash     : Text,
    aiMatchScore : Nat,
    userEdited   : Bool
  ) : async Bool {
    let key = Nat.toText(userId) # ":" # credHash;
    credVerif.put(key, {
      userId; credHash; aiMatchScore; userEdited; storedAt = Time.now()
    });
    true
  };

  public query func getCredentialVerification(userId: Nat, credHash: Text) : async ?CredentialVerification {
    credVerif.get(Nat.toText(userId) # ":" # credHash)
  };

  // ── Exam schedule ─────────────────────────────────────────────────────────

  public func storeExamSchedule(
    jobId         : Nat,
    startEpoch    : Int,
    endEpoch      : Int,
    extendedEpoch : Int
  ) : async Bool {
    examSchedules.put(jobId, {
      jobId; startEpoch; endEpoch; extendedEpoch; sealedAt = Time.now()
    });
    appendAudit(jobId, "EXAM_SCHEDULE", 0,
      "start=" # Int.toText(startEpoch) # " end=" # Int.toText(endEpoch) #
      " extended=" # Int.toText(extendedEpoch));
    true
  };

  public query func getExamSchedule(jobId: Nat) : async ?ExamSchedule {
    examSchedules.get(jobId)
  };

  // ── Interview schedule ────────────────────────────────────────────────────

  public func storeInterviewSchedule(
    jobId          : Nat,
    startEpoch     : Int,
    candidateCount : Nat
  ) : async Bool {
    ivSchedules.put(jobId, {
      jobId; startEpoch; candidateCount; sealedAt = Time.now()
    });
    appendAudit(jobId, "INTERVIEW_SCHEDULE", 0,
      "start=" # Int.toText(startEpoch) # " candidates=" # Nat.toText(candidateCount));
    true
  };

  public query func getInterviewSchedule(jobId: Nat) : async ?InterviewSchedule {
    ivSchedules.get(jobId)
  };

  // ── Job offer record ──────────────────────────────────────────────────────

  public func storeOffer(
    jobId         : Nat,
    userId        : Nat,
    overallRank   : Nat,
    offerAccepted : Bool
  ) : async Bool {
    let key = compositeKey(jobId, userId);
    jobOffers.put(key, {
      jobId; userId; overallRank; offerAccepted; recordedAt = Time.now()
    });
    appendAudit(jobId, "OFFER", userId,
      "rank=" # Nat.toText(overallRank) # " accepted=" # (if offerAccepted "true" else "false"));
    true
  };

  public query func getOffer(jobId: Nat, userId: Nat) : async ?JobOffer {
    jobOffers.get(compositeKey(jobId, userId))
  };

  // ── Audit log ─────────────────────────────────────────────────────────────

  public query func getJobAuditLog(jobId: Nat) : async [AuditEntry] {
    let prefix = Nat.toText(jobId) # ":";
    let matching = Array.filter<(Text, AuditEntry)>(
      Iter.toArray(auditLog.entries()),
      func((k, _)) { Text.startsWith(k, #text prefix) }
    );
    Array.map<(Text, AuditEntry), AuditEntry>(matching, func((_, v)) { v })
  };

  // ── Storage statistics ────────────────────────────────────────────────────

  type StorageStats = {
    users         : Nat;
    credentials   : Nat;
    companies     : Nat;
    jobs          : Nat;
    applications  : Nat;
    examResults   : Nat;
    interviews    : Nat;
    hiringResults : Nat;
    examConfigs   : Nat;
    credVerif     : Nat;
    examSchedules : Nat;
    ivSchedules   : Nat;
    jobOffers     : Nat;
    auditLog      : Nat;
  };

  public query func getStorageStats() : async StorageStats {
    {
      users         = users.size();
      credentials   = credentials.size();
      companies     = companies.size();
      jobs          = jobs.size();
      applications  = applications.size();
      examResults   = examResults.size();
      interviews    = interviews.size();
      hiringResults = hiringResults.size();
      examConfigs   = examConfigs.size();
      credVerif     = credVerif.size();
      examSchedules = examSchedules.size();
      ivSchedules   = ivSchedules.size();
      jobOffers     = jobOffers.size();
      auditLog      = auditLog.size();
    }
  };
}
