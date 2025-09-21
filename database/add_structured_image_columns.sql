-- 構造化データ用の画像とバックグラウンドカラーのカラムを追加

ALTER TABLE materials 
ADD COLUMN structured_image_path VARCHAR(255) DEFAULT NULL COMMENT '構造化データ用1200px画像のパス',
ADD COLUMN structured_bg_color VARCHAR(7) DEFAULT NULL COMMENT '構造化データ用画像の背景色（HEX）';

-- インデックスを追加（未処理の素材を効率的に検索するため）
CREATE INDEX idx_materials_structured ON materials(structured_image_path);