# ICP / Internet Computer Blockchain Guide

The ICP canister requires WSL or a Linux machine with `dfx` installed.
`services.bat` / `services.ps1` manage only the local **ICP Gateway** (Node.js proxy on port 3001).

## Deploying the canister (run inside WSL / Linux)

```bash
cd icp
dfx start --background --clean
dfx deploy
dfx canister id recruitment   # copy this ID
```

Then set `CANISTER_ID` in `icp-gateway/.env` and restart the gateway:

```
services.bat start
# or
.\services.ps1 start
```

## Suggested Motoko additions

File: `icp/src/recruitment/main.mo`

| # | Function | Purpose |
|---|----------|---------|
| 1 | `storeExamConfig(jobId, numQuestions, timeLimitMin, openEnded, closedEnded)` | Record exam settings on-chain |
| 2 | `storeCredentialVerification(userId, credHash, aiMatchScore, userEdited)` | Diploma / credential verification audit |
| 3 | `storeExamSchedule(jobId, startEpoch, endEpoch, extendedEpoch)` | Immutable exam schedule |
| 4 | `storeInterviewSchedule(jobId, startEpoch, candidateCount)` | Interview scheduling audit trail |
| 5 | `storeOffer(jobId, userId, overallRank, offerAccepted)` | Immutably record job offers |
| 6 | `getJobAuditLog(jobId) -> vec AuditEntry` | Full audit log for a job |

These additions keep the canister lean while covering all pipeline stages.
