-- タグ・カテゴリ管理機能追加（本番適用用）
-- 実行日: 2025年8月13日

-- 1. タグテーブル作成
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_name (name)
);

-- 2. 素材とタグの中間テーブル作成（多対多の関係）
CREATE TABLE IF NOT EXISTS material_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    UNIQUE KEY unique_material_tag (material_id, tag_id),
    INDEX idx_material_id (material_id),
    INDEX idx_tag_id (tag_id)
);

-- 3. カテゴリテーブル作成（第一階層のみ）
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_sort_order (sort_order)
);

-- 4. materialsテーブルにカテゴリIDカラムを追加（1対多の関係）
-- 既にカラムが存在する場合はエラーを無視
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'maruttoart' 
     AND TABLE_NAME = 'materials' 
     AND COLUMN_NAME = 'category_id') = 0,
    'ALTER TABLE materials ADD COLUMN category_id INT DEFAULT NULL',
    'SELECT "category_id column already exists" as status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 外部キー制約を追加（既に存在する場合はエラーを無視）
SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = 'maruttoart' 
     AND TABLE_NAME = 'materials' 
     AND CONSTRAINT_NAME = 'fk_materials_category') = 0,
    'ALTER TABLE materials ADD CONSTRAINT fk_materials_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL',
    'SELECT "foreign key already exists" as status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
