-- Profile: extended identity fields
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS national_id       VARCHAR(50)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS father_name       VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS mother_name       VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS gender            ENUM('Male','Female','Other') DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS place_of_birth    VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS residence_district VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS residence_sector  VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS residence_cell    VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS residence_village VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS profile_complete  TINYINT(1)   DEFAULT 0;

-- Education entries (one user → many)
CREATE TABLE IF NOT EXISTS user_education (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    degree_title    VARCHAR(255) NOT NULL,
    institution     VARCHAR(255) NOT NULL,
    country         VARCHAR(100) DEFAULT NULL,
    year_completed  YEAR DEFAULT NULL,
    cert_path       VARCHAR(500) DEFAULT NULL,
    cert_hash       CHAR(64)     DEFAULT NULL,
    ai_suggested_title  VARCHAR(255) DEFAULT NULL,
    ai_match_score  TINYINT UNSIGNED DEFAULT NULL,
    ai_match_ok     TINYINT(1)   DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Work experience
CREATE TABLE IF NOT EXISTS user_experience (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    job_title       VARCHAR(255) NOT NULL,
    company_name    VARCHAR(255) NOT NULL,
    start_date      DATE DEFAULT NULL,
    end_date        DATE DEFAULT NULL,
    is_current      TINYINT(1) DEFAULT 0,
    phone           VARCHAR(50)  DEFAULT NULL,
    email           VARCHAR(255) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Awards & Certificates
CREATE TABLE IF NOT EXISTS user_certificates (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(255) NOT NULL,
    issuer          VARCHAR(255) NOT NULL,
    year_issued     YEAR DEFAULT NULL,
    cert_path       VARCHAR(500) DEFAULT NULL,
    cert_hash       CHAR(64)     DEFAULT NULL,
    ai_suggested_title  VARCHAR(255) DEFAULT NULL,
    ai_match_score  TINYINT UNSIGNED DEFAULT NULL,
    ai_match_ok     TINYINT(1)   DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Languages
CREATE TABLE IF NOT EXISTS user_languages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    language        VARCHAR(100) NOT NULL,
    reading         ENUM('Basic','Good','Very Good','Excellent') DEFAULT 'Basic',
    writing         ENUM('Basic','Good','Very Good','Excellent') DEFAULT 'Basic',
    speaking        ENUM('Basic','Good','Very Good','Excellent') DEFAULT 'Basic',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Disability
CREATE TABLE IF NOT EXISTS user_disability (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    has_disability  TINYINT(1) DEFAULT 0,
    description     TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Referees
CREATE TABLE IF NOT EXISTS user_referees (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    full_name       VARCHAR(255) NOT NULL,
    position        VARCHAR(255) DEFAULT NULL,
    organization    VARCHAR(255) DEFAULT NULL,
    phone           VARCHAR(50)  DEFAULT NULL,
    email           VARCHAR(255) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Publications
CREATE TABLE IF NOT EXISTS user_publications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    title           VARCHAR(500) NOT NULL,
    publisher       VARCHAR(255) DEFAULT NULL,
    year_published  YEAR DEFAULT NULL,
    url             VARCHAR(500) DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Exam config attached to jobs
ALTER TABLE jobs
    ADD COLUMN IF NOT EXISTS eligible_educations  JSON DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS exam_num_questions   INT UNSIGNED DEFAULT 10,
    ADD COLUMN IF NOT EXISTS exam_time_limit_min  INT UNSIGNED DEFAULT 60,
    ADD COLUMN IF NOT EXISTS exam_open_ended      INT UNSIGNED DEFAULT 3,
    ADD COLUMN IF NOT EXISTS exam_closed_ended    INT UNSIGNED DEFAULT 7,
    ADD COLUMN IF NOT EXISTS exam_sample_doc_path VARCHAR(500) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS exam_start_at        DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS exam_end_at          DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS interview_shortlist_n INT UNSIGNED DEFAULT 5,
    ADD COLUMN IF NOT EXISTS interview_start_at   DATETIME DEFAULT NULL;

-- Track exam schedule extensions
CREATE TABLE IF NOT EXISTS exam_extensions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id      INT UNSIGNED NOT NULL,
    extended_to DATETIME NOT NULL,
    reason      TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
