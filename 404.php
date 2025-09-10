<?php
http_response_code(404);
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
    <title>404 - ページが見つかりません｜maruttoart</title>
    <meta name="description" content="お探しのページが見つかりませんでした。トップページからやさしいイラスト素材をお探しください。">
    <link rel="icon" href="/favicon.ico">
    
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
            line-height: 1.6;
            color: #333;
        }

        /* コンテナ */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
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

        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .navbar-brand {
            display: inline-block;
            padding-top: 0.3125rem;
            padding-bottom: 0.3125rem;
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }

        .navbar-brand:hover {
            color: #333;
            text-decoration: none;
        }

        /* ソーシャルリンク */
        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid #e0e0e0;
        }

        .social-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            text-decoration: none;
        }

        .social-link.twitter {
            background-color: #fff;
            color: #1DA1F2;
        }

        .social-link.twitter:hover {
            background-color: #1DA1F2;
            color: #fff;
            border-color: #1DA1F2;
        }

        .social-link.youtube {
            background-color: #fff;
            color: #FF0000;
        }

        .social-link.youtube:hover {
            background-color: #FF0000;
            color: #fff;
            border-color: #FF0000;
        }

        .social-icon {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        /* 404エラーセクション */
        .error-section {
            min-height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4rem 0;
        }

        .error-content {
            text-align: center;
            max-width: 600px;
        }

        .error-title {
            font-size: 8rem;
            font-weight: bold;
            color: #f0f0f0;
            margin-bottom: 1rem;
            line-height: 1;
        }

        .error-heading {
            font-size: 2rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .error-message {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .error-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 2rem;
            background-color: #ffffff;
            color: #444;
            border: 2px solid #ccc;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
        }

        .btn:hover {
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
            text-decoration: none;
        }

        .btn-primary {
            background-color: #007bff;
            color: #fff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            color: #fff;
        }

        /* フッター */
        .footer-custom {
            background-color: #fef9e7;
            margin-top: 3rem;
        }

        .footer-custom .footer-text {
            color: #1a1a1a;
            font-size: 0.9rem;
        }

        .footer-custom .footer-text:hover {
            color: #000000;
        }

        .footer-custom a.footer-text {
            text-decoration: none;
        }

        .footer-custom a.footer-text:hover {
            text-decoration: underline;
        }

        .text-center { text-align: center; }
        .text-decoration-none { text-decoration: none; }
        .mt-5 { margin-top: 3rem; }
        .py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-0 { margin-bottom: 0; }

        /* レスポンシブ */
        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1.5rem;
            }
            
            .social-links {
                gap: 10px;
            }
            
            .social-link {
                width: 35px;
                height: 35px;
            }
            
            .social-icon {
                width: 18px;
                height: 18px;
            }
            
            .error-title {
                font-size: 5rem;
            }
            
            .error-heading {
                font-size: 1.5rem;
            }
            
            .error-actions {
                flex-direction: column;
            }
        }

        /* 検索フォームのスタイル */
        .search-form {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            margin: 2rem 0;
        }

        .search-form form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            background-color: #fff;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            border-color: #007bff;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .search-input::placeholder {
            color: #6c757d;
        }

        .search-button {
            background-color: #007bff;
            color: #fff;
            border: 2px solid #007bff;
            border-radius: 12px;
            padding: 0.75em 2em;
            font-size: 1rem;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease-in-out;
            white-space: nowrap;
            cursor: pointer;
        }

        .search-button:hover {
            background-color: #0056b3;
            border-color: #0056b3;
            color: #fff;
        }

        .search-button:focus {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        /* レスポンシブ対応 */
        @media (max-width: 576px) {
            .search-form {
                padding: 1.25rem;
                margin: 1.5rem 0;
            }
            
            .search-form form {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }
            
            .search-button {
                width: 100%;
                text-align: center;
            }
        }

        /* GDPR Cookie Banner のスタイル */
        #gdprBanner {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            background-color: #212529 !important;
            color: #ffffff !important;
            padding: 1rem !important;
            z-index: 9999 !important;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3) !important;
            display: none; /* 初期状態では非表示 */
            width: 100% !important;
        }
        
        #gdprBanner.hidden,
        .gdpr-banner.hidden {
            display: none !important;
        }
        
        .gdpr-banner-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .gdpr-banner-text {
            flex: 1;
            min-width: 300px;
        }
        
        .gdpr-banner-text p {
            margin: 0 0 0.5rem 0;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .gdpr-banner-text p:last-child {
            margin-bottom: 0;
        }
        
        .gdpr-policy-link {
            color: #007bff !important;
            text-decoration: underline !important;
        }
        
        .gdpr-policy-link:hover {
            color: #0056b3 !important;
        }
        
        .gdpr-banner-buttons {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        
        .gdpr-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .gdpr-btn-accept {
            background-color: #28a745;
            color: white;
        }
        
        .gdpr-btn-accept:hover {
            background-color: #218838;
        }
        
        .gdpr-btn-decline {
            background-color: transparent;
            color: #ffffff;
            border: 1px solid #6c757d;
        }
        
        .gdpr-btn-decline:hover {
            background-color: #6c757d;
            color: #ffffff;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .gdpr-banner-content {
                flex-direction: column;
                text-align: center;
            }
            
            .gdpr-banner-text {
                min-width: unset;
            }
            
            .gdpr-banner-buttons {
                justify-content: center;
                width: 100%;
            }
            
            .gdpr-btn {
                flex: 1;
                max-width: 120px;
            }
        }
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
            padding: 0.375rem 0.75rem !important;
            font-size: 0.875rem !important;
            border-radius: 0.375rem !important;
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
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) - GDPR対応 -->
    <script>
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
            <div class="social-links">
                <a href="https://x.com/marutto_art" class="social-link twitter" target="_blank" rel="noopener noreferrer" title="X (Twitter)">
                    <svg class="social-icon" viewBox="0 0 24 24">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                </a>
                <a href="https://youtube.com/@marutto_art" class="social-link youtube" target="_blank" rel="noopener noreferrer" title="YouTube">
                    <svg class="social-icon" viewBox="0 0 24 24">
                        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                    </svg>
                </a>
            </div>
        </div>
    </nav>

    <!-- 404エラーセクション -->
    <section class="error-section">
        <div class="container">
            <div class="error-content">
                <div class="error-title">404</div>
                <h1 class="error-heading">ページが見つかりません</h1>
                <p class="error-message">
                    お探しのページは存在しないか、移動された可能性があります。<br>
                    以下の検索フォームから目的の素材をお探しいただくか、リンクからページをお探しください。
                </p>
                
                <!-- 検索フォーム -->
                <div class="search-form">
                    <form method="GET" action="/list.php">
                        <input type="text" 
                               name="search" 
                               placeholder="素材を検索（例：猫、花、食べ物など）" 
                               class="search-input"
                               autocomplete="off">
                        <button type="submit" class="search-button">検索</button>
                    </form>
                </div>
                
                <div class="error-actions">
                    <a href="/" class="btn btn-primary">トップページに戻る</a>
                    <a href="/list.php" class="btn">素材一覧を見る</a>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer-custom mt-5 py-4">
        <div class="container">
            <div class="text-center">
                <div class="mb-2">
                    <a href="/privacy-policy.php" class="footer-text text-decoration-none">プライバシーポリシー</a>
                </div>
                <div>
                    <p class="footer-text mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- GDPR Cookie Banner -->
    <div id="gdprBanner" class="gdpr-banner">
        <div class="gdpr-banner-content">
            <div class="gdpr-banner-text">
                <p><strong>クッキーの使用について</strong></p>
                <p>当サイトでは、サイトの利用状況を分析し、より良いサービスを提供するためにクッキーを使用しています。引き続きサイトをご利用いただく場合、クッキーの使用に同意いただいたものとみなします。</p>
                <p><a href="/privacy-policy.php" target="_blank" rel="noopener noreferrer" class="gdpr-policy-link">プライバシーポリシー</a>で詳細をご確認ください。</p>
            </div>
            <div class="gdpr-banner-buttons">
                <button id="gdprAccept" class="gdpr-btn gdpr-btn-accept">同意する</button>
                <button id="gdprDecline" class="gdpr-btn gdpr-btn-decline">拒否する</button>
            </div>
        </div>
    </div>

    <!-- GDPR JavaScript -->
    <script>
    (function() {
        'use strict';
        
        const GDPR_VERSION = 'v1';
        const GDPR_COOKIE_NAME = 'gdpr_consent_' + GDPR_VERSION;
        
        function getGdprConsent() {
            try {
                return localStorage.getItem(GDPR_COOKIE_NAME);
            } catch (e) {
                return null;
            }
        }
        
        function setGdprConsent(value) {
            try {
                localStorage.setItem(GDPR_COOKIE_NAME, value);
                return true;
            } catch (e) {
                return false;
            }
        }
        
        function showGdprBanner() {
            const banner = document.getElementById('gdprBanner');
            console.log('showGdprBanner called, banner element:', banner);
            if (banner) {
                banner.style.display = 'block';
                banner.style.opacity = '0';
                banner.style.transform = 'translateY(100%)';
                
                setTimeout(function() {
                    banner.style.transition = 'all 0.3s ease';
                    banner.style.opacity = '1';
                    banner.style.transform = 'translateY(0)';
                    console.log('GDPR banner should now be visible');
                }, 100);
            } else {
                console.error('GDPR banner element not found!');
            }
        }
        
        function hideGdprBanner() {
            const banner = document.getElementById('gdprBanner');
            if (banner) {
                banner.style.transition = 'all 0.3s ease';
                banner.style.opacity = '0';
                banner.style.transform = 'translateY(100%)';
                
                setTimeout(function() {
                    banner.style.display = 'none';
                }, 300);
            }
        }
        
        function loadGoogleTagManager() {
            if (window.gtmLoaded || window.gtag || window.dataLayer) {
                return; // 既にロード済み
            }
            
            // カスタムイベントを発火してheader部分のGTMも動作させる
            const event = new CustomEvent('gdpr-consent-accepted');
            window.dispatchEvent(event);
        }
        
        function handleGdprConsent(consent) {
            setGdprConsent(consent);
            hideGdprBanner();
            
            if (consent === 'accepted') {
                loadGoogleTagManager();
            }
        }
        
        // ページ読み込み時の処理
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, checking GDPR consent...');
            const consent = getGdprConsent();
            console.log('Current consent status:', consent);
            
            if (!consent) {
                // 同意状況が不明な場合はバナーを表示
                showGdprBanner();
            } else if (consent === 'accepted') {
                // 同意済みの場合はGTMを読み込み
                loadGoogleTagManager();
            }
            
            // ボタンのイベントリスナー
            const acceptBtn = document.getElementById('gdprAccept');
            const declineBtn = document.getElementById('gdprDecline');
            
            if (acceptBtn) {
                acceptBtn.addEventListener('click', function() {
                    handleGdprConsent('accepted');
                });
            }
            
            if (declineBtn) {
                declineBtn.addEventListener('click', function() {
                    handleGdprConsent('declined');
                });
            }
        });
    })();
    </script>

</body>
</html>
