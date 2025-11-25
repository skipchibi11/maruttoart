<?php
/**
 * GDPR & Google Tag Manager インラインスクリプト
 * Speed Insights のネットワーク依存関係を最適化するため、
 * 外部JSファイルではなくインラインで提供
 * 
 * 使用方法:
 * - <head>内で: <?php include 'includes/gdpr-gtm-inline.php'; ?>
 * - <body>直後で: <?php include 'includes/gdpr-gtm-noscript.php'; ?>
 */
?>

<!-- adsense -->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8053468089362860"
     crossorigin="anonymous"></script>

<!-- Google Tag Manager & GDPR (Inline for Performance) -->
<script>
// グローバルGDPR同意チェック関数
window.getGdprConsent = function() {
    try {
        return localStorage.getItem('gdpr_consent_v1');
    } catch (e) {
        return null;
    }
};

// GTM読み込みフラグ
window.gtmLoaded = false;

// GTM読み込み関数
window.loadGTM = function() {
    if (window.gtmLoaded) {
        console.log('GTM already loaded');
        return;
    }
    
    console.log('Loading GTM after consent...');
    
    // Google Tag Manager スクリプトを動的に挿入
    (function(w,d,s,l,i){
        w[l]=w[l]||[];
        w[l].push({'gtm.start': new Date().getTime(), event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),
        dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;
        j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
        f.parentNode.insertBefore(j,f);
        window.gtmLoaded = true;
        console.log('GTM script loaded');
    })(window,document,'script','dataLayer','GTM-579HN546');
};

// GDPR同意イベントリスナー
window.addEventListener('gdpr-consent-accepted', function() {
    window.loadGTM();
});

// ページ読み込み時に同意状況を確認
(function() {
    const consent = window.getGdprConsent();
    if (consent === 'accepted') {
        // 同意済みの場合は即座にGTMを読み込み
        window.loadGTM();
    } else {
        console.log('GTM not loaded - GDPR consent required');
    }
})();
</script>
<!-- End Google Tag Manager & GDPR -->
