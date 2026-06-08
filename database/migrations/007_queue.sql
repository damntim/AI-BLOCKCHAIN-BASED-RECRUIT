CREATE TABLE IF NOT EXISTS queue_jobs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type        VARCHAR(100) NOT NULL,
    payload     JSON,
    status      ENUM('PENDING','PROCESSING','DONE','FAILED') DEFAULT 'PENDING',
    error       TEXT,
    started_at  DATETIME,
    finished_at DATETIME,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
