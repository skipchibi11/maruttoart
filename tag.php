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
    
    <!-- カノニカルタグ -->
    <?php
    $canonicalUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . '/tag/' . $tag['slug'] . '/';
    if ($page > 1) {
        $canonicalUrl .= '?page=' . $page;
    }
    ?>
    <link rel="canonical" href="<?= h($canonicalUrl) ?>">
    
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
        body {
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

        /* ヘッダー・ナビゲーション */
        .navbar {
            padding: 1rem 0;
            background-color: #fff !important;
            border-bottom: 1px solid #dee2e6;
        }

        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
            color: #333 !important;
            text-decoration: none;
        }

        .navbar-brand:hover {
            color: #555 !important;
        }

        .navbar-nav {
            display: flex;
            align-items: center;
        }

        .nav-link {
            color: #6c757d !important;
            text-decoration: none;
            padding: 0.5rem 1rem;
        }

        .nav-link:hover {
            color: #495057 !important;
        }

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
            background-color: #F9F5E9;
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

        /* ページネーション */
        .pagination {
            display: flex;
            padding-left: 0;
            list-style: none;
            border-radius: 0;
            gap: 5px;
        }

        .justify-content-center {
            justify-content: center !important;
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
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-579HN546"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    
    <header class="navbar">
        <div class="container">
            <a class="navbar-brand" href="/">maruttoart</a>
            <div class="navbar-nav">
                <a class="nav-link" href="/">戻る</a>
            </div>
        </div>
    </header>
    
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
                    <a href="/<?= h($material['category_slug']) ?>/<?= h($material['slug']) ?>/" class="material-card">
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
            <div class="text-center">
                <div class="mb-2">
                    <a href="/terms-of-use.php" class="footer-text text-decoration-none me-3">利用規約</a>
                    <a href="/privacy-policy.php" class="footer-text text-decoration-none">プライバシーポリシー</a>
                </div>
                <div>
                    <p class="footer-text mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

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
