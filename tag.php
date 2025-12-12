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
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8053468089362860"
     crossorigin="anonymous"></script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($tag['name']) ?> - ミニマルなフリーイラスト素材（商用利用OK）｜marutto.art</title>
    <meta name="description" content="<?= h($tag['name']) ?>タグのフリーイラスト素材一覧。ミニマルなフリーイラスト素材を商用利用OK。<?= h($tag['name']) ?>に関連する高品質なフリー素材をダウンロードできます。">

    <!-- Site Icons -->
    <link rel="icon" href="/favicon.ico">
    
    <!-- カノニカルタグ -->
    <?php
    $canonicalUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . '/tag/' . $tag['slug'] . '/';
    if ($page > 1) {
        $canonicalUrl .= '?page=' . $page;
    }
    ?>
    <link rel="canonical" href="<?= h($canonicalUrl) ?>">
    
    <!-- Alternate language tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/tag/<?= h($tag['slug']) ?>/" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/tag/<?= h($tag['slug']) ?>/" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/tag/<?= h($tag['slug']) ?>/" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/tag/<?= h($tag['slug']) ?>/" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/tag/<?= h($tag['slug']) ?>/" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/tag/<?= h($tag['slug']) ?>/" />
    
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

        /* Bootstrapクラスの代替 */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ユーティリティクラス */

        .mt-3 { margin-top: 1rem; }
        .mt-4 { margin-top: 1.5rem; }
        .mt-5 { margin-top: 3rem; }
        .py-4 { padding: 1.5rem 0; }
        .py-5 { padding: 3rem 0; }
        .mb-0 { margin-bottom: 0; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-4 { margin-bottom: 1.5rem; }
        .me-3 { margin-right: 1rem; }

        .text-center { text-align: center; }
        .text-muted { color: #6c757d; }
        .text-decoration-none { text-decoration: none; }

        /* パンくずリスト */
        .breadcrumb {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            background: transparent;
        }

        .breadcrumb-item {
            display: flex;
            align-items: center;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
            margin: 0 0.5rem;
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
        }
        .material-title {
            color: #666;
            font-weight: 300;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 0;
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
    
    <?php include __DIR__ . '/includes/analytics-script.php'; ?>
</head>
<body>
    <?php 
    $currentPage = 'tag';
    include 'includes/header.php'; 
    ?>
    
    <!-- パンくずリスト -->
    <div class="container mt-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="/">ホーム</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
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
                <a href="/" style="display: inline-block; padding: 0.5rem 1rem; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 0.375rem; text-decoration: none; color: #495057;">
                    ホームに戻る
                </a>
            </div>
        <?php else: ?>
            <!-- 素材一覧 -->
            <div class="materials-grid">
                <?php foreach ($materials as $material): ?>
                    <?php
                    // 詳細ページのURL（シンプル形式）
                    $detailUrl = "/{$material['category_slug']}/{$material['slug']}/";
                    // AIが指定した背景色を取得（フォールバックは従来の色）
                    $backgroundColor = $material['structured_bg_color'] ?? '#F9F5E9';
                    ?>
                    <a href="<?= h($detailUrl) ?>" class="material-card">
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

    <?php include 'includes/footer.php'; ?>

    <script>
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
