-- Add proctoring and behavioral columns to interview_sessions
ALTER TABLE interview_sessions
    ADD COLUMN IF NOT EXISTS violation_log   JSON          AFTER transcript,
    ADD COLUMN IF NOT EXISTS behavioral_log  JSON          AFTER violation_log,
    ADD COLUMN IF NOT EXISTS confidence_score   DECIMAL(5,2) AFTER behavioral_score,
    ADD COLUMN IF NOT EXISTS anomaly_score      DECIMAL(5,2) AFTER confidence_score,
    ADD COLUMN IF NOT EXISTS attention_score    DECIMAL(5,2) AFTER anomaly_score,
    ADD COLUMN IF NOT EXISTS total_violations   SMALLINT UNSIGNED DEFAULT 0 AFTER anomaly_score;
