-- APIレートリミット管理テーブル
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_hash VARCHAR(64) NOT NULL COMMENT 'SHA256(IPアドレス)',
    request_count INT NOT NULL DEFAULT 0,
    reset_date DATE NOT NULL COMMENT 'このカウントのリセット日（翌日になるとリセット）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_ip_date (ip_hash, reset_date),
    INDEX idx_reset_date (reset_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
