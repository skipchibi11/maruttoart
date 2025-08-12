<?php
// GDPR同意バナーを表示する関数
function renderGDPRBanner() {
    if (!shouldShowGDPRBanner()) {
        return '';
    }
    
    $texts = getGDPRConsentText();
    
    return <<<HTML
    <!-- GDPR同意バナー -->
    <div id="gdpr-banner" class="gdpr-banner">
        <div class="gdpr-content">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h5 class="gdpr-title">{$texts['title']}</h5>
                        <p class="gdpr-message">{$texts['message']}</p>
                        <p class="gdpr-learn-more">
                            <a href="/privacy-policy.php" target="_blank" class="gdpr-link">{$texts['learn_more']}</a>
                        </p>
                    </div>
                    <div class="col-lg-4 text-end">
                        <div class="gdpr-buttons">
                            <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="toggleGDPRSettings()">
                                {$texts['settings_button']}
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm me-2" onclick="rejectAllCookies()">
                                {$texts['reject_button']}
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" onclick="acceptAllCookies()">
                                {$texts['accept_button']}
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- 詳細設定パネル -->
                <div id="gdpr-settings" class="gdpr-settings mt-3" style="display: none;">
                    <div class="row">
                        <div class="col-12">
                            <h6>Cookie設定</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="essential-cookies" checked disabled>
                                <label class="form-check-label" for="essential-cookies">
                                    <strong>必須Cookie</strong> (無効にできません)
                                    <br><small class="text-muted">サイトの基本機能に必要なCookieです。</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="functional-cookies" checked>
                                <label class="form-check-label" for="functional-cookies">
                                    <strong>機能性Cookie</strong>
                                    <br><small class="text-muted">翻訳機能などのサービス提供に使用されます。</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="analytics-cookies">
                                <label class="form-check-label" for="analytics-cookies">
                                    <strong>分析Cookie</strong>
                                    <br><small class="text-muted">サイトの利用状況分析に使用されます。</small>
                                </label>
                            </div>
                            <div class="mt-3">
                                <button type="button" class="btn btn-primary btn-sm" onclick="saveCustomConsent()">
                                    設定を保存
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="toggleGDPRSettings()">
                                    キャンセル
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .gdpr-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.98);
            border-top: 3px solid #007bff;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 10000;
            padding: 1rem 0;
        }
        
        .gdpr-title {
            color: #333;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .gdpr-message {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }
        
        .gdpr-learn-more {
            margin-bottom: 0;
        }
        
        .gdpr-link {
            color: #007bff;
            text-decoration: none;
            font-size: 0.85rem;
        }
        
        .gdpr-link:hover {
            text-decoration: underline;
        }
        
        .gdpr-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        
        .gdpr-settings {
            border-top: 1px solid #dee2e6;
            padding-top: 1rem;
        }
        
        .gdpr-settings .form-check {
            margin-bottom: 1rem;
            padding-left: 1.5rem;
        }
        
        .gdpr-settings .form-check-input:disabled {
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .gdpr-banner {
                padding: 1rem;
            }
            
            .gdpr-buttons {
                justify-content: center;
                margin-top: 1rem;
            }
            
            .gdpr-buttons .btn {
                flex: 1;
                max-width: 120px;
            }
            
            .col-lg-4 {
                text-align: center !important;
            }
        }
        
        @media (max-width: 576px) {
            .gdpr-message {
                font-size: 0.85rem;
            }
            
            .gdpr-buttons .btn {
                font-size: 0.8rem;
                padding: 0.25rem 0.5rem;
            }
        }
    </style>
HTML;
}
?>
