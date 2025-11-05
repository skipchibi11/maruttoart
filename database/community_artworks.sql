-- みんなの作品集テーブル
CREATE TABLE IF NOT EXISTS community_artworks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL COMMENT '作品タイトル',
    pen_name VARCHAR(50) NOT NULL COMMENT 'ペンネーム',
    description TEXT COMMENT '作品説明（任意）',
    
    -- 画像ファイル情報
    original_filename VARCHAR(255) NOT NULL COMMENT '元のファイル名',
    file_path VARCHAR(500) NOT NULL COMMENT 'PNGファイルパス',
    webp_path VARCHAR(500) COMMENT 'WebPファイルパス（サムネイル用）',
    file_size INT NOT NULL COMMENT 'ファイルサイズ（バイト）',
    image_width INT COMMENT '画像幅',
    image_height INT COMMENT '画像高さ',
    
    -- セキュリティ・重複防止
    file_hash CHAR(64) NOT NULL COMMENT 'SHA256ハッシュ値',
    mime_type VARCHAR(50) NOT NULL COMMENT 'MIMEタイプ',
    
    -- フリー素材同意
    free_material_consent TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'フリー素材公開同意',
    license_type VARCHAR(50) DEFAULT 'CC BY 4.0' COMMENT 'ライセンス種類',
    
    -- 承認・管理
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT '承認状態',
    is_featured TINYINT(1) DEFAULT 0 COMMENT 'おすすめ作品フラグ',
    rejection_reason TEXT COMMENT '却下理由',
    
    -- 統計
    view_count INT DEFAULT 0 COMMENT '閲覧数',
    download_count INT DEFAULT 0 COMMENT 'ダウンロード数',
    like_count INT DEFAULT 0 COMMENT 'いいね数',
    
    -- メタデータ
    ip_address VARCHAR(45) COMMENT '投稿者IPアドレス',
    user_agent TEXT COMMENT 'ユーザーエージェント',
    used_material_ids TEXT COMMENT '使用素材ID（カンマ区切り）',
    
    -- タイムスタンプ
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '投稿日時',
    approved_at TIMESTAMP NULL COMMENT '承認日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- インデックス
    INDEX idx_status (status),
    INDEX idx_featured (is_featured),
    INDEX idx_created_at (created_at),
    INDEX idx_file_hash (file_hash),
    INDEX idx_free_material (free_material_consent),
    INDEX idx_pen_name (pen_name),
    INDEX idx_used_material_ids (used_material_ids(255)),
    FULLTEXT idx_search (title, description, pen_name)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='みんなの作品集';

-- 投稿制限テーブル（重複投稿・スパム防止）
CREATE TABLE IF NOT EXISTS artwork_upload_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    upload_date DATE NOT NULL,
    upload_count INT DEFAULT 1,
    last_upload_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_ip_date (ip_address, upload_date),
    INDEX idx_upload_date (upload_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='投稿制限管理';

-- いいね機能テーブル
CREATE TABLE IF NOT EXISTS artwork_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artwork_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (artwork_id) REFERENCES community_artworks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (artwork_id, ip_address),
    INDEX idx_artwork_id (artwork_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='作品いいね管理';