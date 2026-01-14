<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$id = intval($_GET['id'] ?? 0);

if (empty($id)) {
    http_response_code(404);
    header('Location: /404.php');
    exit;
}

$pdo = getDB();

// 子供向け作品情報を取得（即時公開のため審査なし）
$stmt = $pdo->prepare("
    SELECT * FROM kids_artworks 
    WHERE id = ?
");
$stmt->execute([$id]);
$artwork = $stmt->fetch();

if (!$artwork) {
    http_response_code(404);
    header('Location: /404.php');
    exit;
}

// 作品画像のURLを取得（子供向けアトリエ用）
function getArtworkImageUrl($artwork) {
    $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'https';
    $host = $_SERVER['HTTP_HOST'];
    
    // WebP優先、次にimage_path
    $imagePath = '';
    if (!empty($artwork['webp_path'])) {
        $imagePath = ltrim($artwork['webp_path'], '/');
    } elseif (!empty($artwork['image_path'])) {
        $imagePath = ltrim($artwork['image_path'], '/');
    }
    
    if ($imagePath) {
        return "{$scheme}://{$host}/{$imagePath}";
    }
    
    return '';
}

// ダウンロード用の画像パスを取得（オリジナルファイルを優先）
function getDownloadImagePath($artwork) {
    $basePath = __DIR__ . '/uploads/everyone-works/';
    
    // オリジナルファイルが存在する場合は優先
    if (!empty($artwork['original_filename'])) {
        $filePath = $basePath . $artwork['original_filename'];
        if (file_exists($filePath)) {
            return "/uploads/everyone-works/{$artwork['original_filename']}";
        }
    }
    
    // image_pathも確認
    if (!empty($artwork['image_path'])) {
        // 年/月のディレクトリ構造も確認
        $year = date('Y', strtotime($artwork['created_at']));
        $month = date('m', strtotime($artwork['created_at']));
        $filePath = $basePath . $year . '/' . $month . '/' . $artwork['image_path'];
        if (file_exists($filePath)) {
            return "/uploads/everyone-works/{$year}/{$month}/{$artwork['image_path']}";
        }
        
        // 直接パスも確認
        $filePath = $basePath . $artwork['image_path'];
        if (file_exists($filePath)) {
            return "/uploads/everyone-works/{$artwork['image_path']}";
        }
    }
    
    // WebPファイルを代替として使用
    if (!empty($artwork['webp_path'])) {
        $webpPath = ltrim($artwork['webp_path'], '/');
        $filePath = __DIR__ . '/' . $webpPath;
        if (file_exists($filePath)) {
            return "/{$webpPath}";
        }
    }
    
    return '';
}

$artworkImageUrl = getArtworkImageUrl($artwork);
$downloadImagePath = getDownloadImagePath($artwork);

// 使用素材の情報を取得
$usedMaterials = [];
if (isset($artwork['used_material_ids']) && !empty($artwork['used_material_ids'])) {
    try {
        $materialIds = explode(',', $artwork['used_material_ids']);
        $materialIds = array_map('intval', $materialIds);
        $materialIds = array_filter($materialIds); // 0や空の値を除去
        
        if (!empty($materialIds)) {
            $placeholders = str_repeat('?,', count($materialIds) - 1) . '?';
            $materialStmt = $pdo->prepare("
                SELECT m.*, c.slug as category_slug 
                FROM materials m 
                LEFT JOIN categories c ON m.category_id = c.id 
                WHERE m.id IN ($placeholders)
            ");
            $materialStmt->execute($materialIds);
            $usedMaterials = $materialStmt->fetchAll();
        }
    } catch (Exception $e) {
        // カラムが存在しない場合やその他のエラーの場合は空配列を維持
        error_log("Used materials query error: " . $e->getMessage());
        $usedMaterials = [];
    }
}

// 使用素材の中でミニストーリーがあるものを取得
$materialStories = [];
if (!empty($usedMaterials)) {
    $materialStories = array_filter($usedMaterials, function($material) {
        return !empty($material['mini_story']);
    });
    // 配列を再インデックス化
    $materialStories = array_values($materialStories);
}

// ランダムな作品のIDを取得（現在の作品を除く）
$randomArtworkId = null;
try {
    $randomStmt = $pdo->prepare("
        SELECT id FROM kids_artworks 
        WHERE id != ? 
        ORDER BY RAND() 
        LIMIT 1
    ");
    $randomStmt->execute([$artwork['id']]);
    $randomResult = $randomStmt->fetch();
    if ($randomResult) {
        $randomArtworkId = $randomResult['id'];
    }
} catch (Exception $e) {
    error_log("Random artwork query error: " . $e->getMessage());
}

// 子供用アトリエのランダム作品を取得（なかまたち）
$relatedArtworks = [];
$showRelatedSection = false;
$relatedMaterials = [];
$showRelatedMaterialsSection = false;

try {
    // 他のランダムな子供作品を8件取得
    $relatedStmt = $pdo->prepare("
        SELECT 
            id,
            title,
            image_path,
            webp_path,
            created_at
        FROM kids_artworks
        WHERE id != ?
        ORDER BY RAND()
        LIMIT 8
    ");
    $relatedStmt->execute([$artwork['id']]);
    $relatedArtworks = $relatedStmt->fetchAll();
    
    // 作品がある場合のみ関連セクションを表示
    $showRelatedSection = !empty($relatedArtworks);
} catch (Exception $e) {
    error_log("Related kids artworks query error: " . $e->getMessage());
    $relatedArtworks = [];
    $showRelatedSection = false;
}

// 子供用アトリエでは関連素材セクションは表示しない
// （素材を使わずに自由に描くため）
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8053468089362860"
     crossorigin="anonymous"></script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="c5fko6zCuEianJGT3hyZsHgvNx5QAuuHKZ4TWgvV6J0">
    <title><?= h($artwork['title']) ?> - みんなのアトリエ｜marutto.art</title>
    <meta name="description" content="<?= h($artwork['title']) ?>のコミュニティ作品。フリー素材として自由にご利用いただけます。">

    <!-- Site Icons -->
    <link rel="icon" href="/favicon.ico">
    
    <!-- Canonical tag -->
    <link rel="canonical" href="https://marutto.art/everyone-work.php?id=<?= $artwork['id'] ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/everyone-work.php?id=<?= $artwork['id'] ?>">
    <meta property="og:title" content="<?= h($artwork['title']) ?> - みんなのアトリエ">
    <meta property="og:description" content="<?= h($artwork['title']) ?>のコミュニティ作品。フリー素材として自由にご利用いただけます。">
    <meta property="og:image" content="<?= h($artworkImageUrl) ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/everyone-work.php?id=<?= $artwork['id'] ?>">
    <meta property="twitter:title" content="<?= h($artwork['title']) ?> - みんなのアトリエ">
    <meta property="twitter:description" content="<?= h($artwork['title']) ?>のコミュニティ作品。フリー素材として自由にご利用いただけます。">
    <meta property="twitter:image" content="<?= h($artworkImageUrl) ?>">

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
            color: #222;
        }

        /* コンテナシステム */
        .container {
            width: 100%;
            max-width: 1140px;
            margin: 0 auto;
            padding-left: 15px;
            padding-right: 15px;
        }

        /* ヘッダー */
        .site-header {
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .site-logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #222;
            text-decoration: none;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-menu a {
            color: #222;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-menu a:hover {
            color: #007bff;
        }

        /* メインコンテンツ */
        .main-content {
            padding: 2rem 0;
        }

        /* PC用レイアウト: 画像とコンテンツを横並び */
        .artwork-content-wrapper {
            display: flex;
            gap: 2rem;
            align-items: flex-start;
        }

        @media (max-width: 768px) {
            .artwork-content-wrapper {
                flex-direction: column;
            }
        }

        .artwork-detail {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .artwork-image-container {
            text-align: center;
            padding: 0;
            background-color: transparent;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .artwork-image {
            max-width: 100%;
            max-height: 80vh;
            width: auto;
            height: auto;
            border-radius: 0;
            box-shadow: none;
            object-fit: contain;
        }



        /* セクションスタイル */
        .artwork-image-section {
            flex: 1;
            min-width: 0;
        }

        .artwork-detail-section {
            flex: 1;
            min-width: 0;
        }

        @media (max-width: 768px) {
            .artwork-image-section {
                margin-bottom: 2rem;
            }
        }

        .random-story-section {
            margin: 3rem 0;
            padding: 2rem 0;
            text-align: center;
        }

        .random-story-section .btn {
            background-color: #ffc107;
            color: #212529;
            border: none;
            border-radius: 50px;
            padding: 0.75em 2.5em;
            font-size: 1.1rem;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }

        .random-story-section .btn:hover {
            background-color: #ffb300;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 193, 7, 0.4);
            color: #212529;
            text-decoration: none;
        }

        .download-section {
            margin: 3rem 0;
            padding: 2rem 0;
            background-color: #f8f9fa;
            border-radius: 12px;
        }

        .share-section {
            margin: 3rem 0;
            padding: 2rem 0;
        }

        .used-materials-section {
            margin: 3rem 0;
            padding: 2rem 0;
            background-color: #f8f9fa;
            border-radius: 12px;
        }

        .used-materials-section h3 {
            margin-bottom: 2rem;
            color: #333;
            font-size: 1.5rem;
        }

        .materials-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1rem;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .material-item {
            background: white;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            max-width: 120px;
            margin: 0 auto;
        }

        .material-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .material-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .material-link:hover {
            text-decoration: none;
            color: inherit;
        }

        .material-thumbnail {
            aspect-ratio: 1;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .material-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .material-placeholder {
            width: 40px;
            height: 40px;
            color: #6c757d;
        }

        .material-placeholder svg {
            width: 100%;
            height: 100%;
        }

        .material-title {
            padding: 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-align: center;
            line-height: 1.2;
            color: #333;
        }

        /* 関連作品セクション */
        .related-artworks-section {
            margin: 3rem 0;
            padding: 2rem 0;
            background-color: #fff3e0;
            border-radius: 12px;
        }

        .related-artworks-section h3 {
            margin-bottom: 0.5rem;
            color: #333;
            font-size: 1.5rem;
        }

        .related-subtitle {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 2rem;
        }

        .related-artworks-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .related-artwork-item {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .related-artwork-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .related-artwork-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .related-artwork-link:hover {
            text-decoration: none;
            color: inherit;
        }

        .related-artwork-thumbnail {
            aspect-ratio: 1;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .related-artwork-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .related-artwork-placeholder {
            width: 50px;
            height: 50px;
            color: #6c757d;
        }

        .related-artwork-placeholder svg {
            width: 100%;
            height: 100%;
        }

        .related-artwork-info {
            padding: 0.75rem;
        }

        .related-artwork-title {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #333;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .related-artwork-similarity {
            font-size: 0.7rem;
            color: #ff9800;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .materials-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.75rem;
                padding: 0 0.5rem;
            }
            
            .material-item {
                max-width: 100px;
            }
            
            .material-title {
                padding: 0.3rem;
                font-size: 0.7rem;
            }

            .related-artworks-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
                padding: 0 0.5rem;
            }

            .related-artworks-section h3 {
                font-size: 1.2rem;
            }

            .related-subtitle {
                font-size: 0.8rem;
            }
        }

        /* 詳細セクションのスタイル */
        .artwork-info {
            max-width: 600px;
            margin: 0;
            text-align: left;
        }

        @media (max-width: 768px) {
            .artwork-info {
                text-align: center;
            }
        }

        .artwork-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: #212529;
        }

        .artwork-description {
            margin-bottom: 2rem;
        }

        .artwork-description h3 {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .artwork-description p {
            line-height: 1.6;
            color: #666;
        }

        .artwork-meta {
            background-color: transparent;
            padding: 0;
            border-radius: 0;
            border: none;
            margin-top: 2rem;
        }

        .meta-item {
            margin-bottom: 0.8rem;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .meta-item:last-child {
            margin-bottom: 0;
        }

        .meta-item strong {
            color: #6c757d;
            margin-right: 0.5rem;
            font-weight: 500;
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

        .btn-lg {
            padding: 0.5rem 1rem;
            font-size: 1.25rem;
            line-height: 1.5;
            border-radius: 0.3rem;
        }

        /* Download Button Style (same as load-more-button) */
        .download-section .btn {
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

        .download-section .btn:hover {
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
            text-decoration: none;
        }



        /* フッターのスタイル */
        .footer-custom {
            background-color: #fef9e7 !important;
        }

        .footer-custom .footer-text {
            color: #1a1a1a !important;
        }

        .footer-custom .footer-text:hover {
            color: #000000 !important;
        }

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

        /* ユーティリティクラス */
        .text-center {
            text-align: center !important;
        }

        .mt-5 {
            margin-top: 3rem !important;
        }

        .py-4 {
            padding-top: 1.5rem !important;
            padding-bottom: 1.5rem !important;
        }

        .mb-2 {
            margin-bottom: 0.5rem !important;
        }

        .mb-0 {
            margin-bottom: 0 !important;
        }

        .text-decoration-none {
            text-decoration: none !important;
        }

        .me-3 {
            margin-right: 1rem !important;
        }

        /* シェアボタンのスタイル */
        .share-section .text-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .share-section .text-center h3 {
            margin-bottom: 0;
        }

        .share-buttons-container {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .share-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: white !important;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        
        .share-button.twitter {
            background-color: #1da1f2;
        }
        
        .share-button.twitter:hover {
            background-color: #0d8bd9;
            box-shadow: 0 2px 4px rgba(29, 161, 242, 0.3);
        }
        
        .share-button.pinterest {
            background-color: #bd081c;
        }
        
        .share-button.pinterest:hover {
            background-color: #a50718;
            box-shadow: 0 2px 4px rgba(189, 8, 28, 0.3);
        }
        
        .share-button:hover {
            color: white !important;
            text-decoration: none;
            transform: translateY(-1px);
        }
        
        .share-button svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        /* フッター */
        .site-footer {
            background-color: #222;
            color: #fff;
            text-align: center;
            padding: 2rem 0;
            margin-top: 4rem;
        }

        /* レスポンシブ */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-menu {
                gap: 1rem;
            }

            .artwork-info {
                padding: 1.5rem;
            }

            .artwork-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .share-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        /* 使用素材のストーリーセクション */
        .material-stories-section {
            padding: 3rem 0;
        }

        .material-stories-section h2 {
            font-family: 'Hiragino Maru Gothic ProN', sans-serif;
            color: #333;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .material-stories-section .text-muted {
            color: #666 !important;
            font-size: 0.95rem;
        }

        .material-stories-list {
            max-width: 800px;
            margin: 0 auto;
        }

        .material-story-item {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
        }

        .material-story-item:last-child {
            margin-bottom: 0;
        }

        .material-story-image-wrapper {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .material-story-image {
            display: inline-block;
            max-width: 300px;
            width: 100%;
        }

        .material-story-image img {
            width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .material-story-content {
            text-align: left;
        }

        .material-story-title {
            font-family: 'Hiragino Maru Gothic ProN', sans-serif;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .material-story-title a {
            color: #333;
            text-decoration: none;
        }

        .material-story-title a:hover {
            color: #007bff;
        }

        .material-story-text {
            font-size: 1rem;
            line-height: 1.8;
            color: #555;
        }

        @media (max-width: 768px) {
            .material-stories-section {
                padding: 2rem 1rem;
            }

            .material-stories-section h2 {
                font-size: 1.6rem;
            }

            .material-story-item {
                padding: 1.5rem;
            }

            .material-story-title {
                font-size: 1.3rem;
            }
        }
    </style>
    
    <?php include __DIR__ . '/includes/gtm-head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/includes/gtm-body.php'; ?>
    
    <?php 
    $currentPage = 'kids-work';
    include 'includes/header-kids.php'; 
    ?>

    <!-- メインコンテンツ -->
    <main class="main-content">
        <div class="container">
            <div class="artwork-content-wrapper">
                <!-- 作品画像 -->
                <section class="artwork-image-section">
                    <div class="artwork-image-container">
                        <?php if (!empty($artworkImageUrl)): ?>
                        <img src="<?= h($artworkImageUrl) ?>" 
                             alt="<?= h($artwork['title']) ?>"
                             class="artwork-image"
                             loading="lazy">
                        <?php else: ?>
                        <div style="padding: 2rem; text-align: center; color: #6c757d;">
                            画像が見つかりません
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- 詳細セクション -->
                <section class="artwork-detail-section">
                    <div class="artwork-info">
                        <h1 class="artwork-title"><?= h($artwork['title']) ?></h1>
                        
                        <?php if (!empty($artwork['description'])): ?>
                        <div class="artwork-description">
                            <p><?= nl2br(h($artwork['description'])) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($artwork['ai_story'])): ?>
                        <div class="ai-story-section" style="margin-top: 1.5rem; padding: 0;">
                            <p style="line-height: 1.8; color: #212529; font-size: 1rem;">
                                <?= nl2br(h($artwork['ai_story'])) ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="artwork-meta">
                            <div class="meta-item">
                                <strong>素材提供：</strong>marutto.art
                            </div>
                            <div class="meta-item">
                                <strong>投稿日：</strong><?= date('Y-m-d', strtotime($artwork['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ほかのおはなしボタン -->
            <?php if (!empty($randomArtworkId)): ?>
            <section class="random-story-section">
                <a href="/kids-work.php?id=<?= $randomArtworkId ?>" class="btn">
                    <i class="bi bi-arrow-repeat"></i> ほかのおはなし
                </a>
            </section>
            <?php endif; ?>

            <!-- ダウンロードセクション -->
            <section class="download-section">
                <div class="text-center">
                    <?php if (!empty($downloadImagePath)): ?>
                    <a href="/download-artwork.php?id=<?= $artwork['id'] ?>&type=kids" 
                       class="btn btn-outline-primary btn-lg">
                        <i class="bi bi-download"></i> PNGダウンロード
                    </a>
                    <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #6c757d;">
                        フリー素材として商用・非商用問わずご利用いただけます（PNG形式）
                    </p>
                    <?php else: ?>
                    <p style="color: #dc3545;">ダウンロードファイルが見つかりません</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- シェアセクション -->
            <section class="share-section">
                <div class="text-center">
                    <?php
                    $currentUrl = urlencode(($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                    $shareText = urlencode($artwork['title'] . ' - みんなのアトリエ作品 #フリー素材 #無料素材 #イラスト #みんなのアトリエ');
                    $twitterShareUrl = "https://twitter.com/intent/tweet?url={$currentUrl}&text={$shareText}";
                    
                    $pinterestDescription = urlencode($artwork['title'] . ' - みんなのアトリエ作品（商用利用OK）');
                    $pinterestImageUrl = urlencode($artworkImageUrl);
                    $pinterestShareUrl = "https://pinterest.com/pin/create/button/?url={$currentUrl}&media={$pinterestImageUrl}&description={$pinterestDescription}";
                    ?>
                    
                    <div class="share-buttons-container">
                        <!-- Xシェアボタン -->
                        <a href="<?= h($twitterShareUrl) ?>" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="share-button twitter"
                           onclick="gtag('event', 'share', { 'method': 'twitter', 'content_type': 'image', 'item_id': '<?= h($artwork['id']) ?>' });">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                            </svg>
                            Xでシェア
                        </a>
                        
                        <!-- Pinterestシェアボタン -->
                        <a href="<?= h($pinterestShareUrl) ?>" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="share-button pinterest"
                           onclick="gtag('event', 'share', { 'method': 'pinterest', 'content_type': 'image', 'item_id': '<?= h($artwork['id']) ?>' });">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.174-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.097.118.110.221.082.343-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.402.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.357-.629-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24.009 12.017 24.009c6.624 0 11.99-5.367 11.99-11.988C24.007 5.367 18.641.001.017 0z"/>
                            </svg>
                            Pinterestでシェア
                        </a>
                    </div>
                </div>
            </section>

            <!-- 使用素材セクション -->
            <?php if (!empty($usedMaterials)): ?>
            <section class="used-materials-section">
                <div class="text-center">
                    <h3>イラストの子たち</h3>
                    <div class="materials-grid">
                        <?php foreach ($usedMaterials as $material): ?>
                        <div class="material-item">
                            <a href="<?= !empty($material['category_slug']) ? '/' . h($material['category_slug']) . '/' . h($material['slug']) . '/' : '/detail/' . h($material['slug']) ?>" class="material-link">
                                <div class="material-thumbnail">
                                    <?php 
                                    $thumbnailPath = '';
                                    // PNG画像を優先使用（file_path）
                                    if (!empty($material['file_path'])) {
                                        $thumbnailPath = $material['file_path'];
                                    } elseif (!empty($material['image_path'])) {
                                        $thumbnailPath = $material['image_path'];
                                    } elseif (!empty($material['webp_small_path'])) {
                                        $thumbnailPath = $material['webp_small_path'];
                                    }
                                    ?>
                                    <?php if (!empty($thumbnailPath)): ?>
                                    <img src="/<?= h($thumbnailPath) ?>" 
                                         alt="<?= h($material['title']) ?>"
                                         class="material-image"
                                         loading="lazy">
                                    <?php else: ?>
                                    <div class="material-placeholder">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M21 19V5C21 3.9 20.1 3 19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19ZM8.5 13.5L11 16.51L14.5 12L19 18H5L8.5 13.5Z" fill="currentColor"/>
                                        </svg>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="material-title">
                                    <?= h($material['title']) ?>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- 関連作品・素材セクション -->
            <?php if ($showRelatedSection || $showRelatedMaterialsSection): ?>
            <section class="related-artworks-section">
                <div class="text-center">
                    <h3>おともだちのさくひん</h3>
                    <div class="related-artworks-grid">
                        <?php 
                        // 関連作品を表示
                        if ($showRelatedSection):
                            foreach ($relatedArtworks as $relatedArtwork): ?>
                        <div class="related-artwork-item">
                            <a href="/kids-work.php?id=<?= h($relatedArtwork['id']) ?>" class="related-artwork-link">
                                <div class="related-artwork-thumbnail">
                                    <?php 
                                    $relatedThumbnail = '';
                                    // WebP画像を優先使用（パスを正規化）
                                    if (!empty($relatedArtwork['webp_path'])) {
                                        $relatedThumbnail = ltrim($relatedArtwork['webp_path'], '/');
                                    } elseif (!empty($relatedArtwork['image_path'])) {
                                        $relatedThumbnail = ltrim($relatedArtwork['image_path'], '/');
                                    }
                                    ?>
                                    <?php if (!empty($relatedThumbnail)): ?>
                                    <img src="/<?= h($relatedThumbnail) ?>" 
                                         alt="<?= h($relatedArtwork['title']) ?>"
                                         class="related-artwork-image"
                                         loading="lazy">
                                    <?php else: ?>
                                    <div class="related-artwork-placeholder">
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M21 19V5C21 3.9 20.1 3 19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19ZM8.5 13.5L11 16.51L14.5 12L19 18H5L8.5 13.5Z" fill="currentColor"/>
                                        </svg>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="related-artwork-info">
                                    <div class="related-artwork-title"><?= h($relatedArtwork['title']) ?></div>
                                </div>
                            </a>
                        </div>
                        <?php 
                            endforeach;
                        endif;
                        
                        // 関連素材を表示
                        if ($showRelatedMaterialsSection):
                            foreach ($relatedMaterials as $material): 
                            $materialUrl = !empty($material['category_slug']) 
                                ? '/' . h($material['category_slug']) . '/' . h($material['material_slug']) . '/' 
                                : '/detail/' . h($material['material_slug']);
                            $imagePath = !empty($material['material_webp_medium_path']) 
                                ? '/' . h($material['material_webp_medium_path']) 
                                : (!empty($material['material_webp_small_path']) 
                                    ? '/' . h($material['material_webp_small_path']) 
                                    : '/' . h($material['material_image_path']));
                            ?>
                        <div class="related-artwork-item">
                            <a href="<?= $materialUrl ?>" class="related-artwork-link">
                                <div class="related-artwork-thumbnail">
                                    <img src="<?= $imagePath ?>" 
                                         alt="<?= h($material['material_title']) ?>"
                                         class="related-artwork-image"
                                         loading="lazy">
                                </div>
                                <div class="related-artwork-info">
                                    <div class="related-artwork-title"><?= h($material['material_title']) ?></div>
                                </div>
                            </a>
                        </div>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </main>

    <!-- 使用素材のストーリーセクション -->
    <?php if (!empty($materialStories)): ?>
    <section class="material-stories-section mt-5 mb-5">
        <div class="container" style="max-width: 1200px;">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center mb-2">イラストの子たちのストーリー</h2>
                    <p class="text-center text-muted mb-4">この作品で使われた素材たちの物語</p>
                </div>
            </div>
            
            <div class="material-stories-list">
                <?php foreach ($materialStories as $story): ?>
                <div class="material-story-item">
                    <!-- 画像（リンク） -->
                    <?php
                    // list.phpと同じ形式のURL生成: /{category_slug}/{material_slug}/
                    $storyUrl = '/' . h($story['category_slug']) . '/' . h($story['slug']) . '/';
                    $storyImagePath = !empty($story['webp_medium_path']) 
                        ? '/' . h($story['webp_medium_path'])
                        : (!empty($story['webp_small_path']) 
                            ? '/' . h($story['webp_small_path'])
                            : '/' . h($story['image_path']));
                    ?>
                    <a href="<?= $storyUrl ?>" class="text-decoration-none">
                        <div class="material-story-image-wrapper">
                            <div class="material-story-image">
                                <img src="<?= $storyImagePath ?>" 
                                     alt="<?= h($story['title']) ?>"
                                     loading="lazy"
                                     decoding="async">
                            </div>
                        </div>
                    </a>
                    
                    <!-- ストーリー（リンクなし） -->
                    <div class="material-story-content">
                        <h3 class="material-story-title">
                            <a href="<?= $storyUrl ?>" class="text-decoration-none">
                                <?= h($story['title']) ?>
                            </a>
                        </h3>
                        <div class="material-story-text">
                            <?= nl2br(h($story['mini_story'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

</body>
</html>