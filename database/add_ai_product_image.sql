-- AI生成製品画像のカラムを追加
ALTER TABLE materials ADD COLUMN ai_product_image_path VARCHAR(500) DEFAULT NULL AFTER structured_bg_color;
ALTER TABLE materials ADD COLUMN ai_product_image_description TEXT DEFAULT NULL AFTER ai_product_image_path;

-- AI生成製品画像のインデックスを追加（検索性能向上のため）
CREATE INDEX idx_materials_ai_product ON materials(ai_product_image_path);