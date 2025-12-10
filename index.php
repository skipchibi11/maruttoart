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

// ヒーロー背景用のランダムSVG素材を取得（1個）
$heroDecorationSql = "SELECT svg_path 
                      FROM materials 
                      WHERE svg_path IS NOT NULL 
                      ORDER BY RAND() 
                      LIMIT 1";
$heroDecorationStmt = $pdo->query($heroDecorationSql);
$heroDecoration = $heroDecorationStmt->fetch();

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

// ミニストーリーがある素材をランダムに3件取得
$storyMaterials = [];
try {
    $storyStmt = $pdo->query("
        SELECT m.id, m.title, m.slug, m.mini_story,
               m.image_path, m.webp_small_path, m.structured_bg_color,
               c.slug as category_slug
        FROM materials m
        LEFT JOIN categories c ON m.category_id = c.id
        WHERE m.mini_story IS NOT NULL 
        ORDER BY RAND()
        LIMIT 3
    ");
    $storyMaterials = $storyStmt->fetchAll();
} catch (Exception $e) {
    error_log('Error fetching story materials: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google Tag Manager & GDPR (Inline for Speed Optimization) -->
    <?php include 'includes/gdpr-gtm-inline.php'; ?>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ミニマルなフリーイラスト素材集｜marutto.art（商用利用OK）</title>
    <meta name="description" content="ミニマルなフリーイラスト素材サイト。動物や植物、食べものの素材を配布しています。組み合わせや色変更に対応した素材もあり、作品を作って共有する「みんなのアトリエ」もご利用いただけます。">
    
    <!-- Open Graph Protocol (OGP) -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/">
    <meta property="og:title" content="ミニマルなフリーイラスト素材集｜marutto.art（商用利用OK）">
    <meta property="og:description" content="ミニマルなフリーイラスト素材サイト。動物や植物、食べものの素材を配布しています。組み合わせや色変更に対応した素材もあり、作品を作って共有する「みんなのアトリエ」もご利用いただけます。">
    <meta property="og:image" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/assets/icons/logo-ogp.png">
    <meta property="og:image:alt" content="marutto.art - ミニマルなフリーイラスト素材集のロゴ">
    <meta property="og:site_name" content="marutto.art">
    <meta property="og:locale" content="ja_JP">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@maruttoart">
    <meta name="twitter:creator" content="@maruttoart">
    <meta name="twitter:title" content="ミニマルなフリーイラスト素材集｜marutto.art（商用利用OK）">
    <meta name="twitter:description" content="ミニマルなフリーイラスト素材サイト。動物や植物、食べものの素材を配布しています。組み合わせや色変更に対応した素材もあり、作品を作って共有する「みんなのアトリエ」もご利用いただけます。">
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

        /* 広告表示制御 */
        .ad-desktop-only {
            display: none;
        }
        @media (min-width: 768px) {
            .ad-desktop-only {
                display: block;
            }
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
            background: linear-gradient(135deg, #fef9e7 0%, #fff8e1 50%, #fce4ec 100%);
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
            z-index: 10;
            max-width: 100%;
        }

        .hero-text {
            flex: 1;
            padding-right: 40px;
            width: 100%;
            max-width: 100%;
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

        /* ヒーローボタンコンテナ - タイル型 */
        .hero-buttons {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-top: 2rem;
            width: 100%;
            max-width: 100%;
        }

        /* タイル型ボタン */
        .hero-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            aspect-ratio: 1 / 1;
            background: #ffffff;
            border: 1px solid #e0d8c8;
            border-radius: 14px;
            padding: 1rem;
            text-decoration: none;
            color: #7a6d62;
            transition: all 0.2s ease;
            gap: 0.5rem;
        }

        .hero-tile:hover {
            background: #f7f0e6;
            text-decoration: none;
            color: #5d4037;
        }

        .hero-tile-icon {
            width: 36px;
            height: 36px;
            color: #7a6d62;
        }

        .hero-tile-label {
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            line-height: 1.3;
        }

        .hero-image {
            display: none;
        }

        /* ヒーロー右下の装飾素材 */
        .hero-decoration {
            position: absolute;
            bottom: -50px;
            right: 20px;
            width: 310px;
            height: auto;
            opacity: 0.15;
            z-index: 1;
            pointer-events: none;
        }

        /* ヒーローセクション - レスポンシブ対応 */
        @media (max-width: 768px) {
            .hero-section {
                padding: 60px 20px 40px;
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
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }

            .hero-tile {
                padding: 0.8rem;
            }

            .hero-tile-icon {
                width: 32px;
                height: 32px;
            }

            .hero-tile-label {
                font-size: 0.75rem;
            }

            /* スマホでの装飾素材サイズ調整 */
            .hero-decoration {
                width: 205px;
                right: 10px;
                bottom: -30px;
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
                padding: 40px 15px 30px;
            }

            .hero-cta-secondary {
                font-size: 0.9rem;
                padding: 8px 20px;
            }
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

        /* ストーリーのある素材セクション */
        .story-materials-section {
            background: linear-gradient(135deg, #fff8e1 0%, #ffe9c5 100%);
            padding: 3rem 0;
        }

        .story-materials-section h2 {
            color: #d4a574;
            font-weight: 700;
        }

        .story-materials-section .text-muted {
            color: #a68b6a !important;
        }

        .story-materials-list {
            display: flex;
            flex-direction: column;
            gap: 3rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .story-material-item {
            background: #ffffff;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .story-material-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .story-item-image-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
            padding: 2rem;
        }

        .story-item-image {
            width: 100%;
            max-width: 300px;
            aspect-ratio: 1;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .story-item-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .story-item-content {
            padding: 0 2rem 2rem 2rem;
        }

        .story-item-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #d4a574;
            margin-bottom: 1.25rem;
            text-align: center;
        }

        .story-item-text {
            font-size: 1rem;
            line-height: 2;
            color: #555;
            font-family: 'Hiragino Maru Gothic ProN', 'ヒラギノ丸ゴ ProN', 'メイリオ', Meiryo, sans-serif;
            background: #fff9f0;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border-left: 4px solid #d4a574;
        }

        @media (max-width: 768px) {
            .story-materials-section {
                padding: 2rem 0;
            }

            .story-materials-list {
                gap: 2rem;
            }

            .story-item-image-wrapper {
                padding: 1.5rem;
            }

            .story-item-image {
                max-width: 250px;
            }

            .story-item-content {
                padding: 0 1.5rem 1.5rem 1.5rem;
            }

            .story-item-title {
                font-size: 1.1rem;
            }

            .story-item-text {
                font-size: 0.95rem;
                padding: 1rem;
            }
        }

        /* 自己紹介セクション */
        .profile-section {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
            padding: 3rem 0;
        }

        .profile-card {
            max-width: 700px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 1.5rem;
            padding: 3rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .profile-header {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #e8eef5;
        }

        .profile-role {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            letter-spacing: 0.05em;
        }

        .profile-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: #5a7bb5;
            margin: 0;
        }

        .profile-content {
            font-family: 'Hiragino Maru Gothic ProN', 'ヒラギノ丸ゴ ProN', 'メイリオ', Meiryo, sans-serif;
        }

        .profile-content p {
            line-height: 2;
            color: #555;
            margin-bottom: 1.5rem;
        }

        .profile-message {
            font-weight: 600;
            color: #5a7bb5;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e8eef5;
        }

        @media (max-width: 768px) {
            .profile-section {
                padding: 2rem 0;
            }

            .profile-card {
                padding: 2rem 1.5rem;
            }

            .profile-name {
                font-size: 1.5rem;
            }

            .profile-content p {
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/gdpr-gtm-noscript.php'; ?>
    
    <?php 
    $currentPage = 'home';
    include 'includes/header.php'; 
    ?>

    <!-- ヒーローセクション -->
    <section class="hero-section">
        <!-- 右下の装飾素材 -->
        <?php if ($heroDecoration): ?>
            <img src="<?= h($heroDecoration['svg_path']) ?>" 
                 alt="" 
                 class="hero-decoration">
        <?php endif; ?>
        
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1 class="hero-title">ミニマルなフリーイラスト素材集</h1>
                    <p class="hero-description">
                        まるく、やさしく、シンプルに。<br />
                        動物、植物、食べものなどのミニマルなイラスト素材を、商用・個人問わずご利用いただけます。<br />
                        <br />
                        一部の素材は色の変更や組み合わせにも対応しており、自分だけの作品をつくってアレンジできます。<br />
                        また、アレンジした作品を「みんなのアトリエ」で共有し合い、世界中のやさしい作品に触れることもできます。<br />
                    </p>
                    <div class="hero-buttons">
                        <a href="/list.php" class="hero-tile">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hero-tile-icon">
                                <rect width="7" height="7" x="3" y="3" rx="1"/>
                                <rect width="7" height="7" x="3" y="14" rx="1"/>
                                <path d="M14 4h7"/>
                                <path d="M14 9h7"/>
                                <path d="M14 15h7"/>
                                <path d="M14 20h7"/>
                            </svg>
                            <span class="hero-tile-label">素材を見る</span>
                        </a>
                        <a href="/everyone-works.php" class="hero-tile">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hero-tile-icon">
                                <path d="m11 17 2 2a1 1 0 1 0 3-3"/>
                                <path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l .47.28a2 2 0 0 0 1.42.25L21 4"/>
                                <path d="m21 3 1 11h-2"/>
                                <path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/>
                                <path d="M3 4h8"/>
                            </svg>
                            <span class="hero-tile-label">みんなの<br>アトリエ</span>
                        </a>
                        <a href="/compose2" class="hero-tile">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hero-tile-icon">
                                <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
                                <path d="m15 5 4 4"/>
                            </svg>
                            <span class="hero-tile-label">あなたの<br>アトリエ</span>
                        </a>
                        <a href="/compose2/kids.php" class="hero-tile">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hero-tile-icon">
                                <path d="M15.236 22a3 3 0 0 0-2.2-5"/>
                                <path d="M16 20a3 3 0 0 1 3-3h1a2 2 0 0 0 2-2v-2a4 4 0 0 0-4-4V4"/>
                                <path d="M18 13h.01"/>
                                <path d="M18 6a4 4 0 0 0-4 4 7 7 0 0 0-7 7c0-5 4-5 4-10.5a4.5 4.5 0 1 0-9 0 2.5 2.5 0 0 0 5 0C7 10 3 11 3 17c0 2.8 2.2 5 5 5h10"/>
                            </svg>
                            <span class="hero-tile-label">子供用の<br>アトリエ</span>
                        </a>
                    </div>
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
        
        <!-- 広告ユニット1 -->
        <div class="mt-5" style="display: flex; justify-content: center; gap: 100px; flex-wrap: wrap;">
            <?php include __DIR__ . '/includes/ad-display.php'; ?>
            <div class="ad-desktop-only">
                <?php include __DIR__ . '/includes/ad-display.php'; ?>
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
        
        <!-- 広告ユニット2 -->
        <div class="mt-5" style="display: flex; justify-content: center; gap: 100px; flex-wrap: wrap;">
            <?php include __DIR__ . '/includes/ad-display.php'; ?>
            <div class="ad-desktop-only">
                <?php include __DIR__ . '/includes/ad-display.php'; ?>
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

    <!-- ストーリーのある素材セクション -->
    <?php if (!empty($storyMaterials)): ?>
    <section class="story-materials-section mt-5 mb-5">
        <div class="container">
            <h2 class="text-center mb-2">おはなしのある子たち</h2>
            <p class="text-center text-muted mb-4">ちいさな物語とともに、やさしい時間をどうぞ</p>
            
            <div class="story-materials-list">
                <?php foreach ($storyMaterials as $storyMat): ?>
                <div class="story-material-item">
                    <!-- 画像（リンク） -->
                    <a href="/<?= h($storyMat['category_slug']) ?>/<?= h($storyMat['slug']) ?>/" class="text-decoration-none">
                        <div class="story-item-image-wrapper">
                            <?php
                            $storyImageSrc = !empty($storyMat['webp_small_path']) 
                                ? '/' . h($storyMat['webp_small_path'])
                                : '/' . h($storyMat['image_path']);
                            $storyBgColor = !empty($storyMat['structured_bg_color']) 
                                ? h($storyMat['structured_bg_color']) 
                                : '#ffffff';
                            ?>
                            <div class="story-item-image" style="background-color: <?= $storyBgColor ?>;">
                                <img src="<?= $storyImageSrc ?>" 
                                     alt="<?= h($storyMat['title']) ?>"
                                     loading="lazy"
                                     decoding="async">
                            </div>
                        </div>
                    </a>
                    
                    <!-- ストーリー（リンクなし） -->
                    <div class="story-item-content">
                        <h3 class="story-item-title"><?= h($storyMat['title']) ?></h3>
                        <div class="story-item-text">
                            <?= nl2br(h($storyMat['mini_story'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- 自己紹介セクション -->
    <section class="profile-section mt-5 mb-5">
        <div class="container">
            <div class="profile-card">
                <div class="profile-header">
                    <p class="profile-role">サイト開発・イラスト制作担当</p>
                    <h2 class="profile-name">たかせ さとる</h2>
                </div>
                <div class="profile-content">
                    <p>まるくてやさしい世界をつくりたくて、<br>
                    毎日すこしずつイラストを描いています。<br>
                    一部の素材は色変更や組み合わせにも対応しており、<br>
                    みなさんが自由にアレンジして楽しめるようにしています。</p>
                    <p class="profile-message">あなたの日常に、ふわっと寄り添う素材になれたら嬉しいです。</p>
                </div>
            </div>
        </div>
    </section>

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
