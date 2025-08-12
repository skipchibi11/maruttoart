-- 素材テーブルの構造変更
-- 検索用キーワードを1つのカラムに統合

-- 新しいキーワードカラムを追加
ALTER TABLE materials ADD COLUMN search_keywords TEXT AFTER description;

-- 既存データを統合（英語・日本語キーワードを結合）
UPDATE materials 
SET search_keywords = CONCAT(
    COALESCE(search_keywords_en, ''), 
    CASE 
        WHEN search_keywords_en IS NOT NULL AND search_keywords_jp IS NOT NULL THEN ','
        ELSE ''
    END,
    COALESCE(search_keywords_jp, '')
)
WHERE search_keywords_en IS NOT NULL OR search_keywords_jp IS NOT NULL;

-- 古いキーワードカラムを削除
ALTER TABLE materials DROP COLUMN search_keywords_en;
ALTER TABLE materials DROP COLUMN search_keywords_jp;
