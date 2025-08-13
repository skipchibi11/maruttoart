-- materials テーブルに新しいWebPカラムを追加
ALTER TABLE materials 
ADD COLUMN webp_small_path VARCHAR(255) DEFAULT NULL COMMENT 'WebP小サイズ画像パス (180x180)',
ADD COLUMN webp_medium_path VARCHAR(255) DEFAULT NULL COMMENT 'WebP中サイズ画像パス (300x300)';

-- 既存のwebp_pathカラムがある場合は削除（必要に応じて）
-- ALTER TABLE materials DROP COLUMN webp_path;
