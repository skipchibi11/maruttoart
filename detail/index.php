<?php
require_once '../config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$slug = $_GET['slug'] ?? '';
$category_slug = $_GET['category_slug'] ?? '';

if (empty($slug) || empty($category_slug)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$pdo = getDB();

// カテゴリ情報を取得
$categoryStmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
$categoryStmt->execute([$category_slug]);
$category = $categoryStmt->fetch();

if (!$category) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// 素材情報を取得（カテゴリも確認）
$stmt = $pdo->prepare("SELECT * FROM materials WHERE slug = ? AND category_id = ?");
$stmt->execute([$slug, $category['id']]);
$material = $stmt->fetch();

if (!$material) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// 素材に関連付けられたタグを取得
$materialTags = getMaterialTags($material['id'], $pdo);

// 素材に関連付けられた画材を取得
$stmt = $pdo->prepare("
    SELECT am.* 
    FROM art_materials am
    INNER JOIN material_art_materials mam ON am.id = mam.art_material_id
    WHERE mam.material_id = ? AND am.is_active = 1
    ORDER BY am.sort_order, am.name
");
$stmt->execute([$material['id']]);
$materialArtMaterials = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google Tag Manager - GDPR対応 -->
    <script>
    // GDPR同意状況をチェックしてGTMを条件付き読み込み
    (function() {
        function getGdprConsent() {
            try {
                return localStorage.getItem('gdpr_consent_v1');
            } catch (e) {
                return null;
            }
        }
        
        function loadGTM() {
            if (window.gtmLoaded) return; // 重複読み込み防止
            window.gtmLoaded = true;
            
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','GTM-579HN546');
            
            console.log('GTM loaded after GDPR consent');
        }
        
        // 同意状況を確認
        const consent = getGdprConsent();
        if (consent === 'accepted') {
            // 既に同意済みの場合は即座に読み込み
            loadGTM();
        } else {
            // 同意していない場合は読み込まない
            console.log('GTM not loaded - GDPR consent required');
        }
        
        // GDPR同意イベントを監視（将来の同意に対応）
        window.addEventListener('gdpr-consent-accepted', loadGTM);
    })();
    </script>
    <!-- End Google Tag Manager -->
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($material['title']) ?>  - 手描きイラスト素材（無料・商用OK・水彩・パステル）｜maruttoart</title>
    <meta name="description" content="<?= h($material['title']) ?>の手描きイラスト素材（無料・商用OK・水彩・パステル）。<?= h($category['title']) ?>カテゴリの高品質なフリー素材をお楽しみください。">
    
    <!-- Site Icons -->
    <link rel="icon" href="/favicon.ico">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/">
    <meta property="og:title" content="<?= h($material['title']) ?> - 手描きイラスト素材（無料・商用OK・水彩・パステル）">
    <meta property="og:description" content="<?= h($material['title']) ?>の手描きイラスト素材（無料・商用OK・水彩・パステル）。">
    <meta property="og:image" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/<?= h($material['image_path']) ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/">
    <meta property="twitter:title" content="<?= h($material['title']) ?> - 手描きイラスト素材（無料・商用OK・水彩・パステル）">
    <meta property="twitter:description" content="<?= h($material['title']) ?>の手描きイラスト素材（無料・商用OK・水彩・パステル）。">
    <meta property="twitter:image" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/<?= h($material['image_path']) ?>">
    
    <!-- JSON-LD structured data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Product",
        "name": "<?= addslashes(h($material['title'])) ?>",
        "image": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/<?= h($material['webp_medium_path'] ?? $material['image_path']) ?>",
        "description": "<?= addslashes(h($material['description'] ?? $material['title'] . 'の無料手描きイラスト（商用OK・水彩/パステル）。')) ?>",
        "sku": "<?= h($material['slug']) ?>-<?= date('Ymd', strtotime($material['created_at'])) ?>",
        "brand": {
            "@type": "Organization",
            "name": "maruttoart"
        },
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "JPY",
            "availability": "https://schema.org/InStock",
            "url": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/",
            "seller": {
                "@type": "Organization",
                "name": "maruttoart"
            }
        },
        "license": "https://creativecommons.org/publicdomain/zero/1.0/",
        "category": "<?= addslashes(h($category['name'])) ?>",
        "keywords": "<?= addslashes(h($material['search_keywords'] ?? '')) ?>, 無料イラスト, 手描き, 水彩, パステル, 商用利用OK"
    }
    </script>
    
    <style>
        /* リセットCSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            line-height: 1.5;
            color: #222;
        }

        /* コンテナシステム */
        .container {
            width: 100%;
            max-width: 1140px;
            margin: 0 auto;
            padding-left: 15px;
            padding-right: 15px;
        }

        /* グリッドシステム */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-left: -15px;
            margin-right: -15px;
        }

        .col-lg-6 {
            position: relative;
            width: 100%;
            padding-left: 15px;
            padding-right: 15px;
            flex: 0 0 100%;
            max-width: 100%;
        }

        @media (min-width: 992px) {
            .col-lg-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }

        /* ナビゲーション */
        .navbar {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
            background-color: #ffffff;
            border-bottom: 1px solid rgba(0,0,0,.125);
        }

        .navbar-brand {
            display: inline-block;
            padding-top: 0.3125rem;
            padding-bottom: 0.3125rem;
            margin-right: 1rem;
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }

        .navbar-brand:hover {
            color: #333;
            text-decoration: none;
        }

        /* カードコンポーネント */
        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.25rem;
            margin-bottom: 1.5rem;
        }

        .card-body {
            flex: 1 1 auto;
            padding: 1.25rem;
        }

        .card-header {
            padding: 0.75rem 1.25rem;
            margin-bottom: 0;
            background-color: rgba(0,0,0,.03);
            border-bottom: 1px solid rgba(0,0,0,.125);
            border-top-left-radius: calc(0.25rem - 1px);
            border-top-right-radius: calc(0.25rem - 1px);
        }

        /* ユーティリティクラス */
        .text-center { text-align: center !important; }
        .text-muted { color: #6c757d !important; }
        .mb-0 { margin-bottom: 0 !important; }
        .mb-2 { margin-bottom: 0.5rem !important; }
        .mb-3 { margin-bottom: 1rem !important; }
        .mb-4 { margin-bottom: 1.5rem !important; }
        .mt-3 { margin-top: 1rem !important; }
        .mt-4 { margin-top: 1.5rem !important; }
        .mt-5 { margin-top: 3rem !important; }
        .py-4 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
        .bg-light { background-color: #f8f9fa !important; }
        .d-inline-flex { display: inline-flex !important; }
        .align-items-center { align-items: center !important; }
        .me-1 { margin-right: 0.25rem !important; }
        .me-2 { margin-right: 0.5rem !important; }

        /* 画像のスタイル */
        .material-image {
            max-width: 100%;
            width: 100%;
            height: auto;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            background-color: #f8f9fa;
        }
        
        /* YouTube動画コンテナ */
        .youtube-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%;
        }
        
        .youtube-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        /* タグのスタイル */
        .tags-section {
            margin-bottom: 1rem;
        }
        
        .tags-label {
            color: #222;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .tag-item {
            display: inline-block;
            background-color: #e9ecef;
            color: #222 !important;
            padding: 0.25rem 0.5rem;
            margin: 0.125rem;
            border-radius: 0.25rem;
            text-decoration: none;
            font-size: 0.875rem;
            transition: background-color 0.2s ease;
        }
        
        .tag-item:hover {
            background-color: #dee2e6;
            color: #222 !important;
            text-decoration: none;
        }
        
        /* 画材のスタイル */
        .art-materials-section {
            margin-bottom: 1rem;
        }
        
        .art-material-item {
            background-color: #f8f9fa;
            color: #222 !important;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        
        .art-material-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        /* ボタンのスタイル */
        .btn {
            display: inline-block;
            font-weight: 400;
            color: #212529;
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            text-decoration: none;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
        }

        .btn:hover {
            color: #212529;
            text-decoration: none;
        }

        .btn-success {
            color: #fff;
            background-color: #198754;
            border-color: #198754;
        }

        .btn-success:hover {
            color: #fff;
            background-color: #157347;
            border-color: #146c43;
        }

        .btn-outline-light {
            color: #f8f9fa;
            border-color: #f8f9fa;
        }

        .btn-outline-light:hover {
            color: #000;
            background-color: #f8f9fa;
            border-color: #f8f9fa;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
        
        /* フッターのスタイル */
        .footer-custom {
            background-color: #fef9e7 !important;
            color: #222;
        }

        .footer-custom .footer-text {
            color: #222 !important;
        }

        .footer-custom .footer-text:hover {
            color: #0d6efd !important;
            text-decoration: underline !important;
        }

        /* GDPR Cookie Banner のスタイル */
        #gdpr-banner {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            background-color: #212529 !important;
            color: #ffffff !important;
            padding: 1rem !important;
            z-index: 1050 !important;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3) !important;
        }
        
        #gdpr-banner.hidden,
        .gdpr-cookie-banner.hidden {
            display: none !important;
        }
        
        .gdpr-text {
            font-size: 0.9rem !important;
            line-height: 1.4 !important;
            color: #ffffff !important;
        }
        
        .gdpr-text a {
            color: #ffffff !important;
            text-decoration: underline !important;
        }
        
        .gdpr-text a:hover {
            color: #e9ecef !important;
        }
        
        .gdpr-buttons {
            margin-top: 1rem !important;
            display: flex !important;
            gap: 0.5rem !important;
            flex-wrap: wrap !important;
        }
        
        .gdpr-buttons .btn {
            flex: 0 0 auto !important;
            white-space: nowrap !important;
        }
        
        /* GDPR専用のボタンスタイル（より強力な優先度） */
        #gdpr-banner .btn-outline-light {
            color: #ffffff !important;
            border-color: #ffffff !important;
            background-color: transparent !important;
            border-width: 1px !important;
            border-style: solid !important;
        }

        #gdpr-banner .btn-outline-light:hover {
            color: #212529 !important;
            background-color: #ffffff !important;
            border-color: #ffffff !important;
        }

        #gdpr-banner .btn-success {
            color: #000000 !important;
            background-color: #ffffff !important;
            border-color: #ffffff !important;
        }

        #gdpr-banner .btn-success:hover {
            color: #000000 !important;
            background-color: #f8f9fa !important;
            border-color: #f8f9fa !important;
        }
        
        @media (min-width: 768px) {
            .gdpr-buttons {
                margin-top: 0 !important;
                justify-content: flex-end !important;
            }
        }
        
        @media (max-width: 767px) {
            .gdpr-buttons {
                justify-content: center !important;
            }
        }
        
        /* レスポンシブ調整 */
        @media (max-width: 768px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            .navbar-brand {
                font-size: 1.5rem;
            }
            /* モバイル向け画像サイズ調整 */
            .material-image {
                max-width: 250px;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            .card-body {
                padding: 1rem;
            }
            /* 小型スマホ向け画像サイズ調整 */
            .material-image {
                max-width: 200px;
            }
        }
        
        /* ダウンロードリンクのスタイル */
        .download-link {
            color: #222;
            text-decoration: none;
            font-size: 0.95rem;
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            display: inline-block;
            transition: color 0.2s, border-color 0.2s, background-color 0.2s;
        }
        
        .download-link:hover {
            color: #222;
            background-color: #f8f9fa;
            border-color: #adb5bd;
            text-decoration: none;
        }
        
        /* コンテンツのテキストスタイル */
        .detail-title {
            color: #222;
            font-size: 1rem;
            font-weight: 400;
        }
        
        .detail-description {
            color: #222;
            font-size: 1rem;
            line-height: 1.6;
        }
        
        .detail-date {
            color: #222;
            font-size: 0.875rem;
        }
        
        /* 関連動画ヘッダーのスタイル */
        .video-header {
            color: #222;
            font-size: 1rem;
            font-weight: 400;
        }
        
        /* GDPR Cookie Banner のスタイル */
        #gdpr-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #212529;
            color: #ffffff;
            padding: 1rem;
            z-index: 1050;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
        }
        
        #gdpr-banner.hidden {
            display: none;
        }
        
        .gdpr-text {
            font-size: 0.9rem;
            line-height: 1.4;
            color: #ffffff;
        }
        
        .gdpr-buttons {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .gdpr-buttons .btn {
            flex: 0 0 auto;
            white-space: nowrap;
        }
        
        /* GDPR専用のボタンスタイル */
        #gdpr-banner .btn-outline-light {
            color: #ffffff;
            border-color: #ffffff;
            background-color: transparent;
        }

        #gdpr-banner .btn-outline-light:hover {
            color: #212529;
            background-color: #ffffff;
            border-color: #ffffff;
        }

        #gdpr-banner .btn-success {
            color: #000000;
            background-color: #ffffff;
            border-color: #ffffff;
        }

        #gdpr-banner .btn-success:hover {
            color: #000000;
            background-color: #f8f9fa;
            border-color: #f8f9fa;
        }
        
        @media (min-width: 768px) {
            .gdpr-buttons {
                margin-top: 0;
                justify-content: flex-end;
            }
        }
        
        /* YouTube blocked message */
        .youtube-blocked {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 2rem;
            text-align: center;
            border-radius: 8px;
        }
        
        .tags-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f0f0f0;
        }
        
        .art-materials-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f0f0f0;
        }
        
        .art-material-color {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .tags-label {
            color: #222;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
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
            color: #222;
        }
        
        .breadcrumb-item a {
            color: #222;
            text-decoration: none;
        }
        
        .breadcrumb-item a:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
        
        .breadcrumb-item.active {
            color: #222;
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
    <!-- Google Tag Manager (noscript) - GDPR対応 -->
    <script>
    // グローバルGDPR同意チェック関数
    window.getGdprConsent = function() {
        try {
            return localStorage.getItem('gdpr_consent_v1');
        } catch (e) {
            return null;
        }
    };
    
    // GDPR同意状況をチェックしてnoscript GTMを条件付き表示
    (function() {
        function getGdprConsent() {
            try {
                return localStorage.getItem('gdpr_consent_v1');
            } catch (e) {
                return null;
            }
        }
        
        const consent = getGdprConsent();
        if (consent === 'accepted') {
            // 同意済みの場合はnoscript GTMを挿入
            document.write('<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-579HN546" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>');
        }
    })();
    </script>
    <!-- End Google Tag Manager (noscript) -->
    
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="/">maruttoart</a>
        </div>
    </nav>
    
    <!-- パンくずリスト -->
    <div class="container mt-3">
        <nav aria-label="breadcrumb">
            <ol style="list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap;">
                <li style="margin-right: 0.5rem;">
                    <a href="/" style="color: #222; text-decoration: none;">ホーム</a>
                    <span style="margin-left: 0.5rem; color: #222;"> &gt; </span>
                </li>
                <li style="margin-right: 0.5rem;">
                    <a href="/<?= h($category['slug']) ?>/" style="color: #222; text-decoration: none;"><?= h($category['title']) ?></a>
                    <span style="margin-left: 0.5rem; color: #222;"> &gt; </span>
                </li>
                <li style="color: #222;">
                    <?= h($material['title']) ?>
                </li>
            </ol>
        </nav>
    </div>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-6" style="margin: 0 auto;">
                <div class="text-center">
                    <!-- WebPに対応したpicture要素 -->
                    <picture style="display: block; max-width: 400px; margin: 0 auto;">
                        <!-- デスクトップ用：300x300のWebP -->
                        <source media="(min-width: 768px)" srcset="/<?= h($material['webp_medium_path'] ?? $material['image_path']) ?>" type="image/webp">
                        <!-- モバイル用：180x180のWebP -->
                        <source media="(max-width: 767px)" srcset="/<?= h($material['webp_small_path'] ?? $material['image_path']) ?>" type="image/webp">
                        <!-- フォールバック：オリジナル画像 -->
                        <img src="/<?= h($material['image_path']) ?>" 
                             class="material-image mb-3" 
                             alt="<?= h($material['title']) ?>のイラスト"
                             width="300"
                             height="300"
                             loading="eager"
                             decoding="async"
                             fetchpriority="high">
                    </picture>
                    
                    <!-- ダウンロードリンクを画像の直下に配置 -->
                    <div class="mb-4">
                        <a href="/<?= h($material['image_path']) ?>" download class="download-link">
                            <?= h($material['title']) ?>をダウンロード
                        </a>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body text-center">
                        <h1 class="detail-title mb-3"><?= h($material['title']) ?></h1>
                        
                        <?php if (!empty($material['description'])): ?>
                        <p class="detail-description mb-3"><?= nl2br(h($material['description'])) ?></p>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <small class="detail-date">投稿日：<?= date('Y-m-d', strtotime($material['upload_date'])) ?></small>
                        </div>
                        
                        <?php if (!empty($materialTags)): ?>
                        <div class="tags-section">
                            <div class="tags-label">タグ:</div>
                            <div>
                                <?php foreach ($materialTags as $tag): ?>
                                    <a href="/tag/<?= h($tag['slug']) ?>/" class="tag-item">
                                        <?= h($tag['name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($materialArtMaterials)): ?>
                        <div class="art-materials-section">
                            <div class="tags-label">使用画材:</div>
                            <div>
                                <?php foreach ($materialArtMaterials as $artMaterial): ?>
                                    <span class="art-material-item d-inline-flex align-items-center me-2 mb-1">
                                        <?php if ($artMaterial['color_code']): ?>
                                            <span class="art-material-color me-1" style="background-color: <?= h($artMaterial['color_code']) ?>"></span>
                                        <?php endif; ?>
                                        <?= h($artMaterial['name']) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ツイートボタン（カード外に配置） -->
                <div class="text-center mt-3">
                    <a href="https://twitter.com/share?ref_src=twsrc%5Etfw" class="twitter-share-button" data-show-count="false">Tweet</a>
                    <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
                </div>

                <?php 
                // 動画が公開されているかチェック
                $showVideo = !empty($material['youtube_url']);
                if (!empty($material['video_publish_date'])) {
                    $publishDateTime = new DateTime($material['video_publish_date']);
                    $now = new DateTime();
                    $showVideo = $showVideo && ($now >= $publishDateTime);
                }
                // video_publish_dateが空の場合は、youtube_urlがあれば表示
                ?>
                <?php if ($showVideo): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0 text-center video-header">関連動画</h5>
                    </div>
                    <div class="card-body">
                        <div id="youtube-content" class="youtube-container">
                            <?php
                            $youtube_url = $material['youtube_url'];
                            // YouTube URLをembed形式に変換
                            if (strpos($youtube_url, 'youtube.com/watch?v=') !== false) {
                                $video_id = substr($youtube_url, strpos($youtube_url, 'v=') + 2);
                                $embed_url = "https://www.youtube.com/embed/{$video_id}";
                            } else if (strpos($youtube_url, 'youtu.be/') !== false) {
                                $video_id = substr($youtube_url, strrpos($youtube_url, '/') + 1);
                                $embed_url = "https://www.youtube.com/embed/{$video_id}";
                            } else {
                                $embed_url = $youtube_url;
                            }
                            ?>
                            <iframe id="youtube-iframe" src="<?= h($embed_url) ?>" frameborder="0" allowfullscreen></iframe>
                        </div>
                        <div id="youtube-blocked" class="youtube-blocked" style="display: none;">
                            <div class="text-center">
                                <i class="bi bi-play-circle" style="font-size: 3rem; color: #6c757d;"></i>
                                <h5 class="mt-3">動画の表示にはCookieの同意が必要です</h5>
                                <p class="text-muted">
                                    YouTubeの動画を表示するには、Cookieの使用に同意していただく必要があります。<br>
                                    <a href="/" class="text-decoration-none">トップページ</a>で同意いただくと動画をご覧いただけます。
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer-custom mt-5 py-4">
        <div class="container">
            <div class="text-center">
                <p class="footer-text mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- GDPR Cookie Banner (CDN対応・セッション不使用) -->
    <div id="gdpr-banner" class="hidden">
        <div class="container">
            <div style="display: flex; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <div class="gdpr-text">
                        当サイトではサイトの利便性向上のためCookieを使用しています。詳細は
                        <a href="/privacy-policy.php" style="color: #ffffff; text-decoration: underline;">プライバシーポリシー</a>
                        をご確認ください。
                    </div>
                </div>
                <div style="margin-left: auto;">
                    <div class="gdpr-buttons">
                        <button id="gdpr-accept" class="btn btn-success btn-sm">同意する</button>
                        <button id="gdpr-decline" class="btn btn-outline-light btn-sm">拒否する</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- GDPR Cookie Consent Script (CDN対応・localStorage使用) -->
    <script>
    // GDPR Cookie Consent (セッション・Cookie不使用版)
    (function() {
        const GDPR_KEY = 'gdpr_consent_v1';
        const banner = document.getElementById('gdpr-banner');
        const acceptBtn = document.getElementById('gdpr-accept');
        const declineBtn = document.getElementById('gdpr-decline');
        const youtubeContent = document.getElementById('youtube-content');
        const youtubeBlocked = document.getElementById('youtube-blocked');
        const youtubeIframe = document.getElementById('youtube-iframe');
        
        // localStorage から同意状況をチェック
        function getGdprConsent() {
            try {
                return localStorage.getItem(GDPR_KEY);
            } catch (e) {
                return null; // localStorage が使用できない場合
            }
        }
        
        // 同意状況を保存
        function setGdprConsent(value) {
            try {
                localStorage.setItem(GDPR_KEY, value);
                return true;
            } catch (e) {
                return false; // localStorage が使用できない場合
            }
        }
        
        // バナーを表示
        function showBanner() {
            if (banner) {
                banner.classList.remove('hidden');
            }
        }
        
        // バナーを非表示
        function hideBanner() {
            if (banner) {
                banner.classList.add('hidden');
            }
        }
        
        // YouTube表示制御
        function updateYouTubeDisplay(consent) {
            if (!youtubeContent || !youtubeBlocked) return;
            
            if (consent === 'accepted') {
                youtubeContent.style.display = 'block';
                youtubeBlocked.style.display = 'none';
                enableAnalytics();
            } else if (consent === 'declined') {
                youtubeContent.style.display = 'none';
                youtubeBlocked.style.display = 'block';
                disableAnalytics();
            } else {
                // 未設定の場合は非表示
                youtubeContent.style.display = 'none';
                youtubeBlocked.style.display = 'block';
            }
        }
        
        // 同意処理
        function acceptConsent() {
            setGdprConsent('accepted');
            hideBanner();
            updateYouTubeDisplay('accepted');
            
            // GTM読み込みイベントを発火
            const event = new CustomEvent('gdpr-consent-accepted');
            window.dispatchEvent(event);
            console.log('gdpr-consent-accepted event dispatched');
        }
        
        // 拒否処理
        function declineConsent() {
            setGdprConsent('declined');
            hideBanner();
            updateYouTubeDisplay('declined');
            
            // 拒否イベントを発火
            const event = new CustomEvent('gdpr-consent-declined');
            window.dispatchEvent(event);
        }
        
        // アナリティクス有効化（プレースホルダー）
        function enableAnalytics() {
            console.log('Analytics enabled (detail page)');
            // ここに Google Analytics などの初期化コードを追加
        }
        
        // アナリティクス無効化（プレースホルダー）
        function disableAnalytics() {
            console.log('Analytics disabled (detail page)');
            // ここにアナリティクス無効化のコードを追加する
        }
        
        // 初期化
        function init() {
            const consent = getGdprConsent();
            
            if (consent === null) {
                // 未設定の場合はバナーを表示
                showBanner();
                updateYouTubeDisplay(null);
            } else if (consent === 'accepted') {
                // 同意済みの場合はアナリティクスを有効化
                updateYouTubeDisplay('accepted');
            } else if (consent === 'declined') {
                // 拒否済みの場合はアナリティクスを無効化
                updateYouTubeDisplay('declined');
            }
        }
        
        // イベントリスナーを設定
        if (acceptBtn) {
            acceptBtn.addEventListener('click', acceptConsent);
        }
        
        if (declineBtn) {
            declineBtn.addEventListener('click', declineConsent);
        }
        
        // DOMContentLoaded で初期化
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>
</body>
</html>
