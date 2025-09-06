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

        .material-card:focus {
            outline: none;
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
        
        .gdpr-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .gdpr-text {
            font-size: 0.9rem !important;
            line-height: 1.4 !important;
            color: #ffffff !important;
            flex: 1;
            min-width: 280px;
        }
        
        .gdpr-text a {
            color: #ffffff !important;
            text-decoration: underline !important;
        }
        
        .gdpr-text a:hover {
            color: #e9ecef !important;
        }
        
        .gdpr-buttons {
            display: flex !important;
            gap: 0.5rem !important;
            flex-wrap: wrap !important;
        }
        
        .gdpr-buttons .btn {
            flex: 0 0 auto !important;
            white-space: nowrap !important;
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid;
            cursor: pointer;
            font-size: 0.875rem;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.2s ease;
        }
        
        /* GDPR専用のボタンスタイル（より強力な優先度） */
        #gdpr-banner .btn-outline-light {
            color: #ffffff !important;
            border-color: #ffffff !important;
            background-color: transparent !important;
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

    <!-- GDPR Cookie Banner -->
    <div id="gdpr-banner" class="gdpr-cookie-banner hidden">
        <div class="gdpr-content">
            <div class="gdpr-text">
                当サイトではサイトの利便性向上のためCookieを使用しています。詳細は
                <a href="/privacy-policy.php">プライバシーポリシー</a>
                をご確認ください。
            </div>
            <div class="gdpr-buttons">
                <button id="gdpr-accept" class="btn btn-success">同意する</button>
                <button id="gdpr-decline" class="btn btn-outline-light">拒否する</button>
            </div>
        </div>
    </div>
    
    <script>
    // グローバルGDPR同意チェック関数
    window.getGdprConsent = function() {
        try {
            return localStorage.getItem('gdpr_consent_v1');
        } catch (e) {
            return null;
        }
    };

    // GDPR Cookie Consent
    (function() {
        const GDPR_KEY = 'gdpr_consent_v1';
        let isInitialized = false;
        
        // 初期化関数
        function initGDPR() {
            if (isInitialized) return;
            isInitialized = true;
            
            const banner = document.getElementById('gdpr-banner');
            const acceptBtn = document.getElementById('gdpr-accept');
            const declineBtn = document.getElementById('gdpr-decline');
            
            if (!banner || !acceptBtn || !declineBtn) {
                console.error('GDPR elements not found');
                return;
            }
            
            // localStorage から同意状況をチェック
            function getGdprConsent() {
                try {
                    return localStorage.getItem(GDPR_KEY);
                } catch (e) {
                    console.warn('localStorage not available:', e);
                    return null;
                }
            }
            
            // 同意状況を保存
            function setGdprConsent(value) {
                try {
                    localStorage.setItem(GDPR_KEY, value);
                    return true;
                } catch (e) {
                    console.warn('localStorage save failed:', e);
                    return false;
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
            
            // 同意処理
            function acceptConsent() {
                const success = setGdprConsent('accepted');
                hideBanner();
                
                // GTM読み込みイベントを発火
                const event = new CustomEvent('gdpr-consent-accepted');
                window.dispatchEvent(event);
                console.log('gdpr-consent-accepted event dispatched');
            }
            
            // 拒否処理
            function declineConsent() {
                setGdprConsent('declined');
                hideBanner();
                
                // 拒否イベントを発火
                const event = new CustomEvent('gdpr-consent-declined');
                window.dispatchEvent(event);
            }
            
            // イベントリスナーを設定
            acceptBtn.addEventListener('click', acceptConsent);
            declineBtn.addEventListener('click', declineConsent);
            
            // 同意状況をチェックして初期化
            const consent = getGdprConsent();
            
            if (consent === null) {
                // 未設定の場合はバナーを表示
                showBanner();
            } else {
                // 既に設定済みの場合はバナーを非表示
                hideBanner();
            }
        }
        
        // 複数の初期化方法を試行
        function tryInit() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initGDPR);
            } else {
                // DOMが既に読み込まれている場合は即座に実行
                setTimeout(initGDPR, 0);
            }
            
            // フォールバック: window.onloadでも試行
            window.addEventListener('load', function() {
                if (!isInitialized) {
                    initGDPR();
                }
            });
        }
        
        tryInit();
    })();

    </script>
</body>
</html>
