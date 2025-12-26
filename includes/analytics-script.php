<?php
/**
 * アクセス分析スクリプトコンポーネント
 * クライアントサイドでスクロール検知してアクセスログを記録
 */
?>
<script src="/assets/js/analytics.js" defer></script>

<!-- Google Consent Mode v2 -->
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  
  // デフォルトで同意済み（日本向け）、ただし広告関連はGDPRバナーで制御
  gtag('consent', 'default', {
    'ad_storage': 'denied',           // アドセンスバナーで制御
    'analytics_storage': 'granted',    // GA4は即座に有効
    'ad_user_data': 'denied',          // アドセンスバナーで制御
    'ad_personalization': 'denied'     // アドセンスバナーで制御
  });
</script>

<!-- Google Analytics (GA4) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-210K7MXSES"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-210K7MXSES');
</script>
