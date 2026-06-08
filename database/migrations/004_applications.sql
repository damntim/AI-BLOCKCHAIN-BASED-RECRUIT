CREATE TABLE IF NOT EXISTS applications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id          INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    cv_hash_snap    CHAR(64),
    icp_confirmed   TINYINT(1) DEFAULT 0,
    status          ENUM('APPLIED','SCREENED','EXAM_INVITED','EXAM_DONE','INTERVIEW_INVITED','INTERVIEW_DONE','SELECTED','REJECTED') DEFAULT 'APPLIED',
    applied_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_application (job_id, user_id),
    FOREIGN KEY (job_id)  REFERENCES jobs(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS screening_results (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id   INT UNSIGNED NOT NULL,
    job_id           INT UNSIGNED NOT NULL,
    total_score      DECIMAL(5,2),
    skills_score     DECIMAL(5,2),
    experience_score DECIMAL(5,2),
    education_score  DECIMAL(5,2),
    credentials_score DECIMAL(5,2),
    explanation      TEXT,
    is_shortlisted   TINYINT(1) DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
