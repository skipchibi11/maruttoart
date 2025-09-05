<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

$pdo = getDB();

// カテゴリ情報を取得
$categoryStmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
$categoryStmt->execute([$slug]);
$category = $categoryStmt->fetch();

if (!$category) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

// そのカテゴリの素材を取得
$materialsStmt = $pdo->prepare("
    SELECT m.* FROM materials m 
    WHERE m.category_id = ? 
    ORDER BY m.upload_date DESC
");
$materialsStmt->execute([$category['id']]);
$materials = $materialsStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-579HN546');</script>
    <!-- End Google Tag Manager -->
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($category['title']) ?> - 無料のやさしいイラスト素材｜maruttoart（商用利用OK）</title>
    
    <!-- Site Icons -->
    <link rel="icon" href="/favicon.ico">
    <meta name="description" content="<?= h($category['title']) ?>の無料イラスト素材一覧。やさしいイラスト素材を商用利用OK。高品質なフリー素材をダウンロードして、デザイン制作にお役立てください。">
    <style>
        /* リセット */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #ffffff;
        }

        /* コンテナ */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ヘッダー */
        .header {
            background-color: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 0;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-logo {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }

        .header-logo:hover {
            color: #333;
        }

        .header-nav a {
            color: #666;
            text-decoration: none;
            font-size: 1rem;
        }

        .header-nav a:hover {
            color: #333;
        }

        /* パンくずリスト */
        .breadcrumb {
            padding: 15px 0;
            font-size: 0.875rem;
        }

        .breadcrumb-list {
            list-style: none;
            display: flex;
            align-items: center;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
        }

        .breadcrumb-item:not(:last-child)::after {
            content: ">";
            margin: 0 8px;
            color: #6c757d;
        }

        .breadcrumb-item a {
            color: #6c757d;
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: #495057;
            text-decoration: underline;
        }

        .breadcrumb-item.active {
            color: #6c757d;
        }

        /* カテゴリヘッダー */
        .category-header {
            text-align: center;
            color: #6c757d;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 1rem;
            margin: 2rem 0;
        }

        .category-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        /* グリッドレイアウト */
        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin: 2rem 0;
        }

        /* マテリアルカード */
        .material-card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: relative;
            display: block;
        }

        .material-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: inherit;
            text-decoration: none;
        }

        .material-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            display: block;
        }

        .material-card-body {
            padding: 12px;
        }

        .material-title {
            font-size: 0.875rem;
            color: #666;
            text-align: center;
            margin: 0;
        }

        /* 空の状態 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state .icon {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .empty-state h4 {
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: transparent;
            color: #0d6efd;
            border: 1px solid #0d6efd;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn:hover {
            background-color: #0d6efd;
            color: white;
            text-decoration: none;
        }

        /* YouTubeアイコン */
        .youtube-icon {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.2s ease;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
            opacity: 0.8;
        }

        .youtube-icon:hover {
            background: rgba(0, 0, 0, 0.8);
            opacity: 1;
            transform: scale(1.05);
        }

        .youtube-icon::before {
            content: '';
            width: 16px;
            height: 16px;
            background-image: url('/assets/icons/youtube.svg');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            filter: brightness(0) invert(1);
        }

        /* YouTube動画モーダル */
        .youtube-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .youtube-modal.show {
            display: flex;
        }

        .youtube-modal-content {
            position: relative;
            width: 90%;
            max-width: 800px;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }

        .youtube-modal iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .youtube-modal-close {
            position: absolute;
            top: -40px;
            right: 0;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
        }

        .youtube-modal-close:hover {
            color: #ccc;
        }

        /* フッター */
        .footer {
            background-color: #fef9e7;
            padding: 30px 0;
            margin-top: 60px;
        }

        .footer-text {
            color: #2c3e50;
            text-align: center;
            margin: 0;
        }

        /* レスポンシブ */
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }

            .header-logo {
                font-size: 1.5rem;
            }

            .materials-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .category-header h1 {
                font-size: 1.5rem;
            }

            .empty-state {
                padding: 40px 20px;
            }
        }

        @media (max-width: 480px) {
            .materials-grid {
                gap: 10px;
            }

            .material-card-body {
                padding: 8px;
            }

            .material-title {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-579HN546"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a class="header-logo" href="/">maruttoart</a>
            </div>
        </div>
    </header>
    
    <!-- パンくずリスト -->
    <div class="container">
        <nav class="breadcrumb" aria-label="breadcrumb">
            <ol class="breadcrumb-list">
                <li class="breadcrumb-item">
                    <a href="/">ホーム</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= h($category['title']) ?>
                </li>
            </ol>
        </nav>
    </div>
    
    <div class="container">
        <div class="category-header">
            <h1><?= h($category['title']) ?></h1>
            <p><?= count($materials) ?>個の素材があります</p>
        </div>
        
        <?php if (empty($materials)): ?>
            <div class="empty-state">
                <div class="icon">🖼️</div>
                <h4>まだ素材がありません</h4>
                <p>このカテゴリには素材がまだ投稿されていません。</p>
                <a href="/" class="btn">ホームに戻る</a>
            </div>
        <?php else: ?>
            <div class="materials-grid">
                <?php foreach ($materials as $material): ?>
                    <a href="/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/" class="material-card">
                        <picture>
                            <!-- デスクトップ用：300x300のWebP -->
                            <source media="(min-width: 768px)" 
                                    srcset="/<?= h($material['webp_medium_path'] ?? $material['image_path']) ?>" 
                                    type="image/webp">
                            <!-- モバイル用：180x180のWebP -->
                            <source media="(max-width: 767px)" 
                                    srcset="/<?= h($material['webp_small_path'] ?? $material['image_path']) ?>" 
                                    type="image/webp">
                            <!-- フォールバック：オリジナル画像 -->
                            <img src="/<?= h($material['image_path']) ?>" 
                                 class="material-image" 
                                 alt="<?= h($material['title']) ?>のイラスト" 
                                 loading="lazy">
                        </picture>
                        
                        <!-- YouTubeアイコン -->
                        <?php 
                        // 動画表示の判定
                        $showVideo = !empty($material['youtube_url']);
                        if (!empty($material['video_publish_date'])) {
                            $publishDateTime = new DateTime($material['video_publish_date']);
                            $now = new DateTime();
                            $showVideo = $showVideo && ($now >= $publishDateTime);
                        }
                        
                        if ($showVideo): ?>
                            <div class="youtube-icon" 
                                 onclick="openYouTubeModal(event, '<?= h($material['youtube_url']) ?>', '<?= h($material['title']) ?>')"
                                 title="動画を見る">
                            </div>
                        <?php endif; ?>
                        
                        <div class="material-card-body">
                            <p class="material-title">
                                <?= h($material['title']) ?>
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <div class="container">
            <p class="footer-text">&copy; 2024 maruttoart. All rights reserved.</p>
        </div>
    </footer>

    <!-- YouTubeモーダル -->
    <div id="youtube-modal" class="youtube-modal">
        <div class="youtube-modal-content">
            <button class="youtube-modal-close" onclick="closeYouTubeModal()">&times;</button>
            <iframe id="youtube-iframe" src="" allowfullscreen></iframe>
        </div>
    </div>
    
    <script>
    // YouTube動画ポップアップ機能
    function openYouTubeModal(event, youtubeUrl, title) {
        event.preventDefault();
        event.stopPropagation();
        
        const modal = document.getElementById('youtube-modal');
        const iframe = document.getElementById('youtube-iframe');
        
        // YouTube URLをembed形式に変換
        let embedUrl = '';
        if (youtubeUrl.includes('youtube.com/watch?v=')) {
            const videoId = youtubeUrl.split('v=')[1].split('&')[0];
            embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
        } else if (youtubeUrl.includes('youtu.be/')) {
            const videoId = youtubeUrl.split('/').pop().split('?')[0];
            embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
        } else if (youtubeUrl.includes('youtube.com/embed/')) {
            embedUrl = youtubeUrl + (youtubeUrl.includes('?') ? '&' : '?') + 'autoplay=1';
        } else {
            embedUrl = youtubeUrl;
        }
        
        iframe.src = embedUrl;
        modal.classList.add('show');
        
        // Escキーでモーダルを閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeYouTubeModal();
            }
        });
        
        // モーダル背景クリックで閉じる
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeYouTubeModal();
            }
        });
    }
    
    function closeYouTubeModal() {
        const modal = document.getElementById('youtube-modal');
        const iframe = document.getElementById('youtube-iframe');
        
        modal.classList.remove('show');
        iframe.src = '';
    }
    </script>
</body>
</html>
