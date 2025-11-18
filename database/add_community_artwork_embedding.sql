-- community_artworksテーブルに画像ベクトル埋め込みカラムを追加
-- みんなのアトリエ作品の画像類似度計算に使用

ALTER TABLE community_artworks
ADD COLUMN image_embedding TEXT DEFAULT NULL COMMENT '画像ベクトル埋め込み（JSON形式）',
ADD COLUMN embedding_model VARCHAR(100) DEFAULT NULL COMMENT '使用した埋め込みモデル',
ADD COLUMN embedding_created_at TIMESTAMP NULL DEFAULT NULL COMMENT 'ベクトル生成日時';

-- 類似作品管理テーブル
CREATE TABLE IF NOT EXISTS community_artwork_similarities (
    artwork_id INT NOT NULL COMMENT '基準作品ID',
    similar_artwork_id INT NOT NULL COMMENT '類似作品ID',
    similarity_score DECIMAL(5,4) NOT NULL COMMENT '類似度スコア（0-1）',
    calculation_method VARCHAR(50) NOT NULL DEFAULT 'cosine_similarity' COMMENT '計算方法',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '計算日時',
    
    PRIMARY KEY (artwork_id, similar_artwork_id),
    FOREIGN KEY (artwork_id) REFERENCES community_artworks(id) ON DELETE CASCADE,
    FOREIGN KEY (similar_artwork_id) REFERENCES community_artworks(id) ON DELETE CASCADE,
    INDEX idx_similarity_score (similarity_score),
    INDEX idx_similar_artwork_id (similar_artwork_id),
    INDEX idx_calculated_at (calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='コミュニティ作品類似度管理';

-- 類似度計算の進捗管理テーブル
CREATE TABLE IF NOT EXISTS community_artwork_similarity_progress (
    artwork_id INT NOT NULL PRIMARY KEY COMMENT '作品ID',
    status ENUM('pending', 'processing', 'completed', 'error') DEFAULT 'pending' COMMENT '処理状態',
    error_message TEXT COMMENT 'エラーメッセージ',
    processed_at TIMESTAMP NULL DEFAULT NULL COMMENT '処理完了日時',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'レコード作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    priority INT DEFAULT 0 COMMENT '優先度（高い順に処理）',
    
    FOREIGN KEY (artwork_id) REFERENCES community_artworks(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_processed_at (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='コミュニティ作品類似度計算進捗';

-- 上位類似作品のビュー（パフォーマンス最適化）
CREATE OR REPLACE VIEW community_artwork_top_similarities AS
SELECT 
    cas.artwork_id,
    cas.similar_artwork_id,
    cas.similarity_score,
    ca.title as similar_artwork_title,
    ca.pen_name as similar_artwork_pen_name,
    ca.file_path as similar_artwork_file_path,
    ca.webp_path as similar_artwork_webp_path
FROM (
    SELECT 
        artwork_id,
        similar_artwork_id,
        similarity_score,
        ROW_NUMBER() OVER (PARTITION BY artwork_id ORDER BY similarity_score DESC) as rn
    FROM community_artwork_similarities
    WHERE similarity_score >= 0.7
) cas
JOIN community_artworks ca ON cas.similar_artwork_id = ca.id
WHERE cas.rn <= 8
  AND ca.status = 'approved';
