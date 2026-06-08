CREATE TABLE IF NOT EXISTS exams (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id              INT UNSIGNED UNIQUE NOT NULL,
    questions_encrypted LONGTEXT NOT NULL,
    generated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS exam_sessions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id           INT UNSIGNED NOT NULL,
    user_id           INT UNSIGNED NOT NULL,
    started_at        DATETIME,
    submitted_at      DATETIME,
    total_score       DECIMAL(5,2),
    cheat_score       INT DEFAULT 0,
    outcome           ENUM('IN_PROGRESS','CLEAN','FLAGGED','TERMINATED') DEFAULT 'IN_PROGRESS',
    anti_cheat_log    JSON,
    anti_cheat_hash   CHAR(64),
    icp_confirmed     TINYINT(1) DEFAULT 0,
    admin_override    TINYINT(1) DEFAULT 0,
    override_reason   TEXT,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id)  REFERENCES exams(id),
    FOREIGN KEY (user_id)  REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
