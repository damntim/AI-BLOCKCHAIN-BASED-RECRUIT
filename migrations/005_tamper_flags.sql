-- Add tamper detection columns to exam_sessions, interview_sessions, hiring_results
-- Run once: SOURCE migrations/005_tamper_flags.sql;

ALTER TABLE exam_sessions
    ADD COLUMN IF NOT EXISTS tamper_flag        TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS tamper_detected_at DATETIME   NULL;

ALTER TABLE interview_sessions
    ADD COLUMN IF NOT EXISTS tamper_flag        TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS tamper_detected_at DATETIME   NULL;

ALTER TABLE hiring_results
    ADD COLUMN IF NOT EXISTS tamper_flag        TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS tamper_detected_at DATETIME   NULL;
