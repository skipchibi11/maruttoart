<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$tag_slug = $_GET['slug'] ?? '';
if (empty($tag_slug)) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

$pdo = getDB();

// タグ情報を取得
$tagStmt = $pdo->prepare("SELECT * FROM tags WHERE slug = ?");
$tagStmt->execute([$tag_slug]);
$tag = $tagStmt->fetch();

if (!$tag) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

// ページネーション設定
$perPage = 20; // 1ページあたりの表示件数
$page = max(1, intval($_GET['page'] ?? 1)); // 現在のページ（最小値は1）
$offset = ($page - 1) * $perPage;

// そのタグが設定された素材を取得（カテゴリ情報も含める）
$countSql = "SELECT COUNT(DISTINCT m.id) FROM materials m 
             INNER JOIN material_tags mt ON m.id = mt.material_id 
             WHERE mt.tag_id = ?";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute([$tag['id']]);
$totalItems = $countStmt->fetchColumn();

// 5つ以上の素材がない場合は404ページに遷移
if ($totalItems < 5) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

$totalPages = ceil($totalItems / $perPage);

$materialsSql = "SELECT DISTINCT m.*, c.slug as category_slug 
                 FROM materials m 
                 INNER JOIN material_tags mt ON m.id = mt.material_id 
                 LEFT JOIN categories c ON m.category_id = c.id 
                 WHERE mt.tag_id = ? 
                 ORDER BY m.created_at DESC 
                 LIMIT ? OFFSET ?";
$materialsStmt = $pdo->prepare($materialsSql);
$materialsStmt->execute([$tag['id'], $perPage, $offset]);
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
    <title><?= h($tag['name']) ?> - タグ別無料イラスト素材一覧｜maruttoart（商用利用OK）</title>
    <meta name="description" content="<?= h($tag['name']) ?>タグの無料イラスト素材一覧。やさしいイラスト素材を商用利用OK。<?= h($tag['name']) ?>に関連する高品質なフリー素材をダウンロードできます。">
    
    <!-- Site Icons -->
    <link rel="icon" href="/favicon.ico">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/tag/<?= h($tag['slug']) ?>/">
    <meta property="og:title" content="<?= h($tag['name']) ?> - タグ別無料イラスト素材一覧">
    <meta property="og:description" content="<?= h($tag['name']) ?>タグの無料イラスト素材。">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary">
    <meta property="twitter:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/tag/<?= h($tag['slug']) ?>/">
    <meta property="twitter:title" content="<?= h($tag['name']) ?> - タグ別無料イラスト素材一覧">
    <meta property="twitter:description" content="<?= h($tag['name']) ?>タグの無料イラスト素材。">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #ffffff;
        }
        .material-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            position: relative;
        }
        .material-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            color: inherit;
            text-decoration: none;
            border-color: #0d6efd;
        }
        .material-card:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }
        .material-card .card-body {
            padding: 0.75rem 1rem;
        }
        .material-card .card-title {
            color: #666;
            font-weight: 300;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 0;
        }
        .material-card:hover .card-title {
            color: #0d6efd;
        }
        .material-image {
            width: 100%;
            aspect-ratio: 1 / 1; /* 正方形を維持 */
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        .material-title {
            color: #666;
            font-weight: 300;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 0;
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
        .page-title {
            color: #6c757d;
            font-size: 1.5rem;
            font-weight: 400;
            margin-bottom: 2rem;
        }
        .tag-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        .tag-name {
            font-size: 1.25rem;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .tag-count {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* YouTubeアイコンのスタイル */
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
        
        /* YouTube動画ポップアップのスタイル */
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
        
        /* YouTube動画ポップアップのスタイル */
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

        /* フッターのスタイル */
        .footer-custom {
            background-color: #fef9e7 !important;
        }

        /* フッター文字色の改善（コントラスト対応） */
        .footer-custom .footer-text {
            color: #2c3e50 !important;
        }

        .footer-custom .footer-text:hover {
            color: #1a252f !important;
        }
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-579HN546"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand header-logo" href="/">maruttoart</a>
            <div class="navbar-nav ms-auto d-flex align-items-center">
                <a class="nav-link" href="/">戻る</a>
            </div>
        </div>
    </nav>
    
    <!-- パンくずリスト -->
    <div class="container mt-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="/" style="color: #6c757d; text-decoration: none;">ホーム</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page" style="color: #6c757d;">
                    <?= h($tag['name']) ?>
                </li>
            </ol>
        </nav>
    </div>

    <div class="container mt-4">
        <!-- タグ情報 -->
        <div class="tag-info">
            <div class="tag-name"><?= h($tag['name']) ?></div>
            <div class="tag-count"><?= $totalItems ?>件の素材</div>
        </div>

        <?php if (empty($materials)): ?>
            <div class="text-center py-5">
                <p class="text-muted">このタグに関連する素材はまだありません。</p>
                <a href="/" class="btn btn-outline-primary">
                    <i class="bi bi-house"></i>
                    ホームに戻る
                </a>
            </div>
        <?php else: ?>
            <!-- 素材一覧 -->
            <div class="row g-4">
                <?php foreach ($materials as $material): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="/<?= h($material['category_slug']) ?>/<?= h($material['slug']) ?>/" class="material-card card h-100" role="button" tabindex="0" aria-label="<?= h($material['title']) ?>の詳細を見る">
                            <picture>
                                <!-- WebP対応 -->
                                <source srcset="/<?= h($material['webp_small_path'] ?? $material['image_path']) ?>" type="image/webp">
                                <!-- フォールバック -->
                                <img src="/<?= h($material['image_path']) ?>" class="material-image" alt="<?= h($material['title']) ?>のイラスト">
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
                            
                            <div class="card-body">
                                <h5 class="card-title"><?= h($material['title']) ?></h5>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ページネーション -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="ページネーション" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="/tag/<?= h($tag['slug']) ?>/?page=<?= $page - 1 ?>">前へ</a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="/tag/' . h($tag['slug']) . '/?page=1">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <?php if ($i == $page): ?>
                                    <span class="page-link"><?= $i ?></span>
                                <?php else: ?>
                                    <a class="page-link" href="/tag/<?= h($tag['slug']) ?>/?page=<?= $i ?>"><?= $i ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor; ?>
                        
                        <?php
                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="/tag/' . h($tag['slug']) . '/?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                        }
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="/tag/<?= h($tag['slug']) ?>/?page=<?= $page + 1 ?>">次へ</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer class="footer-custom mt-5 py-4">
        <div class="container">
            
            <div class="row align-items-center">
                <div class="col-md-12">
                    <p class="footer-text mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
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
    
    // カードのキーボードナビゲーション対応
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.material-card');
        
        cards.forEach(function(card) {
            card.addEventListener('keydown', function(e) {
                // Enterキーまたはスペースキーで詳細ページに遷移
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    window.location.href = card.href;
                }
            });
        });
    });
    </script>
</body>
</html>
