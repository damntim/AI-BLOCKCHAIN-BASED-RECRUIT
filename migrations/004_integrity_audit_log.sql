-- Blockchain integrity audit log
-- Stores every tamper-detection event, revert action, and anchor retry
-- Run once: SOURCE migrations/004_integrity_audit_log.sql;

CREATE TABLE IF NOT EXISTS integrity_audit_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action     VARCHAR(40)  NOT NULL,   -- VERIFY_INTACT | TAMPER_DETECTED | REVERT_APPLIED | UNANCHORED | ANCHOR_RETRY | ANCHOR_SUCCESS
    detail     TEXT         NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action     (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
