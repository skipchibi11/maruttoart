-- データベースとユーザーを作成
CREATE DATABASE IF NOT EXISTS maruttoart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'maruttoart'@'%' IDENTIFIED BY 'maruttopass';
GRANT ALL PRIVILEGES ON maruttoart.* TO 'maruttoart'@'%';
FLUSH PRIVILEGES;

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

-- 初期管理者データを挿入（パスワードはハッシュ化済み: example1234）
INSERT INTO admins (email, password) VALUES 
('example@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- サンプルデータ
INSERT INTO materials (title, slug, description, youtube_url, search_keywords_en, search_keywords_jp, image_path, webp_path, upload_date) VALUES
('桃のイラスト', 'peach-illustration', '可愛らしい桃のイラストです。', 'https://www.youtube.com/watch?v=example', 'peach,fruit,pink', 'もも,果物,ピンク', 'uploads/2024/08/peach.png', 'uploads/2024/08/peach.webp', '2024-08-12'),
('りんごのイラスト', 'apple-illustration', '真っ赤なりんごのイラストです。', '', 'apple,fruit,red', 'りんご,果物,赤', 'uploads/2024/08/apple.png', 'uploads/2024/08/apple.webp', '2024-08-11'),
('バナナのイラスト', 'banana-illustration', '黄色いバナナのイラストです。', '', 'banana,fruit,yellow', 'バナナ,果物,黄色', 'uploads/2024/08/banana.png', 'uploads/2024/08/banana.webp', '2024-08-10'),
('いちごのイラスト', 'strawberry-illustration', '甘いいちごのイラストです。', '', 'strawberry,fruit,sweet', 'いちご,果物,甘い', 'uploads/2024/08/strawberry.png', 'uploads/2024/08/strawberry.webp', '2024-08-09'),
('ぶどうのイラスト', 'grape-illustration', '紫色のぶどうのイラストです。', '', 'grape,fruit,purple', 'ぶどう,果物,紫', 'uploads/2024/08/grape.png', 'uploads/2024/08/grape.webp', '2024-08-08'),
('花のイラスト', 'flower-illustration', '美しい花のイラストです。', '', 'flower,nature,beautiful', '花,自然,美しい', 'uploads/2024/08/flower.png', 'uploads/2024/08/flower.webp', '2024-08-07'),
('木のイラスト', 'tree-illustration', '大きな木のイラストです。', '', 'tree,nature,green', '木,自然,緑', 'uploads/2024/08/tree.png', 'uploads/2024/08/tree.webp', '2024-08-06'),
('空のイラスト', 'sky-illustration', '青い空のイラストです。', '', 'sky,nature,blue', '空,自然,青', 'uploads/2024/08/sky.png', 'uploads/2024/08/sky.webp', '2024-08-05'),
('海のイラスト', 'sea-illustration', '青い海のイラストです。', '', 'sea,nature,blue', '海,自然,青', 'uploads/2024/08/sea.png', 'uploads/2024/08/sea.webp', '2024-08-04'),
('山のイラスト', 'mountain-illustration', '高い山のイラストです。', '', 'mountain,nature,high', '山,自然,高い', 'uploads/2024/08/mountain.png', 'uploads/2024/08/mountain.webp', '2024-08-03'),
('猫のイラスト', 'cat-illustration', 'かわいい猫のイラストです。', '', 'cat,animal,cute', '猫,動物,かわいい', 'uploads/2024/08/cat.png', 'uploads/2024/08/cat.webp', '2024-08-02'),
('犬のイラスト', 'dog-illustration', '忠実な犬のイラストです。', '', 'dog,animal,loyal', '犬,動物,忠実', 'uploads/2024/08/dog.png', 'uploads/2024/08/dog.webp', '2024-08-01'),
('鳥のイラスト', 'bird-illustration', '美しい鳥のイラストです。', '', 'bird,animal,beautiful', '鳥,動物,美しい', 'uploads/2024/07/bird.png', 'uploads/2024/07/bird.webp', '2024-07-31'),
('魚のイラスト', 'fish-illustration', '泳ぐ魚のイラストです。', '', 'fish,animal,swimming', '魚,動物,泳ぐ', 'uploads/2024/07/fish.png', 'uploads/2024/07/fish.webp', '2024-07-30'),
('蝶のイラスト', 'butterfly-illustration', 'カラフルな蝶のイラストです。', '', 'butterfly,insect,colorful', '蝶,昆虫,カラフル', 'uploads/2024/07/butterfly.png', 'uploads/2024/07/butterfly.webp', '2024-07-29'),
('家のイラスト', 'house-illustration', '温かい家のイラストです。', '', 'house,building,warm', '家,建物,温かい', 'uploads/2024/07/house.png', 'uploads/2024/07/house.webp', '2024-07-28'),
('車のイラスト', 'car-illustration', '赤い車のイラストです。', '', 'car,vehicle,red', '車,乗り物,赤', 'uploads/2024/07/car.png', 'uploads/2024/07/car.webp', '2024-07-27'),
('飛行機のイラスト', 'plane-illustration', '空飛ぶ飛行機のイラストです。', '', 'plane,vehicle,flying', '飛行機,乗り物,飛ぶ', 'uploads/2024/07/plane.png', 'uploads/2024/07/plane.webp', '2024-07-26'),
('船のイラスト', 'ship-illustration', '大きな船のイラストです。', '', 'ship,vehicle,big', '船,乗り物,大きい', 'uploads/2024/07/ship.png', 'uploads/2024/07/ship.webp', '2024-07-25'),
('星のイラスト', 'star-illustration', '輝く星のイラストです。', '', 'star,space,bright', '星,宇宙,輝く', 'uploads/2024/07/star.png', 'uploads/2024/07/star.webp', '2024-07-24'),
('月のイラスト', 'moon-illustration', '美しい月のイラストです。', '', 'moon,space,beautiful', '月,宇宙,美しい', 'uploads/2024/07/moon.png', 'uploads/2024/07/moon.webp', '2024-07-23'),
('太陽のイラスト', 'sun-illustration', '明るい太陽のイラストです。', '', 'sun,space,bright', '太陽,宇宙,明るい', 'uploads/2024/07/sun.png', 'uploads/2024/07/sun.webp', '2024-07-22'),
('雲のイラスト', 'cloud-illustration', 'ふわふわの雲のイラストです。', '', 'cloud,weather,fluffy', '雲,天気,ふわふわ', 'uploads/2024/07/cloud.png', 'uploads/2024/07/cloud.webp', '2024-07-21'),
('雨のイラスト', 'rain-illustration', '降る雨のイラストです。', '', 'rain,weather,falling', '雨,天気,降る', 'uploads/2024/07/rain.png', 'uploads/2024/07/rain.webp', '2024-07-20'),
('雪のイラスト', 'snow-illustration', '白い雪のイラストです。', '', 'snow,weather,white', '雪,天気,白', 'uploads/2024/07/snow.png', 'uploads/2024/07/snow.webp', '2024-07-19');
