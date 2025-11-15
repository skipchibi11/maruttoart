<?php
/**
 * GDPR & Google Tag Manager noscript タグ
 * JavaScriptが無効な環境でのGTM対応
 * GDPR同意済みの場合のみ表示
 */
?>
<!-- Google Tag Manager (noscript) - GDPR対応 -->
<script>
(function() {
    const consent = window.getGdprConsent ? window.getGdprConsent() : null;
    if (consent === 'accepted') {
        // 同意済みの場合はnoscript GTMを挿入
        document.write('<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-579HN546" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>');
    }
})();
</script>
<!-- End Google Tag Manager (noscript) -->
