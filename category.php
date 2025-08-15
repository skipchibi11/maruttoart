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
    <title><?= h($category['title']) ?> - 無料のかわいい水彩イラスト素材｜maruttoart（商用利用OK）</title>
    <meta name="description" content="<?= h($category['title']) ?>の無料イラスト素材一覧。かわいい手描き水彩風のイラスト素材を商用利用OK。高品質なフリー素材をダウンロードして、デザイン制作にお役立てください。">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #ffffff;
        }
        .header-logo {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        .material-card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .material-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .material-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
        }
        .category-header {
            color: #6c757d;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        /* パンくずリストのスタイル */
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
            font-size: 0.875rem;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
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
                <a class="nav-link" href="/">ホーム</a>
            </div>
        </div>
    </nav>
    
    <!-- パンくずリスト -->
    <div class="container mt-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="/">ホーム</a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= h($category['title']) ?>
                </li>
            </ol>
        </nav>
    </div>
    
    <div class="container mt-4">
        <div class="category-header text-center">
            <h1><?= h($category['title']) ?></h1>
            <p class="mb-0"><?= count($materials) ?>個の素材があります</p>
        </div>
        
        <?php if (empty($materials)): ?>
            <div class="text-center py-5">
                <i class="bi bi-images" style="font-size: 3rem; color: #6c757d;"></i>
                <h4 class="mt-3 text-muted">まだ素材がありません</h4>
                <p class="text-muted">このカテゴリには素材がまだ投稿されていません。</p>
                <a href="/" class="btn btn-outline-primary">ホームに戻る</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($materials as $material): ?>
                    <div class="col-6 col-md-4 col-lg-3 mb-4">
                        <div class="card material-card h-100">
                            <a href="/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/" class="text-decoration-none">
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
                                         class="card-img-top material-image" 
                                         alt="<?= h($material['title']) ?>" 
                                         loading="lazy">
                                </picture>
                                <div class="card-body p-2">
                                    <p class="card-text text-muted small text-center mb-0">
                                        <?= h($material['title']) ?>
                                    </p>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
