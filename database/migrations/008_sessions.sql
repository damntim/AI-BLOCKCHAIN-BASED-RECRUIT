-- Sessions are stored on filesystem by default (storage/sessions/)
-- This table is only needed if switching to database-backed sessions

CREATE TABLE IF NOT EXISTS sessions (
    id         VARCHAR(128) PRIMARY KEY,
    data       TEXT,
    timestamp  INT UNSIGNED,
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
