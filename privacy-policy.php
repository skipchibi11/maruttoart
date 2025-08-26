<?php
require_once 'config.php';

// プライバシーポリシーは変更頻度が低いので長期キャッシュ
setPublicCache(86400, 172800); // 24時間 / CDN 48時間
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
    <title>プライバシーポリシー - maruttoart</title>
    <link rel="icon" href="/favicon.ico">
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
        .policy-section {
            margin-bottom: 2rem;
        }
        .policy-section h3 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
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
            
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h1 class="mb-4">プライバシーポリシー</h1>
                <p class="text-muted">最終更新日: <?= date('Y-m-d') ?></p>

                <div class="policy-section">
                    <h3>1. 基本方針</h3>
                    <p>maruttoart（以下「当サイト」）は、ユーザーの個人情報およびプライバシーの保護を重要視し、以下の方針に従って個人情報を取り扱います。</p>
                </div>

                <div class="policy-section">
                    <h3>2. 収集する情報</h3>
                    <p>当サイトでは、以下の情報を収集する場合があります：</p>
                    <ul>
                        <li>Cookieおよび類似技術による情報</li>
                        <li>アクセスログ情報（IPアドレス、ブラウザ情報、アクセス日時等）</li>
                        <li>サイト利用状況に関する情報</li>
                    </ul>
                </div>

                <div class="policy-section">
                    <h3>3. 情報の利用目的</h3>
                    <p>収集した情報は、以下の目的で利用します：</p>
                    <ul>
                        <li>サービスの提供・改善</li>
                        <li>サイトの利用状況分析</li>
                        <li>翻訳機能（GTranslate）の提供</li>
                        <li>セキュリティの維持・向上</li>
                    </ul>
                </div>

                <div class="policy-section">
                    <h3>4. Cookieについて</h3>
                    <p>当サイトでは、以下の種類のCookieを使用します：</p>
                    <ul>
                        <li><strong>必須Cookie</strong>: サイトの基本機能に必要なCookie</li>
                        <li><strong>機能性Cookie</strong>: 翻訳機能などのサービス提供に使用</li>
                        <li><strong>分析Cookie</strong>: サイトの利用状況分析に使用</li>
                    </ul>
                    <p>Cookieの設定は、ブラウザの設定で管理できます。ただし、必須Cookieを無効にした場合、サイトが正常に機能しない可能性があります。</p>
                </div>

                <div class="policy-section">
                    <h3>5. 第三者サービスについて</h3>
                    <p>当サイトでは、以下の第三者サービスを利用しています：</p>
                    <ul>
                        <li><strong>YouTube</strong>: 動画コンテンツの埋め込み表示（同意時のみ）</li>
                        <li><strong>GTranslate</strong>: 自動翻訳機能の提供</li>
                        <li><strong>Bootstrap CDN</strong>: サイトデザインの提供</li>
                    </ul>
                    <p>これらのサービスは、それぞれ独自のプライバシーポリシーに従って運営されています。</p>
                    <p><strong>YouTube動画について：</strong>Cookieの使用に同意していない場合、YouTube動画は自動的に読み込まれません。同意後、動画が表示されるようになります。</p>
                </div>

                <div class="policy-section">
                    <h3>6. 個人情報の保護</h3>
                    <p>当サイトは、収集した情報について適切なセキュリティ対策を講じ、不正アクセス、紛失、破壊、改ざん、漏洩などを防止するよう努めます。</p>
                </div>

                <div class="policy-section">
                    <h3>7. 個人情報の第三者提供</h3>
                    <p>当サイトは、法令に基づく場合を除き、ユーザーの同意なく個人情報を第三者に提供することはありません。</p>
                </div>

                <div class="policy-section">
                    <h3>8. プライバシーポリシーの変更</h3>
                    <p>当サイトは、必要に応じてプライバシーポリシーを変更する場合があります。重要な変更については、サイト上で通知いたします。</p>
                </div>

                <div class="policy-section">
                    <h3>9. お問い合わせ</h3>
                    <p>プライバシーポリシーに関するお問い合わせは、下記までご連絡ください：</p>
                    <p>Email: privacy@maruttoart.com</p>
                </div>

                <div class="text-center mt-5">
                    <a href="/" class="btn btn-primary">ホームに戻る</a>
                </div>
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
        
        // 同意処理
        function acceptConsent() {
            setGdprConsent('accepted');
            hideBanner();
            enableAnalytics();
        }
        
        // 拒否処理
        function declineConsent() {
            setGdprConsent('declined');
            hideBanner();
            disableAnalytics();
        }
        
        // アナリティクス有効化（プレースホルダー）
        function enableAnalytics() {
            console.log('Analytics enabled (privacy policy page)');
            // ここに Google Analytics などの初期化コードを追加
        }
        
        // アナリティクス無効化（プレースホルダー）
        function disableAnalytics() {
            console.log('Analytics disabled (privacy policy page)');
            // ここにアナリティクス無効化のコードを追加
        }
        
        // 初期化
        function init() {
            const consent = getGdprConsent();
            
            if (consent === null) {
                // 未設定の場合はバナーを表示
                showBanner();
            } else if (consent === 'accepted') {
                // 同意済みの場合はアナリティクスを有効化
                enableAnalytics();
            } else if (consent === 'declined') {
                // 拒否済みの場合はアナリティクスを無効化
                disableAnalytics();
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
