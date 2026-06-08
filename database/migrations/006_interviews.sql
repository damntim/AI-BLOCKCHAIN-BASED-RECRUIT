CREATE TABLE IF NOT EXISTS interview_sessions (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id                  INT UNSIGNED NOT NULL,
    user_id                 INT UNSIGNED NOT NULL,
    scheduled_at            DATETIME,
    started_at              DATETIME,
    ended_at                DATETIME,
    transcript              JSON,
    technical_score         DECIMAL(5,2),
    communication_score     DECIMAL(5,2),
    behavioral_score        DECIMAL(5,2),
    problem_solving_score   DECIMAL(5,2),
    professionalism_score   DECIMAL(5,2),
    total_score             DECIMAL(5,2),
    ai_report               TEXT,
    transcript_hash         CHAR(64),
    icp_confirmed           TINYINT(1) DEFAULT 0,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id)  REFERENCES jobs(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hiring_results (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id          INT UNSIGNED UNIQUE NOT NULL,
    winner_user_ids JSON,
    final_scores    JSON,
    ai_report       JSON,
    decided_at      DATETIME,
    icp_confirmed   TINYINT(1) DEFAULT 0,
    verification_url VARCHAR(500),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
