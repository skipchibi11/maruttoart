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
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-579HN546');</script>
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
        .material-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .download-btn {
            font-size: 1.2rem;
            padding: 12px 30px;
        }
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
        
        /* ダウンロードリンクのスタイル */
        .download-link {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.95rem;
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            display: inline-block;
            transition: color 0.2s, border-color 0.2s, background-color 0.2s;
        }
        
        .download-link:hover {
            color: #495057;
            background-color: #f8f9fa;
            border-color: #adb5bd;
            text-decoration: none;
        }
        
        /* コンテンツのテキストスタイル */
        .detail-title {
            color: #6c757d;
            font-size: 1rem;
            font-weight: 400;
        }
        
        .detail-description {
            color: #6c757d;
            font-size: 1rem;
            line-height: 1.6;
        }
        
        .detail-date {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        /* 関連動画ヘッダーのスタイル */
        .video-header {
            color: #6c757d;
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
        
        /* タグのスタイル */
        .tag-item {
            display: inline-block;
            background-color: #f8f9fa;
            color: #6c757d;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-right: 0.25rem;
            margin-bottom: 0.25rem;
            text-decoration: none;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        
        .tag-item:hover {
            background-color: #e9ecef;
            color: #495057;
            text-decoration: none;
            border-color: #dee2e6;
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
        
        .art-material-item {
            background-color: #f8f9fa;
            color: #495057;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            border: 1px solid #e9ecef;
        }
        
        .art-material-color {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .tags-label {
            color: #6c757d;
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
                    <a href="/">ホーム</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="/<?= h($category['slug']) ?>/"><?= h($category['title']) ?></a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= h($material['title']) ?>
                </li>
            </ol>
        </nav>
    </div>
    
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="text-center">
                    <!-- WebPに対応したpicture要素 -->
                    <picture class="d-block">
                        <!-- デスクトップ用：300x300のWebP -->
                        <source media="(min-width: 768px)" srcset="/<?= h($material['webp_medium_path'] ?? $material['image_path']) ?>" type="image/webp">
                        <!-- モバイル用：180x180のWebP -->
                        <source media="(max-width: 767px)" srcset="/<?= h($material['webp_small_path'] ?? $material['image_path']) ?>" type="image/webp">
                        <!-- フォールバック：オリジナル画像 -->
                        <img src="/<?= h($material['image_path']) ?>" class="material-image mb-3" alt="<?= h($material['title']) ?>のイラスト">
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

                <?php if (!empty($material['youtube_url'])): ?>
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
            
            <div class="row align-items-center">
                <div class="col-md-12">
                    <p class="footer-text mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- GDPR Cookie Banner (CDN対応・セッション不使用) -->
    <div id="gdpr-banner" class="hidden">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 col-md-8">
                    <div class="gdpr-text">
                        当サイトではサイトの利便性向上のためCookieを使用しています。詳細は
                        <a href="/privacy-policy.php" class="text-white text-decoration-underline">プライバシーポリシー</a>
                        をご確認ください。
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="gdpr-buttons text-md-end">
                        <button id="gdpr-accept" class="btn btn-success btn-sm">同意する</button>
                        <button id="gdpr-decline" class="btn btn-outline-light btn-sm">拒否する</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
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
        }
        
        // 拒否処理
        function declineConsent() {
            setGdprConsent('declined');
            hideBanner();
            updateYouTubeDisplay('declined');
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
