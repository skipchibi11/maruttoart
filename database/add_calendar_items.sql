-- カレンダーアイテムテーブルを作成
CREATE TABLE IF NOT EXISTS calendar_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year INT NOT NULL COMMENT '年',
    month INT NOT NULL COMMENT '月（1-12）',
    day INT NOT NULL COMMENT '日（1-31）',
    title VARCHAR(255) NOT NULL COMMENT 'タイトル',
    description TEXT COMMENT '説明',
    image_path VARCHAR(255) COMMENT '画像パス',
    thumbnail_path VARCHAR(255) COMMENT 'サムネイル画像パス',
    gif_path VARCHAR(255) COMMENT 'GIFパス',
    is_published BOOLEAN DEFAULT TRUE COMMENT '公開フラグ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date (year, month, day),
    INDEX idx_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
