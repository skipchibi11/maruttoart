-- ミニストーリー機能の追加
-- 素材にAI生成のミニストーリーを追加してコンテンツを充実させる

-- materialsテーブルにミニストーリー関連カラムを追加
ALTER TABLE materials 
ADD COLUMN mini_story TEXT DEFAULT NULL COMMENT 'AI生成ミニストーリー（絵本風）',
ADD COLUMN mini_story_generated_at TIMESTAMP NULL DEFAULT NULL COMMENT 'ミニストーリー生成日時',
ADD COLUMN mini_story_model VARCHAR(100) DEFAULT NULL COMMENT '生成に使用したAIモデル';

-- インデックス追加（未生成の素材を効率的に検索）
ALTER TABLE materials 
ADD INDEX idx_mini_story_generated (mini_story_generated_at);
