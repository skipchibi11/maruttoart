-- 動画公開日時機能のためのデータベース変更
-- 実行日: 2025-08-29
-- 目的: 素材テーブルに動画公開日時カラムを追加

USE maruttoart;

-- materialsテーブルにvideo_publish_dateカラムを追加
ALTER TABLE materials 
ADD COLUMN video_publish_date DATETIME NULL 
COMMENT '動画公開日時。この日時以降に動画が表示される。NULLの場合は即座に表示。' 
AFTER youtube_url;

-- 変更内容の確認
DESCRIBE materials;
