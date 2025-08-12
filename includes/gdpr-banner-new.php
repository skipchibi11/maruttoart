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
    <div id="gdpr-banner" class="gdpr-banner" style="position: fixed; bottom: 0; left: 0; right: 0; background: rgba(0, 0, 0, 0.9); color: white; z-index: 9999; padding: 20px; display: block;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-2">Cookieの使用について</h5>
                    <p class="mb-2">
                        このサイトでは、より良いユーザーエクスペリエンスを提供するためにCookieを使用しています。
                        翻訳機能やサイトの最適化のためにデータを収集・処理しています。
                        詳細については<a href="./privacy-policy.php" style="color: #ffc107;">プライバシーポリシー</a>をご確認ください。
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button id="gdpr-accept" class="btn btn-success me-2">同意する</button>
                    <button id="gdpr-decline" class="btn btn-outline-light">拒否する</button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .gdpr-banner {
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
            border-top: 3px solid #ffc107;
        }
        
        .gdpr-banner h5 {
            color: #ffc107;
            font-weight: bold;
        }
        
        .gdpr-banner p {
            margin-bottom: 0;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .gdpr-banner a {
            text-decoration: underline;
        }
        
        .gdpr-banner a:hover {
            color: #fff !important;
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
    </style>
    <?php
    return ob_get_clean();
}

?>
