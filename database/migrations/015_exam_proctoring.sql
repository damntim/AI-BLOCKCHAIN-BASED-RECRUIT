-- Exam total points
ALTER TABLE exams
    ADD COLUMN IF NOT EXISTS total_points INT UNSIGNED DEFAULT 100;

-- Proctoring and warning columns on exam_sessions
ALTER TABLE exam_sessions
    ADD COLUMN IF NOT EXISTS warning_count      TINYINT UNSIGNED DEFAULT 0,
    ADD COLUMN IF NOT EXISTS violation_log      JSON,
    ADD COLUMN IF NOT EXISTS word_counts        JSON,
    ADD COLUMN IF NOT EXISTS time_per_question  JSON,
    ADD COLUMN IF NOT EXISTS answer_analytics   JSON,
    ADD COLUMN IF NOT EXISTS is_suspicious      TINYINT(1) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS termination_reason VARCHAR(255) DEFAULT NULL;
