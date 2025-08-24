<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// ページネーション設定
$perPage = 20; // 1ページあたりの表示件数
$page = max(1, intval($_GET['page'] ?? 1)); // 現在のページ（最小値は1）
$offset = ($page - 1) * $perPage;

// 検索処理
$search = $_GET['search'] ?? '';
$whereClause = "WHERE 1=1";
$params = [];
$countParams = [];

if (!empty($search)) {
    $whereClause .= " AND (title LIKE ? OR description LIKE ? OR search_keywords LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
    $countParams = $params;
}

// 総件数を取得
$countSql = "SELECT COUNT(*) FROM materials " . $whereClause;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// データを取得（カテゴリ情報も含める）
$sql = "SELECT m.*, c.slug as category_slug FROM materials m 
        LEFT JOIN categories c ON m.category_id = c.id " . 
        $whereClause . " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-579HN546');</script>
    <!-- End Google Tag Manager -->
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>かわいい無料手描き水彩イラスト素材集｜maruttoart（商用利用OK）</title>
    <meta name="description" content="かわいい無料イラスト素材をダウンロード！手描き水彩のやさしいタッチで描かれた動物、植物、食べ物などの素材を商用利用OK。個人・法人問わずご利用いただける高品質なフリー素材集です。">
    <link rel="icon" type="image/png" href="/assets/icons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/assets/icons/favicon.svg" />
    <link rel="shortcut icon" href="/assets/icons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="maruttoart" />
    <link rel="manifest" href="/site.webmanifest" />
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
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            position: relative;
            border-radius: 0.25rem;
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

        .material-card:hover .card-title {
            color: #0d6efd;
        }

        .material-image {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            border-radius: 0.25rem 0.25rem 0 0;
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
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #343a40;
            color: white;
            padding: 1rem;
            z-index: 1050;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
        }
        
        #gdpr-banner.hidden {
            display: none;
        }
        
        .gdpr-text {
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .gdpr-buttons {
            margin-top: 1rem;
        }
        
        .gdpr-buttons .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        @media (min-width: 768px) {
            .gdpr-buttons {
                margin-top: 0;
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
            transition: all 0.2s ease;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
            opacity: 0.8;
        }
        
        .youtube-icon:hover {
            background: rgba(0, 0, 0, 0.8);
            opacity: 1;
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

        /* GDPR Cookie Banner のスタイル */
        #gdpr-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #343a40;
            color: white;
            padding: 1rem;
            z-index: 1050;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
        }
        
        #gdpr-banner.hidden {
            display: none;
        }
        
        .gdpr-text {
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .gdpr-buttons {
            margin-top: 1rem;
        }
        
        .gdpr-buttons .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        @media (min-width: 768px) {
            .gdpr-buttons {
                margin-top: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-579HN546"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="/">maruttoart</a>
        </div>
    </nav>

    <!-- ヒーローセクション -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1 class="hero-title">かわいい無料イラスト素材</h1>
                    <p class="hero-description">
                        手描き水彩のやさしいタッチで描かれた動物、植物、食べ物などの素材を商用利用OK。個人・法人問わずご利用いただけるフリー素材集です。
                    </p>
                    <a href="#materials" class="hero-cta">素材を見る</a>
                </div>
                <div class="hero-image">
                    <img src="/assets/images/hero.webp" 
                         alt="かわいい水彩イラスト素材のサンプル" 
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
                <?php if (!empty($search)): ?>
                    <h2 class="mb-2">検索結果: "<?= h($search) ?>"</h2>
                    <p class="text-muted mb-4">
                        <?= number_format($totalItems) ?>件中 
                        <?= number_format(($page - 1) * $perPage + 1) ?>-<?= number_format(min($page * $perPage, $totalItems)) ?>件目を表示 
                        (<?= $page ?>/<?= $totalPages ?>ページ)
                    </p>
                <?php else: ?>
                    <h2 class="mb-2">無料で使えるかわいい水彩イラスト素材集</h2>
                    <p class="text-muted mb-4">
                        全<?= number_format($totalItems) ?>件中 
                        <?= number_format(($page - 1) * $perPage + 1) ?>-<?= number_format(min($page * $perPage, $totalItems)) ?>件目を表示 
                        (<?= $page ?>/<?= $totalPages ?>ページ)
                    </p>
                <?php endif; ?>
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
                        <img src="/<?= h($material['image_path']) ?>" class="material-image" alt="<?= h($material['title']) ?>">
                    </picture>
                    
                    <!-- YouTubeアイコン -->
                    <?php if (!empty($material['youtube_url'])): ?>
                        <div class="youtube-icon" 
                             onclick="openYouTubeModal(event, '<?= h($material['youtube_url']) ?>', '<?= h($material['title']) ?>')"
                             title="動画を見る">
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h5 class="card-title"><?= h($material['title']) ?></h5>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ページネーション -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="ページネーション" class="mt-5">
            <ul class="pagination justify-content-center">
                <!-- 前のページ -->
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" aria-label="前のページ">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link" aria-label="前のページ">
                            <span aria-hidden="true">&laquo;</span>
                        </span>
                    </li>
                <?php endif; ?>

                <!-- ページ番号 -->
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                // 最初のページを表示
                if ($startPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif;
                endif;

                // 現在のページ周辺を表示
                for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <?php if ($i == $page): ?>
                            <span class="page-link"><?= $i ?></span>
                        <?php else: ?>
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                        <?php endif; ?>
                    </li>
                <?php endfor;

                // 最後のページを表示
                if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $totalPages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $totalPages ?></a>
                    </li>
                <?php endif; ?>

                <!-- 次のページ -->
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" aria-label="次のページ">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link" aria-label="次のページ">
                            <span aria-hidden="true">&raquo;</span>
                        </span>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <?php if (empty($materials)): ?>
        <div class="row">
            <div class="col-12 text-center">
                <p class="text-muted">
                    <?php if (!empty($search)): ?>
                        「<?= h($search) ?>」に該当する素材が見つかりませんでした。
                    <?php else: ?>
                        素材が見つかりませんでした。
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- GDPR Cookie Banner (CDN対応・セッション不使用) -->
    <div id="gdpr-banner" class="hidden">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="gdpr-text">
                        当サイトではサイトの利便性向上のためCookieを使用しています。詳細は
                        <a href="/privacy-policy.php" class="text-white text-decoration-underline">プライバシーポリシー</a>
                        をご確認ください。
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="gdpr-buttons text-md-end">
                        <button id="gdpr-accept" class="btn btn-success btn-sm">同意する</button>
                        <button id="gdpr-decline" class="btn btn-outline-light btn-sm">拒否する</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                </div>
                <div class="col-md-4 text-md-end">
                    <!-- gTranslate言語切り替え -->
                    <div class="gtranslate_wrapper"></div>
                </div>
            </div>
            <div class="row align-items-center">
                <div class="col-md-12">
                    <p class="text-muted mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <?php
    // 既存のGTranslate機能は削除
    // echo renderGTranslate();
    ?>

    <script>window.gtranslateSettings = {"default_language":"ja","url_structure":"sub_directory","languages":["ja","en","fr","es","nl"],"wrapper_selector":".gtranslate_wrapper"}</script>
    <script src="https://cdn.gtranslate.net/widgets/latest/lc.js" defer></script>
    
    <!-- YouTubeモーダル -->
    <div id="youtube-modal" class="youtube-modal">
        <div class="youtube-modal-content">
            <button class="youtube-modal-close" onclick="closeYouTubeModal()">&times;</button>
            <iframe id="youtube-iframe" src="" allowfullscreen></iframe>
        </div>
    </div>    <!-- GDPR Cookie Consent Script (CDN対応・localStorage使用) -->
    <script>
    // GDPR Cookie Consent (セッション・Cookie不使用版)
    (function() {
        const GDPR_KEY = 'gdpr_consent_v1';
        const banner = document.getElementById('gdpr-banner');
        const acceptBtn = document.getElementById('gdpr-accept');
        const declineBtn = document.getElementById('gdpr-decline');
        
        // localStorage から同意状況をチェック
        function getGdprConsent() {
            try {
                return localStorage.getItem(GDPR_KEY);
            } catch (e) {
                return null; // localStorage が使用できない場合
            }
        }
        
        // 同意状況を保存
        function setGdprConsent(value) {
            try {
                localStorage.setItem(GDPR_KEY, value);
                return true;
            } catch (e) {
                return false; // localStorage が使用できない場合
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
            setGdprConsent('accepted');
            hideBanner();
            // 必要に応じて Google Analytics などを有効化
            enableAnalytics();
        }
        
        // 拒否処理
        function declineConsent() {
            setGdprConsent('declined');
            hideBanner();
            // アナリティクスを無効化
            disableAnalytics();
        }
        
        // アナリティクス有効化（プレースホルダー）
        function enableAnalytics() {
            console.log('Analytics enabled (placeholder)');
            // ここに Google Analytics などの初期化コードを追加
        }
        
        // アナリティクス無効化（プレースホルダー）
        function disableAnalytics() {
            console.log('Analytics disabled (placeholder)');
            // ここにアナリティクス無効化のコードを追加
        }
        
        // 初期化
        function init() {
            const consent = getGdprConsent();
            
            if (consent === null) {
                // 未設定の場合はバナーを表示
                showBanner();
            } else if (consent === 'accepted') {
                // 同意済みの場合はアナリティクスを有効化
                enableAnalytics();
            } else if (consent === 'declined') {
                // 拒否済みの場合はアナリティクスを無効化
                disableAnalytics();
            }
        }
        
        // イベントリスナーを設定
        if (acceptBtn) {
            acceptBtn.addEventListener('click', acceptConsent);
        }
        
        if (declineBtn) {
            declineBtn.addEventListener('click', declineConsent);
        }
        
        // DOMContentLoaded で初期化
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
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
        
        const modal = document.getElementById('youtube-modal');
        const iframe = document.getElementById('youtube-iframe');
        
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
        
        iframe.src = embedUrl;
        modal.classList.add('show');
        
        // Escキーでモーダルを閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeYouTubeModal();
            }
        });
        
        // モーダル背景クリックで閉じる
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeYouTubeModal();
            }
        });
    }
    
    function closeYouTubeModal() {
        const modal = document.getElementById('youtube-modal');
        const iframe = document.getElementById('youtube-iframe');
        
        modal.classList.remove('show');
        iframe.src = '';
    }
    </script>
</body>
</html>
