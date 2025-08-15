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
    <meta name="description" content="<?= h($tag['name']) ?>タグの無料イラスト素材一覧。かわいい手描き水彩風のイラスト素材を商用利用OK。<?= h($tag['name']) ?>に関連する高品質なフリー素材をダウンロードできます。">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/tag/<?= h($tag['slug']) ?>/">
    <meta property="og:title" content="<?= h($tag['name']) ?> - タグ別無料イラスト素材一覧">
    <meta property="og:description" content="<?= h($tag['name']) ?>タグの無料イラスト素材。かわいい手描き水彩風で商用利用OK。">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary">
    <meta property="twitter:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/tag/<?= h($tag['slug']) ?>/">
    <meta property="twitter:title" content="<?= h($tag['name']) ?> - タグ別無料イラスト素材一覧">
    <meta property="twitter:description" content="<?= h($tag['name']) ?>タグの無料イラスト素材。かわいい手描き水彩風で商用利用OK。">
    
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
        }
        .material-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            color: inherit;
            text-decoration: none;
            border-color: #0d6efd;
        }
        .material-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background-color: #f8f9fa;
        }
        .material-title {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            font-weight: 400;
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
                        <a href="/<?= h($material['category_slug']) ?>/<?= h($material['slug']) ?>/" class="material-card card h-100">
                            <picture>
                                <!-- WebP対応 -->
                                <source srcset="/<?= h($material['webp_small_path'] ?? $material['image_path']) ?>" type="image/webp">
                                <!-- フォールバック -->
                                <img src="/<?= h($material['image_path']) ?>" class="card-img-top material-image" alt="<?= h($material['title']) ?>">
                            </picture>
                            <div class="card-body p-3">
                                <h5 class="material-title"><?= h($material['title']) ?></h5>
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

    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                </div>
                <div class="col-md-4 text-md-end">
                    <!-- gTranslate言語切り替え -->
                    <div class="gtranslate_wrapper"></div>
                </div>
            </div>
            <div class="row align-items-center">
                <div class="col-md-12">
                    <p class="text-muted mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script>window.gtranslateSettings = {"default_language":"ja","native_language_names":true,"detect_browser_language":true,"url_structure":"sub_domain","languages":["ja","en","fr","es","nl"],"wrapper_selector":".gtranslate_wrapper","alt_flags":{"en":"usa"}}</script>
    <script src="https://cdn.gtranslate.net/widgets/latest/lc.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
