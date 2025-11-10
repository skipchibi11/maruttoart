/**
 * GDPR & Google Tag Manager 統合管理
 * 全ページで使用する共通のコンセント管理とGTM連携
 * 
 * @version 1.0.0
 * @license MIT
 */

(function() {
    'use strict';

    // GTM Container ID
    const GTM_CONTAINER_ID = 'GTM-579HN546';
    
    // GDPR設定
    const GDPR_CONFIG = {
        storageKey: 'gdpr_consent_v1',
        bannerSelector: '#gdpr-banner',
        acceptButtonSelector: '#gdpr-accept',
        declineButtonSelector: '#gdpr-decline'
    };

    /**
     * GTMとコンセントモードの初期化
     */
    function initializeGTM() {
        // dataLayerの初期化
        window.dataLayer = window.dataLayer || [];
        
        // gtagヘルパー関数
        if (!window.gtag) {
            window.gtag = function() {
                window.dataLayer.push(arguments);
            };
        }
        
        // デフォルトでコンセントを拒否に設定（GDPR準拠）
        window.gtag('consent', 'default', {
            'ad_storage': 'denied',
            'analytics_storage': 'denied',
            'functionality_storage': 'denied',
            'personalization_storage': 'denied',
            'security_storage': 'granted',
            'wait_for_update': 500
        });
        
        console.log('GTM consent mode initialized with default denied state');
    }

    /**
     * GTM noscriptタグを動的に挿入
     */
    function insertGTMNoscript() {
        // 既にnoscriptタグが存在するかチェック
        if (document.querySelector('noscript iframe[src*="googletagmanager.com"]')) {
            console.log('GTM noscript already exists');
            return;
        }
        
        // noscriptタグを作成
        const noscript = document.createElement('noscript');
        const iframe = document.createElement('iframe');
        iframe.src = `https://www.googletagmanager.com/ns.html?id=${GTM_CONTAINER_ID}`;
        iframe.height = '0';
        iframe.width = '0';
        iframe.style.display = 'none';
        iframe.style.visibility = 'hidden';
        noscript.appendChild(iframe);
        
        // body直下に挿入
        if (document.body) {
            document.body.insertBefore(noscript, document.body.firstChild);
            console.log('GTM noscript tag inserted at body start');
        } else {
            // bodyがまだ存在しない場合は、DOM読み込み後に挿入
            document.addEventListener('DOMContentLoaded', function() {
                if (document.body) {
                    document.body.insertBefore(noscript, document.body.firstChild);
                    console.log('GTM noscript tag inserted after DOM ready');
                }
            });
        }
    }

    /**
     * GTMスクリプトを読み込む
     */
    function loadGTMScript() {
        if (window.gtmScriptLoaded) {
            return; // 重複読み込み防止
        }
        
        window.gtmScriptLoaded = true;
        
        // GTM初期化
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            'gtm.start': new Date().getTime(),
            'event': 'gtm.js'
        });
        
        // GTMスクリプトを動的に読み込み
        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtm.js?id=${GTM_CONTAINER_ID}`;
        
        const firstScript = document.getElementsByTagName('script')[0];
        firstScript.parentNode.insertBefore(script, firstScript);
        
        // noscriptタグを挿入
        insertGTMNoscript();
        
        console.log('GTM script loaded');
    }

    /**
     * GDPR同意状況を取得
     */
    function getGdprConsent() {
        try {
            return localStorage.getItem(GDPR_CONFIG.storageKey);
        } catch (e) {
            console.warn('localStorage not available:', e);
            return null;
        }
    }

    /**
     * GDPR同意状況を保存
     */
    function setGdprConsent(value) {
        try {
            localStorage.setItem(GDPR_CONFIG.storageKey, value);
            console.log('GDPR consent saved:', value);
            return true;
        } catch (e) {
            console.warn('localStorage save failed:', e);
            return false;
        }
    }

    /**
     * コンセント状態をGTMに反映
     */
    function updateGTMConsent(granted = false) {
        if (typeof window.gtag === 'undefined') {
            console.warn('gtag is not available');
            return;
        }
        
        const consentState = granted ? 'granted' : 'denied';
        
        window.gtag('consent', 'update', {
            'ad_storage': consentState,
            'analytics_storage': consentState,
            'functionality_storage': consentState,
            'personalization_storage': consentState
        });
        
        console.log(`GTM consent updated: ${consentState}`);
    }

    /**
     * dataLayerにコンセントイベントを送信
     */
    function sendConsentEvent(granted = false) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            'event': granted ? 'consent_granted' : 'consent_denied',
            'consent_type': 'all',
            'timestamp': new Date().toISOString()
        });
        
        console.log(`Consent event sent: ${granted ? 'granted' : 'denied'}`);
    }

    /**
     * バナー表示制御
     */
    function toggleBanner(show = false) {
        const banner = document.querySelector(GDPR_CONFIG.bannerSelector);
        if (!banner) {
            console.warn('GDPR banner not found');
            return;
        }
        
        if (show) {
            banner.classList.remove('hidden');
            console.log('GDPR banner shown');
        } else {
            banner.classList.add('hidden');
            console.log('GDPR banner hidden');
        }
    }

    /**
     * 同意処理
     */
    function handleAcceptConsent() {
        console.log('Accept consent clicked');
        
        // 同意状況を保存
        setGdprConsent('accepted');
        
        // バナーを非表示
        toggleBanner(false);
        
        // GTMコンセント状態を更新
        updateGTMConsent(true);
        
        // dataLayerにイベントを送信
        sendConsentEvent(true);
        
        // カスタムイベントを発火（他のスクリプトで利用可能）
        const event = new CustomEvent('gdpr-consent-accepted', {
            detail: { timestamp: new Date().toISOString() }
        });
        window.dispatchEvent(event);
    }

    /**
     * 拒否処理
     */
    function handleDeclineConsent() {
        console.log('Decline consent clicked');
        
        // 拒否状況を保存
        setGdprConsent('declined');
        
        // バナーを非表示
        toggleBanner(false);
        
        // GTMコンセント状態を維持（拒否状態）
        updateGTMConsent(false);
        
        // dataLayerにイベントを送信
        sendConsentEvent(false);
        
        // カスタムイベントを発火
        const event = new CustomEvent('gdpr-consent-declined', {
            detail: { timestamp: new Date().toISOString() }
        });
        window.dispatchEvent(event);
    }

    /**
     * GDPR UI イベントリスナーを設定
     */
    function attachGDPREventListeners() {
        const acceptBtn = document.querySelector(GDPR_CONFIG.acceptButtonSelector);
        const declineBtn = document.querySelector(GDPR_CONFIG.declineButtonSelector);
        
        if (acceptBtn) {
            acceptBtn.addEventListener('click', handleAcceptConsent);
        }
        
        if (declineBtn) {
            declineBtn.addEventListener('click', handleDeclineConsent);
        }
        
        if (acceptBtn || declineBtn) {
            console.log('GDPR event listeners attached');
        }
    }

    /**
     * 既存の同意状況に基づく初期化
     */
    function initializeBasedOnConsent() {
        const consent = getGdprConsent();
        console.log('Current GDPR consent:', consent);
        
        if (consent === null) {
            // 未設定の場合はバナーを表示
            console.log('No consent found, showing banner');
            toggleBanner(true);
        } else if (consent === 'accepted') {
            // 同意済みの場合
            console.log('Consent already accepted');
            toggleBanner(false);
            updateGTMConsent(true);
        } else if (consent === 'declined') {
            // 拒否済みの場合
            console.log('Consent declined');
            toggleBanner(false);
            updateGTMConsent(false);
        }
    }

    /**
     * メイン初期化関数
     */
    function initializeGDPRGTM() {
        console.log('GDPR-GTM initialization started');
        
        // GTMとコンセントモードを初期化
        initializeGTM();
        
        // GTMスクリプトを読み込み
        loadGTMScript();
        
        // DOM読み込み後の処理
        function onDOMReady() {
            // GDPR UIのイベントリスナーを設定
            attachGDPREventListeners();
            
            // 既存の同意状況に基づいて初期化
            initializeBasedOnConsent();
            
            console.log('GDPR-GTM initialization completed');
        }
        
        // DOM読み込み状況に応じて初期化
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', onDOMReady);
        } else {
            // 既に読み込み済みの場合は即座に実行
            setTimeout(onDOMReady, 0);
        }
        
        // フォールバック: window.loadでも試行
        window.addEventListener('load', function() {
            if (!window.gdprGtmInitialized) {
                console.log('Fallback initialization on window load');
                onDOMReady();
            }
            window.gdprGtmInitialized = true;
        });
    }

    /**
     * 公開API
     */
    window.GDPRGTM = {
        // 同意状況の取得
        getConsent: getGdprConsent,
        
        // 手動で同意を設定
        setConsent: function(granted) {
            if (granted) {
                handleAcceptConsent();
            } else {
                handleDeclineConsent();
            }
        },
        
        // GTMコンセント状態の手動更新
        updateConsent: updateGTMConsent,
        
        // バナー表示制御
        showBanner: function() { toggleBanner(true); },
        hideBanner: function() { toggleBanner(false); },
        
        // 再初期化（必要に応じて）
        reinitialize: initializeBasedOnConsent
    };

    // 自動初期化
    initializeGDPRGTM();

})();