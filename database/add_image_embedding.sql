-- 画像ベクトル化機能のためのカラム追加
-- 実行日: 2025年10月20日

-- materialsテーブルにベクトル関連カラムを追加
ALTER TABLE materials 
ADD COLUMN image_embedding TEXT DEFAULT NULL COMMENT '画像のベクトル数値（JSON形式）' AFTER ai_product_image_description,
ADD COLUMN embedding_model VARCHAR(100) DEFAULT NULL COMMENT '使用したベクトル化モデル名' AFTER image_embedding,
ADD COLUMN embedding_created_at TIMESTAMP NULL DEFAULT NULL COMMENT 'ベクトル化実行日時' AFTER embedding_model;

-- ベクトル化処理の効率化のためのインデックス
CREATE INDEX idx_materials_embedding_created ON materials(embedding_created_at);
CREATE INDEX idx_materials_embedding_model ON materials(embedding_model);

-- ベクトル化未実施の素材を効率的に取得するためのインデックス
CREATE INDEX idx_materials_no_embedding ON materials(id) WHERE image_embedding IS NULL;