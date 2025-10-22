-- SVGファイルパス用のカラムを追加

ALTER TABLE materials 
ADD COLUMN svg_path VARCHAR(255) DEFAULT NULL COMMENT 'SVGファイルのパス';

-- インデックスを追加（SVGファイルの検索用）
CREATE INDEX idx_materials_svg ON materials(svg_path);