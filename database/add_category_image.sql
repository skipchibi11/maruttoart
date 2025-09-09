-- カテゴリテーブルにカテゴリ画像パスを追加
ALTER TABLE categories ADD COLUMN category_image_path VARCHAR(500) DEFAULT NULL COMMENT 'カテゴリ用の代表画像パス' AFTER sort_order;

-- 既存データの更新例（必要に応じて手動で設定）
-- UPDATE categories SET category_image_path = 'assets/images/category-fruits.png' WHERE slug = 'fruits';
-- UPDATE categories SET category_image_path = 'assets/images/category-vegetables.png' WHERE slug = 'vegetables';
-- UPDATE categories SET category_image_path = 'assets/images/category-animals.png' WHERE slug = 'animals';
