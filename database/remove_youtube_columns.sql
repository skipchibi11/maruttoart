-- YouTube関連カラムを削除するSQLスクリプト
-- 実行前にバックアップを取ることを強く推奨します
-- 
-- 使用方法:
-- 1. データベースのバックアップを取得
-- 2. このファイルをMySQLで実行
-- 
-- 実行コマンド例:
-- mysql -u username -p database_name < remove_youtube_columns.sql

USE maruttoart;

-- 実行前の現在のテーブル構造を確認（ログ用）
SELECT 'YouTube関連カラム削除処理を開始します' AS status;

-- materialsテーブルからyoutube_urlカラムを削除
-- カラムが存在する場合のみ削除
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'maruttoart' 
     AND TABLE_NAME = 'materials' 
     AND COLUMN_NAME = 'youtube_url') > 0,
    'ALTER TABLE materials DROP COLUMN youtube_url',
    'SELECT "youtube_url カラムは存在しません" AS message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- materialsテーブルからvideo_publish_dateカラムを削除
-- カラムが存在する場合のみ削除
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'maruttoart' 
     AND TABLE_NAME = 'materials' 
     AND COLUMN_NAME = 'video_publish_date') > 0,
    'ALTER TABLE materials DROP COLUMN video_publish_date',
    'SELECT "video_publish_date カラムは存在しません" AS message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 完了メッセージ
SELECT 'YouTube関連カラムの削除処理が完了しました' AS result;

-- 削除後のテーブル構造を表示（確認用）
DESCRIBE materials;
