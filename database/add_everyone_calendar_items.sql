-- みんなのカレンダー用テーブル
CREATE TABLE IF NOT EXISTS everyone_calendar_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    month INT NOT NULL COMMENT '月（1-12）',
    day INT NOT NULL COMMENT '日（1-31）',
    country_id INT DEFAULT NULL COMMENT '国ID',
    artwork_id INT NOT NULL COMMENT 'community_artworks ID',
    date_reason TEXT COMMENT '日付選定理由（AI）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_month_day (month, day),
    INDEX idx_month_day_created (month, day, created_at),
    INDEX idx_country_id (country_id),
    INDEX idx_artwork_id (artwork_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
