<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// トップページは新着6件のみ表示
$sql = "SELECT m.*, c.slug as category_slug FROM materials m 
        LEFT JOIN categories c ON m.category_id = c.id 
        ORDER BY m.created_at DESC LIMIT 6";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$materials = $stmt->fetchAll();

// 全素材件数を取得（JSON-LD用）
$totalCountSql = "SELECT COUNT(*) FROM materials";
$totalCountStmt = $pdo->prepare($totalCountSql);
$totalCountStmt->execute();
$totalMaterialsCount = $totalCountStmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google Tag Manager - GDPR対応 -->
    <script>
    // GDPR同意状況をチェックしてGTMを条件付き読み込み
    (function() {
        function getGdprConsent() {
            try {
                return localStorage.getItem('gdpr_consent_v1');
            } catch (e) {
                return null;
            }
        }
        
        function loadGTM() {
            if (window.gtmLoaded) return; // 重複読み込み防止
            window.gtmLoaded = true;
            
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','GTM-579HN546');
            
            console.log('GTM loaded after GDPR consent');
        }
        
        // 同意状況を確認
        const consent = getGdprConsent();
        if (consent === 'accepted') {
            // 既に同意済みの場合は即座に読み込み
            loadGTM();
        } else {
            // 同意していない場合は読み込まない
            console.log('GTM not loaded - GDPR consent required');
        }
        
        // GDPR同意イベントを監視（将来の同意に対応）
        window.addEventListener('gdpr-consent-accepted', loadGTM);
    })();
    </script>
    <!-- End Google Tag Manager -->
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>手描きイラスト素材集｜marutto.art（無料・商用利用OK、水彩・パステル）</title>
    <meta name="description" content="手描きイラスト素材をダウンロード！手描きのやさしいタッチで描かれた動物、植物、食べ物などの素材を商用利用OK。個人・法人問わずご利用いただける無料素材集です。">
    <link rel="icon" href="/favicon.ico">
    
    <!-- ヒーロー画像のpreload -->
    <link rel="preload" as="image" href="/assets/images/hero.webp" fetchpriority="high" />
    
    <style>
        /* リセットCSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            line-height: 1.5;
            color: #212529;
        }

        /* コンテナシステム */
        .container {
            width: 100%;
            max-width: 1140px;
            margin: 0 auto;
            padding-left: 15px;
            padding-right: 15px;
        }

        /* グリッドシステム */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-left: -15px;
            margin-right: -15px;
        }

        /* 基本カラム設定 */
        [class*="col-"] {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-12 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
            flex: 0 0 100%;
            max-width: 100%;
        }

        .col-6 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
            flex: 0 0 50%;
            max-width: 50%;
        }

        .col-md-4 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-lg-3 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-xl-2 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-xxl-2 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        /* レスポンシブブレークポイント */
        /* ~576px: 2列表示 (col-6: 50%) */
        /* 768px~: 3列表示 (col-md-4: 33.33%) */
        /* 992px~: 4列表示 (col-lg-3: 25%) */
        /* 1200px~: 6列表示 (col-xl-2: 16.67%) */
        /* 1400px~: 6列表示維持 (col-xxl-2: 16.67%) */
        
        @media (min-width: 768px) {
            .col-md-4 {
                flex: 0 0 33.333333% !important;
                max-width: 33.333333% !important;
            }
        }

        @media (min-width: 992px) {
            .col-lg-3 {
                flex: 0 0 25% !important;
                max-width: 25% !important;
            }
        }

        /* 1200px以上: 6列表示 (16.666%) */
        @media (min-width: 1200px) {
            .col-xl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
        }

        /* 1400px以上: 6列表示を維持 */
        @media (min-width: 1400px) {
            .col-xxl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
            
            /* コンテナの最大幅を拡張 */
            .container {
                max-width: 1320px;
            }
        }

        /* 1600px以上: さらに大きな画面向け調整 */
        @media (min-width: 1600px) {
            .container {
                max-width: 1500px;
            }
            
            /* 大型画面で確実に6列表示 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
        }

        /* 1800px以上: 超大型画面向け調整 */
        @media (min-width: 1800px) {
            .container {
                max-width: 1680px;
            }
            
            /* 大型画面でも確実に6列表示 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
        }

        /* 2000px以上: 4K画面等の超大型画面 */
        @media (min-width: 2000px) {
            .container {
                max-width: 1860px;
            }
            
            /* 超大型画面でも確実に6列表示 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
        }

        /* ヒーローセクション */
        .hero-section {
            background: #fef9e7;
            color: #5d4037;
            padding: 80px 0 60px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .hero-content {
            display: flex;
            align-items: center;
            min-height: 400px;
            position: relative;
            z-index: 2;
        }

        .hero-text {
            flex: 1;
            padding-right: 40px;
        }

        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .hero-description {
            font-size: 1.25rem;
            margin-bottom: 30px;
            line-height: 1.6;
            opacity: 0.95;
        }

        .hero-cta {
            display: inline-block;
            background: rgba(93, 64, 55, 0.1);
            color: #5d4037;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid rgba(93, 64, 55, 0.3);
        }

        .hero-cta:hover {
            background: rgba(93, 64, 55, 0.2);
            transform: translateY(-2px);
            color: #5d4037;
            text-decoration: none;
        }

        .hero-image {
            flex: 1;
            text-align: center;
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 50%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            aspect-ratio: 1 / 1;
            object-fit: cover;
        }

        /* ヒーローセクション - レスポンシブ対応 */
        @media (max-width: 768px) {
            .hero-section {
                padding: 60px 0 40px;
            }

            .hero-content {
                flex-direction: column;
                text-align: center;
                min-height: auto;
            }

            .hero-text {
                padding-right: 0;
                margin-bottom: 30px;
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .hero-description {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .hero-title {
                font-size: 2rem;
            }

            .hero-description {
                font-size: 1rem;
            }

            .hero-section {
                padding: 40px 0 30px;
            }
        }

        /* ナビゲーション */
        .navbar {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
            background-color: #ffffff;
            border-bottom: 1px solid rgba(0,0,0,.125);
        }

        .navbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .navbar-brand {
            display: inline-block;
            padding-top: 0.3125rem;
            padding-bottom: 0.3125rem;
            margin-right: 1rem;
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }

        .navbar-brand:hover {
            color: #333;
            text-decoration: none;
        }

        /* SNSリンク */
        .social-links {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
        }

        .social-link:hover {
            transform: translateY(-2px);
            text-decoration: none;
        }

        .social-link.twitter {
            color: #1da1f2;
        }

        .social-link.twitter:hover {
            background-color: #1da1f2;
            color: white;
            border-color: #1da1f2;
        }

        .social-link.youtube {
            color: #ff0000;
        }

        .social-link.youtube:hover {
            background-color: #ff0000;
            color: white;
            border-color: #ff0000;
        }

        .social-icon {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        /* カードコンポーネント */
        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.25rem;
        }

        .material-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            position: relative;
            border-radius: 0.25rem;
            will-change: transform, box-shadow;
        }
            background-color: #fff;
            margin-bottom: 1.5rem;
        }

        .material-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            color: inherit;
            text-decoration: none;
            border-color: #0d6efd;
        }

        .material-card:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .card-body {
            flex: 1 1 auto;
            padding: 0.75rem 1rem;
        }

        .card-title {
            color: #666;
            font-weight: 300;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 0;
        }

        /* h3のcard-titleで既存のh5と同じ見た目を維持 */
        h3.card-title {
            color: #666;
            font-weight: 300;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 0;
            margin-top: 0;
        }

        .material-card:hover .card-title,
        .material-card:hover h3.card-title {
            color: #0d6efd;
        }

        .material-image {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            border-radius: 0.25rem 0.25rem 0 0;
            transition: opacity 0.3s ease-in-out;
            background-color: #f8f9fa;
        }

        /* Lazyload用のスタイル */
        .material-image[loading="lazy"] {
            opacity: 0;
        }

        .material-image[loading="lazy"].loaded {
            opacity: 1;
        }

        /* 読み込み中のプレースホルダー */
        .material-image:not(.loaded) {
            background-image: linear-gradient(45deg, #f8f9fa 25%, transparent 25%, transparent 75%, #f8f9fa 75%, #f8f9fa),
                              linear-gradient(45deg, #f8f9fa 25%, transparent 25%, transparent 75%, #f8f9fa 75%, #f8f9fa);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
        }

        /* ユーティリティクラス */
        .mt-4 { margin-top: 1.5rem !important; }
        .mt-5 { margin-top: 3rem !important; }
        .mb-2 { margin-bottom: 0.5rem !important; }
        .mb-4 { margin-bottom: 1.5rem !important; }
        .mb-0 { margin-bottom: 0 !important; }
        .text-muted { color: #6c757d !important; }
        .text-center { text-align: center !important; }
        .text-white { color: #fff !important; }
        .text-decoration-underline { text-decoration: underline !important; }
        .bg-light { background-color: #f8f9fa !important; }
        .py-4 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
        .h-100 { height: 100% !important; }

        /* ページネーション */
        .pagination {
            display: flex;
            padding-left: 0;
            list-style: none;
            border-radius: 0.25rem;
        }

        .justify-content-center {
            justify-content: center !important;
        }

        .page-item {
            position: relative;
            display: block;
        }

        .page-item:first-child .page-link {
            margin-left: 0;
            border-top-left-radius: 0.25rem;
            border-bottom-left-radius: 0.25rem;
        }

        .page-item:last-child .page-link {
            border-top-right-radius: 0.25rem;
            border-bottom-right-radius: 0.25rem;
        }

        .page-item.active .page-link {
            z-index: 3;
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
            border-color: #dee2e6;
        }

        .page-link {
            position: relative;
            display: block;
            padding: 0.5rem 0.75rem;
            margin-left: -1px;
            line-height: 1.25;
            color: #0d6efd;
            text-decoration: none;
            background-color: #fff;
            border: 1px solid #dee2e6;
        }

        .page-link:hover {
            z-index: 2;
            color: #0a58ca;
            text-decoration: none;
            background-color: #e9ecef;
            border-color: #dee2e6;
        }

        .page-link:focus {
            z-index: 3;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        /* ボタン */
        .btn {
            display: inline-block;
            font-weight: 400;
            color: #212529;
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            text-decoration: none;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .btn:hover {
            color: #212529;
            text-decoration: none;
        }

        .btn:focus {
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .btn-primary {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-primary:hover {
            color: #fff;
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        .btn-primary:focus {
            color: #fff;
            background-color: #0b5ed7;
            border-color: #0a58ca;
            box-shadow: 0 0 0 0.2rem rgba(49, 132, 253, 0.5);
        }

        .btn-lg {
            padding: 0.5rem 1rem;
            font-size: 1.25rem;
            line-height: 1.5;
            border-radius: 0.3rem;
        }

        /* Load More Button */
        .load-more-button a {
            background-color: #ffffff;
            color: #444;
            border: 2px solid #ccc;
            border-radius: 12px;
            padding: 0.75em 2em;
            font-size: 1rem;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease-in-out;
        }

        .load-more-button a:hover {
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
            text-decoration: none;
        }

        .btn-success {
            color: #fff;
            background-color: #198754;
            border-color: #198754;
        }

        .btn-success:hover {
            color: #fff;
            background-color: #157347;
            border-color: #146c43;
        }

        .btn-outline-light {
            color: #f8f9fa;
            border-color: #f8f9fa;
        }

        .btn-outline-light:hover {
            color: #000;
            background-color: #f8f9fa;
            border-color: #f8f9fa;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }

        /* フッター */
        .align-items-center {
            align-items: center !important;
        }

        .col-md-8, .col-md-4, .col-md-12 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        @media (min-width: 768px) {
            .col-md-8 {
                flex: 0 0 66.666667%;
                max-width: 66.666667%;
            }
            .col-md-4 {
                flex: 0 0 33.333333%;
                max-width: 33.333333%;
            }
            .col-md-12 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            .text-md-end {
                text-align: right !important;
            }
        }

        /* レスポンシブ調整 */
        @media (max-width: 768px) {
            .material-image {
                height: auto;
                min-height: 200px;
                max-height: 250px;
            }
            .navbar-brand {
                font-size: 1.5rem;
            }
            
            .social-links {
                gap: 10px;
            }
            
            .social-link {
                width: 35px;
                height: 35px;
            }
            
            .social-icon {
                width: 18px;
                height: 18px;
            }
            }
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            /* スマホでのカラムパディング調整 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                padding-left: 10px;
                padding-right: 10px;
            }
            .row {
                margin-left: -10px;
                margin-right: -10px;
            }
        }

        @media (max-width: 576px) {
            .material-image {
                min-height: 180px;
                max-height: 200px;
            }
            .card-body {
                padding: 0.75rem;
            }
            .card-title {
                font-size: 1rem;
            }
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            /* より小さなスマホでのカラムパディング調整 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                padding-left: 8px;
                padding-right: 8px;
            }
            .row {
                margin-left: -8px;
                margin-right: -8px;
            }
        }
                padding-left: 15px;
                padding-right: 15px;
            }
        }

        /* GDPR Cookie Banner のスタイル */
        #gdpr-banner {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            background-color: #212529 !important;
            color: #ffffff !important;
            padding: 1rem !important;
            z-index: 1050 !important;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3) !important;
        }
        
        #gdpr-banner.hidden,
        .gdpr-cookie-banner.hidden {
            display: none !important;
        }
        
        .gdpr-text {
            font-size: 0.9rem !important;
            line-height: 1.4 !important;
            color: #ffffff !important;
        }
        
        .gdpr-text a {
            color: #ffffff !important;
            text-decoration: underline !important;
        }
        
        .gdpr-text a:hover {
            color: #e9ecef !important;
        }
        
        .gdpr-buttons {
            margin-top: 1rem !important;
            display: flex !important;
            gap: 0.5rem !important;
            flex-wrap: wrap !important;
        }
        
        .gdpr-buttons .btn {
            flex: 0 0 auto !important;
            white-space: nowrap !important;
        }
        
        /* GDPR専用のボタンスタイル（より強力な優先度） */
        #gdpr-banner .btn-outline-light {
            color: #ffffff !important;
            border-color: #ffffff !important;
            background-color: transparent !important;
            border-width: 1px !important;
            border-style: solid !important;
        }

        #gdpr-banner .btn-outline-light:hover {
            color: #212529 !important;
            background-color: #ffffff !important;
            border-color: #ffffff !important;
        }

        #gdpr-banner .btn-success {
            color: #000000 !important;
            background-color: #ffffff !important;
            border-color: #ffffff !important;
        }

        #gdpr-banner .btn-success:hover {
            color: #000000 !important;
            background-color: #f8f9fa !important;
            border-color: #f8f9fa !important;
        }
        
        @media (min-width: 768px) {
            .gdpr-buttons {
                margin-top: 0 !important;
                justify-content: flex-end !important;
            }
        }
        
        @media (max-width: 767px) {
            .gdpr-buttons {
                justify-content: center !important;
            }
        }
        
        /* YouTubeアイコンのスタイル */
        .youtube-icon {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            cursor: pointer;
            z-index: 10;
            transition: transform 0.2s ease, opacity 0.2s ease, background-color 0.2s ease;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
            opacity: 0.8;
            will-change: transform, opacity, background-color;
        }
        
        .youtube-icon:hover {
            background: rgba(0, 0, 0, 0.8);
            opacity: 1;
            transform: scale(1.05);
        }

        /* GDPR未承認時のYouTubeアイコンスタイル */
        .youtube-icon.blocked {
            background: rgba(150, 150, 150, 0.6);
            opacity: 0.6;
        }
        
        .youtube-icon.blocked:hover {
            background: rgba(150, 150, 150, 0.7);
            opacity: 0.7;
            transform: scale(1.05);
        }

        .youtube-icon::before {
            content: '▶';
            font-size: 10px;
        }
        
        /* YouTube動画ポップアップのスタイル */
        .youtube-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .youtube-modal.show {
            display: flex;
        }
        
        .youtube-modal-content {
            position: relative;
            width: 90%;
            max-width: 800px;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .youtube-modal iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .youtube-modal-close {
            position: absolute;
            top: -40px;
            right: 0;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
        }
        
        .youtube-modal-close:hover {
            color: #ccc;
        }

        /* フッターのスタイル */
        .footer-custom {
            background-color: #fef9e7 !important;
        }

        /* フッター文字色の改善（コントラスト対応） */
        .footer-custom .footer-text {
            color: #1a1a1a !important;
        }

        .footer-custom .footer-text:hover {
            color: #000000 !important;
        }

        /* プライバシーポリシーリンクのスタイル */
        .footer-custom a.footer-text {
            transition: color 0.2s ease;
        }

        .footer-custom a.footer-text:hover {
            color: #0d6efd !important;
            text-decoration: underline !important;
        }

        /* 言語切替のスタイル */
        .language-switcher {
            margin-top: 10px;
        }

        .language-switcher .language-links {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .language-switcher .language-link {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
            padding: 2px 4px;
            border-radius: 3px;
            transition: all 0.2s ease;
        }

        .language-switcher .language-link:hover {
            color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.1);
        }

        .language-switcher .language-link.current {
            color: #0d6efd;
            font-weight: 600;
        }

        .language-switcher .separator {
            color: #dee2e6;
            margin: 0 2px;
        }

        @media (max-width: 576px) {
            .language-switcher .language-links {
                gap: 6px;
            }
            
            .language-switcher .language-link {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) - GDPR対応 -->
    <script>
    // グローバルGDPR同意チェック関数
    window.getGdprConsent = function() {
        try {
            return localStorage.getItem('gdpr_consent_v1');
        } catch (e) {
            return null;
        }
    };
    
    // GDPR同意状況をチェックしてnoscript GTMを条件付き表示
    (function() {
        function getGdprConsent() {
            try {
                return localStorage.getItem('gdpr_consent_v1');
            } catch (e) {
                return null;
            }
        }
        
        const consent = getGdprConsent();
        if (consent === 'accepted') {
            // 同意済みの場合はnoscript GTMを挿入
            document.write('<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-579HN546" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>');
        }
    })();
    </script>
    <!-- End Google Tag Manager (noscript) -->
    
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="/">maruttoart</a>
            <div class="social-links">
                <a href="https://x.com/marutto_art" class="social-link twitter" target="_blank" rel="noopener noreferrer" title="X (Twitter)">
                    <svg class="social-icon" viewBox="0 0 24 24">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                </a>
                <a href="https://youtube.com/@marutto_art" class="social-link youtube" target="_blank" rel="noopener noreferrer" title="YouTube">
                    <svg class="social-icon" viewBox="0 0 24 24">
                        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                    </svg>
                </a>
            </div>
        </div>
    </nav>

    <!-- ヒーローセクション -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1 class="hero-title">手描きイラスト素材</h1>
                    <p class="hero-description">
                        手描きのやさしいタッチで描かれた動物、植物、食べ物などの素材を商用利用OK。個人・法人問わずご利用いただける無料素材集です。
                    </p>
                    <a href="/list.php" class="hero-cta">素材を見る</a>
                </div>
                <div class="hero-image">
                    <img src="/assets/images/hero.webp" 
                         alt="手描きイラスト素材のサンプル" 
                         width="500"
                         height="500"
                         fetchpriority="high"
                         loading="eager">
                </div>
            </div>
        </div>
    </section>

    <div class="container mt-4" id="materials">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-2">新着イラスト素材</h2>
                <p class="text-muted mb-4">
                    最新の手描きイラスト素材をご紹介
                </p>
            </div>
        </div>

        <div class="row">
            <?php foreach ($materials as $material): ?>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2 col-xxl-2 mb-4">
                <?php if (!empty($material['category_slug'])): ?>
                    <a href="/<?= h($material['category_slug']) ?>/<?= h($material['slug']) ?>/" class="card material-card h-100" role="button" tabindex="0" aria-label="<?= h($material['title']) ?>の詳細を見る">
                <?php else: ?>
                    <a href="/detail/<?= h($material['slug']) ?>" class="card material-card h-100" role="button" tabindex="0" aria-label="<?= h($material['title']) ?>の詳細を見る">
                <?php endif; ?>
                    <?php
                    // レスポンシブ画像の設定
                    $smallImage = $material['webp_small_path'] ?? $material['image_path'];
                    $mediumImage = $material['webp_medium_path'] ?? $material['image_path'];
                    ?>
                    <picture>
                        <!-- スマホ: 180x180のWebP画像 -->
                        <source media="(max-width: 768px)" srcset="/<?= h($smallImage) ?>" type="image/webp">
                        <!-- PC: 300x300のWebP画像 -->
                        <source media="(min-width: 769px)" srcset="/<?= h($mediumImage) ?>" type="image/webp">
                        <!-- フォールバック -->
                        <img src="/<?= h($material['image_path']) ?>" 
                             class="material-image" 
                             alt="<?= h($material['title']) ?>のイラスト"
                             loading="lazy"
                             decoding="async">
                    </picture>
                    
                    <!-- YouTubeアイコン -->
                    <?php 
                    // 動画が公開されているかチェック
                    $showVideo = !empty($material['youtube_url']);
                    if (!empty($material['video_publish_date'])) {
                        $publishDateTime = new DateTime($material['video_publish_date']);
                        $now = new DateTime();
                        $showVideo = $showVideo && ($now >= $publishDateTime);
                    }
                    // video_publish_dateが空の場合は、youtube_urlがあれば表示
                    ?>
                    <?php if ($showVideo): ?>
                        <div class="youtube-icon" 
                             onclick="openYouTubeModal(event, '<?= h($material['youtube_url']) ?>', '<?= h($material['title']) ?>')"
                             title="動画を見る">
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h3 class="card-title"><?= h($material['title']) ?></h3>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- もっと見るボタン -->
        <div class="row mt-5">
            <div class="col-12 text-center load-more-button">
                <a href="/list.php" class="btn btn-outline-primary btn-lg">
                    もっと見る
                </a>
            </div>
        </div>

        <?php if (empty($materials)): ?>
        <div class="row">
            <div class="col-12 text-center">
                <p class="text-muted">
                    素材が見つかりませんでした。
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- GDPR Cookie Banner (CDN対応・セッション不使用) -->
    <div id="gdpr-banner" class="gdpr-cookie-banner hidden" style="position: fixed; bottom: 0; left: 0; right: 0; background-color: #212529; color: #ffffff; padding: 1rem; z-index: 1050; box-shadow: 0 -2px 10px rgba(0,0,0,0.3);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 col-md-8">
                    <div class="gdpr-text" style="color: #ffffff;">
                        当サイトではサイトの利便性向上のためCookieを使用しています。詳細は
                        <a href="/privacy-policy.php" class="text-white text-decoration-underline" style="color: #ffffff; text-decoration: underline;">プライバシーポリシー</a>
                        をご確認ください。
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="gdpr-buttons text-md-end" style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button id="gdpr-accept" class="btn btn-success btn-sm" style="color: #000000; background-color: #ffffff; border-color: #ffffff;">同意する</button>
                        <button id="gdpr-decline" class="btn btn-outline-light btn-sm" style="color: #ffffff; border-color: #ffffff; background-color: transparent;">拒否する</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer-custom mt-5 py-4">
        <div class="container">
            <div class="text-center">
                <div class="mb-2">
                    <a href="/privacy-policy.php" class="footer-text text-decoration-none">プライバシーポリシー</a>
                </div>
                <div class="language-switcher mb-2">
                    <div class="language-links">
                        <a href="/" class="language-link current" title="日本語">日本語</a>
                        <span class="separator">|</span>
                        <a href="https://marutto.art/en/" class="language-link" title="English">English</a>
                        <span class="separator">|</span>
                        <a href="https://marutto.art/es/" class="language-link" title="Español">Español</a>
                        <span class="separator">|</span>
                        <a href="https://marutto.art/fr/" class="language-link" title="Français">Français</a>
                        <span class="separator">|</span>
                        <a href="https://marutto.art/nl/" class="language-link" title="Nederlands">Nederlands</a>
                    </div>
                </div>
                <div>
                    <p class="footer-text mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- YouTubeモーダル -->
    <div id="youtube-modal" class="youtube-modal">
        <div class="youtube-modal-content">
            <button class="youtube-modal-close" onclick="closeYouTubeModal()">&times;</button>
            <iframe id="youtube-iframe" src="" allowfullscreen></iframe>
        </div>
    </div>

    <!-- GDPR Cookie Consent Script (CDN対応・localStorage使用) -->
    <script>
    // GDPR Cookie Consent (セッション・Cookie不使用版)
    (function() {
        const GDPR_KEY = 'gdpr_consent_v1';
        let isInitialized = false;
        
        // 初期化関数
        function initGDPR() {
            if (isInitialized) return;
            isInitialized = true;
            
            const banner = document.getElementById('gdpr-banner');
            const acceptBtn = document.getElementById('gdpr-accept');
            const declineBtn = document.getElementById('gdpr-decline');
            
            if (!banner || !acceptBtn || !declineBtn) {
                console.error('GDPR elements not found');
                return;
            }
            
            // localStorage から同意状況をチェック
            function getGdprConsent() {
                try {
                    return localStorage.getItem(GDPR_KEY);
                } catch (e) {
                    console.warn('localStorage not available:', e);
                    return null;
                }
            }
            
            // 同意状況を保存
            function setGdprConsent(value) {
                try {
                    localStorage.setItem(GDPR_KEY, value);
                    return true;
                } catch (e) {
                    console.warn('localStorage save failed:', e);
                    return false;
                }
            }
            
            // バナーを表示
            function showBanner() {
                if (banner) {
                    banner.classList.remove('hidden');
                }
            }
            
            // バナーを非表示
            function hideBanner() {
                if (banner) {
                    banner.classList.add('hidden');
                }
            }
            
            // 同意処理
            function acceptConsent() {
                const success = setGdprConsent('accepted');
                hideBanner();
                enableAnalytics();
                
                // GTM読み込みイベントを発火
                const event = new CustomEvent('gdpr-consent-accepted');
                window.dispatchEvent(event);
                console.log('gdpr-consent-accepted event dispatched');
            }
            
            // 拒否処理
            function declineConsent() {
                setGdprConsent('declined');
                hideBanner();
                disableAnalytics();
                
                // 拒否イベントを発火
                const event = new CustomEvent('gdpr-consent-declined');
                window.dispatchEvent(event);
            }
            
            // アナリティクス有効化（プレースホルダー）
            function enableAnalytics() {
                // GTMが未読み込みの場合は読み込み
                if (!window.gtmLoaded) {
                    const event = new CustomEvent('gdpr-consent-accepted');
                    window.dispatchEvent(event);
                }
            }
            
            // アナリティクス無効化（プレースホルダー）
            function disableAnalytics() {
                console.log('Analytics disabled');
                // アナリティクス無効化のコードをここに追加
            }
            
            // イベントリスナーを設定
            acceptBtn.addEventListener('click', acceptConsent);
            declineBtn.addEventListener('click', declineConsent);
            
            // 同意状況をチェックして初期化
            const consent = getGdprConsent();
            
            if (consent === null) {
                // 未設定の場合はバナーを表示
                showBanner();
            } else if (consent === 'accepted') {
                // 同意済みの場合はアナリティクスを有効化
                hideBanner();
                enableAnalytics();
            } else if (consent === 'declined') {
                // 拒否済みの場合はアナリティクスを無効化
                hideBanner();
                disableAnalytics();
            }
        }
        
        // 複数の初期化方法を試行
        function tryInit() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initGDPR);
            } else {
                // DOMが既に読み込まれている場合は即座に実行
                setTimeout(initGDPR, 0);
            }
            
            // フォールバック: window.onloadでも試行
            window.addEventListener('load', function() {
                if (!isInitialized) {
                    initGDPR();
                }
            });
        }
        
        tryInit();
    })();
    
    // カードのキーボードナビゲーション対応
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.material-card');
        
        cards.forEach(function(card) {
            card.addEventListener('keydown', function(e) {
                // Enterキーまたはスペースキーで詳細ページに遷移
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    window.location.href = card.href;
                }
            });
        });
    });
    
    // YouTube動画ポップアップ機能
    function openYouTubeModal(event, youtubeUrl, title) {
        event.preventDefault();
        event.stopPropagation();
        
        // GDPR同意状況をチェック
        const consent = window.getGdprConsent();
        console.log('YouTube modal - GDPR consent:', consent);
        if (!consent || consent === 'declined') {
            // GDPR未承認または拒否の場合は代替メッセージを表示
            showYouTubeBlockedMessage(title);
            return;
        }
        
        const modal = document.getElementById('youtube-modal');
        const iframe = document.getElementById('youtube-iframe');
        
        console.log('YouTube modal elements:', { modal, iframe });
        console.log('Original YouTube URL:', youtubeUrl);
        
        // YouTube URLをembed形式に変換
        let embedUrl = '';
        if (youtubeUrl.includes('youtube.com/watch?v=')) {
            const videoId = youtubeUrl.split('v=')[1].split('&')[0];
            embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
        } else if (youtubeUrl.includes('youtu.be/')) {
            const videoId = youtubeUrl.split('/').pop().split('?')[0];
            embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
        } else if (youtubeUrl.includes('youtube.com/embed/')) {
            embedUrl = youtubeUrl + (youtubeUrl.includes('?') ? '&' : '?') + 'autoplay=1';
        } else {
            embedUrl = youtubeUrl;
        }
        
        console.log('Converted embed URL:', embedUrl);
        
        // リフロー最適化: 全ての変更をバッチ処理
        requestAnimationFrame(() => {
            iframe.src = embedUrl;
            modal.classList.add('show');
            console.log('YouTube modal opened with URL:', embedUrl);
        });
        
        // Escキーでモーダルを閉じる
        document.addEventListener('keydown', function escKeyHandler(e) {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', escKeyHandler);
                closeYouTubeModal();
            }
        });
        
        // モーダル背景クリックで閉じる
        modal.addEventListener('click', function modalClickHandler(e) {
            if (e.target === modal) {
                modal.removeEventListener('click', modalClickHandler);
                closeYouTubeModal();
            }
        });
    }
    
    // GDPR拒否時のYouTube動画ブロックメッセージ表示
    function showYouTubeBlockedMessage(title) {
        const message = `動画を視聴するには、Cookieの使用に同意が必要です。\n\nページ下部のバナーから「同意する」をクリックしてください。`;
        alert(message);
    }
    
    function closeYouTubeModal() {
        const modal = document.getElementById('youtube-modal');
        const iframe = document.getElementById('youtube-iframe');
        
        // リフロー最適化: 全ての変更をバッチ処理
        requestAnimationFrame(() => {
            modal.classList.remove('show');
            iframe.src = '';
        });
    }

    // Lazyload画像の読み込み完了処理
    document.addEventListener('DOMContentLoaded', function() {
        const lazyImages = document.querySelectorAll('img[loading="lazy"]');
        
        lazyImages.forEach(img => {
            // 画像が既に読み込まれている場合
            if (img.complete && img.naturalHeight !== 0) {
                img.classList.add('loaded');
            } else {
                // 画像の読み込み完了を待機
                img.addEventListener('load', function() {
                    this.classList.add('loaded');
                });
                
                // 読み込みエラーの場合
                img.addEventListener('error', function() {
                    this.classList.add('loaded'); // エラーでも表示状態にする
                });
            }
        });
        
        // GDPR状態に基づいてYouTubeアイコンの表示を更新
        updateYouTubeIconsGdprState();
    });
    
    // YouTubeアイコンのGDPR状態を更新
    function updateYouTubeIconsGdprState() {
        const youtubeIcons = document.querySelectorAll('.youtube-icon');
        const consent = window.getGdprConsent();
        console.log('Updating YouTube icons - GDPR consent:', consent);
        
        youtubeIcons.forEach(icon => {
            if (!consent || consent === 'declined') {
                icon.classList.add('blocked');
                icon.title = 'Cookieの使用に同意が必要です';
            } else {
                icon.classList.remove('blocked');
                icon.title = '動画を再生';
            }
        });
    }
    
    // GDPR同意状態変更時にYouTubeアイコンを更新
    window.addEventListener('gdpr-consent-accepted', updateYouTubeIconsGdprState);
    window.addEventListener('gdpr-consent-declined', updateYouTubeIconsGdprState);
    </script>
</body>
</html>
