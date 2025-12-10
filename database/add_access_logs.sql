-- シンプルなアクセスログテーブル（GDPR同意不要）
CREATE TABLE IF NOT EXISTS access_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL COMMENT 'IPアドレス',
    page_url VARCHAR(255) NOT NULL COMMENT 'アクセスしたページ',
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'アクセス日時',
    INDEX idx_page_date (page_url, accessed_at),
    INDEX idx_date (accessed_at),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='アクセスログ（Cookie不使用・GDPR同意不要）';

-- 古いログを自動削除（90日保持）
DROP EVENT IF EXISTS cleanup_old_access_logs;
CREATE EVENT cleanup_old_access_logs
ON SCHEDULE EVERY 1 DAY
DO
DELETE FROM access_logs WHERE accessed_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
