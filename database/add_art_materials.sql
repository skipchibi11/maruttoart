-- 画材管理機能追加スクリプト
-- 実行手順: mysql -u root -p maruttoart < add_art_materials.sql

-- 画材マスタテーブルの作成
CREATE TABLE art_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    name_en VARCHAR(100),
    description TEXT,
    color_code VARCHAR(7), -- HEXカラーコード（例: #FF6B6B）
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 素材と画材の中間テーブル
CREATE TABLE material_art_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    art_material_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    FOREIGN KEY (art_material_id) REFERENCES art_materials(id) ON DELETE CASCADE,
    UNIQUE KEY unique_material_art_material (material_id, art_material_id)
);
