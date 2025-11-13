<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// トップページは新着6件のみ表示（背景色情報も取得）
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

// みんなのアトリエの最新承認済み作品6件を取得
$communityArtworksSql = "SELECT id, title, pen_name, webp_path, file_path, created_at 
                        FROM community_artworks 
                        WHERE status = 'approved' 
                        ORDER BY created_at DESC 
                        LIMIT 12";
$communityArtworksStmt = $pdo->prepare($communityArtworksSql);
$communityArtworksStmt->execute();
$communityArtworks = $communityArtworksStmt->fetchAll();

// タイル表示用のランダムベクター素材を取得
// 1. ベクター素材（svg_pathがある）の総数と最大IDを取得
$totalVectorMaterials = $pdo->query("SELECT COUNT(*) FROM materials WHERE svg_path IS NOT NULL")->fetchColumn();
$maxVectorId = $pdo->query("SELECT MAX(id) FROM materials WHERE svg_path IS NOT NULL")->fetchColumn();

// 2. 表示する件数を決定（最大12件、実際のベクター素材数が少ない場合はその数）
$tileCount = min(12, $totalVectorMaterials);

// 3. ベクター素材数が0の場合は空配列
$tileMaterials = [];
if ($tileCount > 0 && $maxVectorId > 0) {
    // 4. ベクター素材のIDリストを取得
    $vectorIdsStmt = $pdo->query("SELECT id FROM materials WHERE svg_path IS NOT NULL ORDER BY id");
    $vectorIds = $vectorIdsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 5. ベクター素材からランダムに選出
    $selectedIds = [];
    $vectorIdsCopy = $vectorIds; // コピーを作成
    for ($i = 0; $i < $tileCount && count($vectorIdsCopy) > 0; $i++) {
        $randomIndex = array_rand($vectorIdsCopy);
        $selectedIds[] = $vectorIdsCopy[$randomIndex];
        unset($vectorIdsCopy[$randomIndex]); // 重複排除
        $vectorIdsCopy = array_values($vectorIdsCopy); // インデックスを再設定
    }

    // 6. 選択されたIDで素材を取得
    if (!empty($selectedIds)) {
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $tileSql = "SELECT m.*, c.slug as category_slug FROM materials m 
                    LEFT JOIN categories c ON m.category_id = c.id 
                    WHERE m.id IN ($placeholders) AND m.svg_path IS NOT NULL 
                    ORDER BY RAND()";
        $tileStmt = $pdo->prepare($tileSql);
        $tileStmt->execute($selectedIds);
        $tileMaterials = $tileStmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google Tag Manager & GDPR -->
    <script src="/assets/js/gdpr-gtm.js"></script>
    
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ミニマルなフリーイラスト素材集｜marutto.art（商用利用OK）</title>
    <meta name="description" content="ミニマルなフリーイラスト素材をダウンロード！ミニマルに描かれた動物、植物、食べ物などの素材を商用利用OK。個人・法人問わずご利用いただけるフリー素材集です。">
    
    <!-- Open Graph Protocol (OGP) -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/">
    <meta property="og:title" content="ミニマルなフリーイラスト素材集｜marutto.art（商用利用OK）">
    <meta property="og:description" content="ミニマルなフリーイラスト素材をダウンロード！ミニマルに描かれた動物、植物、食べ物などの素材を商用利用OK。個人・法人問わずご利用いただけるフリー素材集です。">
    <meta property="og:image" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/assets/icons/logo-ogp.png">
    <meta property="og:image:alt" content="marutto.art - ミニマルなフリーイラスト素材集のロゴ">
    <meta property="og:site_name" content="marutto.art">
    <meta property="og:locale" content="ja_JP">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@maruttoart">
    <meta name="twitter:creator" content="@maruttoart">
    <meta name="twitter:title" content="ミニマルなフリーイラスト素材集｜marutto.art（商用利用OK）">
    <meta name="twitter:description" content="ミニマルなフリーイラスト素材をダウンロード！ミニマルに描かれた動物、植物、食べ物などの素材を商用利用OK。個人・法人問わずご利用いただけるフリー素材集です。">
    <meta name="twitter:image" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/assets/icons/logo-ogp.png">
    <meta name="twitter:image:alt" content="marutto.art - ミニマルなフリーイラスト素材集のロゴ">
    
    <!-- Icons and Apple Touch Icons -->
    <link rel="icon" href="/favicon.ico">
    <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/assets/icons/favicon-96x96.png" sizes="96x96" type="image/png">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    
    <!-- Web App Manifest -->
    <link rel="manifest" href="/site.webmanifest">
    
    <!-- Apple specific meta tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="marutto.art">
    <meta name="theme-color" content="#ffffff">
    
    <!-- Microsoft Edge/IE specific meta tags -->
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-config" content="/browserconfig.xml">
    
    <!-- Alternate language tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/" />
    
    <!-- ヒーロー画像のpreload -->
    <link rel="preload" as="image" href="/assets/images/simple-apple-red.webp" fetchpriority="high" />

    <!-- JSON-LD structured data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "marutto.art",
        "alternateName": "ミニマルなフリーイラスト素材集",
        "description": "ミニマルなフリーイラスト素材をダウンロード！ミニマルに描かれた動物、植物、食べ物などの素材を商用利用OK。個人・法人問わずご利用いただけるフリー素材集です。",
        "url": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/",
        "image": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/assets/icons/logo-ogp.png",
        "publisher": {
            "@type": "Organization",
            "name": "marutto.art",
            "logo": {
                "@type": "ImageObject",
                "url": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/assets/icons/logo-ogp.png"
            }
        },
        "potentialAction": {
            "@type": "SearchAction",
            "target": {
                "@type": "EntryPoint",
                "urlTemplate": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/list.php?search={search_term_string}"
            },
            "query-input": "required name=search_term_string"
        },
        "mainEntity": {
            "@type": "ItemList",
            "name": "フリーイラスト素材一覧",
            "numberOfItems": <?= $totalMaterialsCount ?>,
            "itemListElement": [
                <?php foreach ($materials as $index => $material): ?>
                {
                    "@type": "ImageObject",
                    "position": <?= $index + 1 ?>,
                    "name": "<?= h($material['title']) ?>",
                    "url": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?><?= !empty($material['category_slug']) ? '/' . h($material['category_slug']) . '/' . h($material['slug']) . '/' : '/detail/' . h($material['slug']) ?>",
                    "contentUrl": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/<?= h($material['webp_small_path'] ?? $material['image_path']) ?>",
                    "license": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/terms-of-use.php",
                    "creditText": "marutto.art"
                }<?= $index < count($materials) - 1 ? ',' : '' ?>
                <?php endforeach; ?>
            ]
        }
    }
    </script>

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

        /* ヒーローボタンコンテナ */
        .hero-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: flex-start;
        }

        .hero-image {
            flex: 1;
            text-align: center;
        }

        .hero-image img {
            max-width: 100%;
            height: auto;
            border-radius: 50%;
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

            .hero-buttons {
                align-items: center;
                width: 100%;
            }

            .hero-cta, .hero-cta-secondary {
                width: 100%;
                max-width: 300px;
                text-align: center;
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

            .hero-cta-secondary {
                font-size: 0.9rem;
                padding: 8px 20px;
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
            border-radius: 8px;
            will-change: transform, box-shadow;
            background-color: #F9F5E9;
            margin-bottom: 0.5rem;
            padding: 20px;
            overflow: hidden;
        }

        .material-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: inherit;
            text-decoration: none;
        }

        .material-card:focus {
            outline: none;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: inherit;
            text-decoration: none;
        }

        .card-body {
            flex: 1 1 auto;
            padding: 0.5rem 1rem 0.1rem 1rem;
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
            color: #666;
        }

        .material-image {
            width: 100% !important;
            aspect-ratio: 1 / 1 !important;
            object-fit: contain !important;
            border-radius: 4px;
            transition: opacity 0.3s ease-in-out;
            height: auto !important;
            min-height: unset !important;
            max-height: unset !important;
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

        /* ランダム素材セクション */
        .random-materials-section {
            background-color: #f9f9f9; /* 薄いグレー */
            padding-bottom: 40px;
        }

        /* タイトルスタイル */
        .random-materials-title {
            font-size: 24px;
            font-weight: 600;
            margin: 40px 0;
            color: #333;
        }

        /* 素材タイルグリッド - レスポンシブ対応 */
        .material-tiles-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr); /* スマホ: 3列 */
            gap: 1rem; /* スマホでは狭めのギャップ */
            width: 100%;
            margin-top: 20px;
        }

        @media (min-width: 576px) {
            .material-tiles-grid {
                grid-template-columns: repeat(3, 1fr); /* 小タブレット: 3列維持 */
                gap: 1.5rem;
            }
        }

        @media (min-width: 768px) {
            .material-tiles-grid {
                grid-template-columns: repeat(4, 1fr); /* タブレット: 4列 */
                gap: 2rem;
            }
        }

        @media (min-width: 992px) {
            .material-tiles-grid {
                grid-template-columns: repeat(5, 1fr); /* デスクトップ: 5列 */
                gap: 2.5rem;
            }
        }

        @media (min-width: 1200px) {
            .material-tiles-grid {
                grid-template-columns: repeat(6, 1fr); /* 大画面: 6列 */
                gap: 3rem;
            }
        }

        /* カード風デザイン */
        .material-tile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); /* より薄いシャドウ */
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            aspect-ratio: 1 / 1; /* 正方形比率 */
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10%; /* カードの10%をパディングとして確保 */
        }

        .material-tile-card:hover {
            transform: scale(1.03); /* わずかに拡大 */
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        .material-tile-link {
            display: block;
            text-decoration: none;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .material-tile-image {
            max-width: 100%; /* カード内の80%相当（paddingを考慮） */
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain; /* 画像を切り取らず、全体を表示 */
            object-position: center; /* 中央配置 */
        }

        /* スマホでのバランス調整 */
        @media (max-width: 767px) {
            .random-materials-title {
                margin: 30px 0 20px 0;
                font-size: 22px;
            }
            
            .material-tiles-grid {
                margin-top: 10px;
                gap: 0.8rem; /* 3列なのでより狭く */
            }
            
            .material-tile-card {
                padding: 6%; /* 3列なのでより狭く */
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
                    <h1 class="hero-title">ミニマルなフリーイラスト素材集</h1>
                    <p class="hero-description">
                        まるく、やさしく、シンプルに。動物や植物、食べ物などのイラスト素材を、商用・個人問わずご利用いただけます。
                    </p>
                    <div class="hero-buttons">
                        <a href="/list.php" class="hero-cta">素材を見る</a>
                        <a href="/compose2" class="hero-cta">あなたのアトリエ</a>
                        <a href="/everyone-works.php" class="hero-cta">みんなのアトリエ</a>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="/assets/images/simple-apple-red.webp" 
                         alt="ミニマルなフリーイラスト素材のサンプル" 
                         width="500"
                         height="500"
                         fetchpriority="high"
                         loading="eager">
                </div>
            </div>
        </div>
    </section>

    <!-- みんなのアトリエセクション -->
    <div class="container mt-5" id="community-artworks">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-2">みんなのアトリエ</h2>
                <p class="text-muted mb-4">
                    marutto.art の素材で生まれた、みんなのやさしい作品を自由にお楽しみください。
                </p>
            </div>
        </div>

        <?php if (!empty($communityArtworks)): ?>
        <div class="row">
            <?php foreach ($communityArtworks as $artwork): ?>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2 col-xxl-2 mb-4">
                <a href="/everyone-work.php?id=<?= h($artwork['id']) ?>" 
                   class="card material-card h-100" 
                   role="button" 
                   tabindex="0" 
                   aria-label="<?= h($artwork['title']) ?>の詳細を見る">
                    <?php
                    // レスポンシブ画像の設定
                    $imageUrl = !empty($artwork['webp_path']) ? $artwork['webp_path'] : $artwork['file_path'];
                    ?>
                    <img src="/<?= h($imageUrl) ?>" 
                         class="material-image" 
                         alt="<?= h($artwork['title']) ?>の作品"
                         loading="lazy"
                         decoding="async"
                         style="background-color: #f8f9fa;">
                    
                    <div class="card-body">
                        <p class="card-text text-muted small">by <?= h($artwork['pen_name']) ?></p>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ボタンエリア -->
        <div class="row mt-5">
            <div class="col-12 text-center load-more-button">
                <a href="/everyone-works.php" class="btn btn-outline-primary btn-lg">
                    もっと見る
                </a>
            </div>
        </div>
        
        <!-- 作品作成誘導 -->
        <div class="row mt-4">
            <div class="col-12 text-center load-more-button">
                <a href="/compose2" class="btn btn-outline-primary btn-lg">
                    あなたのアトリエで作る
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="row">
            <div class="col-12 text-center">
                <p class="text-muted">
                    まだ作品が投稿されていません。<br>
                    <a href="/compose2" class="text-decoration-none">あなたのアトリエ</a>で作品を作ってみませんか？
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="container mt-4" id="materials">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-2">新着イラスト素材</h2>
                <p class="text-muted mb-4">
                    最新のミニマルなフリーイラスト素材をご紹介
                </p>
            </div>
        </div>

        <div class="row">
            <?php foreach ($materials as $material): ?>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2 col-xxl-2 mb-4">
                <?php 
                // AIが指定した背景色を取得（フォールバックは従来の色）
                $backgroundColor = $material['structured_bg_color'] ?? '#F9F5E9';
                ?>
                <?php if (!empty($material['category_slug'])): ?>
                    <a href="/<?= h($material['category_slug']) ?>/<?= h($material['slug']) ?>/" 
                       class="card material-card h-100" 
                       role="button" 
                       tabindex="0" 
                       aria-label="<?= h($material['title']) ?>の詳細を見る">
                <?php else: ?>
                    <a href="/detail/<?= h($material['slug']) ?>" 
                       class="card material-card h-100" 
                       role="button" 
                       tabindex="0" 
                       aria-label="<?= h($material['title']) ?>の詳細を見る">
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
                             decoding="async"
                             style="background-color: <?= h($backgroundColor) ?>;">
                    </picture>
                    
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

    

    <!-- ランダム素材から探すセクション -->
    <?php if (!empty($tileMaterials)): ?>
    <div class="random-materials-section mt-5 py-5" id="random-materials">
        <div class="container">
            <!-- タイトル部分 -->
            <div class="row">
                <div class="col-12">
                    <h2 class="random-materials-title text-center">ランダム素材から探す</h2>
                </div>
            </div>
            
            <!-- タイルグリッド -->
            <div class="material-tiles-grid">
                <?php foreach ($tileMaterials as $material): ?>
                    <?php
                    // レスポンシブ画像の設定（新着画像と同じ方法）
                    $smallImage = $material['webp_small_path'] ?? $material['image_path'];
                    $mediumImage = $material['webp_medium_path'] ?? $material['image_path'];
                    // AIが指定した背景色を取得（フォールバックは白）
                    $tileBackgroundColor = $material['structured_bg_color'] ?? '#ffffff';
                    ?>
                    <div class="material-tile-card">
                        <a href="/<?= h($material['category_slug']) ?>/<?= h($material['slug']) ?>/" class="material-tile-link">
                            <picture>
                                <!-- スマホ: 180x180のWebP画像 -->
                                <source media="(max-width: 768px)" srcset="/<?= h($smallImage) ?>" type="image/webp">
                                <!-- PC: 300x300のWebP画像 -->
                                <source media="(min-width: 769px)" srcset="/<?= h($mediumImage) ?>" type="image/webp">
                                <!-- フォールバック -->
                                <img src="/<?= h($material['image_path']) ?>" 
                                     alt="<?= h($material['title']) ?>" 
                                     class="material-tile-image"
                                     loading="lazy"
                                     decoding="async"
                                     style="background-color: <?= h($tileBackgroundColor) ?>;">
                            </picture>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- GDPR Cookie Banner (CDN対応・セッション不使用) -->
    <div id="gdpr-banner" class="gdpr-cookie-banner hidden" style="position: fixed; bottom: 0; left: 0; right: 0; background-color: #212529; color: #ffffff; padding: 1rem; z-index: 1050; box-shadow: 0 -2px 10px rgba(0,0,0,0.3);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 col-md-8">
                    <div class="gdpr-text" style="color: #ffffff;">
                        当サイトではサイトの利便性向上のためCookieを使用しています。詳細は
                        <a href="/terms-of-use.php" class="text-white text-decoration-underline" style="color: #ffffff; text-decoration: underline;">利用規約</a>・
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

    <?php include 'includes/footer.php'; ?>

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
