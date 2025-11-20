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

// ページング設定
$perPage = 20; // 1ページあたりの表示件数
$page = max(1, intval($_GET['page'] ?? 1)); // 現在のページ（最小値は1）
$offset = ($page - 1) * $perPage;

// カテゴリ情報を取得
$categoryStmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
$categoryStmt->execute([$slug]);
$category = $categoryStmt->fetch();

if (!$category) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

// 総件数を取得
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE category_id = ?");
$countStmt->execute([$category['id']]);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// そのカテゴリの素材を取得（ページング付き）
$materialsStmt = $pdo->prepare("
    SELECT m.* FROM materials m 
    WHERE m.category_id = ? 
    ORDER BY m.upload_date DESC 
    LIMIT ? OFFSET ?
");
$materialsStmt->execute([$category['id'], $perPage, $offset]);
$materials = $materialsStmt->fetchAll();

// 現在表示されている素材からミニストーリーがあるものをランダムに3件取得
$storyMaterials = [];
if (!empty($materials)) {
    $materialIds = array_column($materials, 'id');
    if (!empty($materialIds)) {
        $placeholders = implode(',', array_fill(0, count($materialIds), '?'));
        $storyStmt = $pdo->prepare("
            SELECT m.id, m.title, m.slug, m.mini_story,
                   m.image_path, m.webp_small_path, m.structured_bg_color,
                   c.slug as category_slug
            FROM materials m
            LEFT JOIN categories c ON m.category_id = c.id
            WHERE m.id IN ($placeholders)
            AND m.mini_story IS NOT NULL
            ORDER BY RAND()
            LIMIT 3
        ");
        $storyStmt->execute($materialIds);
        $storyMaterials = $storyStmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google Tag Manager & GDPR -->
    <script src="/assets/js/gdpr-gtm.js"></script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($category['title']) ?> - ミニマルなフリーイラスト素材（商用利用OK）｜marutto.art</title>
    
    <!-- Site Icons -->
    <link rel="icon" href="/favicon.ico">
    
    <!-- External CSS -->
    <link rel="stylesheet" href="/assets/css/gdpr.css">
    
    <meta name="description" content="<?= h($category['title']) ?>のフリーイラスト素材一覧。ミニマルなフリーイラスト素材を商用利用OK">

    <!-- カノニカルタグ -->
    <?php
    $canonicalUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . '/' . $slug . '/';
    if ($page > 1) {
        $canonicalUrl .= '?page=' . $page;
    }
    ?>
    <link rel="canonical" href="<?= h($canonicalUrl) ?>">
    
    <!-- Alternate language tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/<?= h($slug) ?>/" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/<?= h($slug) ?>/" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/<?= h($slug) ?>/" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/<?= h($slug) ?>/" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/<?= h($slug) ?>/" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/<?= h($slug) ?>/" />
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
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 2rem 0;
        }
        
        /* 768px以上: 3列表示 (list.phpのcol-md-4と同等) */
        @media (min-width: 768px) {
            .materials-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        /* 992px以上: 4列表示 (list.phpのcol-lg-3と同等) */
        @media (min-width: 992px) {
            .materials-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        /* 1200px以上: 6列表示 (list.phpのcol-xl-2と同等) */
        @media (min-width: 1200px) {
            .materials-grid {
                grid-template-columns: repeat(6, 1fr);
            }
        }

        /* マテリアルカード */
        .material-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            position: relative;
            border-radius: 8px;
            will-change: transform, box-shadow;
            background-color: #F9F5E9;
            margin-bottom: 0.5rem;
            padding: 20px;
            overflow: hidden;
        }

        .material-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: inherit;
            text-decoration: none;
        }

        .material-card:focus {
            outline: none;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: inherit;
            text-decoration: none;
        }

        .material-image {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            border-radius: 4px;
            transition: opacity 0.3s ease-in-out;
        }

        .material-card-body {
            flex: 1 1 auto;
            padding: 0.5rem 1rem 0.1rem 1rem;
        }

        .material-title {
            color: #666;
            font-weight: 300;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 0;
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
        
        /* ページネーション */
        .pagination {
            display: flex;
            padding-left: 0;
            list-style: none;
            border-radius: 0;
            gap: 5px;
            justify-content: center;
            align-items: center;
        }

        .justify-content-center {
            justify-content: center !important;
        }

        nav[aria-label="ページネーション"] {
            margin-top: 3rem;
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .page-item {
            position: relative;
            display: block;
        }

        .page-item:first-child .page-link,
        .page-item:last-child .page-link {
            border-radius: 8px;
        }

        .page-item.active .page-link {
            z-index: 3;
            background-color: #f5f5f5;
            color: #444;
            border: 2px solid #999;
            font-weight: bold;
        }

        .page-item.disabled .page-link {
            color: #adb5bd;
            pointer-events: none;
            background-color: #f8f9fa;
            border: 2px solid #e9ecef;
        }

        .page-link {
            position: relative;
            display: block;
            padding: 0.75em 1em;
            margin: 0;
            line-height: 1.2;
            background-color: #ffffff;
            color: #444;
            border: 2px solid #ccc;
            border-radius: 12px;
            font-weight: bold;
            text-decoration: none;
            min-width: 44px;
            text-align: center;
            transition: all 0.2s ease-in-out;
        }

        .page-link:hover {
            z-index: 2;
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
            text-decoration: none;
        }

        .page-link:focus {
            z-index: 3;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(204, 204, 204, 0.3);
        }

        /* ストーリーのある素材セクション */
        .story-materials-section {
            background: linear-gradient(135deg, #fff8e1 0%, #ffe9c5 100%);
            padding: 3rem 2rem;
            border-radius: 1rem;
            margin: 2rem 0;
        }

        .story-materials-section h2 {
            color: #d4a574;
            font-weight: 700;
        }

        .story-materials-section .text-muted {
            color: #a68b6a !important;
        }

        .story-materials-list {
            display: flex;
            flex-direction: column;
            gap: 3rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .story-material-item {
            background: #ffffff;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .story-material-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .story-item-image-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
            padding: 2rem;
        }

        .story-item-image {
            width: 100%;
            max-width: 300px;
            aspect-ratio: 1;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .story-item-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .story-item-content {
            padding: 0 2rem 2rem 2rem;
        }

        .story-item-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #d4a574;
            margin-bottom: 1.25rem;
            text-align: center;
        }

        .story-item-text {
            font-size: 1rem;
            line-height: 2;
            color: #555;
            font-family: 'Hiragino Maru Gothic ProN', 'ヒラギノ丸ゴ ProN', 'メイリオ', Meiryo, sans-serif;
            background: #fff9f0;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border-left: 4px solid #d4a574;
        }

        @media (max-width: 768px) {
            .story-materials-section {
                padding: 2rem 1rem;
            }

            .story-materials-list {
                gap: 2rem;
            }

            .story-item-image-wrapper {
                padding: 1.5rem;
            }

            .story-item-image {
                max-width: 250px;
            }

            .story-item-content {
                padding: 0 1.5rem 1.5rem 1.5rem;
            }

            .story-item-title {
                font-size: 1.1rem;
            }

            .story-item-text {
                font-size: 0.95rem;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'category';
    include 'includes/header.php'; 
    ?>
    
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
            <p><?= $totalItems ?>個の素材があります<?php if ($totalPages > 1): ?>
                        (<?= $page ?>/<?= $totalPages ?>ページ)
                    <?php endif; ?></p>
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
                    <?php
                    // AIが指定した背景色を取得（フォールバックは従来の色）
                    $backgroundColor = $material['structured_bg_color'] ?? '#F9F5E9';
                    ?>
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
                                 loading="lazy"
                                 style="background-color: <?= h($backgroundColor) ?>;"
                        </picture>
                        
                        <div class="material-card-body">
                            <p class="material-title">
                                <?= h($material['title']) ?>
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- ページネーション -->
            <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: center; width: 100%; margin-top: 3rem;">
                <nav aria-label="ページネーション">
                    <ul class="pagination justify-content-center">
                        <!-- 前のページ -->
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="前のページ">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link" aria-label="前のページ">
                                <span aria-hidden="true">&laquo;</span>
                            </span>
                        </li>
                    <?php endif; ?>

                    <!-- ページ番号 -->
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    // 最初のページを表示
                    if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif;
                    endif;

                    // 現在のページ周辺を表示
                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <?php if ($i == $page): ?>
                                <span class="page-link"><?= $i ?></span>
                            <?php else: ?>
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endfor;

                    // 最後のページを表示
                    if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $totalPages ?>"><?= $totalPages ?></a>
                        </li>
                    <?php endif; ?>

                    <!-- 次のページ -->
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="次のページ">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link" aria-label="次のページ">
                                <span aria-hidden="true">&raquo;</span>
                            </span>
                        </li>
                    <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

            <!-- ストーリーのある素材セクション -->
            <?php if (!empty($storyMaterials)): ?>
            <section class="story-materials-section mt-5 mb-5">
                <div class="row">
                    <div class="col-12">
                        <h2 class="text-center mb-2">おはなしのある子たち</h2>
                        <p class="text-center text-muted mb-4">ちいさな物語とともに、やさしい時間をどうぞ</p>
                    </div>
                </div>
                
                <div class="story-materials-list">
                    <?php foreach ($storyMaterials as $storyMat): ?>
                    <div class="story-material-item">
                        <!-- 画像（リンク） -->
                        <a href="/<?= h($storyMat['category_slug']) ?>/<?= h($storyMat['slug']) ?>/" class="text-decoration-none">
                            <div class="story-item-image-wrapper">
                                <?php
                                $storyImageSrc = !empty($storyMat['webp_small_path']) 
                                    ? '/' . h($storyMat['webp_small_path'])
                                    : '/' . h($storyMat['image_path']);
                                $storyBgColor = !empty($storyMat['structured_bg_color']) 
                                    ? h($storyMat['structured_bg_color']) 
                                    : '#ffffff';
                                ?>
                                <div class="story-item-image" style="background-color: <?= $storyBgColor ?>;">
                                    <img src="<?= $storyImageSrc ?>" 
                                         alt="<?= h($storyMat['title']) ?>"
                                         loading="lazy"
                                         decoding="async">
                                </div>
                            </div>
                        </a>
                        
                        <!-- ストーリー（リンクなし） -->
                        <div class="story-item-content">
                            <h3 class="story-item-title"><?= h($storyMat['title']) ?></h3>
                            <div class="story-item-text">
                                <?= nl2br(h($storyMat['mini_story'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="text-center">
                <div class="mb-2">
                    <a href="/terms-of-use.php" class="text-decoration-none me-3" style="color: inherit;">利用規約</a>
                    <a href="/privacy-policy.php" class="text-decoration-none" style="color: inherit;">プライバシーポリシー</a>
                </div>
                <p class="footer-text">&copy; 2024 maruttoart. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- GDPR Cookie Banner -->
    <div id="gdpr-banner" class="gdpr-cookie-banner hidden">
        <div class="gdpr-content">
            <div class="gdpr-text">
                当サイトではサイトの利便性向上のためCookieを使用しています。詳細は
                <a href="/terms-of-use.php">利用規約</a>・
                <a href="/privacy-policy.php">プライバシーポリシー</a>
                をご確認ください。
            </div>
            <div class="gdpr-buttons">
                <button id="gdpr-accept" class="btn btn-success">同意する</button>
                <button id="gdpr-decline" class="btn btn-outline-light">拒否する</button>
            </div>
        </div>
    </div>
    
</body>
</html>
