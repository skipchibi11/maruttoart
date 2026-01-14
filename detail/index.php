<?php
require_once '../config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$slug = $_GET['slug'] ?? '';
$category_slug = $_GET['category_slug'] ?? '';

if (empty($slug) || empty($category_slug)) {
    http_response_code(404);
    header('Location: /404.php');
    exit;
}

$pdo = getDB();

// カテゴリ情報を取得
$categoryStmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
$categoryStmt->execute([$category_slug]);
$category = $categoryStmt->fetch();

if (!$category) {
    http_response_code(404);
    header('Location: /404.php');
    exit;
}

// 素材情報を取得（カテゴリも確認）
$stmt = $pdo->prepare("SELECT * FROM materials WHERE slug = ? AND category_id = ?");
$stmt->execute([$slug, $category['id']]);
$material = $stmt->fetch();

if (!$material) {
    http_response_code(404);
    header('Location: /404.php');
    exit;
}

// 素材に関連付けられたタグを取得
$materialTags = getMaterialTags($material['id'], $pdo);

// ツイート用テキストを生成
function createTweetText($title) {
    
    // ツイート用テキストを構築
    $tweetText = $title . 'のイラスト' . "\n";
    $tweetText .= '#フリー素材 #無料素材 #イラスト #clipart';
    
    return $tweetText;
}

// 構造化データ用画像のURLを取得（優先順位: 構造化データ用画像 > 通常画像）
function getStructuredDataImageUrl($material) {
    $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'https';
    $host = $_SERVER['HTTP_HOST'];
    
    // 構造化データ用画像が存在する場合は優先使用
    if (!empty($material['structured_image_path'])) {
        $absolutePath = dirname(__DIR__) . '/' . $material['structured_image_path'];
        if (file_exists($absolutePath)) {
            return "{$scheme}://{$host}/{$material['structured_image_path']}";
        }
    }
    
    // フォールバック: 通常画像を使用
    return "{$scheme}://{$host}/{$material['image_path']}";
}

$tweetText = createTweetText($material['title']);
$structuredImageUrl = getStructuredDataImageUrl($material);

// 関連画像（類似度）を取得
$relatedMaterials = [];
$showRelatedSection = false;

try {
    // ビューの存在確認
    $viewCheckStmt = $pdo->query("
        SELECT COUNT(*) as view_count 
        FROM information_schema.views 
        WHERE table_schema = DATABASE() 
        AND table_name = 'material_top_similarities'
    ");
    $viewResult = $viewCheckStmt->fetch();
    $viewExists = $viewResult['view_count'] > 0;
    
    if ($viewExists) {
        // 既存のビューを使用して類似画像を取得
        $relatedStmt = $pdo->prepare("
            SELECT 
                mts.similar_material_id as id,
                mts.similar_material_title as title,
                mts.similar_material_slug as slug,
                mts.similar_material_category_slug as category_slug,
                mts.similarity_score,
                m.image_path,
                m.webp_small_path,
                m.structured_bg_color
            FROM material_top_similarities mts
            JOIN materials m ON mts.similar_material_id = m.id
            WHERE mts.material_id = ?
            ORDER BY mts.similarity_score DESC
            LIMIT 8
        ");
        $relatedStmt->execute([$material['id']]);
        $relatedMaterials = $relatedStmt->fetchAll();
        
        // 類似度データがある場合のみ関連セクションを表示
        if (!empty($relatedMaterials)) {
            $showRelatedSection = true;
        }
    }
} catch (Exception $e) {
    // エラーが発生した場合は関連セクションを表示しない
}

// 関連するみんなのアトリエ作品を取得
$relatedArtworks = [];
$showRelatedArtworksSection = false;

try {
    // ビューの存在確認
    $viewCheckStmt = $pdo->query("
        SELECT COUNT(*) as view_count 
        FROM information_schema.views 
        WHERE table_schema = DATABASE() 
        AND table_name = 'material_related_community_artworks'
    ");
    $viewResult = $viewCheckStmt->fetch();
    $viewExists = $viewResult['view_count'] > 0;
    
    if ($viewExists) {
        $artworkStmt = $pdo->prepare("
            SELECT * FROM material_related_community_artworks
            WHERE material_id = ?
            ORDER BY similarity_score DESC
        ");
        $artworkStmt->execute([$material['id']]);
        $relatedArtworks = $artworkStmt->fetchAll();
        $showRelatedArtworksSection = !empty($relatedArtworks);
    }
} catch (Exception $e) {
    error_log('Error fetching related community artworks: ' . $e->getMessage());
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
    <title><?= h($material['title']) ?>  - ミニマルなフリーイラスト素材（商用利用OK）｜marutto.art</title>
    <meta name="description" content="<?= h($material['title']) ?>のミニマルなフリーイラスト素材（商用利用OK）。<?= h($category['title']) ?>カテゴリのフリーイラストをお楽しみください。">

    <!-- Site Icons -->
    <link rel="icon" href="/favicon.ico">
    
    <!-- Canonical tag -->
    <link rel="canonical" href="https://marutto.art/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/">
    
    <!-- Alternate language tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/" />
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/">
    <meta property="og:title" content="<?= h($material['title']) ?> - ミニマルなフリーイラスト素材（商用利用OK）">
    <meta property="og:description" content="<?= h($material['title']) ?>のミニマルなフリーイラスト素材（商用利用OK）。">
    <meta property="og:image" content="<?= h($structuredImageUrl) ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/">
    <meta property="twitter:title" content="<?= h($material['title']) ?> - ミニマルなフリーイラスト素材（商用利用OK）">
    <meta property="twitter:description" content="<?= h($material['title']) ?>のミニマルなフリーイラスト素材（商用利用OK）。">
    <meta property="twitter:image" content="<?= h($structuredImageUrl) ?>">
    
    <!-- JSON-LD structured data -->
    <?php if (!empty($material['ai_product_image_path'])): ?>
    <!-- 複数画像がある場合：配列形式 -->
    <script type="application/ld+json">
    [{
        "@context": "https://schema.org",
        "@type": "ImageObject",
        "contentUrl": "<?= h($structuredImageUrl) ?>",
        "license": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/terms-of-use.php",
        "acquireLicensePage": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/terms-of-use.php",
        "creditText": "marutto.art",
        "creator": {
            "@type": "Organization",
            "name": "marutto.art"
        },
        "copyrightNotice": "marutto.art"
    },
    {
        "@context": "https://schema.org",
        "@type": "ImageObject",
        "contentUrl": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/<?= h($material['ai_product_image_path']) ?>",
        "license": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/terms-of-use.php",
        "acquireLicensePage": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/terms-of-use.php",
        "creditText": "marutto.art",
        "creator": {
            "@type": "Organization",
            "name": "marutto.art"
        },
        "copyrightNotice": "marutto.art"
    }]
    </script>
    <?php else: ?>
    <!-- 単一画像の場合：従来の形式 -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ImageObject",
        "contentUrl": "<?= h($structuredImageUrl) ?>",
        "license": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/terms-of-use.php",
        "acquireLicensePage": "<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/terms-of-use.php",
        "creditText": "marutto.art",
        "creator": {
            "@type": "Organization",
            "name": "marutto.art"
        },
        "copyrightNotice": "marutto.art"
    }
    </script>
    <?php endif; ?>
    
    <!-- パンくずリスト構造化データ（JavaScript動的生成） -->
    <script type="application/ld+json" id="breadcrumb-structured-data">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": []
    }
    </script>


    
    <script>
    // GTtranslate対応：JavaScript動的パンくずリスト構造化データ生成
    (function() {
        // 現在のURLから言語を検出
        const currentUrl = window.location.pathname;
        const pathParts = currentUrl.split('/').filter(part => part !== '');
        
        // 対応言語リスト
        const supportedLangs = ['en', 'es', 'fr', 'nl'];
        
        let detectedLang = '';
        let langPrefix = '';
        
        // URLの最初の部分が言語コードかチェック
        if (pathParts.length > 0 && supportedLangs.includes(pathParts[0])) {
            detectedLang = pathParts[0];
            langPrefix = '/' + detectedLang;
        }
        
        // 言語別のホーム名
        const homeNames = {
            '': 'ホーム',      // 日本語（デフォルト）
            'en': 'Home',
            'es': 'Inicio',
            'fr': 'Accueil',
            'nl': 'Home'
        };
        
        const homeName = homeNames[detectedLang] || homeNames[''];
        const baseUrl = window.location.protocol + '//' + window.location.host;
        
        // パンくずリスト構造化データを動的生成
        const breadcrumbData = {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            "itemListElement": [
                {
                    "@type": "ListItem",
                    "position": 1,
                    "name": homeName,
                    "item": baseUrl + langPrefix + "/"
                },
                {
                    "@type": "ListItem",
                    "position": 2,
                    "name": "<?= h($category['title']) ?>",
                    "item": baseUrl + langPrefix + "/<?= h($category['slug']) ?>/"
                },
                {
                    "@type": "ListItem",
                    "position": 3,
                    "name": "<?= h($material['title']) ?>"
                }
            ]
        };
        
        // 構造化データを更新
        const scriptElement = document.getElementById('breadcrumb-structured-data');
        if (scriptElement) {
            scriptElement.textContent = JSON.stringify(breadcrumbData, null, 2);
        }
    })();
    </script>
    

    
    <style>
        /* リセットCSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html {
            /* スクロール動作の確保 */
            -webkit-overflow-scrolling: touch;
            touch-action: manipulation;
            overflow-y: scroll;
        }

        body {
            background-color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            line-height: 1.5;
            color: #222;
            /* スクロール動作の改善 */
            -webkit-overflow-scrolling: touch;
            touch-action: pan-y pinch-zoom;
            overflow-x: hidden;
            overflow-y: auto;
        }

        /* コンテナシステム */
        .container {
            width: 100%;
            max-width: 1140px;
            margin: 0 auto;
            padding-left: 15px;
            padding-right: 15px;
            /* スクロール動作の確保 */
            touch-action: pan-y;
        }

        /* グリッドシステム */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-left: -15px;
            margin-right: -15px;
        }

        /* カラムクラスの基本設定 */
        .col-6,
        .col-md-4,
        .col-lg-3,
        .col-xl-2,
        .col-xxl-2 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }

        @media (min-width: 768px) {
            .col-md-4 {
                flex: 0 0 33.333333%;
                max-width: 33.333333%;
            }
        }

        @media (min-width: 992px) {
            .col-lg-3 {
                flex: 0 0 25%;
                max-width: 25%;
            }
        }

        @media (min-width: 1200px) {
            .col-xl-2 {
                flex: 0 0 16.666667%;
                max-width: 16.666667%;
            }
        }

        @media (min-width: 1400px) {
            .col-xxl-2 {
                flex: 0 0 16.666667%;
                max-width: 16.666667%;
            }
        }
            margin-left: -15px;
            margin-right: -15px;
        }

        .col-lg-6 {
            position: relative;
            width: 100%;
            padding-left: 15px;
            padding-right: 15px;
            flex: 0 0 100%;
            max-width: 100%;
        }

        @media (min-width: 992px) {
            .col-lg-6 {
                flex: 0 0 50%;
                max-width: 50%;
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
            margin-bottom: 1.5rem;
        }

        .card-body {
            flex: 1 1 auto;
            padding: 0.5rem 1rem 0.1rem 1rem;
        }

        .card-header {
            padding: 0.75rem 1.25rem;
            margin-bottom: 0;
            background-color: rgba(0,0,0,.03);
            border-bottom: 1px solid rgba(0,0,0,.125);
            border-top-left-radius: calc(0.25rem - 1px);
            border-top-right-radius: calc(0.25rem - 1px);
        }

        /* ユーティリティクラス */
        .text-center { text-align: center !important; }
        .text-muted { color: #6c757d !important; }
        .mb-0 { margin-bottom: 0 !important; }
        .mb-2 { margin-bottom: 0.5rem !important; }
        .mb-3 { margin-bottom: 1rem !important; }
        .mb-4 { margin-bottom: 1.5rem !important; }
        .mt-3 { margin-top: 1rem !important; }
        .mt-4 { margin-top: 1.5rem !important; }
        .mt-5 { margin-top: 3rem !important; }
        .py-4 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
        .bg-light { background-color: #f8f9fa !important; }
        .d-inline-flex { display: inline-flex !important; }
        .align-items-center { align-items: center !important; }
        .me-1 { margin-right: 0.25rem !important; }
        .me-2 { margin-right: 0.5rem !important; }

        /* メイン画像のスタイル */
        .detail-main-image {
            max-width: 100%;
            width: 100%;
            height: auto;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            border-radius: 8px;
            background-color: #F9F5E9;
            padding: 40px;
            box-sizing: border-box;
        }

        @media (min-width: 768px) {
            .detail-main-image {
                max-width: 400px;
                width: auto;
            }
        }

        /* カード内画像のスタイル（関連素材用） */
        .material-image {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            border-radius: 4px;
            transition: opacity 0.3s ease-in-out;
            background-color: #F9F5E9;
        }
        
        /* タグのスタイル */
        .tags-section {
            margin-bottom: 1rem;
        }
        
        .tags-label {
            color: #222;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .tag-item {
            display: inline-block;
            background-color: #e9ecef;
            color: #222 !important;
            padding: 0.25rem 0.5rem;
            margin: 0.125rem;
            border-radius: 0.25rem;
            text-decoration: none;
            font-size: 0.875rem;
            transition: background-color 0.2s ease;
        }
        
        .tag-item:hover {
            background-color: #dee2e6;
            color: #222 !important;
            text-decoration: none;
        }
        
        /* シェアボタンのスタイル */
        .share-section {
            margin: 1rem 0;
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
            margin: 0 0.25rem;
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
        
        /* 画材のスタイル */
        .art-materials-section {
            margin-bottom: 1rem;
        }
        
        .art-material-item {
            background-color: #f8f9fa;
            color: #222 !important;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        
        .art-material-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        /* ボタンのスタイル */
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
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
        }

        .btn:hover {
            color: #212529;
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
        
        /* AI生成製品画像セクションのスタイル - シンプルセンター配置 */
        #ai-product-section {
            padding: 40px 0;
            text-align: center;
        }

        /* Bootstrap rowクラスのflexを無効化 */
        #ai-product-section .row {
            display: block !important;
            margin: 0;
        }

        #ai-product-section .col-12 {
            padding: 0;
        }

        .ai-section-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            max-width: 400px;
            margin: 0 auto;
        }

        .ai-product-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .ai-product-subtitle {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .ai-product-card {
            background: transparent;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s ease;
            max-width: 280px;
            width: 100%;
            margin: 0 auto;
        }

        .ai-product-card:hover {
            transform: translateY(-1px);
        }

        .ai-product-image-wrapper {
            position: relative;
            aspect-ratio: 1 / 1;
            overflow: hidden;
            border-radius: 12px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
        }

        .ai-product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: opacity 0.3s ease;
        }

        .ai-product-description {
            padding: 15px 0 0;
            text-align: center;
        }

        .ai-product-description p {
            margin-bottom: 0;
            font-size: 0.85rem;
            line-height: 1.5;
            color: #6c757d;
        }

        .ai-product-title {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 8px;
            color: #495057;
        }

        .ai-product-subtitle {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 20px;
        }

        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            #ai-product-section {
                padding: 20px 0;
                margin: 30px 0;
            }

            .ai-product-card {
                max-width: 220px;
            }

            .ai-product-description p {
                font-size: 0.8rem;
            }

            .ai-product-title {
                font-size: 1rem;
            }

            .ai-product-subtitle {
                font-size: 0.85rem;
            }
        }
        
        /* フッターのスタイル */
        .footer-custom {
            background-color: #fef9e7 !important;
            color: #222;
        }

        .footer-custom .footer-text {
            color: #222 !important;
        }

        .footer-custom .footer-text:hover {
            color: #0d6efd !important;
            text-decoration: underline !important;
        }

        /* プライバシーポリシーリンクのスタイル */
        .footer-custom a.footer-text {
            transition: color 0.2s ease;
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

        .video-icon-overlay {
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

        .video-icon-overlay::before {
            content: '▶';
            font-size: 10px;
        }

        /* レスポンシブ調整 */
        @media (max-width: 768px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            .navbar-brand {
                font-size: 1.5rem;
            }
            /* モバイル向けメイン画像サイズ調整 */
            .detail-main-image {
                padding: 25px;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            .card-body {
                padding: 0.5rem 0.75rem 0.1rem 0.75rem;
            }
            /* 小型スマホ向けメイン画像サイズ調整 */
            .detail-main-image {
                padding: 20px;
            }
        }
        
        /* ダウンロードリンクのスタイル */
        .download-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: center;
        }
        
        .download-link {
            color: #222;
            text-decoration: none;
            font-size: 0.95rem;
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.2s, border-color 0.2s, background-color 0.2s;
            min-width: 200px;
            justify-content: center;
        }
        
        .download-link:hover {
            color: #222;
            background-color: #f8f9fa;
            border-color: #adb5bd;
            text-decoration: none;
        }
        
        .svg-download {
            border-color: #28a745;
            color: #28a745;
        }
        
        .svg-download:hover {
            background-color: #28a745;
            color: white;
            border-color: #28a745;
        }
        
        @media (min-width: 576px) {
            .download-buttons {
                flex-direction: row;
                justify-content: center;
            }
        }
        
        /* ダウンロード注記のスタイル */
        .download-notes {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            padding: 0.75rem 1rem;
            margin: 0 auto;
            max-width: 400px;
            text-align: center;
            line-height: 1.4;
        }
        
        .download-notes strong {
            color: #495057;
        }
        
        @media (max-width: 576px) {
            .download-notes {
                max-width: 100%;
                font-size: 0.85rem;
                padding: 0.6rem 0.8rem;
            }
        }
        
        /* コンテンツのテキストスタイル */
        .detail-title {
            color: #222;
            font-size: 1rem;
            font-weight: 400;
        }
        
        .detail-description {
            color: #222;
            font-size: 1rem;
            line-height: 1.6;
        }
        
        .detail-date {
            color: #222;
            font-size: 0.875rem;
        }
        
        /* 関連動画ヘッダーのスタイル */
        .video-header {
            color: #222;
            font-size: 1rem;
            font-weight: 400;
        }
        
        .tags-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f0f0f0;
        }
        
        .art-materials-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f0f0f0;
        }
        
        .art-material-color {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .tags-label {
            color: #222;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        /* パンくずリストのスタイル */
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
            font-size: 0.875rem;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
            color: #222;
        }
        
        .breadcrumb-item a {
            color: #222;
            text-decoration: none;
        }
        
        .breadcrumb-item a:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
        
        .breadcrumb-item.active {
            color: #222;
        }

        /* フッターのスタイル */
        .footer-custom {
            background-color: #fef9e7 !important;
        }

        /* フッター文字色の改善（コントラスト対応） */
        .footer-custom .footer-text {
            color: #2c3e50 !important;
        }

        .footer-custom .footer-text:hover {
            color: #1a252f !important;
        }

        /* Load More Button - トップページと同じスタイル */
        .load-more-button {
            text-align: center;
        }
        
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

        /* 関連画像セクション */
        .related-materials {
            background-color: #F9F5E9;
            padding: 2rem 0;
            border-radius: 8px;
        }

        .related-materials .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-radius: 8px;
            overflow: hidden;
            background-color: #fff;
        }

        .related-materials .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }

        .related-materials .card-img-top-wrapper {
            position: relative;
            padding-bottom: 100%; /* 1:1 aspect ratio for square */
            overflow: hidden;
            border-radius: 8px;
        }

        .related-materials .card-img-top {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* PC用：確実に6列表示にする */
        @media (min-width: 992px) {
            .related-materials .col-lg-2 {
                flex: 0 0 auto;
                width: 16.66666667%;
            }
        }

        /* タブレット用：左右余白と3列表示 */
        @media (min-width: 768px) and (max-width: 991px) {
            .related-materials {
                padding: 1.5rem 1rem;
            }
            
            .related-materials .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .related-materials .row > .col-md-3 {
                flex: 0 0 auto;
                width: 33.33333333% !important;
                max-width: 33.33333333%;
            }
        }

        @media (max-width: 576px) {
            .related-materials {
                padding: 1.5rem 1rem;
                margin: 0 -15px;
            }
            
            .related-materials .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }

        /* ミニストーリーセクション */
        .mini-story-section {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
            padding: 3rem 0;
            margin: 3rem 0;
        }

        .mini-story-container {
            max-width: 700px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 1.5rem;
            padding: 3rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }

        .mini-story-row {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        .mini-story-image-col {
            width: 100%;
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #e8eef5;
        }

        .mini-story-text-col {
            width: 100%;
        }

        .mini-story-image-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
        }

        .mini-story-image {
            width: 100%;
            max-width: 300px;
            aspect-ratio: 1;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .mini-story-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .mini-story-content {
            padding: 0;
            font-family: 'Hiragino Maru Gothic ProN', 'ヒラギノ丸ゴ ProN', 'メイリオ', Meiryo, sans-serif;
        }

        .mini-story-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #5a7bb5;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .mini-story-icon {
            color: #5a7bb5;
            flex-shrink: 0;
        }

        .mini-story-text {
            font-size: 1rem;
            line-height: 2;
            color: #555;
            text-align: center;
        }

        @media (max-width: 768px) {
            .mini-story-section {
                padding: 2rem 0;
                margin: 2rem 0;
            }

            .mini-story-container {
                padding: 2rem 1.5rem;
            }

            .mini-story-image-col {
                margin-bottom: 1.5rem;
                padding-bottom: 1rem;
            }

            .mini-story-title {
                font-size: 1.1rem;
            }

            .mini-story-text {
                font-size: 0.95rem;
            }

            .mini-story-image {
                max-width: 250px;
            }
        }

        /* SVG表示セクション */
        .svg-display-section {
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
            border: 2px solid #e8f0fe;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .svg-section-title {
            color: #4285f4;
            font-weight: 600;
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .svg-section-title::before {
            content: '🎨';
            font-size: 1.2rem;
        }

        .svg-container {
            text-align: center;
            padding: 2rem;
            overflow: visible;
        }

        .svg-image-wrapper {
            background: #ffffff;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 1rem;
            display: inline-block;
            max-width: 100%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s ease;
            min-height: 300px;
            width: 400px;
            /* タッチスクロールの改善 */
            touch-action: manipulation;
            -webkit-overflow-scrolling: touch;
        }

        .svg-image-wrapper .svg-inline {
            max-width: 100%;
            height: auto;
            max-height: 400px;
            display: block;
            margin: 0 auto;
        }

        .svg-info {
            margin-top: 0.75rem;
            color: #666;
            font-size: 0.875rem;
        }

        .svg-info .bi {
            color: #4285f4;
            margin-right: 0.25rem;
        }

        /* SVG色変更コントロール */
        .svg-controls {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid #e9ecef;
        }

        .svg-controls .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }

        .form-control-color {
            width: 50px;
            height: 38px;
            border-radius: 6px;
            border: 2px solid #dee2e6;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .form-control-color:hover {
            border-color: #4285f4;
            transform: scale(1.05);
        }

        .form-control-color:focus {
            border-color: #4285f4;
            box-shadow: 0 0 0 0.2rem rgba(66, 133, 244, 0.25);
        }

        .svg-controls .btn {
            transition: all 0.2s ease;
        }

        .svg-controls .btn:hover {
            transform: translateY(-1px);
        }

        /* スポイト機能用スタイル */
        .color-preview {
            width: 40px;
            height: 32px;
            border: 2px solid #dee2e6;
            border-radius: 4px;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .color-preview:hover {
            border-color: #4285f4;
        }

        .eyedropper-mode .svg-inline {
            cursor: crosshair !important;
        }

        .eyedropper-mode .svg-inline * {
            cursor: crosshair !important;
        }

        .btn-check {
            position: absolute;
            clip: rect(0, 0, 0, 0);
            pointer-events: none;
        }

        .btn-check:checked + .btn {
            background-color: #4285f4;
            border-color: #4285f4;
            color: white;
        }

        /* タブアイコン用スタイル */
        .tab-icons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .tab-icon {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #6c757d;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            min-width: 60px;
        }

        .tab-icon:hover {
            background: #e9ecef;
            border-color: #4285f4;
            color: #4285f4;
            transform: translateY(-2px);
        }

        .tab-icon.active {
            background: #4285f4;
            border-color: #4285f4;
            color: white;
        }

        .tab-icon.active .tab-icon-img {
            color: white;
        }

        .tab-icon-img {
            width: 24px;
            height: 24px;
            transition: color 0.3s ease;
        }

        /* 黒・グレー除外設定スタイル */
        .exclude-colors-section {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
        }

        .exclude-colors-section .form-label {
            font-weight: 600;
            color: #495057;
        }

        .form-check-input:checked {
            background-color: #4285f4;
            border-color: #4285f4;
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(66, 133, 244, 0.25);
        }

        /* カラーパレット用スタイル */
        .color-palette {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 16px;
            padding: 2rem;
            border: 2px solid #e3f2fd;
            min-height: 140px;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            flex-wrap: wrap;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .color-palette:hover {
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1);
        }

        .color-palette.loaded {
            animation: fadeIn 0.5s ease-out;
        }

        .color-palette .text-center {
            width: 100%;
            margin-bottom: 1rem;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* 背景色パレット用スタイル */
        .bg-color-section {
            background: linear-gradient(135deg, #fff8f0 0%, #f8f9fa 100%);
            border-radius: 16px;
            padding: 1.5rem;
            border: 2px solid #f0f0f0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }

        .bg-color-palette {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .bg-color-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 0.5rem;
            border-radius: 12px;
        }

        .bg-color-item:hover {
            transform: translateY(-2px);
            background-color: rgba(66, 133, 244, 0.1);
        }

        .bg-color-item.active {
            background-color: rgba(66, 133, 244, 0.15);
            transform: translateY(-2px);
        }

        .bg-color-swatch {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid #dee2e6;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
            margin-bottom: 0.5rem;
        }

        .bg-color-item:hover .bg-color-swatch {
            border-color: #4285f4;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.3);
        }

        .bg-color-item.active .bg-color-swatch {
            border-color: #4285f4;
            border-width: 4px;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.4);
        }

        .transparent-bg {
            background: linear-gradient(45deg, #ddd 25%, transparent 25%), 
                        linear-gradient(-45deg, #ddd 25%, transparent 25%), 
                        linear-gradient(45deg, transparent 75%, #ddd 75%), 
                        linear-gradient(-45deg, transparent 75%, #ddd 75%);
            background-size: 10px 10px;
            background-position: 0 0, 0 5px, 5px -5px, -5px 0px;
        }

        .bg-color-label {
            font-size: 0.75rem;
            color: #666;
            font-weight: 500;
            text-align: center;
        }

        .bg-color-item.active .bg-color-label {
            color: #4285f4;
            font-weight: 600;
        }

        /* 新しい背景色コントロール用スタイル */
        .bg-color-controls {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        .bg-color-btn {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 60px;
        }

        .bg-color-btn:hover {
            border-color: #4285f4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.2);
        }

        .bg-color-btn.active {
            border-color: #4285f4;
            border-width: 3px;
            background-color: rgba(66, 133, 244, 0.1);
        }

        .bg-swatch {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-bottom: 0.25rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .bg-color-btn small {
            font-size: 0.7rem;
            color: #666;
            font-weight: 500;
        }

        .bg-color-btn.active small {
            color: #4285f4;
            font-weight: 600;
        }

        /* 季節テーマ用スタイル */
        .seasonal-themes-section {
            border-top: 1px solid #e9ecef;
            padding-top: 1rem;
        }

        .seasonal-themes {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .seasonal-btn {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 0.75rem 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 70px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .seasonal-btn:hover {
            border-color: #4285f4;
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(66, 133, 244, 0.2);
        }

        .seasonal-btn:active {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(66, 133, 244, 0.3);
        }

        .seasonal-icon {
            margin-bottom: 0.25rem;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .seasonal-icon svg {
            width: 24px;
            height: 24px;
            color: #666;
            transition: color 0.3s ease;
        }

        .seasonal-btn small {
            font-size: 0.7rem;
            color: #666;
            font-weight: 500;
            text-transform: capitalize;
        }

        .seasonal-btn:hover small {
            color: #4285f4;
            font-weight: 600;
        }
        
        .seasonal-btn:hover .seasonal-icon svg {
            color: #4285f4;
        }

        /* スマホ用レスポンシブデザイン（季節テーマボタン） */
        @media (max-width: 768px) {
            .seasonal-themes {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 0.75rem;
                justify-items: center;
                margin: 0 auto;
            }

            .seasonal-btn {
                min-width: 60px;
                padding: 10px 8px;
            }

            .seasonal-icon svg {
                width: 20px;
                height: 20px;
            }
        }

        /* 非常に小さいスマホ用 */
        @media (max-width: 576px) {
            .seasonal-themes {
                gap: 0.5rem;
            }

            .seasonal-btn {
                min-width: 50px;
                padding: 8px 6px;
            }

            .seasonal-icon svg {
                width: 18px;
                height: 18px;
            }
        }

        /* 移動コントロール用スタイル */
        .move-controls {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
        }

        .move-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        .move-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            background: white;
            color: #6c757d;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            gap: 0.25rem;
            padding: 0.5rem;
        }

        .move-btn:hover {
            border-color: #4285f4;
            color: #4285f4;
            background-color: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(66, 133, 244, 0.2);
        }

        .move-btn:active {
            transform: translateY(0);
            box-shadow: 0 1px 4px rgba(66, 133, 244, 0.3);
        }

        .move-btn svg {
            margin-bottom: 0.125rem;
        }

        /* 拡大縮小コントロール */
        .scale-controls {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
        }

        .scale-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .scale-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 40px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            background: white;
            color: #6c757d;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 0.5rem;
        }

        .scale-btn:hover {
            border-color: #4285f4;
            color: #4285f4;
            background-color: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(66, 133, 244, 0.2);
        }

        .scale-btn.active {
            border-color: #4285f4;
            background-color: #4285f4;
            color: white;
            font-weight: 700;
        }

        .scale-btn.active:hover {
            background-color: #3367d6;
            border-color: #3367d6;
        }

        .scale-btn:active {
            transform: translateY(0);
            box-shadow: 0 1px 4px rgba(66, 133, 244, 0.3);
        }

        .scale-reset {
            display: flex;
            justify-content: center;
        }

        /* 回転コントロール */
        .rotate-controls {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
        }

        .rotate-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2rem;
        }

        .rotate-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            background: white;
            color: #6c757d;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            gap: 0.5rem;
            padding: 0.75rem;
        }

        .rotate-btn:hover {
            border-color: #4285f4;
            color: #4285f4;
            background-color: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.2);
        }

        .rotate-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(66, 133, 244, 0.3);
        }

        .rotate-btn svg {
            margin-bottom: 0.25rem;
        }

        .rotate-reset {
            display: flex;
            justify-content: center;
        }

        .color-item {
            display: inline-block;
            margin: 0.75rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 12px;
            padding: 0.5rem;
            vertical-align: top;
            width: 120px;
            min-height: 140px;
        }

        .color-item:hover {
            transform: scale(1.05) translateY(-2px);
            background-color: rgba(66, 133, 244, 0.08);
        }

        .color-swatch {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 4px solid #ffffff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15), 
                        0 2px 4px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            background-clip: padding-box;
        }

        .color-swatch:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2), 
                        0 4px 8px rgba(0, 0, 0, 0.15);
            border-color: rgba(66, 133, 244, 0.3);
        }

        .color-swatch.active {
            border-color: #4285f4;
            border-width: 5px;
            transform: scale(1.08);
            box-shadow: 0 8px 30px rgba(66, 133, 244, 0.3), 
                        0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .color-swatch.active::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
            z-index: 1;
        }

        .color-code {
            font-size: 0.8rem;
            color: #495057;
            font-weight: 600;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            background-color: rgba(108, 117, 125, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            display: inline-block;
            margin-top: 0.25rem;
            transition: all 0.3s ease;
        }

        .color-item:hover .color-code {
            background-color: rgba(66, 133, 244, 0.1);
            color: #4285f4;
        }

        .color-picker-wrapper {
            margin-top: 1rem;
            padding: 1.5rem 1rem;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 12px;
            border: 2px solid #e3f2fd;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            display: none;
            animation: slideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            max-width: 100%;
            overflow: hidden;
            grid-column: 1 / -1;
            width: 100%;
        }

        .color-picker-wrapper.active {
            display: block;
        }

        @keyframes slideIn {
            0% {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            50% {
                opacity: 0.8;
                transform: translateY(-5px) scale(1.02);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* カラーピッカー内のラベル */
        .color-picker-wrapper .form-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }

        /* 色ピッカーセクション */
        .color-picker-section {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 1rem 0;
            padding: 1rem;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 8px;
            border: 1px solid #e9ecef;
            max-width: 100%;
            box-sizing: border-box;
        }

        .color-picker-section > div {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .color-picker-section .color-swatch {
            width: 50px;
            height: 50px;
            border: 2px solid #dee2e6;
            border-radius: 50%;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin: 0 auto 0.2rem auto;
            position: relative;
            flex-shrink: 0;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .color-picker-section .color-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 500;
            margin: 0;
            white-space: nowrap;
        }

        .color-swatch-wrapper {
            cursor: pointer;
            position: relative;
            display: inline-block;
        }

        .color-swatch-wrapper:hover .color-swatch {
            border-color: #4285f4;
            transform: scale(1.05);
            box-shadow: 0 3px 8px rgba(66, 133, 244, 0.2);
        }

        .swatch-color-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 2;
            border: none;
            background: transparent;
        }

        .hidden-color-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 1;
        }
            white-space: nowrap;
        }

        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .color-palette,
            .color-palette.loaded {
                padding: 1.5rem;
                display: grid !important;
                grid-template-columns: repeat(3, 1fr);
                gap: 0.75rem;
                align-items: start;
                justify-items: center;
            }
            
            .color-palette .text-center {
                grid-column: 1 / -1;
                width: 100%;
                margin-bottom: 0.75rem;
            }
            
            .color-item {
                display: block;
                width: 100%;
                max-width: 120px;
                margin: 0;
                padding: 0.5rem;
                min-height: 130px;
            }
            
            .color-picker-wrapper {
                grid-column: 1 / -1;
                width: 100%;
                margin-top: 1rem;
                padding: 1rem 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .color-palette,
            .color-palette.loaded {
                padding: 1rem;
                display: grid !important;
                grid-template-columns: 1fr 1fr;
                gap: 0.5rem;
                align-items: start;
                justify-items: center;
            }
            
            .color-palette .text-center {
                grid-column: 1 / -1;
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .color-item {
                display: block;
                width: 100%;
                max-width: 140px;
                margin: 0;
                padding: 0.5rem;
                min-height: 120px;
            }
            
            .color-picker-wrapper {
                grid-column: 1 / -1;
                width: 100%;
                margin-top: 0.8rem;
                padding: 0.8rem 0.6rem;
            }
        }

        /* カラーピッカー入力 */
        .form-control-color {
            width: 35px !important;
            height: 35px !important;
            border: 2px solid #dee2e6 !important;
            border-radius: 50% !important;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            display: block;
            flex-shrink: 0;
            min-width: 35px;
        }

        .form-control-color:hover {
            border-color: #4285f4 !important;
            transform: scale(1.05);
            box-shadow: 0 3px 8px rgba(66, 133, 244, 0.2);
        }

        .form-control-color:focus {
            border-color: #4285f4 !important;
            box-shadow: 0 0 0 0.2rem rgba(66, 133, 244, 0.25), 
                        0 3px 8px rgba(66, 133, 244, 0.2) !important;
            transform: scale(1.02);
        }

        .form-control-color::-webkit-color-swatch-wrapper {
            padding: 0;
            border-radius: 50%;
        }

        .form-control-color::-webkit-color-swatch {
            border: none;
            border-radius: 50%;
        }

        /* アクションボタン */
        .color-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .color-actions .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 0.6rem 1.2rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-width: 100px;
            font-size: 0.85rem;
        }

        .color-actions .btn-primary {
            background: linear-gradient(135deg, #4285f4 0%, #357ae8 100%);
            border: none;
            box-shadow: 0 3px 12px rgba(66, 133, 244, 0.3);
            color: white;
        }

        .color-actions .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 133, 244, 0.4);
        }

        .color-actions .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(66, 133, 244, 0.3);
        }

        .color-actions .btn-outline-secondary {
            border: 2px solid #dee2e6;
            color: #6c757d;
            background: white;
        }

        .color-actions .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
            color: #495057;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* モバイル対応 */
        @media (max-width: 768px) {
            .tab-icons {
                gap: 0.75rem;
                margin: 1rem 0;
            }

            .tab-icon {
                padding: 10px;
                min-width: 50px;
            }

            .tab-icon-img {
                width: 20px;
                height: 20px;
            }

            .color-picker-wrapper {
                margin-top: 1.5rem;
                padding: 1.5rem;
            }
            
            .color-comparison {
                gap: 1rem;
            }
            
            .color-comparison .color-swatch,
            .form-control-color {
                width: 50px !important;
                height: 50px !important;
            }
        }

        @media (max-width: 576px) {
            .color-picker-wrapper {
                padding: 1rem;
                border-radius: 12px;
            }
            
            .color-actions {
                flex-direction: column;
                width: 100%;
                gap: 0.5rem;
            }
            
            .color-actions .btn {
                width: 100%;
                min-width: auto;
                padding: 0.75rem;
            }
            
            .color-comparison .color-swatch,
            .form-control-color {
                width: 45px !important;
                height: 45px !important;
                border-width: 3px !important;
            }
        }

        .rotating {
            animation: rotate 2s infinite linear;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .usage-count {
            position: absolute;
            top: -10px;
            right: -10px;
            background: linear-gradient(135deg, #4285f4 0%, #357ae8 100%);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 0.75rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(66, 133, 244, 0.4);
            z-index: 2;
            font-weight: bold;
        }

        /* 色変更状態の表示 */
        .color-swatch-container {
            position: relative;
            display: inline-block;
            width: 70px;
            height: 70px;
            margin: 0 auto 0.75rem;
        }

        .color-swatch.changed {
            border-color: #28a745;
            border-width: 4px;
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);
        }

        .original-code {
            font-size: 0.7rem;
            color: #6c757d;
            font-weight: 500;
            margin-top: 0.25rem;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            text-align: center;
        }

        @media (max-width: 768px) {
            .svg-display-section {
                padding: 1rem;
                margin: 1rem 0;
            }

            .svg-image-wrapper {
                padding: 0.75rem;
                width: 100%;
                max-width: 350px;
                min-height: 250px;
            }

            .svg-image-wrapper .svg-inline {
                max-height: 300px;
            }

            .svg-controls {
                padding: 0.75rem;
            }

            .svg-controls .row {
                flex-direction: column;
                gap: 0.5rem;
            }

            .svg-controls .col-auto {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .svg-controls .form-label {
                margin-bottom: 0;
                min-width: 60px;
            }
        }
    </style>
    
    <?php include __DIR__ . '/../includes/gtm-head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../includes/gtm-body.php'; ?>
    
    <?php 
    $currentPage = 'detail';
    include '../includes/header.php'; 
    ?>
    
    <div class="container mt-3">
        <nav aria-label="breadcrumb">
            <ol style="list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap;">
                <li style="margin-right: 0.5rem;">
                    <a href="/" style="color: #222; text-decoration: none;">ホーム</a>
                    <span style="margin-left: 0.5rem; color: #222;"> &gt; </span>
                </li>
                <li style="margin-right: 0.5rem;">
                    <a href="/<?= h($category['slug']) ?>/" style="color: #222; text-decoration: none;"><?= h($category['title']) ?></a>
                    <span style="margin-left: 0.5rem; color: #222;"> &gt; </span>
                </li>
                <li style="color: #222;">
                    <?= h($material['title']) ?>
                </li>
            </ol>
        </nav>
    </div>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-6" style="margin: 0 auto;">
                <div class="text-center position-relative">
                    <!-- 構造化データ画像を優先使用 -->
                    <?php
                    // 画像パスの決定（優先順位: 構造化データ用画像 > WebP中サイズ > 通常画像）
                    $displayImagePath = $material['image_path']; // デフォルト
                    
                    if (!empty($material['structured_image_path'])) {
                        $absolutePath = dirname(__DIR__) . '/' . $material['structured_image_path'];
                        if (file_exists($absolutePath)) {
                            $displayImagePath = $material['structured_image_path'];
                        }
                    } elseif (!empty($material['webp_medium_path'])) {
                        $displayImagePath = $material['webp_medium_path'];
                    }
                    ?>
                    
                    <img src="/<?= h($displayImagePath) ?>" 
                         class="detail-main-image mb-3" 
                         alt="<?= h($material['title']) ?>のイラスト"
                         width="300"
                         height="300"
                         loading="eager"
                         decoding="async"
                         fetchpriority="high"
                         style="display: block; max-width: 100%; width: 100%; margin: 0 auto;">
                    
                    <!-- ダウンロードリンク -->
                    <div class="mb-4">
                        <div class="download-buttons">
                            <a href="/<?= h($material['image_path']) ?>" download class="download-link">
                                PNGをダウンロード
                            </a>
                            
                            <?php if (isset($material['svg_path']) && !empty($material['svg_path'])): ?>
                            <a href="/<?= h($material['svg_path']) ?>" download class="download-link svg-download">
                                SVGをダウンロード
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body text-center">
                        <h1 class="detail-title mb-3"><?= h($material['title']) ?></h1>
                        
                        <?php if (!empty($material['description'])): ?>
                        <p class="detail-description mb-3"><?= nl2br(h($material['description'])) ?></p>
                        <?php endif; ?>
                        

                        
                        <div class="mb-3">
                            <small class="detail-date">投稿日：<?= date('Y-m-d', strtotime($material['upload_date'])) ?></small>
                        </div>
                        
                        <?php if (!empty($materialTags)): ?>
                        <div class="tags-section">
                            <div class="tags-label">タグ:</div>
                            <div>
                                <?php foreach ($materialTags as $tag): ?>
                                    <a href="/tag/<?= h($tag['slug']) ?>/" class="tag-item">
                                        <?= h($tag['name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SVG表示セクション（シェアボタンの上） -->
    <?php 
    // SVGファイルの表示
    $hasSvgColumn = false;
    try {
        $pdo->query("SELECT svg_path FROM materials LIMIT 1");
        $hasSvgColumn = true;
    } catch (PDOException $e) {
        $hasSvgColumn = false;
    }
    
    if ($hasSvgColumn && !empty($material['svg_path']) && file_exists(__DIR__ . '/../' . $material['svg_path'])): 
    ?>
    <div class="container mt-4">
        <div class="svg-display-section">
            
            <div class="svg-container">
                <div class="svg-image-wrapper">
                    <?php
                    // SVGファイルの内容を安全に表示（インライン方式）
                    $svgFilePath = __DIR__ . '/../' . $material['svg_path'];
                    if (file_exists($svgFilePath)) {
                        $svgContent = file_get_contents($svgFilePath);
                        
                        // セキュリティのための基本的なサニタイズ
                        $svgContent = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $svgContent);
                        $svgContent = preg_replace('/on[a-z]+\s*=\s*["\'][^"\']*["\']/i', '', $svgContent);
                        $svgContent = preg_replace('/javascript\s*:/i', '', $svgContent);
                        
                        // SVGタグにIDとクラスを追加
                        $svgContent = preg_replace('/<svg([^>]*)>/i', '<svg$1 id="customizable-svg" class="svg-inline">', $svgContent);
                        
                        echo $svgContent;
                    } else {
                        echo '<p class="text-muted">SVGファイルが見つかりません</p>';
                    }
                    ?>
                </div>
                
                <!-- タブアイコン -->
                <div class="tab-icons mt-3 mb-4">
                    <button type="button" class="tab-icon" data-tab="color" onclick="switchTab('color')" title="色変更">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-palette-icon lucide-palette tab-icon-img">
                            <path d="M12 22a1 1 0 0 1 0-20 10 9 0 0 1 10 9 5 5 0 0 1-5 5h-2.25a1.75 1.75 0 0 0-1.4 2.8l.3.4a1.75 1.75 0 0 1-1.4 2.8z"/>
                            <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/>
                            <circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/>
                            <circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/>
                            <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>
                        </svg>
                    </button>
                    
                    <button type="button" class="tab-icon" data-tab="background" onclick="switchTab('background')" title="背景変更">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-paint-bucket-icon lucide-paint-bucket tab-icon-img">
                            <path d="m19 11-8-8-8.6 8.6a2 2 0 0 0 0 2.8l5.2 5.2c.8.8 2 .8 2.8 0L19 11Z"/>
                            <path d="m5 2 5 5"/>
                            <path d="M2 13h15"/>
                            <path d="M22 20a2 2 0 1 1-4 0c0-1.6 1.7-2.4 2-4 .3 1.6 2 2.4 2 4Z"/>
                        </svg>
                    </button>
                    
                    <button type="button" class="tab-icon" data-tab="move" onclick="switchTab('move')" title="位置変更">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-move-icon lucide-move tab-icon-img">
                            <polyline points="5,9 2,12 5,15"></polyline>
                            <polyline points="9,5 12,2 15,5"></polyline>
                            <polyline points="15,19 12,22 9,19"></polyline>
                            <polyline points="19,9 22,12 19,15"></polyline>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <line x1="12" y1="2" x2="12" y2="22"></line>
                        </svg>
                    </button>
                    
                    <button type="button" class="tab-icon" data-tab="scale" onclick="switchTab('scale')" title="拡大縮小">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-zoom-in-icon lucide-zoom-in tab-icon-img">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" x2="16.65" y1="21" y2="16.65"/>
                            <line x1="11" x2="11" y1="8" y2="14"/>
                            <line x1="8" x2="14" y1="11" y2="11"/>
                        </svg>
                    </button>
                    
                    <button type="button" class="tab-icon" data-tab="rotate" onclick="switchTab('rotate')" title="回転">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-rotate-ccw-icon lucide-rotate-ccw tab-icon-img">
                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                            <path d="M3 3v5h5"/>
                        </svg>
                    </button>
                </div>
                
                <!-- 色変更コントロール（カラーパレット方式） -->
                <div class="svg-controls mt-4" id="colorTab" style="display: none;">
                    
                    <!-- 色パレット表示エリア -->
                    <div id="colorPalette" class="color-palette mb-4">
                        <div class="text-center text-muted">
                            <div class="mb-2">
                                読み込み中...
                            </div>
                            <div>色を抽出しています...</div>
                            <small class="d-block mt-1">しばらくお待ちください</small>
                        </div>
                    </div>
                    
                </div>

                <!-- 背景色変更パネル -->
                <div class="svg-controls mt-4" id="backgroundTab" style="display: none;">
                    
                    <!-- 背景色選択 -->
                    <div class="bg-color-section mb-4">
                        <div class="bg-color-palette d-flex justify-content-center align-items-center gap-3">
                            <button type="button" class="bg-color-btn active" data-color="transparent" title="透明（背景なし）">
                                <div class="bg-swatch transparent-bg"></div>
                            </button>
                            
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" id="customBgColor" class="form-control form-control-color" 
                                       style="width: 50px; height: 38px;" title="カスタم背景色を選択" value="#ffffff">
                            </div>
                        </div>
                    </div>
                    
                    
                    
                    <!-- 季節テーマ選択 -->
                    <div class="seasonal-themes-section">
                        <div class="seasonal-themes">
                            <button type="button" class="seasonal-btn" onclick="applySeasonalTheme('spring')" title="春のパステルカラー">
                                <div class="seasonal-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower-icon lucide-flower">
                                        <circle cx="12" cy="12" r="3"/>
                                        <path d="M12 16.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 1 1 4.5 4.5 4.5 4.5 0 1 1-4.5 4.5"/>
                                        <path d="M12 7.5V9"/>
                                        <path d="M7.5 12H9"/>
                                        <path d="M16.5 12H15"/>
                                        <path d="M12 16.5V15"/>
                                        <path d="m8 8 1.88 1.88"/>
                                        <path d="M14.12 9.88 16 8"/>
                                        <path d="m8 16 1.88-1.88"/>
                                        <path d="M14.12 14.12 16 16"/>
                                    </svg>
                                </div>
                            </button>
                            <button type="button" class="seasonal-btn" onclick="applySeasonalTheme('summer')" title="夏のパステルカラー">
                                <div class="seasonal-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sun-icon lucide-sun">
                                        <circle cx="12" cy="12" r="4"/>
                                        <path d="M12 2v2"/>
                                        <path d="M12 20v2"/>
                                        <path d="m4.93 4.93 1.41 1.41"/>
                                        <path d="m17.66 17.66 1.41 1.41"/>
                                        <path d="M2 12h2"/>
                                        <path d="M20 12h2"/>
                                        <path d="m6.34 17.66-1.41 1.41"/>
                                        <path d="m19.07 4.93-1.41 1.41"/>
                                    </svg>
                                </div>
                            </button>
                            <button type="button" class="seasonal-btn" onclick="applySeasonalTheme('autumn')" title="秋のパステルカラー">
                                <div class="seasonal-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-leaf-icon lucide-leaf">
                                        <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
                                        <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                                    </svg>
                                </div>
                            </button>
                            <button type="button" class="seasonal-btn" onclick="applySeasonalTheme('winter')" title="冬のパステルカラー">
                                <div class="seasonal-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-snowflake-icon lucide-snowflake">
                                        <path d="m10 20-1.25-2.5L6 18"/>
                                        <path d="M10 4 8.75 6.5 6 6"/>
                                        <path d="m14 20 1.25-2.5L18 18"/>
                                        <path d="m14 4 1.25 2.5L18 6"/>
                                        <path d="m17 21-3-6h-4"/>
                                        <path d="m17 3-3 6 1.5 3"/>
                                        <path d="M2 12h6.5L10 9"/>
                                        <path d="m20 10-1.5 2 1.5 2"/>
                                        <path d="M22 12h-6.5L14 15"/>
                                        <path d="m4 10 1.5 2L4 14"/>
                                        <path d="m7 21 3-6-1.5-3"/>
                                        <path d="m7 3 3 6h4"/>
                                    </svg>
                                </div>
                            </button>
                            <button type="button" class="seasonal-btn" onclick="applySeasonalTheme('monochrome')" title="白黒の濃淡">
                                <div class="seasonal-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-panda-icon lucide-panda">
                                        <path d="M11.25 17.25h1.5L12 18z"/>
                                        <path d="m15 12 2 2"/>
                                        <path d="M18 6.5a.5.5 0 0 0-.5-.5"/>
                                        <path d="M20.69 9.67a4.5 4.5 0 1 0-7.04-5.5 8.35 8.35 0 0 0-3.3 0 4.5 4.5 0 1 0-7.04 5.5C2.49 11.2 2 12.88 2 14.5 2 19.47 6.48 22 12 22s10-2.53 10-7.5c0-1.62-.48-3.3-1.3-4.83"/>
                                        <path d="M6 6.5a.495.495 0 0 1 .5-.5"/>
                                        <path d="m9 12-2 2"/>
                                    </svg>
                                </div>
                            </button>
                            <button type="button" class="seasonal-btn" onclick="applySeasonalTheme('sepia')" title="セピアの温もり">
                                <div class="seasonal-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-coffee-icon lucide-coffee">
                                        <path d="M10 2v2"/>
                                        <path d="M14 2v2"/>
                                        <path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1"/>
                                        <path d="M6 2v2"/>
                                    </svg>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- 黒・グレー除外設定 -->
                    <div class="exclude-colors-section mt-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <label class="form-label mb-0" for="excludeGraySwitch">
                                <i class="bi bi-palette me-1"></i>黒・グレー系色の保護
                            </label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="excludeGraySwitch" checked>
                                <label class="form-check-label" for="excludeGraySwitch"></label>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-1">
                            ONの場合、季節テーマ適用時に黒・グレー系の色は変更されません
                        </small>
                    </div>
                </div>

                <!-- 移動コントロールパネル -->
                <div class="svg-controls mt-4" id="moveTab" style="display: none;">
                    <div class="move-controls">
                        <!-- 上ボタン -->
                        <div class="move-row">
                            <button type="button" class="move-btn" onclick="moveSvg('up')" title="上に移動">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="m7.247 4.86-4.796 5.481c-.566.647-.106 1.659.753 1.659h9.592a1 1 0 0 0 .753-1.659l-4.796-5.48a1 1 0 0 0-1.506 0z"/>
                                </svg>
                                上
                            </button>
                        </div>
                        
                        <!-- 左・右ボタン -->
                        <div class="move-row">
                            <button type="button" class="move-btn" onclick="moveSvg('left')" title="左に移動">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="m3.86 8.753 5.482 4.796c.646.566 1.658.106 1.658-.753V3.204a1 1 0 0 0-1.659-.753l-5.48 4.796a1 1 0 0 0 0 1.506z"/>
                                </svg>
                                左
                            </button>
                            
                            <button type="button" class="move-btn" onclick="resetSvgPosition()" title="位置をリセット">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                    <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                                </svg>
                                リセット
                            </button>
                            
                            <button type="button" class="move-btn" onclick="moveSvg('right')" title="右に移動">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="m12.14 8.753-5.482 4.796c-.646.566-1.658.106-1.658-.753V3.204a1 1 0 0 1 1.659-.753l5.48 4.796a1 1 0 0 1 0 1.506z"/>
                                </svg>
                                右
                            </button>
                        </div>
                        
                        <!-- 下ボタン -->
                        <div class="move-row">
                            <button type="button" class="move-btn" onclick="moveSvg('down')" title="下に移動">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                                </svg>
                                下
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 拡大縮小コントロールパネル -->
                <div class="svg-controls mt-4" id="scaleTab" style="display: none;">
                    
                    <div class="scale-controls">
                        <div class="scale-buttons">
                            <button type="button" class="scale-btn" onclick="scaleSvg(0.5)" title="50%に縮小">
                                50%
                            </button>
                            <button type="button" class="scale-btn" onclick="scaleSvg(0.7)" title="70%に縮小">
                                70%
                            </button>
                            <button type="button" class="scale-btn active" onclick="scaleSvg(1.0)" title="元のサイズ（100%）">
                                100%
                            </button>
                            <button type="button" class="scale-btn" onclick="scaleSvg(1.2)" title="120%に拡大">
                                120%
                            </button>
                            <button type="button" class="scale-btn" onclick="scaleSvg(1.5)" title="150%に拡大">
                                150%
                            </button>
                        </div>
                        
                        <div class="scale-reset mt-3">
                            <button type="button" class="move-btn" onclick="resetSvgScale()" title="サイズをリセット">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                    <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                                </svg>
                                リセット
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 回転コントロールパネル -->
                <div class="svg-controls mt-4" id="rotateTab" style="display: none;">
                    
                    <div class="rotate-controls">
                        <div class="rotate-buttons">
                            <button type="button" class="rotate-btn" onclick="rotateSvg(-15)" title="左に15度回転">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                    <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                                </svg>
                                左回転
                            </button>
                            
                            <button type="button" class="rotate-btn" onclick="rotateSvg(15)" title="右に15度回転">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16" style="transform: scaleX(-1);">
                                    <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                    <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                                </svg>
                                右回転
                            </button>
                        </div>
                        
                        <div class="rotate-reset mt-3">
                            <button type="button" class="move-btn" onclick="resetSvgRotation()" title="回転をリセット">
                                <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                                    <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
                                </svg>
                                リセット
                            </button>
                        </div>
                    </div>
                </div>

                <!-- パネル外のダウンロードとリセットボタン -->
                <div class="text-center mt-4">
                    <div class="mb-3 download-buttons">
                        <a href="#" class="download-link svg-download" onclick="downloadCustomSvg(); return false;">
                            SVGダウンロード
                        </a>
                        <a href="#" class="download-link" onclick="downloadCustomPng(); return false;">
                            PNGダウンロード
                        </a>
                    </div>
                    
                    <!-- ダウンロード仕様の注記 -->
                    <div class="download-notes mb-3">
                        <small class="text-muted">
                            <strong>SVGダウンロード：</strong>色変更・背景変更のみ反映<br>
                            <strong>PNGダウンロード：</strong>すべての変更を反映（移動・拡大縮小・回転を含む）
                        </small>
                    </div>
                    
                    <div>
                        <button type="button" class="btn btn-outline-secondary" onclick="resetSvgColors()">
                            リセット
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- シェアボタン（枠外） -->
    <div class="container mt-3">
        <div class="share-section text-center">
            <?php
            $currentUrl = urlencode(($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
            $shareText = urlencode($material['title'] . ' - ミニマルなフリーイラスト素材 #フリー素材 #イラスト素材 #freeillustration #minimalart');
            $twitterShareUrl = "https://twitter.com/intent/tweet?url={$currentUrl}&text={$shareText}";
            
            // Pinterest用のパラメータ
            $pinterestUrl = urldecode($currentUrl);
            $pinterestDescription = urlencode($material['title'] . ' - ミニマルなフリーイラスト素材（商用利用OK） #フリー素材 #イラスト素材 #freeillustration #minimalart');
            $pinterestImageUrl = urlencode(($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . '/' . $displayImagePath);
            $pinterestShareUrl = "https://pinterest.com/pin/create/button/?url={$currentUrl}&media={$pinterestImageUrl}&description={$pinterestDescription}";
            ?>
            
            <!-- Xシェアボタン -->
            <a href="<?= h($twitterShareUrl) ?>" 
               target="_blank" 
               rel="noopener noreferrer" 
               class="share-button twitter"
               onclick="gtag('event', 'share', { 'method': 'twitter', 'content_type': 'image', 'item_id': '<?= h($material['slug']) ?>' });">
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
               onclick="gtag('event', 'share', { 'method': 'pinterest', 'content_type': 'image', 'item_id': '<?= h($material['slug']) ?>' });">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.174-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.097.118.110.221.082.343-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.402.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.357-.629-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24.009 12.017 24.009c6.624 0 11.99-5.367 11.99-11.988C24.007 5.367 18.641.001.017 0z"/>
                </svg>
                Pinterestでシェア
            </a>
        </div>
    </div>

    <!-- 広告ユニット -->
    <div class="container mt-5">
        <div style="display: flex; justify-content: center; gap: 100px; flex-wrap: wrap;">
            <?php include __DIR__ . '/../includes/ad-display.php'; ?>
            <div class="ad-desktop-only">
                <?php include __DIR__ . '/../includes/ad-display.php'; ?>
            </div>
        </div>
    </div>

    <!-- AI生成製品画像セクション -->
    <?php if (!empty($material['ai_product_image_path'])): ?>
    <section id="ai-product-section">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="ai-section-content">
                        <h3 class="ai-product-title">イラストの世界を、かたちにしてみました</h3>
                        <p class="ai-product-subtitle">
                            marutto.artのイラストをもとに、AIで立体化しました。<br />
                            やさしい世界が、ちょっとだけ現実にやってきたようです。
                        </p>
                        
                        <div class="ai-product-card">
                        <div class="ai-product-image-wrapper">
                            <img src="/<?= h($material['ai_product_image_path']) ?>" 
                                 alt="<?= h($material['title']) ?>を使用したAI生成製品"
                                 class="ai-product-image"
                                 loading="lazy"
                                 decoding="async">
                        </div>
                        
                        <?php if (!empty($material['ai_product_image_description'])): ?>
                        <div class="ai-product-description">
                            <p><?= nl2br(h($material['ai_product_image_description'])) ?></p>
                        </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- 関連画像セクション -->
    <?php if ($showRelatedSection || $showRelatedArtworksSection): ?>
    <section class="related-materials mt-5">
        <div class="container">
            <h2 class="text-center mb-4">なかまたち</h2>
            <div class="row g-3">
                <?php 
                // 素材同士の関連画像を表示
                if ($showRelatedSection):
                    foreach ($relatedMaterials as $relatedMaterial): ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2 col-xl-2">
                    <div class="card h-100 border-0 shadow-sm">
                        <a href="/<?= h($relatedMaterial['category_slug']) ?>/<?= h($relatedMaterial['slug']) ?>/" class="text-decoration-none">
                            <div class="card-img-top-wrapper" 
                                 style="background-color: <?= !empty($relatedMaterial['structured_bg_color']) ? h($relatedMaterial['structured_bg_color']) : '#ffffff' ?>;">
                                <?php
                                // WebP画像のパスを優先的に使用
                                $imageSrc = !empty($relatedMaterial['webp_small_path']) 
                                    ? '/' . h($relatedMaterial['webp_small_path'])
                                    : '/' . h($relatedMaterial['image_path']);
                                ?>
                                <img src="<?= $imageSrc ?>" 
                                     class="card-img-top" 
                                     alt="<?= h($relatedMaterial['title']) ?>"
                                     loading="lazy"
                                     decoding="async">
                            </div>
                        </a>
                    </div>
                </div>
                <?php 
                    endforeach;
                endif;
                
                // みんなのアトリエ作品を表示
                if ($showRelatedArtworksSection):
                    foreach ($relatedArtworks as $artwork): ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2 col-xl-2">
                    <div class="card h-100 border-0 shadow-sm">
                        <a href="/everyone-work.php?id=<?= h($artwork['community_artwork_id']) ?>" class="text-decoration-none">
                            <div class="card-img-top-wrapper" style="background-color: #ffffff;">
                                <?php
                                // community_artworksにはwebp_pathとfile_pathのみ存在
                                $artworkImagePath = !empty($artwork['artwork_webp_path']) 
                                    ? '/' . h($artwork['artwork_webp_path']) 
                                    : '/' . h($artwork['artwork_image_path']);
                                ?>
                                <img src="<?= $artworkImagePath ?>" 
                                     class="card-img-top" 
                                     alt="<?= h($artwork['artwork_title']) ?>"
                                     loading="lazy"
                                     decoding="async">
                            </div>
                        </a>
                    </div>
                </div>
                <?php 
                    endforeach;
                endif;
                ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ミニストーリーセクション -->
    <?php if (!empty($material['mini_story'])): ?>
    <section class="mini-story-section">
        <div class="container">
            <div class="mini-story-container">
                <div class="mini-story-row">
                    <!-- 素材画像（中央配置） -->
                    <div class="mini-story-image-col">
                        <div class="mini-story-image-wrapper">
                            <?php
                            // 構造化画像を優先使用
                            $storyImagePath = !empty($material['structured_image_path']) 
                                ? '/' . h($material['structured_image_path'])
                                : (!empty($material['webp_medium_path']) 
                                    ? '/' . h($material['webp_medium_path'])
                                    : '/' . h($material['image_path']));
                            
                            $storyBgColor = !empty($material['structured_bg_color']) 
                                ? h($material['structured_bg_color']) 
                                : '#ffffff';
                            ?>
                            <div class="mini-story-image" style="background-color: <?= $storyBgColor ?>;">
                                <img src="<?= $storyImagePath ?>" 
                                     alt="<?= h($material['title']) ?>" 
                                     loading="lazy"
                                     decoding="async">
                            </div>
                        </div>
                    </div>
                    
                    <!-- ミニストーリー -->
                    <div class="mini-story-text-col">
                        <div class="mini-story-content">
                            <h3 class="mini-story-title">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mini-story-icon">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                </svg>
                                <?= h($material['title']) ?>のおはなし
                            </h3>
                            <div class="mini-story-text">
                                <?= nl2br(h($material['mini_story'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- 他のイラストを見るボタン -->
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-center">
            <div class="load-more-button">
                <a href="/list.php">
                    他のイラストを見る
                </a>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <!-- YouTube動画モーダル用JavaScript -->
    <script>
    </script>

    <!-- SVG色変更機能JavaScript -->
    <script>
    let originalSvgContent = null;
    let extractedColors = [];
    let originalColors = []; // オリジナルの色情報を保持（変更されない）
    let initialColorStates = []; // 初期状態の完全なコピー（リセット用）
    let colorMappings = new Map(); // オリジナル色 → 現在色のマッピング
    let selectedColorIndex = -1;
    let currentBackgroundColor = 'transparent'; // 現在の背景色を保持
    let currentTransform = { x: 0, y: 0, scale: 1.0, rotation: 0 }; // 現在の移動位置、拡大率、回転角度を保持
    
    // 季節テーマのカラーパレット定義（marutto.art向け・明るめくすみパステル）
const seasonalPalettes = {
    spring: {
        name: '春のやわらかパステル',
        colors: [
            '#F8CFCF', // 桜ピンク
            '#FFF2B7', // レモンイエロー
            '#D7EED1', // 若草グリーン
            '#D7E8F8', // 空のあお
            '#F3CFE8', // すみれピンク
            '#F5E2C8', // クリームベージュ
            '#D9EDD8', // 新芽グリーン
            '#F6DCC3', // 木漏れ日ベージュ
            '#F4C6D0', // 花びらピンク
            '#FAF3E7'  // 春霞ホワイト
        ],
        bgColors: [
            '#FCEBEA', // 桜の光
            '#F8F3D9', // はるの陽ざし
            '#E9F4EB', // 若葉の風
            '#EEF5FB', // 春空
            '#FFF7F5'  // 花びらホワイト
        ]
    },
    summer: {
        name: '夏のやわらかパステル',
        colors: [
            '#BDE7F7', // 空と海
            '#FFF3A4', // ひまわりイエロー
            '#C9F3E1', // ミント
            '#D3EBFF', // そよ風ブルー
            '#F7D5E8', // ラムネピンク
            '#CFEAD2', // 木陰グリーン
            '#FAEFD8', // 白砂ベージュ
            '#FFF9D6', // ひだまり
            '#BCE1F2', // 水しぶき
            '#E3F2FA'  // 朝の空
        ],
        bgColors: [
            '#E2F7FF', // 海のひかり
            '#EAFBEF', // ミント風
            '#FFFDE2', // 夏の陽ざし
            '#FCEEF6', // ピンクラムネ
            '#F4F9FF'  // 青空ホワイト
        ]
    },
    autumn: {
        name: '秋のやわらかパステル',
        colors: [
            '#F7D6B3', // 木の実ベージュ
            '#FFD9A6', // くりオレンジ
            '#F2E0B9', // こがねいろ
            '#E7D5C1', // ベージュグレー
            '#E7B9A4', // 紅茶ピンク
            '#FAE9D2', // ミルクキャラメル
            '#E1C4A7', // ほうじ茶
            '#F5CDBB', // 秋風オレンジ
            '#EBC7A4', // 焼きたてパン
            '#F8E5D6'  // 秋霧ホワイト
        ],
        bgColors: [
            '#FFF2E3', // 木漏れ日
            '#FFECDD', // 秋の光
            '#FAF1E8', // ベージュの風
            '#F9EDE1', // 焼きたて空気
            '#FFF8F1'  // 柔らかホワイト
        ]
    },
    winter: {
        name: '冬のやわらかパステル',
        colors: [
            '#E5EEF5', // 雪の青
            '#DAD7F0', // ラベンダー
            '#F1ECE6', // ミルクホワイト
            '#D3DDE0', // 氷の灰
            '#CBDDE1', // 冬の空
            '#F2EFE9', // 白銀
            '#E1DBD5', // ウールグレー
            '#D9E2EA', // 凍てつく風
            '#E8E1DC', // ココアベージュ
            '#F4F3F1'  // 冬霞
        ],
        bgColors: [
            '#EEF3F8', // 雪空
            '#EDE8F3', // 冬の朝
            '#F2F2F0', // あたたか光
            '#E8EBEE', // 冷たい風
            '#F7F6F4'  // 雪明かり
        ]
    },
    monochrome: {
        name: 'やさしいモノクロ',
        colors: [
            '#FFFFFF', // 白
            '#FAFAFA', // オフホワイト
            '#F3F3F3', // ライトグレー
            '#E6E6E6', // ソフトグレー
            '#D8D8D8', // グレージュ
            '#C8C8C8', // ミディアムグレー
            '#B0B0B0', // 穏やかグレー
            '#999999', // まろやかグレー
            '#7F7F7F', // スモーキーグレー
            '#666666'  // 最暗トーン（真っ黒は使わない）
        ],
        bgColors: [
            '#FFFFFF', // 白背景
            '#FAFAFA', // オフホワイト
            '#F5F5F5', // 薄いグレー
            '#EEEEEE', // ソフトグレー背景
            '#E8E8E8'  // ニュートラルグレー
        ]
    },
    sepia: {
        name: 'やさしいセピア',
        colors: [
            '#FFFDF8', // ほぼ白
            '#FBF4EA', // クリーム
            '#F6EBDC', // ベージュ
            '#F0E2CF', // ミルクティー
            '#E8D7BD', // カフェオレ
            '#E0C9A6', // ハニーベージュ
            '#D1B68D', // キャメル
            '#C19D72', // ミルクコーヒー
            '#AD8C63', // モカ
            '#937550'  // やわらかブラウン止まり（黒系なし）
        ],
        bgColors: [
            '#FFFBF5', // ホワイトベージュ
            '#FBF5EC', // クリームホワイト
            '#F7EFE4', // ナチュラルベージュ
            '#F2E6D5', // カフェラテ背景
            '#EBDCC3'  // ソフトセピア
        ]
    }
};
    
    // ページ読み込み時にオリジナルのSVGを保存し、色を抽出
    document.addEventListener('DOMContentLoaded', function() {
        const svg = document.getElementById('customizable-svg');
        if (svg) {
            originalSvgContent = svg.outerHTML;
            
            // 色を自動抽出
            setTimeout(extractColorsFromSvg, 500);
        }
        
        // 背景色パレットのイベントリスナーを設定
        const bgColorItems = document.querySelectorAll('.bg-color-item');
        bgColorItems.forEach(item => {
            item.addEventListener('click', function() {
                const color = this.getAttribute('data-color');
                setBackgroundColor(color);
                
                // アクティブ状態を更新
                bgColorItems.forEach(bgItem => bgItem.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // 透明背景ボタンのイベントリスナーを設定
        const transparentBtn = document.querySelector('.bg-color-btn[data-color="transparent"]');
        if (transparentBtn) {
            transparentBtn.addEventListener('click', function() {
                setBackgroundColor('transparent');
                
                // アクティブ状態を更新
                transparentBtn.classList.add('active');
                
                // カスタムカラーピッカーをリセット
                const customBgColorInput = document.getElementById('customBgColor');
                if (customBgColorInput) {
                    customBgColorInput.value = '#ffffff';
                }
            });
        }
        
        // カスタム背景色ピッカーのイベントリスナーを設定（即座適用）
        const customBgColorInput = document.getElementById('customBgColor');
        if (customBgColorInput) {
            customBgColorInput.addEventListener('input', function() {
                const color = this.value;
                setBackgroundColor(color);
                
                // 透明ボタンのアクティブ状態をクリア
                if (transparentBtn) {
                    transparentBtn.classList.remove('active');
                }
            });
        }
    });
    

    

    
    // 元の色に戻す関数
    function resetSvgColors() {
        if (originalSvgContent) {
            const svgWrapper = document.querySelector('.svg-image-wrapper');
            if (svgWrapper) {
                svgWrapper.innerHTML = originalSvgContent;
                
                // 色のマッピングを初期化
                colorMappings.clear();
                
                // SVGが復元された後、再度色抽出を実行してdata属性を設定
                setTimeout(() => {
                    extractColorsFromSvg();
                }, 100);
            }
        }

        // 背景色もリセット
        currentBackgroundColor = 'transparent';
        const bgColorItems = document.querySelectorAll('.bg-color-item');
        bgColorItems.forEach(item => item.classList.remove('active'));
        const transparentItem = document.querySelector('.bg-color-item[data-color="transparent"]');
        if (transparentItem) {
            transparentItem.classList.add('active');
        }
        
        // 透明背景ボタンをアクティブにする
        const transparentBtn = document.querySelector('.bg-color-btn[data-color="transparent"]');
        if (transparentBtn) {
            transparentBtn.classList.add('active');
        }
        
        // カスタム背景色ピッカーをリセット
        const customBgColorInput = document.getElementById('customBgColor');
        if (customBgColorInput) {
            customBgColorInput.value = '#ffffff'; // デフォルト色に戻す
        }

        // 色パレット選択をリセット
        selectedColorIndex = -1;
        
        // 移動位置、拡大率、回転もリセット
        currentTransform.x = 0;
        currentTransform.y = 0;
        currentTransform.scale = 1.0;
        currentTransform.rotation = 0;
        applySvgTransform();
        
        // 拡大ボタンのアクティブ状態もリセット
        updateScaleButtons();
    }
    
    // 背景色を設定する関数
    function setBackgroundColor(color) {
        currentBackgroundColor = color;
        
        const svg = document.getElementById('customizable-svg');
        if (!svg) {
            return;
        }
        
        // 既存の背景rect要素を削除
        const existingBg = svg.querySelector('#svg-background');
        if (existingBg) {
            existingBg.remove();
        }
        
        // 透明以外の場合は背景rect要素を追加
        if (color !== 'transparent') {
            const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            rect.setAttribute('id', 'svg-background');
            rect.setAttribute('x', '0');
            rect.setAttribute('y', '0');
            rect.setAttribute('width', '100%');
            rect.setAttribute('height', '100%');
            rect.setAttribute('fill', color);
            
            // 背景要素にはtransformを適用しない
            rect.style.transform = 'none';
            
            // 最初の子要素として挿入（背景として）
            svg.insertBefore(rect, svg.firstChild);
        }
        
        console.log(`Background color set to: ${color}`);
    }
    
    // カスタム色でSVGをダウンロードする関数（色変更と背景変更のみ対応）
    function downloadCustomSvg() {
        const svg = document.getElementById('customizable-svg');
        if (!svg) {
            alert('SVGが見つかりません');
            return;
        }
        
        // SVGのクローンを作成
        const svgClone = svg.cloneNode(true);
        
        // 移動グループの変形をリセット（移動は反映しない）
        const moveGroup = svgClone.querySelector('#svg-move-group');
        if (moveGroup) {
            moveGroup.removeAttribute('transform');
            console.log('Reset transform for SVG download - position changes not included');
        }
        
        // 元のSVGサイズを維持（512×512）
        const viewBox = svg.getAttribute('viewBox');
        if (viewBox) {
            svgClone.setAttribute('viewBox', viewBox);
        } else {
            svgClone.setAttribute('viewBox', '0 0 512 512');
        }
        svgClone.setAttribute('width', '512');
        svgClone.setAttribute('height', '512');
        
        // 背景がある場合はフルサイズに調整（100%で全体をカバー）
        const background = svgClone.querySelector('#svg-background');
        if (background) {
            background.setAttribute('width', '100%');
            background.setAttribute('height', '100%');
            background.setAttribute('x', '0');
            background.setAttribute('y', '0');
        }
        
        // clipPath関連の要素を削除（移動に関連するため不要）
        const clipPath = svgClone.querySelector('clipPath#download-clip');
        if (clipPath) {
            clipPath.remove();
        }
        
        // clipPath属性も削除
        const elementsWithClip = svgClone.querySelectorAll('[clip-path]');
        elementsWithClip.forEach(element => {
            element.removeAttribute('clip-path');
        });
        
        console.log('SVG download: colors and background only (no position changes)');
        
        // SVGの内容を取得
        const svgData = new XMLSerializer().serializeToString(svgClone);
        
        // デバッグ: 生成されたSVGデータの一部をコンソールに出力
        console.log('Generated SVG data (first 500 chars):', svgData.substring(0, 500));
        
        // Blobを作成
        const blob = new Blob([svgData], { type: 'image/svg+xml' });
        
        // ダウンロードリンクを作成
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = '<?= h($material['slug']) ?>_custom_colors.svg';
        
        // ダウンロードを実行
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // URLを解放
        URL.revokeObjectURL(url);
        
        // ダウンロード追跡（Google Analytics）
        if (typeof gtag !== 'undefined') {
            gtag('event', 'download', {
                'event_category': 'SVG',
                'event_label': 'custom_colors',
                'item_id': '<?= h($material['slug']) ?>'
            });
        }
    }
    
    // カスタム色でSVGをPNGとしてダウンロードする関数（色変更、背景変更、変形すべて対応）
    function downloadCustomPng() {
        const svg = document.getElementById('customizable-svg');
        if (!svg) {
            alert('SVGが見つかりません');
            return;
        }
        
        // 現在の画面表示のSVGをそのまま使用（全ての変更を反映）
        const svgData = new XMLSerializer().serializeToString(svg);
        
        // SVGのサイズを取得
        const viewBox = svg.getAttribute('viewBox');
        let width = 512; // デフォルトサイズ
        let height = 512;
        
        if (viewBox) {
            const [, , w, h] = viewBox.split(' ').map(Number);
            width = w || 512;
            height = h || 512;
        } else {
            width = svg.getAttribute('width') || 512;
            height = svg.getAttribute('height') || 512;
        }
        
        // 高解像度で出力（4倍サイズで高品質）
        const scale = 4;
        const canvasWidth = width * scale;
        const canvasHeight = height * scale;
        
        // Canvasを作成
        const canvas = document.createElement('canvas');
        canvas.width = canvasWidth;
        canvas.height = canvasHeight;
        const ctx = canvas.getContext('2d');
        
        // アンチエイリアシングを有効にして高品質描画
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        
        // 透明背景の場合は何も描画しない、背景色がある場合は背景を描画
        if (currentBackgroundColor !== 'transparent') {
            ctx.fillStyle = currentBackgroundColor;
            ctx.fillRect(0, 0, canvasWidth, canvasHeight);
        }
        
        // SVGをImageとして読み込み
        const img = new Image();
        img.onload = function() {
            // 高解像度でCanvasに描画（移動位置も含めて画面表示通り）
            ctx.drawImage(img, 0, 0, canvasWidth, canvasHeight);
            
            // PNGとしてダウンロード
            canvas.toBlob(function(blob) {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = '<?= h($material['slug']) ?>_custom_full.png';
                
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                URL.revokeObjectURL(url);
                
                // ダウンロード追跡（Google Analytics）
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'download', {
                        'event_category': 'PNG',
                        'event_label': 'custom_full',
                        'item_id': '<?= h($material['slug']) ?>'
                    });
                }
            }, 'image/png');
        };
        
        img.onerror = function() {
            alert('PNG変換に失敗しました');
        };
        
        // SVGデータをData URLとして設定
        const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
        const svgUrl = URL.createObjectURL(svgBlob);
        img.src = svgUrl;
        
        // 少し後にURLを解放
        setTimeout(() => {
            URL.revokeObjectURL(svgUrl);
        }, 1000);
        
        console.log('PNG download: includes all changes (colors, background, and position)');
    }
    
    // SVGから色を自動抽出
    function extractColorsFromSvg() {
        const svg = document.getElementById('customizable-svg');
        
        if (!svg) {
            return;
        }
        
        const elements = svg.querySelectorAll('*');
        const colorMap = new Map();
        
        elements.forEach((element, index) => {
            const colors = getElementColors(element);
            
            colors.forEach(colorInfo => {
                const hex = convertToHex(colorInfo.color);
                if (hex && hex !== '#FFFFFF' && hex !== '#000000') { // 白と黒は除外
                    // 元の色情報をdata属性として保存
                    if (colorInfo.type === 'fill') {
                        element.setAttribute('data-original-fill', hex);
                    } else if (colorInfo.type === 'stroke') {
                        element.setAttribute('data-original-stroke', hex);
                    } else if (colorInfo.type === 'style-fill') {
                        element.setAttribute('data-original-style-fill', hex);
                    } else if (colorInfo.type === 'style-stroke') {
                        element.setAttribute('data-original-style-stroke', hex);
                    }
                    
                    if (colorMap.has(hex)) {
                        colorMap.get(hex).count++;
                        colorMap.get(hex).elements.push({ element, type: colorInfo.type });
                    } else {
                        colorMap.set(hex, {
                            color: hex,
                            count: 1,
                            elements: [{ element, type: colorInfo.type }]
                        });
                    }
                }
            });
        });
        
        // 色を使用回数順にソート
        extractedColors = Array.from(colorMap.values())
            .sort((a, b) => b.count - a.count)
            .slice(0, 8); // 最大8色まで
        
        // オリジナル色情報のディープコピーを保存
        originalColors = extractedColors.map(color => ({
            color: color.color,
            count: color.count,
            elements: [...color.elements]
        }));
        
        // 初期状態の完全なコピーを保存（リセット用）
        initialColorStates = JSON.parse(JSON.stringify(originalColors));
        
        // 色のマッピングを初期化（オリジナル色 → 現在色）
        colorMappings.clear();
        originalColors.forEach(colorInfo => {
            colorMappings.set(colorInfo.color, colorInfo.color);
        });
        
        console.log('Extracted colors:', extractedColors.map(c => c.color));
        console.log('Initial color mappings:', Object.fromEntries(colorMappings));
        
        displayColorPalette();
    }
    
    // 要素から色情報を取得
    function getElementColors(element) {
        const colors = [];
        
        // fill属性
        const fill = element.getAttribute('fill');
        if (fill && fill !== 'none' && fill !== 'transparent') {
            colors.push({ color: fill, type: 'fill' });
        }
        
        // stroke属性
        const stroke = element.getAttribute('stroke');
        if (stroke && stroke !== 'none' && stroke !== 'transparent') {
            colors.push({ color: stroke, type: 'stroke' });
        }
        
        // style属性
        const style = element.getAttribute('style');
        if (style) {
            const fillMatch = style.match(/fill\s*:\s*([^;]+)/);
            const strokeMatch = style.match(/stroke\s*:\s*([^;]+)/);
            
            if (fillMatch && fillMatch[1].trim() !== 'none') {
                colors.push({ color: fillMatch[1].trim(), type: 'style-fill' });
            }
            if (strokeMatch && strokeMatch[1].trim() !== 'none') {
                colors.push({ color: strokeMatch[1].trim(), type: 'style-stroke' });
            }
        }
        
        return colors;
    }
    
    // パレット表示を更新（色変更後）
    function updateColorPalette() {
        const paletteContainer = document.getElementById('colorPalette');
        
        // 色スウォッチのみ更新
        let paletteHTML = '';
        
        extractedColors.forEach((colorInfo, index) => {
            const originalColor = colorInfo.color;
            const currentColor = colorMappings.get(originalColor) || originalColor;
            const isChanged = currentColor !== originalColor;
            
            paletteHTML += `
                <div class="color-item">
                    <div class="color-swatch-container">
                        <div class="color-swatch-wrapper" onclick="event.stopPropagation()">
                            <div class="color-swatch ${isChanged ? 'changed' : ''}" style="background-color: ${currentColor}" id="swatch-${index}">
                                <div class="usage-count">${colorInfo.count}</div>
                            </div>
                            <input type="color" class="swatch-color-input" id="colorInput-${index}" value="${currentColor}" oninput="changeColorDirectly(${index}, this.value)">
                        </div>
                    </div>
                    <div class="color-code" id="colorCode-${index}">${currentColor}</div>
                    ${isChanged ? `<div class="original-code">元: ${originalColor}</div>` : ''}
                </div>
            `;
        });
        
        paletteContainer.innerHTML = paletteHTML;
    }
    
    // カラーパレット表示
    function displayColorPalette() {
        const paletteContainer = document.getElementById('colorPalette');
        
        if (extractedColors.length === 0) {
            paletteContainer.innerHTML = `
                <div class="text-center text-muted">
                    このSVGには変更可能な色が見つかりませんでした
                </div>
            `;
            return;
        }
        
        let paletteHTML = '';
        
        extractedColors.forEach((colorInfo, index) => {
            const originalColor = colorInfo.color;
            const currentColor = colorMappings.get(originalColor) || originalColor;
            const isChanged = currentColor !== originalColor;
            
            paletteHTML += `
                <div class="color-item">
                    <div class="color-swatch-container">
                        <div class="color-swatch-wrapper" onclick="event.stopPropagation()">
                            <div class="color-swatch ${isChanged ? 'changed' : ''}" style="background-color: ${currentColor}" id="swatch-${index}">
                                <div class="usage-count">${colorInfo.count}</div>
                            </div>
                            <input type="color" class="swatch-color-input" id="colorInput-${index}" value="${currentColor}" oninput="changeColorDirectly(${index}, this.value)">
                        </div>
                    </div>
                    <div class="color-code" id="colorCode-${index}">${currentColor}</div>
                    ${isChanged ? `<div class="original-code">元: ${originalColor}</div>` : ''}
                </div>
            `;
        });
        
        paletteContainer.innerHTML = paletteHTML;
        paletteContainer.classList.add('loaded');
    }
    
    // カラースワッチから直接色を変更
    function changeColorDirectly(index, newColor) {
        if (index < 0 || index >= extractedColors.length) return;
        
        const colorInfo = extractedColors[index];
        const originalColor = colorInfo.color; // 元の色（初期状態）
        const currentColor = colorMappings.get(originalColor) || originalColor; // 現在の色
        
        // 新しい色を統一形式に変換
        const normalizedNewColor = convertToHex(newColor) || newColor.toUpperCase();
        
        // 同じ色への変更は処理しない
        if (currentColor === normalizedNewColor) return;
        
        // 元の色から新しい色へのマッピングを更新
        colorMappings.set(originalColor, normalizedNewColor);
        
        console.log(`Color change: ${originalColor} -> ${currentColor} -> ${normalizedNewColor}`);
        console.log('Updated mappings:', Object.fromEntries(colorMappings));
        
        // SVGの色を即座に更新
        updateSvgColors();
        
        // カラースワッチの表示を更新
        const swatch = document.getElementById(`swatch-${index}`);
        const colorCode = document.getElementById(`colorCode-${index}`);
        const colorInput = document.getElementById(`colorInput-${index}`);
        
        if (swatch) {
            swatch.style.backgroundColor = normalizedNewColor;
            // 変更されたことを示すクラスを追加
            if (normalizedNewColor !== originalColor) {
                swatch.classList.add('changed');
            } else {
                swatch.classList.remove('changed');
            }
        }
        
        if (colorCode) {
            colorCode.textContent = normalizedNewColor;
        }
        
        if (colorInput) {
            colorInput.value = normalizedNewColor;
        }
        
        // 元の色コードの表示を更新
        const parentItem = swatch?.closest('.color-item');
        if (parentItem) {
            const originalCodeDiv = parentItem.querySelector('.original-code');
            if (normalizedNewColor !== originalColor) {
                if (!originalCodeDiv) {
                    const newOriginalCodeDiv = document.createElement('div');
                    newOriginalCodeDiv.className = 'original-code';
                    newOriginalCodeDiv.textContent = `元: ${originalColor}`;
                    parentItem.appendChild(newOriginalCodeDiv);
                } else {
                    // 既存の元色表示を更新（元の色は変わらない）
                    originalCodeDiv.textContent = `元: ${originalColor}`;
                }
            } else {
                if (originalCodeDiv) {
                    originalCodeDiv.remove();
                }
            }
        }
    }
    
    // 色を16進数に変換
    function convertToHex(color) {
        if (!color) return null;
        
        color = color.trim().toLowerCase();
        
        // すでに16進数の場合
        if (color.startsWith('#')) {
            // 3桁の場合は6桁に展開
            if (color.length === 4) {
                const expanded = '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
                return expanded.toUpperCase();
            }
            return color.toUpperCase();
        }
        
        // RGB形式の場合
        const rgbMatch = color.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/);
        if (rgbMatch) {
            const r = parseInt(rgbMatch[1]);
            const g = parseInt(rgbMatch[2]);
            const b = parseInt(rgbMatch[3]);
            const hex = '#' + 
                ('0' + r.toString(16)).slice(-2) + 
                ('0' + g.toString(16)).slice(-2) + 
                ('0' + b.toString(16)).slice(-2);
            return hex.toUpperCase();
        }
        
        // RGBA形式の場合
        const rgbaMatch = color.match(/rgba\((\d+),\s*(\d+),\s*(\d+),\s*[\d.]+\)/);
        if (rgbaMatch) {
            const r = parseInt(rgbaMatch[1]);
            const g = parseInt(rgbaMatch[2]);
            const b = parseInt(rgbaMatch[3]);
            const hex = '#' + 
                ('0' + r.toString(16)).slice(-2) + 
                ('0' + g.toString(16)).slice(-2) + 
                ('0' + b.toString(16)).slice(-2);
            return hex.toUpperCase();
        }
        
        // 色名の場合（拡張版）
        const colorNames = {
            'black': '#000000',
            'white': '#FFFFFF',
            'red': '#FF0000',
            'green': '#008000',
            'lime': '#00FF00',
            'blue': '#0000FF',
            'yellow': '#FFFF00',
            'cyan': '#00FFFF',
            'magenta': '#FF00FF',
            'silver': '#C0C0C0',
            'gray': '#808080',
            'grey': '#808080',
            'maroon': '#800000',
            'olive': '#808000',
            'navy': '#000080',
            'purple': '#800080',
            'teal': '#008080',
            'aqua': '#00FFFF'
        };
        
        if (colorNames[color]) {
            return colorNames[color];
        }
        
        return color.toUpperCase();
    }
    
    // 色変更を適用
    function applyColorChange() {
        if (selectedColorIndex === -1) {
            return;
        }
        
        const newColorElement = document.getElementById('newColorPicker');
        if (!newColorElement) {
            alert('カラーピッカーが見つかりません');
            return;
        }
        
        const newColor = newColorElement.value;
        const oldColor = originalColors[selectedColorIndex].color; // 常にオリジナル色を使用
        
        if (newColor === oldColor) {
            return;
        }
        
        // SVG内の該当する色を全て変更
        const svgElement = document.getElementById('customizable-svg');
        if (!svgElement) {
            alert('SVGが見つかりません');
            return;
        }
        
        let changeCount = 0;
        
        // 現在その色グループに適用されている色を取得
        const currentColor = colorMappings.get(oldColor);
        
        // 全てのSVG要素をチェックして、現在の色を新しい色に置換
        const allElements = svgElement.querySelectorAll('*');
        
        allElements.forEach(element => {
            // fill属性をチェック
            const fillAttr = element.getAttribute('fill');
            if (fillAttr && convertToHex(fillAttr) === convertToHex(currentColor)) {
                element.setAttribute('fill', newColor);
                changeCount++;
            }
            
            // stroke属性をチェック
            const strokeAttr = element.getAttribute('stroke');
            if (strokeAttr && convertToHex(strokeAttr) === convertToHex(currentColor)) {
                element.setAttribute('stroke', newColor);
                changeCount++;
            }
            
            // style属性をチェック
            const styleAttr = element.getAttribute('style');
            if (styleAttr) {
                let newStyle = styleAttr;
                let styleChanged = false;
                
                const fillMatch = styleAttr.match(/fill\s*:\s*([^;]+)/);
                const strokeMatch = styleAttr.match(/stroke\s*:\s*([^;]+)/);
                
                if (fillMatch && convertToHex(fillMatch[1].trim()) === convertToHex(currentColor)) {
                    newStyle = newStyle.replace(/fill\s*:\s*[^;]+/, `fill: ${newColor}`);
                    styleChanged = true;
                    changeCount++;
                }
                
                if (strokeMatch && convertToHex(strokeMatch[1].trim()) === convertToHex(currentColor)) {
                    newStyle = newStyle.replace(/stroke\s*:\s*[^;]+/, `stroke: ${newColor}`);
                    styleChanged = true;
                    changeCount++;
                }
                
                if (styleChanged) {
                    element.setAttribute('style', newStyle);
                }
            }
        });
        
        // 色のマッピングを更新
        colorMappings.set(oldColor, newColor);
        
        // パレットはオリジナル色のまま保持（更新しない）
        
        // 完了メッセージ
        showMessage(`${currentColor} → ${newColor} に変更しました（${changeCount}箇所）`, 'success');
        
        // パレット表示を更新
        updateColorPalette();
    }
    
    // SVGの色を更新する関数
    function updateSvgColors() {
        const svgElement = document.getElementById('customizable-svg');
        if (!svgElement) {
            console.log('SVG element not found');
            return;
        }
        
        console.log('Updating SVG colors with mappings:', Object.fromEntries(colorMappings));
        
        // 全てのSVG要素をチェック
        const allElements = svgElement.querySelectorAll('*');
        let changeCount = 0;
        
        console.log(`Found ${allElements.length} SVG elements to check`);
        
        allElements.forEach((element, elementIndex) => {
            // fill属性をチェック
            const fillAttr = element.getAttribute('fill');
            const originalFill = element.getAttribute('data-original-fill');
            if (fillAttr && fillAttr !== 'none' && originalFill) {
                console.log(`Element ${elementIndex}: fill="${fillAttr}" -> original="${originalFill}"`);
                if (colorMappings.has(originalFill)) {
                    const newColor = colorMappings.get(originalFill);
                    console.log(`Changing fill: ${fillAttr} (original: ${originalFill}) -> ${newColor}`);
                    element.setAttribute('fill', newColor);
                    changeCount++;
                }
            }
            
            // stroke属性をチェック
            const strokeAttr = element.getAttribute('stroke');
            const originalStroke = element.getAttribute('data-original-stroke');
            if (strokeAttr && strokeAttr !== 'none' && originalStroke) {
                console.log(`Element ${elementIndex}: stroke="${strokeAttr}" -> original="${originalStroke}"`);
                if (colorMappings.has(originalStroke)) {
                    const newColor = colorMappings.get(originalStroke);
                    console.log(`Changing stroke: ${strokeAttr} (original: ${originalStroke}) -> ${newColor}`);
                    element.setAttribute('stroke', newColor);
                    changeCount++;
                }
            }
            
            // style属性をチェック
            const styleAttr = element.getAttribute('style');
            if (styleAttr) {
                let newStyle = styleAttr;
                let styleChanged = false;
                
                const fillMatch = styleAttr.match(/fill\s*:\s*([^;]+)/);
                const strokeMatch = styleAttr.match(/stroke\s*:\s*([^;]+)/);
                
                if (fillMatch && fillMatch[1].trim() !== 'none') {
                    const originalStyleFill = element.getAttribute('data-original-style-fill');
                    if (originalStyleFill) {
                        console.log(`Element ${elementIndex}: style fill="${fillMatch[1].trim()}" -> original="${originalStyleFill}"`);
                        if (colorMappings.has(originalStyleFill)) {
                            const newColor = colorMappings.get(originalStyleFill);
                            console.log(`Changing style fill: ${fillMatch[1].trim()} (original: ${originalStyleFill}) -> ${newColor}`);
                            newStyle = newStyle.replace(/fill\s*:\s*[^;]+/, `fill: ${newColor}`);
                            styleChanged = true;
                            changeCount++;
                        }
                    }
                }
                
                if (strokeMatch && strokeMatch[1].trim() !== 'none') {
                    const originalStyleStroke = element.getAttribute('data-original-style-stroke');
                    if (originalStyleStroke) {
                        console.log(`Element ${elementIndex}: style stroke="${strokeMatch[1].trim()}" -> original="${originalStyleStroke}"`);
                        if (colorMappings.has(originalStyleStroke)) {
                            const newColor = colorMappings.get(originalStyleStroke);
                            console.log(`Changing style stroke: ${strokeMatch[1].trim()} (original: ${originalStyleStroke}) -> ${newColor}`);
                            newStyle = newStyle.replace(/stroke\s*:\s*[^;]+/, `stroke: ${newColor}`);
                            styleChanged = true;
                            changeCount++;
                        }
                    }
                }
                
                if (styleChanged) {
                    element.setAttribute('style', newStyle);
                }
            }
        });
        
        console.log(`Updated ${changeCount} color attributes in SVG`);
    }
    
    // メッセージ表示
    function showMessage(message, type = 'info') {
        const messageDiv = document.createElement('div');
        messageDiv.className = `alert alert-${type === 'success' ? 'success' : 'info'} alert-dismissible fade show position-fixed`;
        messageDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        messageDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(messageDiv);
        
        // 3秒後に自動削除
        setTimeout(() => {
            messageDiv.remove();
        }, 3000);
    }
    
    // 抽出した色の表示を更新
    function updateExtractedColorDisplay(color) {
        const preview = document.getElementById('extractedColorPreview');
        const code = document.getElementById('extractedColorCode');
        
        preview.style.backgroundColor = color;
        code.textContent = color;
        code.style.fontWeight = 'bold';
    }
    
    // タブ切り替え関数
    function switchTab(tabName) {
        // すべてのタブパネルを非表示にする
        const allTabs = ['colorTab', 'backgroundTab', 'moveTab'];
        allTabs.forEach(tab => {
            const tabElement = document.getElementById(tab);
            if (tabElement) {
                tabElement.style.display = 'none';
            }
        });
        
        // すべてのタブボタンのアクティブ状態をリセット
        const allTabButtons = document.querySelectorAll('.tab-icon');
        allTabButtons.forEach(button => {
            button.classList.remove('active');
        });
        
        // 指定されたタブを表示
        const targetTab = document.getElementById(tabName + 'Tab');
        if (targetTab) {
            targetTab.style.display = 'block';
            
            // クリックされたボタンをアクティブにする
            const activeButton = document.querySelector(`.tab-icon[data-tab="${tabName}"]`);
            if (activeButton) {
                activeButton.classList.add('active');
            }
            
            // 色変更タブの場合、色抽出処理を実行
            if (tabName === 'color') {
                console.log('Color tab activated, extracting colors...');
                // SVGが完全に読み込まれるまで少し待つ
                setTimeout(() => {
                    extractColorsFromSvg();
                }, 100);
            }
        }
    }
    
    // SVG移動機能
    function moveSvg(direction) {
        const svg = document.getElementById('customizable-svg');
        if (!svg) {
            return;
        }
        
        const moveDistance = 20; // 移動距離（ピクセル）
        
        // SVGコンテナのサイズに基づいた動的な移動制限を計算
        const svgWrapper = document.querySelector('.svg-image-wrapper');
        const svgRect = svg.getBoundingClientRect();
        const wrapperRect = svgWrapper ? svgWrapper.getBoundingClientRect() : svgRect;
        
        // SVGの実際のサイズを取得
        const svgWidth = svgRect.width;
        const svgHeight = svgRect.height;
        const wrapperWidth = wrapperRect.width;
        const wrapperHeight = wrapperRect.height;
        
        // 枠外に出るギリギリまでの移動制限を計算（イラストの上部がキャンバ下部まで移動可能）
        const maxMoveX = Math.max(200, wrapperWidth * 0.8 + svgWidth * 0.9);
        const maxMoveY = Math.max(200, wrapperHeight * 0.9 + svgHeight * 0.9);
        
        let newX = currentTransform.x;
        let newY = currentTransform.y;
        
        switch (direction) {
            case 'up':
                newY = currentTransform.y - moveDistance;
                break;
            case 'down':
                newY = currentTransform.y + moveDistance;
                break;
            case 'left':
                newX = currentTransform.x - moveDistance;
                break;
            case 'right':
                newX = currentTransform.x + moveDistance;
                break;
        }
        
        // 動的に計算された移動範囲で制限
        newX = Math.max(-maxMoveX, Math.min(maxMoveX, newX));
        newY = Math.max(-maxMoveY, Math.min(maxMoveY, newY));
        
        // 値が変更された場合のみ更新
        if (newX !== currentTransform.x || newY !== currentTransform.y) {
            currentTransform.x = newX;
            currentTransform.y = newY;
            
            // transformを適用
            applySvgTransform();
            
            console.log(`SVG moved ${direction}. Current position: x=${currentTransform.x}, y=${currentTransform.y} (limits: ±${maxMoveX}, ±${maxMoveY})`);
        } else {
            console.log(`Movement ${direction} blocked - reached boundary (limits: ±${maxMoveX}, ±${maxMoveY})`);
        }
    }
    
    // SVG位置をリセット
    function resetSvgPosition() {
        currentTransform.x = 0;
        currentTransform.y = 0;
        applySvgTransform();
        
        console.log('SVG position reset to origin');
    }
    
    // SVGサイズをリセット
    function resetSvgScale() {
        currentTransform.scale = 1.0;
        applySvgTransform();
        updateScaleButtons();
        
        console.log('SVG scale reset to 100%');
    }
    
    // SVG回転機能
    function rotateSvg(degrees) {
        currentTransform.rotation += degrees;
        
        // 360度を超えた場合は正規化
        currentTransform.rotation = currentTransform.rotation % 360;
        if (currentTransform.rotation < 0) {
            currentTransform.rotation += 360;
        }
        
        applySvgTransform();
        
        console.log(`SVG rotated by ${degrees} degrees. Current rotation: ${currentTransform.rotation}°`);
    }
    
    // SVG回転をリセット
    function resetSvgRotation() {
        currentTransform.rotation = 0;
        applySvgTransform();
        
        console.log('SVG rotation reset to 0°');
    }
    
    // 色がグレー系・黒系かどうかを判定する関数
    function isGrayOrBlackColor(hexColor) {
        // 16進数カラーをRGBに変換
        const hex = hexColor.replace('#', '');
        const r = parseInt(hex.substr(0, 2), 16);
        const g = parseInt(hex.substr(2, 2), 16);
        const b = parseInt(hex.substr(4, 2), 16);
        
        // グレースケール判定：RGB値の差が小さい
        const maxDiff = Math.max(Math.abs(r - g), Math.abs(g - b), Math.abs(r - b));
        const isGrayish = maxDiff < 20; // RGB値の差が20以下ならグレー系
        
        // 明度計算（0-255）
        const brightness = (r * 0.299 + g * 0.587 + b * 0.114);
        
        // 以下の条件に該当する場合は除外対象（白に近い明るい色は除外しない）
        // 1. グレー系かつ中程度の明度（30-200の範囲）
        // 2. 非常に暗い色（明度30以下、黒に近い色）
        if (brightness < 30) {
            // 非常に暗い色は無条件で除外
            return true;
        } else if (isGrayish && brightness >= 30 && brightness <= 200) {
            // 中程度の明度のグレー系のみ除外（白に近い色は変更対象）
            return true;
        }
        
        return false;
    }
    
    // 季節テーマを適用する関数（ランダム選択、ダークグレー系除外）
    function applySeasonalTheme(season) {
        if (!seasonalPalettes[season]) {
            console.error(`Unknown season: ${season}`);
            return;
        }
        
        const palette = seasonalPalettes[season];
        console.log(`Applying ${palette.name} (random selection, excluding gray/black colors)...`);
        
        // 抽出された色が存在しない場合は処理を停止
        if (!extractedColors || extractedColors.length === 0) {
            console.log('No colors extracted yet. Extracting colors first...');
            extractColorsFromSvg();
            setTimeout(() => applySeasonalTheme(season), 500);
            return;
        }
        
        let appliedCount = 0;
        let excludedCount = 0;
        
        // 黒・グレー除外設定を確認
        const excludeGraySwitch = document.getElementById('excludeGraySwitch');
        let shouldExcludeGray = excludeGraySwitch ? excludeGraySwitch.checked : true;
        
        // モノクロームテーマの場合は自動的に除外設定をOFFにする
        if (season === 'monochrome') {
            shouldExcludeGray = false;
            if (excludeGraySwitch) {
                excludeGraySwitch.checked = false;
            }
            console.log('Monochrome theme: Gray exclusion automatically disabled');
        }
        
        // 各抽出色に対してランダムな季節カラーを適用
        for (let i = 0; i < extractedColors.length; i++) {
            const originalColor = extractedColors[i].color;
            
            // 設定がONの場合のみグレー系・黒系の色は変更しない
            if (shouldExcludeGray && isGrayOrBlackColor(originalColor)) {
                console.log(`Excluded gray/black color: ${originalColor}`);
                excludedCount++;
                continue;
            }
            
            // パレットの色をランダムに選択（重複も許可）
            const randomIndex = Math.floor(Math.random() * palette.colors.length);
            const newColor = palette.colors[randomIndex];
            
            // 色のマッピングを更新
            colorMappings.set(originalColor, newColor);
            appliedCount++;
        }
        
        // SVGの色を更新
        updateSvgColors();
        
        // カラーパレット表示を更新
        updateColorPalette();
        
        // 成功メッセージ（除外情報も含む）
        let message = `${palette.name}をランダム適用しました（${appliedCount}色）`;
        if (shouldExcludeGray && excludedCount > 0) {
            message += `・グレー/黒系${excludedCount}色は保持`;
        } else if (!shouldExcludeGray && extractedColors.length > appliedCount) {
            message += `・全ての色を変更対象としました`;
        }
        showMessage(message, 'success');
        
        // 季節の背景色もランダムに適用
        if (palette.bgColors && palette.bgColors.length > 0) {
            const randomBgIndex = Math.floor(Math.random() * palette.bgColors.length);
            const seasonalBgColor = palette.bgColors[randomBgIndex];
            setBackgroundColor(seasonalBgColor);
            
            // カスタム背景色ピッカーにも反映
            const customBgColorInput = document.getElementById('customBgColor');
            if (customBgColorInput) {
                customBgColorInput.value = seasonalBgColor;
            }
            
            // 透明ボタンのアクティブ状態をクリア
            const transparentBtn = document.querySelector('.bg-color-btn[data-color="transparent"]');
            if (transparentBtn) {
                transparentBtn.classList.remove('active');
            }
            
            console.log(`Applied seasonal background color: ${seasonalBgColor}`);
        }
        
        console.log(`Applied seasonal theme randomly: ${season}`);
        console.log(`Applied: ${appliedCount} colors, Excluded (gray/black): ${excludedCount} colors, Gray exclusion: ${shouldExcludeGray ? 'ON' : 'OFF'}`);
        console.log('Color mappings:', Object.fromEntries(colorMappings));
    }
    
    // SVGにtransformを適用
    function applySvgTransform() {
        const svg = document.getElementById('customizable-svg');
        if (!svg) {
            return;
        }
        
        // 移動用のグループ要素が存在しない場合は作成
        let moveGroup = svg.querySelector('#svg-move-group');
        if (!moveGroup) {
            // 新しいgタグを作成
            moveGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            moveGroup.setAttribute('id', 'svg-move-group');
            
            // 背景要素以外の全ての子要素を移動グループに移動
            const childrenToMove = Array.from(svg.children).filter(child => child.id !== 'svg-background');
            childrenToMove.forEach(child => {
                moveGroup.appendChild(child);
            });
            
            // 移動グループをSVGに追加
            svg.appendChild(moveGroup);
        }
        
        // SVGの中心点を取得
        const svgRect = svg.getBoundingClientRect();
        const viewBox = svg.getAttribute('viewBox');
        let centerX = 256, centerY = 256; // デフォルト中心点（512x512の場合）
        
        if (viewBox) {
            const [vx, vy, vw, vh] = viewBox.split(' ').map(Number);
            centerX = vw / 2;
            centerY = vh / 2;
        } else {
            const width = parseFloat(svg.getAttribute('width')) || 512;
            const height = parseFloat(svg.getAttribute('height')) || 512;
            centerX = width / 2;
            centerY = height / 2;
        }
        
        // 移動グループにSVGのtransform属性を適用（移動、拡大、中心点回転）
        // 中心点を基準に回転するため、中心点まで移動→回転→中心点から戻す→拡大→最終位置へ移動
        const transformString = `translate(${currentTransform.x}, ${currentTransform.y}) scale(${currentTransform.scale}) translate(${centerX}, ${centerY}) rotate(${currentTransform.rotation}) translate(${-centerX}, ${-centerY})`;
        moveGroup.setAttribute('transform', transformString);
        
        console.log(`Applied SVG transform to move group: ${transformString}`);
    }
    
    // SVG拡大機能
    function scaleSvg(scale) {
        if (scale < 0.3 || scale > 2.0) {
            console.log(`Scale ${scale} is out of range (0.3 - 2.0)`);
            return;
        }
        
        currentTransform.scale = scale;
        applySvgTransform();
        updateScaleButtons();
        
        console.log(`SVG scaled to ${(scale * 100)}%`);
    }
    
    // 拡大ボタンのアクティブ状態を更新
    function updateScaleButtons() {
        const scaleButtons = document.querySelectorAll('.scale-btn');
        scaleButtons.forEach(button => {
            button.classList.remove('active');
        });
        
        // 現在のスケールに最も近いボタンをアクティブにする
        const currentScale = currentTransform.scale;
        const scaleOptions = [0.5, 0.7, 1.0, 1.2, 1.5];
        const closestScale = scaleOptions.reduce((prev, curr) => 
            Math.abs(curr - currentScale) < Math.abs(prev - currentScale) ? curr : prev
        );
        
        scaleButtons.forEach(button => {
            const buttonScale = parseFloat(button.textContent.replace('%', '')) / 100;
            if (Math.abs(buttonScale - closestScale) < 0.01) {
                button.classList.add('active');
            }
        });
    }
    
    // タブ切り替え関数にscaleタブを追加
    const originalSwitchTab = switchTab;
    function switchTab(tabName) {
        // すべてのタブパネルを非表示にする
        const allTabs = ['colorTab', 'backgroundTab', 'moveTab', 'scaleTab', 'rotateTab'];
        allTabs.forEach(tab => {
            const tabElement = document.getElementById(tab);
            if (tabElement) {
                tabElement.style.display = 'none';
            }
        });
        
        // すべてのタブボタンのアクティブ状態をリセット
        const allTabButtons = document.querySelectorAll('.tab-icon');
        allTabButtons.forEach(button => {
            button.classList.remove('active');
        });
        
        // 指定されたタブを表示
        const targetTab = document.getElementById(tabName + 'Tab');
        if (targetTab) {
            targetTab.style.display = 'block';
            
            // クリックされたボタンをアクティブにする
            const activeButton = document.querySelector(`.tab-icon[data-tab="${tabName}"]`);
            if (activeButton) {
                activeButton.classList.add('active');
            }
            
            // 色変更タブの場合、色抽出処理を実行
            if (tabName === 'color') {
                console.log('Color tab activated, extracting colors...');
                // SVGが完全に読み込まれるまで少し待つ
                setTimeout(() => {
                    extractColorsFromSvg();
                }, 100);
            }
            
            // 拡大タブの場合、ボタンの状態を更新
            if (tabName === 'scale') {
                updateScaleButtons();
            }
        }
    }
    

    </script>

</body>
</html>
