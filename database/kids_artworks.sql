-- 子供向けアトリエ作品テーブル
CREATE TABLE IF NOT EXISTS kids_artworks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    ai_story TEXT COMMENT 'AIが生成した物語',
    image_path VARCHAR(512) NOT NULL,
    webp_path VARCHAR(512) COMMENT 'WebPサムネイルパス（表示用）',
    pen_name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) COMMENT 'アップロード元IPアドレス（1日1回制限用）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_featured BOOLEAN DEFAULT FALSE COMMENT '注目作品フラグ',
    downloads INT DEFAULT 0 COMMENT 'ダウンロード数',
    INDEX idx_created_at (created_at),
    INDEX idx_is_featured (is_featured),
    INDEX idx_ip_date (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
