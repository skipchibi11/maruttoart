-- 類似画像管理のための中間テーブル作成
-- 実行日: 2025年10月21日

-- 類似画像関係を管理する中間テーブル
CREATE TABLE material_similarities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL COMMENT '基準となる素材ID',
    similar_material_id INT NOT NULL COMMENT '類似する素材ID',
    similarity_score DECIMAL(5,4) NOT NULL COMMENT '類似度スコア（0.0000-1.0000）',
    calculation_method VARCHAR(50) NOT NULL DEFAULT 'cosine_similarity' COMMENT '類似度計算方法',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '計算実行日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- 外部キー制約
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    FOREIGN KEY (similar_material_id) REFERENCES materials(id) ON DELETE CASCADE,
    
    -- 重複防止のためのユニーク制約
    UNIQUE KEY unique_similarity_pair (material_id, similar_material_id),
    
    -- 検索効率化のためのインデックス
    INDEX idx_material_similarity (material_id, similarity_score DESC),
    INDEX idx_similar_material (similar_material_id),
    INDEX idx_similarity_score (similarity_score DESC),
    INDEX idx_calculated_at (calculated_at),
    
    -- 自分自身との類似度は除外
    CHECK (material_id != similar_material_id),
    -- 類似度スコアの範囲制限
    CHECK (similarity_score >= 0.0000 AND similarity_score <= 1.0000)
) COMMENT='素材間の類似度を管理するテーブル';

-- 類似度計算の進捗管理テーブル
CREATE TABLE similarity_calculation_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL COMMENT '処理対象の素材ID',
    status ENUM('pending', 'processing', 'completed', 'error') NOT NULL DEFAULT 'pending' COMMENT '処理状況',
    processed_at TIMESTAMP NULL COMMENT '処理完了日時',
    error_message TEXT NULL COMMENT 'エラーメッセージ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- 外部キー制約
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    
    -- 1つの素材につき1つの進捗レコード
    UNIQUE KEY unique_material_progress (material_id),
    
    -- 検索効率化のためのインデックス
    INDEX idx_status (status),
    INDEX idx_processed_at (processed_at)
) COMMENT='類似度計算の進捗管理テーブル';

-- 未処理の素材を自動的に進捗テーブルに登録
INSERT INTO similarity_calculation_progress (material_id, status)
SELECT id, 'pending'
FROM materials 
WHERE image_embedding IS NOT NULL
  AND id NOT IN (SELECT material_id FROM similarity_calculation_progress);

-- 類似画像検索用のビュー（上位5件の類似画像を取得）
CREATE VIEW material_top_similarities AS
SELECT 
    ms.material_id,
    ms.similar_material_id,
    ms.similarity_score,
    m1.title as material_title,
    m2.title as similar_material_title,
    m1.slug as material_slug,
    m2.slug as similar_material_slug,
    c1.slug as material_category_slug,
    c2.slug as similar_material_category_slug
FROM material_similarities ms
JOIN materials m1 ON ms.material_id = m1.id
JOIN materials m2 ON ms.similar_material_id = m2.id
LEFT JOIN categories c1 ON m1.category_id = c1.id
LEFT JOIN categories c2 ON m2.category_id = c2.id
WHERE ms.similarity_score >= 0.7  -- 閾値以上の類似度のみ
ORDER BY ms.material_id, ms.similarity_score DESC;