<?php
require_once '../config.php';
require_once '../includes/gtranslate.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM materials WHERE slug = ?");
$stmt->execute([$slug]);
$material = $stmt->fetch();

if (!$material) {
    header('HTTP/1.0 404 Not Found');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($material['title']) ?>  - 無料のかわいい水彩イラスト素材｜maruttoart（商用利用OK）</title>
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
        
        /* GDPR Cookie Banner のスタイル */
        #gdpr-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #343a40;
            color: white;
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
        }
        
        .gdpr-buttons {
            margin-top: 1rem;
        }
        
        .gdpr-buttons .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        @media (min-width: 768px) {
            .gdpr-buttons {
                margin-top: 0;
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand header-logo" href="/">maruttoart</a>
            <div class="navbar-nav ms-auto d-flex align-items-center">
                <!-- 言語切り替えメニュー -->
                <div class="dropdown me-3">
                    <button class="btn btn-outline-secondary dropdown-toggle btn-sm" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-globe"></i> 言語
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                        <li><a class="dropdown-item" href="https://marutto.art">🇯🇵 日本語</a></li>
                        <li><a class="dropdown-item" href="https://en.marutto.art">🇺🇸 English</a></li>
                        <li><a class="dropdown-item" href="https://es.marutto.art">🇪🇸 Español</a></li>
                        <li><a class="dropdown-item" href="https://fr.marutto.art">🇫🇷 Français</a></li>
                        <li><a class="dropdown-item" href="https://nl.marutto.art">🇳🇱 Nederlands</a></li>
                    </ul>
                </div>
                <a class="nav-link" href="/">戻る</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="/<?= h($material['image_path']) ?>" class="material-image mb-4" alt="<?= h($material['title']) ?>">
                        <a href="/<?= h($material['image_path']) ?>" download class="btn btn-success download-btn">
                            <i class="bi bi-download"></i> ダウンロード
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h1 class="card-title"><?= h($material['title']) ?></h1>
                        <p class="card-text"><?= nl2br(h($material['description'])) ?></p>
                        
                        <?php if (!empty($material['search_keywords_jp'])): ?>
                        <div class="mb-3">
                            <strong>キーワード：</strong>
                            <span class="text-muted"><?= h($material['search_keywords_jp']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <small class="text-muted">投稿日：<?= date('Y年m月d日', strtotime($material['upload_date'])) ?></small>
                        </div>
                    </div>
                </div>

                <?php if (!empty($material['youtube_url'])): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">関連動画</h5>
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
                            <i class="bi bi-play-circle" style="font-size: 3rem; color: #6c757d;"></i>
                            <h5 class="mt-3">動画の表示にはCookieの同意が必要です</h5>
                            <p class="text-muted">
                                YouTubeの動画を表示するには、Cookieの使用に同意していただく必要があります。<br>
                                <a href="/" class="text-decoration-none">トップページ</a>で同意いただくと動画をご覧いただけます。
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="text-muted mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- GDPR Cookie Banner (CDN対応・セッション不使用) -->
    <div id="gdpr-banner" class="hidden">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="gdpr-text">
                        当サイトではサイトの利便性向上のためCookieを使用しています。詳細は
                        <a href="/privacy-policy.php" class="text-white text-decoration-underline">プライバシーポリシー</a>
                        をご確認ください。
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="gdpr-buttons text-md-end">
                        <button id="gdpr-accept" class="btn btn-success btn-sm">同意する</button>
                        <button id="gdpr-decline" class="btn btn-outline-light btn-sm">拒否する</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    // GTranslate機能を追加
    echo renderGTranslate();
    ?>

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
