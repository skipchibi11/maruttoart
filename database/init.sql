CREATE DATABASE IF NOT EXISTS maruttoart;
USE maruttoart;

-- 管理者テーブル
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 無料素材テーブル
CREATE TABLE materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    youtube_url VARCHAR(500),
    search_keywords_en VARCHAR(500),
    search_keywords_jp VARCHAR(500),
    image_path VARCHAR(500) NOT NULL,
    webp_path VARCHAR(500) NOT NULL,
    upload_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 初期管理者データを挿入
INSERT INTO admins (email, password) VALUES 
('example@example.com', 'example1234');

-- サンプルデータ
INSERT INTO materials (title, slug, description, youtube_url, search_keywords_en, search_keywords_jp, image_path, webp_path, upload_date) VALUES
('桃のイラスト', 'peach-illustration', '可愛らしい桃のイラストです。', 'https://www.youtube.com/watch?v=example', 'peach,fruit,pink', 'もも,果物,ピンク', 'uploads/2024/08/peach.png', 'uploads/2024/08/peach.webp', '2024-08-12');
