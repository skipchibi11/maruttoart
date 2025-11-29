-- kids_artworksテーブルのpen_nameカラムをNULL許可に変更
-- ペンネーム機能を削除したため、新しいアップロードではpen_nameは不要

USE maruttoart;

-- pen_nameをNULL許可に変更
ALTER TABLE kids_artworks 
MODIFY COLUMN pen_name VARCHAR(100) NULL DEFAULT NULL;

-- 既存のデフォルト値のペンネームをNULLに更新（オプション）
-- UPDATE kids_artworks SET pen_name = NULL WHERE pen_name = 'げんきな おともだち';
