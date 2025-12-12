/**
 * クライアントサイド アクセス分析（スクロール検知版）
 * 名前空間を使用して他のスクリプトとの競合を回避
 */

(function() {
    'use strict';
    
    // 既に初期化済みかチェック（重複実行防止）
    if (window.MaruttoAnalytics) {
        console.log('MaruttoAnalytics already initialized');
        return;
    }
    
    // 名前空間オブジェクトを作成
    window.MaruttoAnalytics = {
        initialized: false,
        config: {
            apiEndpoint: '/api/log-access.php',
            scrollThreshold: 10 // スクロール検知の閾値（px）
        },
        state: {
            pageUrl: window.location.pathname + window.location.search,
            enteredAt: Date.now(),
            hasScrolled: false,
            initialScrollY: window.pageYOffset || document.documentElement.scrollTop,
            logSent: false
        },
        
        /**
         * ログデータを送信
         */
        sendLog: function() {
            if (this.state.logSent) return;
            
            const logData = {
                page_url: this.state.pageUrl,
                has_scrolled: this.state.hasScrolled ? 1 : 0
            };
            
            // Beacon API を使用（ページ離脱時も確実に送信）
            if (navigator.sendBeacon) {
                const formData = new FormData();
                Object.keys(logData).forEach(key => {
                    formData.append(key, logData[key]);
                });
                const result = navigator.sendBeacon(this.config.apiEndpoint, formData);
                if (result) {
                    this.state.logSent = true;
                    console.log('[MaruttoAnalytics] Log sent:', logData);
                }
            } else {
                // フォールバック: fetch API
                fetch(this.config.apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(logData),
                    keepalive: true
                }).then(() => {
                    this.state.logSent = true;
                    console.log('[MaruttoAnalytics] Log sent:', logData);
                }).catch(err => {
                    console.error('[MaruttoAnalytics] Log send error:', err);
                });
            }
        },
        
        /**
         * スクロール検知
         */
        handleScroll: function() {
            if (this.state.hasScrolled) return;
            
            const currentScrollY = window.pageYOffset || document.documentElement.scrollTop;
            const scrollDiff = Math.abs(currentScrollY - this.state.initialScrollY);
            
            if (scrollDiff > this.config.scrollThreshold) {
                this.state.hasScrolled = true;
                console.log('[MaruttoAnalytics] Scroll detected - sending log');
                this.sendLog();
                
                // スクロールイベントリスナーを削除
                window.removeEventListener('scroll', this._scrollHandler);
            }
        },
        
        /**
         * イベントリスナーを設定
         */
        setupEventListeners: function() {
            // スクロールハンドラーを保存（削除できるように）
            this._scrollHandler = this.handleScroll.bind(this);
            window.addEventListener('scroll', this._scrollHandler, { passive: true });
            
            // ページ離脱時に最終ログを送信
            const unloadHandler = () => this.sendLog();
            window.addEventListener('beforeunload', unloadHandler);
            window.addEventListener('pagehide', unloadHandler);
        },
        
        /**
         * 広告経由アクセスかチェック
         */
        isAdReferral: function() {
            const urlParams = new URLSearchParams(window.location.search);
            // utm_source, utm_medium, utm_campaign などの広告パラメータをチェック
            return urlParams.has('utm_source') || 
                   urlParams.has('utm_medium') || 
                   urlParams.has('utm_campaign') ||
                   urlParams.has('gclid') || // Google Ads
                   urlParams.has('fbclid');  // Facebook Ads
        },
        
        /**
         * 初期化
         */
        init: function() {
            if (this.initialized) {
                console.log('[MaruttoAnalytics] Already initialized');
                return;
            }
            
            // 広告経由の場合は即座にログ送信
            if (this.isAdReferral()) {
                console.log('[MaruttoAnalytics] Ad referral detected - sending log immediately');
                this.sendLog();
            } else {
                // 通常アクセスはスクロール検知モード
                this.setupEventListeners();
                
                // 2秒後にスクロールしていなければログ送信
                setTimeout(() => {
                    if (!this.state.hasScrolled && !this.state.logSent) {
                        console.log('[MaruttoAnalytics] No scroll after 2s - sending log');
                        this.sendLog();
                    }
                }, 2000);
            }
            
            this.initialized = true;
            console.log('[MaruttoAnalytics] Initialized');
        }
    };
    
    // ページ読み込み完了後に初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.MaruttoAnalytics.init();
        });
    } else {
        window.MaruttoAnalytics.init();
    }
})();
