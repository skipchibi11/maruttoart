<?php
require_once 'config.php';
require_once 'includes/gdpr-banner-new.php';
require_once 'includes/gtranslate.php';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プライバシーポリシー - maruttoart</title>
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
                        <li><a class="dropdown-item" href="http://localhost">🇯🇵 日本語</a></li>
                        <li><a class="dropdown-item" href="http://en.localhost">🇺🇸 English</a></li>
                        <li><a class="dropdown-item" href="http://es.localhost">🇪🇸 Español</a></li>
                        <li><a class="dropdown-item" href="http://fr.localhost">🇫🇷 Français</a></li>
                        <li><a class="dropdown-item" href="http://nl.localhost">🇳🇱 Nederlands</a></li>
                    </ul>
                </div>
                <a class="nav-link" href="/">ホーム</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h1 class="mb-4">プライバシーポリシー</h1>
                <p class="text-muted">最終更新日: <?= date('Y年m月d日') ?></p>

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

    <?php
    // GDPR バナーを表示
    echo renderGDPRBanner();
    
    // GTranslate機能を追加
    echo renderGTranslate();
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // GDPR 同意管理のJavaScript
        document.addEventListener('DOMContentLoaded', function() {
            console.log('GDPR script loaded on privacy policy page');
            const acceptBtn = document.getElementById('gdpr-accept');
            const declineBtn = document.getElementById('gdpr-decline');
            const banner = document.getElementById('gdpr-banner');
            
            if (acceptBtn) {
                acceptBtn.addEventListener('click', function() {
                    console.log('Accept button clicked');
                    handleGDPRConsent(true);
                });
            }
            
            if (declineBtn) {
                declineBtn.addEventListener('click', function() {
                    console.log('Decline button clicked');
                    handleGDPRConsent(false);
                });
            }
        });
        
        function handleGDPRConsent(consent) {
            console.log('handleGDPRConsent called with:', consent);
            
            fetch('./gdpr-consent.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ consent: consent })
            })
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.status === 'success') {
                    const banner = document.getElementById('gdpr-banner');
                    if (banner) {
                        console.log('Hiding banner');
                        banner.style.display = 'none';
                        // ページをリロードして最新の状態を反映
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>
