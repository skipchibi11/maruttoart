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
                    
                    <h4>自動的に収集される情報</h4>
                    <ul>
                        <li><strong>アクセスログ情報</strong>: IPアドレス、ブラウザ情報、アクセス日時、参照元URL等</li>
                        <li><strong>利用状況情報</strong>: ページビュー、セッション時間、クリック行動、スクロール状況等</li>
                        <li><strong>デバイス情報</strong>: 画面解像度、OS、ブラウザの種類・バージョン等</li>
                        <li><strong>地理的情報</strong>: 国、地域レベルの位置情報（IPアドレスベース）</li>
                    </ul>
                    
                    <h4>Cookieおよび類似技術による情報</h4>
                    <ul>
                        <li><strong>Google Analytics Cookie</strong>: _ga, _ga_*, _gid等（同意した場合のみ）</li>
                        <li><strong>YouTube Cookie</strong>: 動画視聴に関する設定・履歴（動画閲覧時のみ）</li>
                        <li><strong>同意管理Cookie</strong>: GDPR同意状況の記録（localStorage使用）</li>
                    </ul>
                    
                    <h4>検索エンジン経由の情報</h4>
                    <ul>
                        <li><strong>検索クエリ</strong>: Google Search Console経由の検索キーワード（集計データ）</li>
                        <li><strong>検索パフォーマンス</strong>: 表示回数、クリック数、平均掲載順位等</li>
                    </ul>
                    
                    <p><strong>注意：</strong>当サイトは直接的に個人を特定できる情報（氏名、メールアドレス等）は収集しません。</p>
                </div>

                <div class="policy-section">
                    <h3>3. 情報の利用目的</h3>
                    <p>収集した情報は、以下の目的で利用します：</p>
                    <ul>
                        <li><strong>サービスの提供・改善</strong>: ウェブサイトの基本機能、コンテンツ配信</li>
                        <li><strong>サイトの利用状況分析</strong>: Google Analytics 4を使用したアクセス解析、ユーザー行動分析</li>
                        <li><strong>検索エンジン最適化</strong>: Google Search Consoleを使用した検索パフォーマンス分析</li>
                        <li><strong>マーケティング・改善施策</strong>: Google Tag Managerを使用したデータ収集と分析</li>
                        <li><strong>セキュリティの維持・向上</strong>: 不正アクセスの検知・防止</li>
                        <li><strong>法的要件への対応</strong>: 法令に基づく情報開示等</li>
                    </ul>
                    <p><strong>データの処理根拠：</strong>当サイトは、正当な利益（サイト運営・改善）およびユーザーの同意に基づいて情報を処理します。</p>
                </div>

                <div class="policy-section">
                    <h3>4. Cookieについて</h3>
                    <p>当サイトでは、以下の種類のCookieを使用します：</p>
                    
                    <h4>必須Cookie</h4>
                    <ul>
                        <li>サイトの基本機能（ナビゲーション、セキュリティ等）に必要</li>
                        <li>これらのCookieは無効にできません</li>
                    </ul>
                    
                    <h4>分析Cookie（同意が必要）</h4>
                    <ul>
                        <li><strong>Google Analytics 4</strong>: サイトの利用状況分析、改善のためのデータ収集</li>
                        <li><strong>Google Tag Manager</strong>: 各種分析ツールの管理・配信</li>
                        <li>これらは匿名化されたデータを収集し、個人を特定することはありません</li>
                        <li>Cookie同意バナーで拒否することができます</li>
                    </ul>
                    
                    <h4>機能性Cookie（同意が必要）</h4>
                    <ul>
                        <li><strong>YouTube</strong>: 動画の再生、ユーザー設定の保存</li>
                        <li>Cookie使用に同意していない場合、これらの機能は制限されます</li>
                    </ul>
                    
                    <p><strong>Cookieの管理：</strong></p>
                    <ul>
                        <li>ブラウザの設定でCookieを管理できます</li>
                        <li>当サイトのCookie同意バナーで選択を変更できます（localStorage に保存）</li>
                        <li>必須Cookie以外は、同意しない限り設置されません</li>
                        <li>同意を撤回したい場合は、ブラウザのデータを削除するか、当サイトにお問い合わせください</li>
                    </ul>
                </div>

                <div class="policy-section">
                    <h3>5. 第三者サービスについて</h3>
                    <p>当サイトでは、以下の第三者サービスを利用しています：</p>
                    
                    <h4>Google Tag Manager（GTM）・Google Analytics 4（GA4）</h4>
                    <ul>
                        <li><strong>提供会社</strong>: Google LLC</li>
                        <li><strong>利用目的</strong>: ウェブサイトのアクセス解析、ユーザー行動の分析、サイト改善のためのデータ収集</li>
                        <li><strong>収集される情報</strong>: 
                            <ul>
                                <li>ページビュー、セッション情報</li>
                                <li>リファラー情報（どのサイトから訪問したか）</li>
                                <li>デバイス情報（ブラウザ、OS、画面解像度等）</li>
                                <li>地理的位置情報（国、地域レベル）</li>
                                <li>サイト内での行動データ（クリック、スクロール等）</li>
                            </ul>
                        </li>
                        <li><strong>データ保持期間</strong>: 14ヶ月（Google Analytics 4の設定による）</li>
                        <li><strong>制御方法</strong>: 当サイトのCookie設定で無効にできます。また、<a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener">Google Analytics オプトアウト アドオン</a>でブラウザレベルで無効化可能です</li>
                    </ul>
                    
                    <h4>Google Search Console</h4>
                    <ul>
                        <li><strong>提供会社</strong>: Google LLC</li>
                        <li><strong>利用目的</strong>: サイトの検索パフォーマンス分析、検索エンジンでの表示改善</li>
                        <li><strong>収集される情報</strong>: 検索クエリ、クリック数、表示回数、検索順位等</li>
                        <li><strong>データの性質</strong>: 個人を特定しない集計データのみを使用</li>
                    </ul>
                    
                    <h4>その他のサービス</h4>
                    <ul>
                        <li><strong>YouTube</strong>: 動画コンテンツの埋め込み表示（Cookieに同意した場合のみ）</li>
                    </ul>
                    
                    <p><strong>重要な注意事項：</strong></p>
                    <ul>
                        <li>これらのサービスは、それぞれ独自のプライバシーポリシーに従って運営されています</li>
                        <li>詳細については<a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Googleプライバシーポリシー</a>をご確認ください</li>
                        <li>Google Analytics および Google Tag Manager は、Cookieの使用に同意した場合のみ動作します</li>
                        <li>YouTube動画は、Cookieの使用に同意していない場合は自動的に読み込まれません</li>
                    </ul>
                </div>

                <div class="policy-section">
                    <h3>6. 個人情報の保護</h3>
                    <p>当サイトは、収集した情報について適切なセキュリティ対策を講じ、不正アクセス、紛失、破壊、改ざん、漏洩などを防止するよう努めます。</p>
                </div>

                <div class="policy-section">
                    <h3>7. 個人情報の第三者提供</h3>
                    <p>当サイトは、法令に基づく場合を除き、ユーザーの同意なく個人情報を第三者に提供することはありません。</p>
                    
                    <h4>Google サービスでのデータ処理</h4>
                    <p>ユーザーがCookieの使用に同意した場合、以下のGoogleサービスで情報が処理されます：</p>
                    <ul>
                        <li><strong>Google Analytics</strong>: 匿名化されたアクセス解析データ</li>
                        <li><strong>Google Tag Manager</strong>: タグ管理・配信のためのデータ</li>
                        <li><strong>Google Search Console</strong>: 検索パフォーマンスの集計データ</li>
                        <li><strong>YouTube</strong>: 動画視聴データ（動画閲覧時のみ）</li>
                    </ul>
                    
                    <p><strong>データ転送について：</strong></p>
                    <ul>
                        <li>これらのサービスでは、日本国外（主に米国）でデータが処理される場合があります</li>
                        <li>Googleは適切なデータ保護措置を講じており、<a href="https://policies.google.com/privacy/frameworks" target="_blank" rel="noopener">プライバシーフレームワーク</a>に準拠しています</li>
                        <li>データは匿名化され、個人を特定することはできません</li>
                    </ul>
                </div>

                <div class="policy-section">
                    <h3>8. プライバシーポリシーの変更</h3>
                    <p>当サイトは、必要に応じてプライバシーポリシーを変更する場合があります。重要な変更については、サイト上で通知いたします。</p>
                </div>

                <div class="policy-section">
                    <h3>9. お問い合わせ</h3>
                    <p>プライバシーポリシーに関するお問い合わせは、下記までご連絡ください：</p>
                    <p>Email: contact@maruttoart.art</p>
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
