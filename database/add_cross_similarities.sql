-- コミュニティ作品と素材のクロス類似度管理システム
-- 既存のimage_embeddingを活用してクロス検索を実現

-- コミュニティ作品と素材の類似度管理テーブル
CREATE TABLE IF NOT EXISTS community_artwork_material_similarities (
    community_artwork_id INT NOT NULL COMMENT 'コミュニティ作品ID',
    material_id INT NOT NULL COMMENT '素材ID',
    similarity_score DECIMAL(5,4) NOT NULL COMMENT '類似度スコア（0-1）',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '計算日時',
    
    PRIMARY KEY (community_artwork_id, material_id),
    FOREIGN KEY (community_artwork_id) REFERENCES community_artworks(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    INDEX idx_community_artwork_id (community_artwork_id, similarity_score DESC),
    INDEX idx_material_id (material_id, similarity_score DESC),
    INDEX idx_similarity_score (similarity_score),
    INDEX idx_calculated_at (calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='コミュニティ作品と素材の類似度';

-- 類似度計算の進捗管理テーブル
CREATE TABLE IF NOT EXISTS cross_similarity_progress (
    community_artwork_id INT NOT NULL PRIMARY KEY COMMENT 'コミュニティ作品ID',
    status ENUM('pending', 'processing', 'completed', 'error') DEFAULT 'pending' COMMENT '処理状態',
    error_message TEXT COMMENT 'エラーメッセージ',
    processed_at TIMESTAMP NULL DEFAULT NULL COMMENT '処理完了日時',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'レコード作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    
    FOREIGN KEY (community_artwork_id) REFERENCES community_artworks(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_processed_at (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='クロス類似度計算進捗';

-- コミュニティ作品から見た関連素材ビュー
CREATE OR REPLACE VIEW community_artwork_related_materials AS
SELECT 
    cams.community_artwork_id,
    cams.material_id,
    cams.similarity_score,
    m.title as material_title,
    m.slug as material_slug,
    m.image_path as material_image_path,
    m.webp_small_path as material_webp_small_path,
    m.webp_medium_path as material_webp_medium_path,
    m.svg_path as material_svg_path,
    c.slug as category_slug
FROM (
    SELECT 
        community_artwork_id,
        material_id,
        similarity_score,
        ROW_NUMBER() OVER (PARTITION BY community_artwork_id ORDER BY similarity_score DESC) as rn
    FROM community_artwork_material_similarities
    WHERE similarity_score >= 0.7
) cams
JOIN materials m ON cams.material_id = m.id
LEFT JOIN categories c ON m.category_id = c.id
WHERE cams.rn <= 8;

-- 素材から見た関連コミュニティ作品ビュー
CREATE OR REPLACE VIEW material_related_community_artworks AS
SELECT 
    cams.material_id,
    cams.community_artwork_id,
    cams.similarity_score,
    ca.title as artwork_title,
    ca.pen_name,
    ca.file_path as artwork_image_path,
    ca.webp_path as artwork_webp_path
FROM (
    SELECT 
        material_id,
        community_artwork_id,
        similarity_score,
        ROW_NUMBER() OVER (PARTITION BY material_id ORDER BY similarity_score DESC) as rn
    FROM community_artwork_material_similarities
    WHERE similarity_score >= 0.7
) cams
JOIN community_artworks ca ON cams.community_artwork_id = ca.id
WHERE cams.rn <= 8
  AND ca.status = 'approved';
