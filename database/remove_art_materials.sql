-- 画材関連テーブル削除スクリプト
-- 実行手順: mysql -u root -p maruttoart < remove_art_materials.sql

-- 外部キー制約があるため、中間テーブルから先に削除
DROP TABLE IF EXISTS material_art_materials;

-- 画材マスタテーブルを削除
DROP TABLE IF EXISTS art_materials;

-- 確認用クエリ（実行後にテーブルが存在しないことを確認）
-- SHOW TABLES LIKE '%art_material%';
