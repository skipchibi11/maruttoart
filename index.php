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

// トライアル用のSVGデータを持つ作品をランダムに1件取得（正方形キャンバスのみ）
$trialArtworkSql = "SELECT id, title, svg_data
                   FROM community_artworks 
                   WHERE status = 'approved' 
                   AND svg_data IS NOT NULL 
                   AND JSON_EXTRACT(svg_data, '$.canvasWidth') = JSON_EXTRACT(svg_data, '$.canvasHeight')
                   ORDER BY RAND() 
                   LIMIT 1";
$trialArtworkStmt = $pdo->prepare($trialArtworkSql);
$trialArtworkStmt->execute();
$trialArtwork = $trialArtworkStmt->fetch();

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
    <!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8053468089362860"
         crossorigin="anonymous"></script>
    
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
            background-color: #fefbf3; /* ソフトな黄色ベージュ */
            padding-bottom: 40px;
        }

        /* タイトルスタイル */
        .random-materials-title {
            font-size: 24px;
            font-weight: 600;
            margin: 40px 0;
            color: #5d4037;
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

            .story-item-image-wrapper {
                padding-bottom: 1rem;
                margin-bottom: 1.5rem;
            }

            .story-item-image {
                max-width: 250px;
            }

            .story-item-title {
                font-size: 1.1rem;
            }

            .story-item-text {
                font-size: 0.95rem;
            }
        }

        /* アトリエトライアルセクション */
        .trial-section {
            background: linear-gradient(135deg, #fefbf3 0%, #fef5e7 100%);
            padding: 4rem 0;
            margin-bottom: 3rem;
        }

        .trial-title {
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            color: #5d4037;
            margin-bottom: 1rem;
        }

        .trial-description {
            text-align: center;
            color: #5d4037;
            font-size: 1.1rem;
            margin-bottom: 3rem;
        }

        .trial-workspace {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .trial-canvas-area {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .trial-canvas {
            width: 300px;
            height: 300px;
            max-width: 100%;
            margin: 0 auto;
            background: #ffffff;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .trial-canvas svg {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .trial-canvas svg path,
        .trial-canvas svg line,
        .trial-canvas svg polyline,
        .trial-canvas svg polygon {
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .trial-canvas g {
            cursor: move;
            transition: opacity 0.2s ease;
            pointer-events: all;
        }

        .trial-canvas g:hover {
            opacity: 0.8;
        }

        .trial-canvas g.selected {
            filter: drop-shadow(0 0 8px rgba(90, 123, 181, 0.6));
        }

        .trial-info {
            margin-top: 1rem;
            text-align: center;
        }

        .trial-artwork-title {
            font-size: 0.9rem;
            color: #6c757d;
            margin: 0;
        }

        .trial-controls {
            display: flex;
            flex-direction: row;
            gap: 1rem;
            width: 100%;
            max-width: 300px;
        }

        .trial-download-link {
            text-align: center;
            margin-top: 0.5rem;
        }

        .trial-download-link a {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            color: #6c757d;
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s ease;
        }

        .trial-download-link a:hover {
            color: #5a7bb5;
            text-decoration: underline;
        }

        .trial-download-link svg {
            flex-shrink: 0;
        }

        .trial-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            flex: 1;
        }

        .trial-btn svg {
            flex-shrink: 0;
        }

        .trial-btn-primary {
            background: #5a7bb5;
            color: white;
        }

        .trial-btn-primary:hover {
            background: #4a6ba5;
        }

        .trial-btn-danger {
            background: #dc3545;
            color: white;
        }

        .trial-btn-danger:hover {
            background: #c82333;
        }

        .trial-btn-success {
            background: #28a745;
            color: white;
        }

        .trial-btn-success:hover {
            background: #218838;
        }

        .trial-btn-link {
            background: #fef9e7;
            color: #7a6d62;
            border: 2px solid #e0d8c8;
        }

        .trial-btn-link:hover {
            background: #f7f0e6;
            color: #5d4037;
        }

        .trial-layer-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            text-align: center;
        }

        .trial-layer-info p {
            margin: 0;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .trial-layer-info strong {
            color: #5a7bb5;
            font-size: 1.1rem;
        }

        @media (max-width: 992px) {
            .trial-workspace {
                grid-template-columns: 1fr;
            }

            .trial-controls {
                flex-direction: row;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 576px) {
            .trial-section {
                padding: 2rem 0;
            }

            .trial-title {
                font-size: 1.5rem;
            }

            .trial-description {
                font-size: 1rem;
            }

            .trial-canvas-area {
                padding: 1rem;
            }

            .trial-controls {
                flex-direction: column;
            }

            .trial-btn {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
    
    <?php include __DIR__ . '/includes/analytics-script.php'; ?>
</head>
<body>
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
                        <a href="/compose" class="hero-tile">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hero-tile-icon">
                                <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
                                <path d="m15 5 4 4"/>
                            </svg>
                            <span class="hero-tile-label">あなたの<br>アトリエ</span>
                        </a>
                        <a href="/compose/kids.php" class="hero-tile">
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

    <!-- アトリエトライアルセクション -->
    <?php if ($trialArtwork): ?>
    <section class="trial-section">
        <div class="container">
            <h2 class="trial-title">ミニアトリエ</h2>
            <p class="trial-description">イラストの色や配置を楽しもう</p>
            
            <div class="trial-workspace">
                <div class="trial-canvas-area">
                    <div id="trialCanvas" class="trial-canvas"></div>
                    <div class="trial-info">
                        <p class="trial-artwork-title">元作品: <?= h($trialArtwork['title']) ?></p>
                    </div>
                </div>
                
                <div class="trial-controls">
                    <button id="randomizeColors" class="trial-btn trial-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                        </svg>
                        色を変更してみる
                    </button>
                </div>
                <div class="trial-download-link">
                    <a href="#" id="downloadTrial">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" x2="12" y1="15" y2="3"/>
                        </svg>
                        ダウンロード
                    </a>
                </div>
            </div>
        </div>
    </section>
    
    <script>
        const trialSvgData = <?= json_encode($trialArtwork['svg_data']) ?>;
    </script>
    <?php endif; ?>

    <!-- 広告ユニット（ミニアトリエとみんなのアトリエの間） -->
    <div class="container">
        <div class="mt-5" style="display: flex; justify-content: center; gap: 100px; flex-wrap: wrap;">
            <?php include __DIR__ . '/includes/ad-display.php'; ?>
            <div class="ad-desktop-only">
                <?php include __DIR__ . '/includes/ad-display.php'; ?>
            </div>
        </div>
    </div>

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
                        <h3 class="card-title"><?= h($artwork['title']) ?></h3>
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
                <a href="/compose" class="btn btn-outline-primary btn-lg">
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
                    <a href="/compose" class="text-decoration-none">あなたのアトリエ</a>で作品を作ってみませんか？
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



    <?php include 'includes/footer.php'; ?>

    <script>
    // トライアル機能のJavaScript
    if (typeof trialSvgData !== 'undefined' && trialSvgData) {
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('trialCanvas');
            const currentLayerText = document.getElementById('currentLayerText');
            
            // データ構造の確認
            console.log('Type of trialSvgData:', typeof trialSvgData);
            console.log('Raw trial data:', trialSvgData);
            
            let svgData;
            
            // 文字列の場合はパース
            if (typeof trialSvgData === 'string') {
                try {
                    svgData = JSON.parse(trialSvgData);
                    console.log('Parsed from string:', svgData);
                } catch (e) {
                    console.error('Failed to parse string data:', e);
                    canvas.innerHTML = '<p style="text-align:center;padding:2rem;color:#6c757d;">SVGデータの形式が不正です</p>';
                    return;
                }
            } else {
                svgData = trialSvgData;
            }
            
            console.log('Has layers?', svgData.layers);
            console.log('Layer count:', svgData.layers?.length);
            
            // layersが存在しない、または空の場合はエラー
            if (!svgData || !svgData.layers || !Array.isArray(svgData.layers) || svgData.layers.length === 0) {
                console.log('No valid SVG layers found');
                canvas.innerHTML = '<p style="text-align:center;padding:2rem;color:#6c757d;">SVGデータが見つかりませんでした</p>';
                return;
            }
            
            console.log('Successfully loaded', svgData.layers.length, 'layers');
            
            let currentLayerIndex = 0;
            let isDragging = false;
            let hasMoved = false;
            let startX, startY, initialX, initialY;

            // 初期描画
            renderSvg();

            function renderSvg() {
                canvas.innerHTML = '';
                
                const svgElement = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                // 元のキャンバスサイズをviewBoxとして使用（正方形）
                const originalWidth = svgData.canvasWidth || 1920;
                const originalHeight = svgData.canvasHeight || 1006;
                
                // viewBoxは元のサイズをそのまま使用
                svgElement.setAttribute('viewBox', `0 0 ${originalWidth} ${originalHeight}`);
                svgElement.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
                svgElement.setAttribute('width', '100%');
                svgElement.setAttribute('height', '100%');
                svgElement.id = 'trialSvgCanvas';
                
                // 背景色を設定
                if (svgData.backgroundColor && svgData.backgroundColor !== 'transparent') {
                    const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                    rect.setAttribute('width', '100%');
                    rect.setAttribute('height', '100%');
                    rect.setAttribute('fill', svgData.backgroundColor);
                    rect.id = 'canvasBackground';
                    svgElement.appendChild(rect);
                }

                svgData.layers.forEach((layer, index) => {
                    if (!layer.visible) return;
                    if (!layer.svgContent) return;
                    
                    const layerGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                    layerGroup.id = `trial-layer-${layer.id}`;
                    layerGroup.classList.add('trial-layer-element');
                    layerGroup.innerHTML = layer.svgContent;
                    layerGroup.dataset.layerIndex = index;
                    
                    // レイヤーのtransform情報を適用（composeと同じロジック）
                    const centerX = layer.originalCenter ? layer.originalCenter.x : 0;
                    const centerY = layer.originalCenter ? layer.originalCenter.y : 0;
                    
                    // transformオブジェクトまたは直接プロパティから値を取得
                    const x = layer.transform ? layer.transform.x : (layer.x || 0);
                    const y = layer.transform ? layer.transform.y : (layer.y || 0);
                    const scale = layer.transform ? layer.transform.scale : (layer.scale || 1);
                    const rotation = layer.transform ? layer.transform.rotation : (layer.rotation || 0);
                    const flipHorizontal = layer.transform ? layer.transform.flipHorizontal : false;
                    const flipVertical = layer.transform ? layer.transform.flipVertical : false;
                    const flipX = layer.flipX !== undefined ? layer.flipX : 1;
                    const flipY = layer.flipY !== undefined ? layer.flipY : 1;
                    
                    // 変換を適用: 移動→スケール→反転→中心回転
                    let scaleX = scale * flipX;
                    let scaleY = scale * flipY;
                    
                    if (flipHorizontal) {
                        scaleX = -scaleX;
                    }
                    
                    if (flipVertical) {
                        scaleY = -scaleY;
                    }
                    
                    const transformString = `translate(${x}, ${y}) scale(${scaleX}, ${scaleY}) rotate(${rotation}, ${centerX}, ${centerY})`;
                    layerGroup.setAttribute('transform', transformString);
                    
                    // 選択中のレイヤーにクラスを追加
                    if (index === currentLayerIndex) {
                        layerGroup.classList.add('selected');
                    }
                    
                    // レイヤークリックイベント（選択のみ）
                    layerGroup.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const idx = parseInt(this.dataset.layerIndex);
                        currentLayerIndex = idx;
                        
                        const svgRect = svgElement.getBoundingClientRect();
                        const viewBoxWidth = parseFloat(svgElement.getAttribute('viewBox').split(' ')[2]);
                        const viewBoxHeight = parseFloat(svgElement.getAttribute('viewBox').split(' ')[3]);
                        const scaleXRatio = viewBoxWidth / svgRect.width;
                        const scaleYRatio = viewBoxHeight / svgRect.height;
                        
                        startX = (e.clientX - svgRect.left) * scaleXRatio;
                        startY = (e.clientY - svgRect.top) * scaleYRatio;
                        
                        const currentLayer = svgData.layers[idx];
                        initialX = currentLayer.transform ? currentLayer.transform.x : (currentLayer.x || 0);
                        initialY = currentLayer.transform ? currentLayer.transform.y : (currentLayer.y || 0);
                        
                        isDragging = true;
                        renderSvg();
                    });
                    
                    layerGroup.addEventListener('touchstart', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const idx = parseInt(this.dataset.layerIndex);
                        currentLayerIndex = idx;
                        
                        const touch = e.touches[0];
                        const svgRect = svgElement.getBoundingClientRect();
                        const viewBoxWidth = parseFloat(svgElement.getAttribute('viewBox').split(' ')[2]);
                        const viewBoxHeight = parseFloat(svgElement.getAttribute('viewBox').split(' ')[3]);
                        const scaleXRatio = viewBoxWidth / svgRect.width;
                        const scaleYRatio = viewBoxHeight / svgRect.height;
                        
                        startX = (touch.clientX - svgRect.left) * scaleXRatio;
                        startY = (touch.clientY - svgRect.top) * scaleYRatio;
                        
                        const currentLayer = svgData.layers[idx];
                        initialX = currentLayer.transform ? currentLayer.transform.x : (currentLayer.x || 0);
                        initialY = currentLayer.transform ? currentLayer.transform.y : (currentLayer.y || 0);
                        
                        isDragging = true;
                        hasMoved = false;
                        hasMoved = false;
                        // 選択状態のみ更新
                        const allLayers = svgElement.querySelectorAll('.trial-layer-element');
                        allLayers.forEach(l => l.classList.remove('selected'));
                        this.classList.add('selected');
                    });
                    
                    svgElement.appendChild(layerGroup);
                });
                
                // マウス移動イベント（SVG要素に設定）
                svgElement.addEventListener('mousemove', function(e) {
                    if (!isDragging) return;
                    e.preventDefault();
                    
                    hasMoved = true;
                    
                    const svgRect = this.getBoundingClientRect();
                    const viewBoxWidth = parseFloat(this.getAttribute('viewBox').split(' ')[2]);
                    const viewBoxHeight = parseFloat(this.getAttribute('viewBox').split(' ')[3]);
                    const scaleXRatio = viewBoxWidth / svgRect.width;
                    const scaleYRatio = viewBoxHeight / svgRect.height;
                    
                    const currentX = (e.clientX - svgRect.left) * scaleXRatio;
                    const currentY = (e.clientY - svgRect.top) * scaleYRatio;
                    
                    const deltaX = currentX - startX;
                    const deltaY = currentY - startY;
                    
                    const currentLayer = svgData.layers[currentLayerIndex];
                    if (!currentLayer.transform) {
                        currentLayer.transform = {
                            x: currentLayer.x || 0,
                            y: currentLayer.y || 0,
                            scale: currentLayer.scale || 1,
                            rotation: currentLayer.rotation || 0,
                            flipHorizontal: false,
                            flipVertical: false
                        };
                    }
                    
                    currentLayer.transform.x = initialX + deltaX;
                    currentLayer.transform.y = initialY + deltaY;
                    
                    // 該当レイヤーのtransformのみ更新（再描画しない）
                    const layerElement = document.getElementById(`trial-layer-${currentLayer.id}`);
                    if (layerElement) {
                        const centerX = currentLayer.originalCenter ? currentLayer.originalCenter.x : 0;
                        const centerY = currentLayer.originalCenter ? currentLayer.originalCenter.y : 0;
                        const scale = currentLayer.transform.scale || 1;
                        const rotation = currentLayer.transform.rotation || 0;
                        const flipHorizontal = currentLayer.transform.flipHorizontal || false;
                        const flipVertical = currentLayer.transform.flipVertical || false;
                        const flipX = currentLayer.flipX !== undefined ? currentLayer.flipX : 1;
                        const flipY = currentLayer.flipY !== undefined ? currentLayer.flipY : 1;
                        
                        let scaleX = scale * flipX;
                        let scaleY = scale * flipY;
                        
                        if (flipHorizontal) scaleX = -scaleX;
                        if (flipVertical) scaleY = -scaleY;
                        
                        const transformString = `translate(${currentLayer.transform.x}, ${currentLayer.transform.y}) scale(${scaleX}, ${scaleY}) rotate(${rotation}, ${centerX}, ${centerY})`;
                        layerElement.setAttribute('transform', transformString);
                    }
                });
                
                svgElement.addEventListener('touchmove', function(e) {
                    if (!isDragging) return;
                    e.preventDefault();
                    
                    hasMoved = true;
                    
                    const touch = e.touches[0];
                    const svgRect = this.getBoundingClientRect();
                    const viewBoxWidth = parseFloat(this.getAttribute('viewBox').split(' ')[2]);
                    const viewBoxHeight = parseFloat(this.getAttribute('viewBox').split(' ')[3]);
                    const scaleXRatio = viewBoxWidth / svgRect.width;
                    const scaleYRatio = viewBoxHeight / svgRect.height;
                    
                    const currentX = (touch.clientX - svgRect.left) * scaleXRatio;
                    const currentY = (touch.clientY - svgRect.top) * scaleYRatio;
                    
                    const deltaX = currentX - startX;
                    const deltaY = currentY - startY;
                    
                    const currentLayer = svgData.layers[currentLayerIndex];
                    if (!currentLayer.transform) {
                        currentLayer.transform = {
                            x: currentLayer.x || 0,
                            y: currentLayer.y || 0,
                            scale: currentLayer.scale || 1,
                            rotation: currentLayer.rotation || 0,
                            flipHorizontal: false,
                            flipVertical: false
                        };
                    }
                    
                    currentLayer.transform.x = initialX + deltaX;
                    currentLayer.transform.y = initialY + deltaY;
                    
                    // 該当レイヤーのtransformのみ更新（再描画しない）
                    const layerElement = document.getElementById(`trial-layer-${currentLayer.id}`);
                    if (layerElement) {
                        const centerX = currentLayer.originalCenter ? currentLayer.originalCenter.x : 0;
                        const centerY = currentLayer.originalCenter ? currentLayer.originalCenter.y : 0;
                        const scale = currentLayer.transform.scale || 1;
                        const rotation = currentLayer.transform.rotation || 0;
                        const flipHorizontal = currentLayer.transform.flipHorizontal || false;
                        const flipVertical = currentLayer.transform.flipVertical || false;
                        const flipX = currentLayer.flipX !== undefined ? currentLayer.flipX : 1;
                        const flipY = currentLayer.flipY !== undefined ? currentLayer.flipY : 1;
                        
                        let scaleX = scale * flipX;
                        let scaleY = scale * flipY;
                        
                        if (flipHorizontal) scaleX = -scaleX;
                        if (flipVertical) scaleY = -scaleY;
                        
                        const transformString = `translate(${currentLayer.transform.x}, ${currentLayer.transform.y}) scale(${scaleX}, ${scaleY}) rotate(${rotation}, ${centerX}, ${centerY})`;
                        layerElement.setAttribute('transform', transformString);
                    }
                });
                
                svgElement.addEventListener('mouseup', function() {
                    if (isDragging) {
                        isDragging = false;
                        // 実際にドラッグした場合のみ再描画
                        if (hasMoved) {
                            renderSvg();
                        }
                    }
                });
                
                svgElement.addEventListener('touchend', function() {
                    if (isDragging) {
                        isDragging = false;
                        if (hasMoved) {
                            renderSvg();
                        }
                    }
                });
                
                svgElement.addEventListener('mouseleave', function() {
                    if (isDragging) {
                        isDragging = false;
                        if (hasMoved) {
                            renderSvg();
                        }
                    }
                });

                canvas.appendChild(svgElement);
            }

            // ランダム色変更（配列から色を選択）
            document.getElementById('randomizeColors').addEventListener('click', function() {
                // 背景用カラーパレット
                const backgroundColors = [
                    // クリーム・生成り系
                    '#FFFDF5', '#FFF8E8', '#FFF5E1', '#FFF2D8',
                    '#FDF6EC', '#FAF3E8', '#F7EFE4', '#F5EEDD',

                    // ピンク・ピーチ系
                    '#FFF0F3', '#FFEFF0', '#FFE8EC', '#FFE4E1',
                    '#FADDE1', '#F6D6DB', '#F2CED4', '#EEC6CD',

                    // グリーン系
                    '#F1F7EC', '#EAF4E1', '#E3EED7', '#DCE7CD',
                    '#EEF6E9', '#E6F0E1', '#DEE9D8', '#D6E2CF',

                    // ブルー系
                    '#EEF5FB', '#E7F0F7', '#DFEAF3', '#D7E4EF',
                    '#F0F6FA', '#E8F0F6', '#E0EAF2', '#D8E4EE',

                    // グレー・ベージュ寄り
                    '#F5F5F2', '#EFEFEA', '#E9E8E3', '#E3E2DC',
                    '#F1F0EB', '#EBEAE4', '#E5E4DD', '#DFDED6'
                ];

                // 素材用カラーパレット
                const materialColors = [
                    // ピンク・赤系
                    '#F7C1CC', '#F4B6C2', '#F1A9B8', '#EE9DAC',
                    '#F6D1D8', '#F2C3CC', '#EEB5C0', '#EAA7B4',

                    // イエロー・オレンジ系
                    '#FFE4B5', '#FFD9A0', '#FFCE8A', '#FFC374',
                    '#FFE8C7', '#FFDEB0', '#FFD499', '#FFCA82',

                    // グリーン系
                    '#CDE8D6', '#BFE0CB', '#B1D8C0', '#A3D0B5',
                    '#D6EFE3', '#C7E6D7', '#B8DDCB', '#A9D4BF',

                    // ブルー系
                    '#CFE4F6', '#BDD8EF', '#ABCBE8', '#99BFE1',
                    '#D9ECFA', '#C9E1F4', '#B9D6EE', '#A9CBE8',

                    // パープル・アクセント
                    '#E6DDF2', '#DAD0EB', '#CEC3E4', '#C2B6DD',
                    '#F0E9FA', '#E6DDF4', '#DCCFEE', '#D2C1E8'
                ];
                
                // 背景色用の色を選択する関数
                function getRandomBackgroundColor() {
                    return backgroundColors[Math.floor(Math.random() * backgroundColors.length)];
                }
                
                // 素材用の色を選択する関数
                function getRandomMaterialColor() {
                    return materialColors[Math.floor(Math.random() * materialColors.length)];
                }
                
                // 小さい要素かどうかを判定する関数（目などの小さいパーツ）
                function isSmallElement(element) {
                    const tagName = element.tagName ? element.tagName.toLowerCase() : '';
                    
                    // 円形要素のチェック
                    if (tagName === 'circle') {
                        const r = parseFloat(element.getAttribute('r') || 0);
                        // 半径30以下は小さい要素（目など）
                        return r <= 30;
                    }
                    
                    // 楕円形要素のチェック
                    if (tagName === 'ellipse') {
                        const rx = parseFloat(element.getAttribute('rx') || 0);
                        const ry = parseFloat(element.getAttribute('ry') || 0);
                        // 両方30以下の場合は小さい要素
                        return rx <= 30 && ry <= 30;
                    }
                    
                    return false;
                }
                
                // 色を変更すべきかどうかを判定する関数
                function shouldChangeColor(color) {
                    if (!color || color === 'none' || color === 'transparent' || color === '') {
                        return false;
                    }
                    return true;
                }
                
                // 背景色も変更（背景用パレットから選択）
                if (svgData.backgroundColor && svgData.backgroundColor !== 'transparent') {
                    svgData.backgroundColor = getRandomBackgroundColor();
                }
                
                svgData.layers.forEach(layer => {
                    if (!layer.svgContent) return;
                    
                    // SVGコンテンツをDOMとしてパース
                    const parser = new DOMParser();
                    const svgDoc = parser.parseFromString(`<svg xmlns="http://www.w3.org/2000/svg">${layer.svgContent}</svg>`, 'image/svg+xml');
                    
                    // レイヤーごとに色のマッピングを作成
                    const colorMap = new Map();
                    
                    // すべての要素を走査
                    function processElement(element) {
                        // 小さい要素は色を変更しない
                        if (isSmallElement(element)) {
                            return;
                        }
                        
                        // fill属性の処理
                        if (element.hasAttribute('fill')) {
                            const color = element.getAttribute('fill');
                            if (shouldChangeColor(color)) {
                                if (!colorMap.has(color)) {
                                    colorMap.set(color, getRandomMaterialColor());
                                }
                                element.setAttribute('fill', colorMap.get(color));
                            }
                        }
                        
                        // stroke属性の処理
                        if (element.hasAttribute('stroke')) {
                            const color = element.getAttribute('stroke');
                            if (shouldChangeColor(color)) {
                                if (!colorMap.has(color)) {
                                    colorMap.set(color, getRandomMaterialColor());
                                }
                                element.setAttribute('stroke', colorMap.get(color));
                            }
                        }
                        
                        // style属性の処理
                        if (element.hasAttribute('style')) {
                            let style = element.getAttribute('style');
                            
                            // style内のfill
                            style = style.replace(/fill:\s*([^;}"]+)/g, (match, color) => {
                                const trimmedColor = color.trim();
                                if (shouldChangeColor(trimmedColor)) {
                                    if (!colorMap.has(trimmedColor)) {
                                        colorMap.set(trimmedColor, getRandomMaterialColor());
                                    }
                                    return `fill:${colorMap.get(trimmedColor)}`;
                                }
                                return match;
                            });
                            
                            // style内のstroke
                            style = style.replace(/stroke:\s*([^;}"]+)/g, (match, color) => {
                                const trimmedColor = color.trim();
                                if (shouldChangeColor(trimmedColor)) {
                                    if (!colorMap.has(trimmedColor)) {
                                        colorMap.set(trimmedColor, getRandomMaterialColor());
                                    }
                                    return `stroke:${colorMap.get(trimmedColor)}`;
                                }
                                return match;
                            });
                            
                            element.setAttribute('style', style);
                        }
                        
                        // 子要素を再帰的に処理
                        Array.from(element.children).forEach(child => processElement(child));
                    }
                    
                    // ルート要素から処理開始
                    Array.from(svgDoc.documentElement.children).forEach(child => processElement(child));
                    
                    // 変更後のSVGコンテンツを取得（svg要素の内側のみ）
                    layer.svgContent = svgDoc.documentElement.innerHTML;
                });
                renderSvg();
            });

            // ダウンロード（PNG形式）
            document.getElementById('downloadTrial').addEventListener('click', function(e) {
                e.preventDefault();
                const svgElement = canvas.querySelector('svg');
                if (!svgElement) return;
                
                // SVGをシリアライズ
                const svgString = new XMLSerializer().serializeToString(svgElement);
                const svgBlob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
                const url = URL.createObjectURL(svgBlob);
                
                // 画像として読み込み
                const img = new Image();
                img.onload = function() {
                    // Canvasに描画
                    const tempCanvas = document.createElement('canvas');
                    tempCanvas.width = 1200; // 高解像度で出力
                    tempCanvas.height = 1200;
                    const ctx = tempCanvas.getContext('2d');
                    
                    // 白背景を設定
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
                    
                    // SVG画像を描画
                    ctx.drawImage(img, 0, 0, tempCanvas.width, tempCanvas.height);
                    
                    // PNGとしてダウンロード
                    tempCanvas.toBlob(function(blob) {
                        const pngUrl = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = pngUrl;
                        a.download = 'trial-artwork.png';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(pngUrl);
                        URL.revokeObjectURL(url);
                    }, 'image/png');
                };
                img.src = url;
            });
        });
    }

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
    });

    </script>
</body>
</html>
