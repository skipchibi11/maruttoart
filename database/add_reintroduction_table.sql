-- 再紹介アイテムを保存するテーブル
CREATE TABLE IF NOT EXISTS reintroduction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('material', 'artwork') NOT NULL,
    item_id INT NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    image_url VARCHAR(500),
    page_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at DESC),
    UNIQUE KEY unique_item (item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
