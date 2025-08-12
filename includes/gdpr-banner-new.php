<?php

function renderGDPRBanner() {
    // 既に同意済みの場合は表示しない
    if (hasGDPRConsent()) {
        return '';
    }
    
    // バナーを表示する必要がない場合
    if (!shouldShowGDPRBanner()) {
        return '';
    }

    ob_start();
    ?>
    <div id="gdpr-banner" class="gdpr-banner" style="position: fixed; bottom: 0; left: 0; right: 0; background: rgba(255, 255, 255, 0.98); color: #333; z-index: 9999; padding: 20px; display: block; border-top: 1px solid #dee2e6;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-2" data-translate="gdpr-title">Cookieの使用について</h5>
                    <p class="mb-2" data-translate="gdpr-message">
                        このサイトでは、より良いユーザーエクスペリエンスを提供するためにCookieを使用しています。
                        翻訳機能やサイトの最適化のためにデータを収集・処理しています。
                        詳細については<a href="/privacy-policy.php" style="color: #0d6efd;" data-translate="privacy-policy-link">プライバシーポリシー</a>をご確認ください。
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button id="gdpr-accept" class="btn btn-success me-2" data-translate="gdpr-accept">同意する</button>
                    <button id="gdpr-decline" class="btn btn-outline-secondary" data-translate="gdpr-decline">拒否する</button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .gdpr-banner {
            box-shadow: 0 -2px 15px rgba(0, 0, 0, 0.1);
            border-top: 3px solid #0d6efd;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
        }
        
        .gdpr-banner h5 {
            color: #0d6efd;
            font-weight: bold;
        }
        
        .gdpr-banner p {
            margin-bottom: 0;
            font-size: 0.9rem;
            line-height: 1.4;
            color: #6c757d;
        }
        
        .gdpr-banner a {
            text-decoration: underline;
            font-weight: 500;
        }
        
        .gdpr-banner a:hover {
            color: #0b5ed7 !important;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .gdpr-banner {
                padding: 15px;
            }
            
            .gdpr-banner .col-md-4 {
                margin-top: 10px;
            }
            
            .gdpr-banner .btn {
                width: 48%;
                font-size: 0.9rem;
            }
            
            .gdpr-banner .btn.me-2 {
                margin-right: 4% !important;
            }
        }
        
        /* ページコンテンツとの干渉を防ぐ */
        body.gdpr-banner-visible {
            padding-bottom: 120px;
        }
        
        @media (max-width: 768px) {
            body.gdpr-banner-visible {
                padding-bottom: 140px;
            }
        }
    </style>
    
    <script>
        // GDPRバナーが表示されている時のみbody paddingを適用
        document.addEventListener('DOMContentLoaded', function() {
            const banner = document.getElementById('gdpr-banner');
            if (banner && banner.style.display !== 'none') {
                document.body.classList.add('gdpr-banner-visible');
            }
        });
    </script>
    <?php
    return ob_get_clean();
}

?>
