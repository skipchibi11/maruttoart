-- =====================================================
-- 子供向けアトリエ機能 - 統合マイグレーション
-- =====================================================
-- このファイルには以下が含まれます：
-- 1. kids_artworksテーブルの作成
-- 2. ペンネーム機能の削除（NULL許可）
-- 3. 必要なインデックスの作成
-- =====================================================

-- データベース選択
USE maruttoart;

-- =====================================================
-- 1. 子供向けアトリエ作品テーブル作成
-- =====================================================
CREATE TABLE IF NOT EXISTS kids_artworks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- 基本情報
    title VARCHAR(255) NOT NULL COMMENT 'AIが生成した作品タイトル',
    description TEXT COMMENT '作品の説明（オプション）',
    ai_story TEXT COMMENT 'AIが生成した物語',
    
    -- 画像情報
    image_path VARCHAR(512) NOT NULL COMMENT '元の画像パス',
    webp_path VARCHAR(512) COMMENT 'WebPサムネイルパス（表示用）',
    
    -- ユーザー情報
    pen_name VARCHAR(100) NULL DEFAULT NULL COMMENT 'ペンネーム（レガシー、現在は使用しない）',
    ip_address VARCHAR(45) COMMENT 'アップロード元IPアドレス（1日1回制限用）',
    
    -- 統計情報
    downloads INT DEFAULT 0 COMMENT 'ダウンロード数',
    is_featured BOOLEAN DEFAULT FALSE COMMENT '注目作品フラグ',
    
    -- タイムスタンプ
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- インデックス
    INDEX idx_created_at (created_at),
    INDEX idx_is_featured (is_featured),
    INDEX idx_ip_date (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='子供向けアトリエの作品';

-- =====================================================
-- 2. 既存テーブルの更新（pen_nameをNULL許可に変更）
-- =====================================================
-- 注意: テーブルが既に存在する場合のみ実行されます
ALTER TABLE kids_artworks 
MODIFY COLUMN pen_name VARCHAR(100) NULL DEFAULT NULL COMMENT 'ペンネーム（レガシー、現在は使用しない）';

-- =====================================================
-- 3. オプション: 既存のデフォルトペンネームをNULLに更新
-- =====================================================
-- 必要に応じてコメントを解除してください
-- UPDATE kids_artworks SET pen_name = NULL WHERE pen_name = 'げんきな おともだち';

-- =====================================================
-- マイグレーション完了
-- =====================================================
-- 使用方法:
-- mysql -u root -p maruttoart < database/kids_artworks_complete.sql
-- =====================================================
