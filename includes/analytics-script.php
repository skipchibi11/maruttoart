<?php
/**
 * アクセス分析スクリプトコンポーネント
 * クライアントサイドでスクロール検知してアクセスログを記録
 */
?>
<script src="/assets/js/analytics.js" defer></script>

<!-- Google Consent Mode v2 + Analytics (GA4) -->
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  
  // デフォルトで同意なし状態（GDPR対応）
  gtag('consent', 'default', {
    'ad_storage': 'denied',
    'analytics_storage': 'denied',
    'ad_user_data': 'denied',
    'ad_personalization': 'denied'
  });
</script>

<!-- Google Analytics (GA4) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-F2TSTT5S17"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-F2TSTT5S17');
</script>
