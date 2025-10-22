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

// リファラーから検索やタグページからのアクセスかを判定
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$searchQuery = '';
$tagSlug = '';
$isFromSearch = false;
$isFromTag = false;

// URLパラメータもチェック（直接アクセスの場合）
$fromSearch = $_GET['from'] ?? '';
$searchParam = $_GET['q'] ?? '';
$tagParam = $_GET['tag'] ?? '';

if ($fromSearch === 'search' && !empty($searchParam)) {
    $isFromSearch = true;
    $searchQuery = $searchParam;
} elseif ($fromSearch === 'tag' && !empty($tagParam)) {
    $isFromTag = true;
    $tagSlug = $tagParam;
} elseif (strpos($referer, '/list.php') !== false && strpos($referer, 'q=') !== false) {
    // 検索結果からのアクセス
    $isFromSearch = true;
    parse_str(parse_url($referer, PHP_URL_QUERY), $queryParams);
    $searchQuery = $queryParams['q'] ?? '';
} elseif (strpos($referer, '/tag/') !== false) {
    // タグページからのアクセス
    $isFromTag = true;
    $refererPath = parse_url($referer, PHP_URL_PATH);
    if (preg_match('/\/tag\/([^\/]+)\/?/', $refererPath, $matches)) {
        $tagSlug = $matches[1];
    }
}

// 前後の素材を取得（コンテキストに応じて）
$prevMaterial = null;
$nextMaterial = null;

if ($isFromSearch && !empty($searchQuery)) {
    // 検索結果内での前後
    $searchTerms = explode(' ', $searchQuery);
    $searchConditions = [];
    $searchParams = [];
    
    foreach ($searchTerms as $term) {
        if (!empty(trim($term))) {
            $searchConditions[] = "(m.title LIKE ? OR m.description LIKE ?)";
            $searchParams[] = '%' . trim($term) . '%';
            $searchParams[] = '%' . trim($term) . '%';
        }
    }
    
    if (!empty($searchConditions)) {
        $searchWhere = implode(' AND ', $searchConditions);
        
        // 前の素材
        $prevQuery = "SELECT m.slug, m.title, c.slug as category_slug FROM materials m 
                     JOIN categories c ON m.category_id = c.id 
                     WHERE ($searchWhere) AND m.id < ? 
                     ORDER BY m.id DESC LIMIT 1";
        $prevParams = array_merge($searchParams, [$material['id']]);
        $prevStmt = $pdo->prepare($prevQuery);
        $prevStmt->execute($prevParams);
        $prevMaterial = $prevStmt->fetch();
        
        // 次の素材
        $nextQuery = "SELECT m.slug, m.title, c.slug as category_slug FROM materials m 
                     JOIN categories c ON m.category_id = c.id 
                     WHERE ($searchWhere) AND m.id > ? 
                     ORDER BY m.id ASC LIMIT 1";
        $nextParams = array_merge($searchParams, [$material['id']]);
        $nextStmt = $pdo->prepare($nextQuery);
        $nextStmt->execute($nextParams);
        $nextMaterial = $nextStmt->fetch();
    }
} elseif ($isFromTag && !empty($tagSlug)) {
    // タグ内での前後
    $prevQuery = "SELECT m.slug, m.title, c.slug as category_slug FROM materials m 
                 JOIN categories c ON m.category_id = c.id 
                 JOIN material_tags mt ON m.id = mt.material_id 
                 JOIN tags t ON mt.tag_id = t.id 
                 WHERE t.slug = ? AND m.id < ? 
                 ORDER BY m.id DESC LIMIT 1";
    $prevStmt = $pdo->prepare($prevQuery);
    $prevStmt->execute([$tagSlug, $material['id']]);
    $prevMaterial = $prevStmt->fetch();
    
    $nextQuery = "SELECT m.slug, m.title, c.slug as category_slug FROM materials m 
                 JOIN categories c ON m.category_id = c.id 
                 JOIN material_tags mt ON m.id = mt.material_id 
                 JOIN tags t ON mt.tag_id = t.id 
                 WHERE t.slug = ? AND m.id > ? 
                 ORDER BY m.id ASC LIMIT 1";
    $nextStmt = $pdo->prepare($nextQuery);
    $nextStmt->execute([$tagSlug, $material['id']]);
    $nextMaterial = $nextStmt->fetch();
} else {
    // デフォルト：同じカテゴリ内での前後の素材を取得
    $prevStmt = $pdo->prepare("SELECT slug, title FROM materials WHERE category_id = ? AND id < ? ORDER BY id DESC LIMIT 1");
    $prevStmt->execute([$category['id'], $material['id']]);
    $prevMaterial = $prevStmt->fetch();

    $nextStmt = $pdo->prepare("SELECT slug, title FROM materials WHERE category_id = ? AND id > ? ORDER BY id ASC LIMIT 1");
    $nextStmt->execute([$category['id'], $material['id']]);
    $nextMaterial = $nextStmt->fetch();
}

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
        }
        
        // 同意状況を確認
        const consent = getGdprConsent();
        if (consent === 'accepted') {
            // 既に同意済みの場合は即座に読み込み
            loadGTM();
        }
        
        // GDPR同意イベントを監視（将来の同意に対応）
        window.addEventListener('gdpr-consent-accepted', loadGTM);
    })();
    </script>
    <!-- End Google Tag Manager -->
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($material['title']) ?>  - ミニマルなフリーイラスト素材（商用利用OK）｜marutto.art</title>
    <meta name="description" content="<?= h($material['title']) ?>のミニマルなフリーイラスト素材（商用利用OK）。<?= h($category['title']) ?>カテゴリのフリーイラストをお楽しみください。">

    <!-- Site Icons -->
    <link rel="icon" href="/favicon.ico">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
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

        /* ナビゲーションボタン - load-more-buttonと同じスタイル */
        .nav-button {
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
            white-space: nowrap;
        }

        .nav-button:hover {
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
            text-decoration: none;
        }

        .nav-button-disabled {
            background-color: #f8f9fa;
            color: #6c757d;
            border: 2px solid #dee2e6;
            opacity: 0.6;
            cursor: not-allowed;
        }

        .nav-button-disabled:hover {
            background-color: #f8f9fa;
            border-color: #dee2e6;
            color: #6c757d;
        }

        /* ナビゲーション全体の中央配置 */
        .nav-container {
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: nowrap;
        }

        @media (max-width: 576px) {
            .nav-container {
                flex-direction: row;
                gap: 0.75rem;
            }
            
            .nav-button {
                padding: 0.5em 1.5em;
                font-size: 0.9rem;
            }
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
        
        /* GDPR Cookie Banner のスタイル */
        #gdpr-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #212529;
            color: #ffffff;
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
            color: #ffffff;
        }
        
        .gdpr-buttons {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .gdpr-buttons .btn {
            flex: 0 0 auto;
            white-space: nowrap;
        }
        
        /* GDPR専用のボタンスタイル */
        #gdpr-banner .btn-outline-light {
            color: #ffffff;
            border-color: #ffffff;
            background-color: transparent;
        }

        #gdpr-banner .btn-outline-light:hover {
            color: #212529;
            background-color: #ffffff;
            border-color: #ffffff;
        }

        #gdpr-banner .btn-success {
            color: #000000;
            background-color: #ffffff;
            border-color: #ffffff;
        }

        #gdpr-banner .btn-success:hover {
            color: #000000;
            background-color: #f8f9fa;
            border-color: #f8f9fa;
        }
        
        @media (min-width: 768px) {
            .gdpr-buttons {
                margin-top: 0;
                justify-content: flex-end;
            }
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
        }

        .svg-image-wrapper {
            background: #ffffff;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 1rem;
            display: inline-block;
            max-width: 100%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
        </div>
    </nav>
    
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
                    
                    <!-- 前へ・次へナビゲーション（ダウンロードリンクの上） -->
                    <div class="nav-container mb-4">
                        <?php if ($prevMaterial): ?>
                            <?php
                            // リンクURLを生成（コンテキストに応じて）
                            $prevUrl = '';
                            if ($isFromSearch && !empty($searchQuery)) {
                                $prevCategorySlug = $prevMaterial['category_slug'] ?? $category['slug'];
                                $prevUrl = "/{$prevCategorySlug}/{$prevMaterial['slug']}/?from=search&q=" . urlencode($searchQuery);
                            } elseif ($isFromTag && !empty($tagSlug)) {
                                $prevCategorySlug = $prevMaterial['category_slug'] ?? $category['slug'];
                                $prevUrl = "/{$prevCategorySlug}/{$prevMaterial['slug']}/?from=tag&tag=" . urlencode($tagSlug);
                            } else {
                                $prevUrl = "/{$category['slug']}/{$prevMaterial['slug']}/";
                            }
                            ?>
                            <a href="<?= h($prevUrl) ?>" class="nav-button">
                                ← 前へ
                            </a>
                        <?php else: ?>
                            <span class="nav-button nav-button-disabled">← 前へ</span>
                        <?php endif; ?>
                        
                        <?php if ($nextMaterial): ?>
                            <?php
                            // リンクURLを生成（コンテキストに応じて）
                            $nextUrl = '';
                            if ($isFromSearch && !empty($searchQuery)) {
                                $nextCategorySlug = $nextMaterial['category_slug'] ?? $category['slug'];
                                $nextUrl = "/{$nextCategorySlug}/{$nextMaterial['slug']}/?from=search&q=" . urlencode($searchQuery);
                            } elseif ($isFromTag && !empty($tagSlug)) {
                                $nextCategorySlug = $nextMaterial['category_slug'] ?? $category['slug'];
                                $nextUrl = "/{$nextCategorySlug}/{$nextMaterial['slug']}/?from=tag&tag=" . urlencode($tagSlug);
                            } else {
                                $nextUrl = "/{$category['slug']}/{$nextMaterial['slug']}/";
                            }
                            ?>
                            <a href="<?= h($nextUrl) ?>" class="nav-button">
                                次へ →
                            </a>
                        <?php else: ?>
                            <span class="nav-button nav-button-disabled">次へ →</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- ダウンロードリンク -->
                    <div class="mb-4">
                        <div class="download-buttons">
                            <a href="/<?= h($material['image_path']) ?>" download class="download-link">
                                <i class="bi bi-download"></i> PNG/JPEGをダウンロード
                            </a>
                            
                            <?php if (isset($material['svg_path']) && !empty($material['svg_path'])): ?>
                            <a href="/<?= h($material['svg_path']) ?>" download class="download-link svg-download">
                                <i class="bi bi-vector-pen"></i> SVGをダウンロード
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
            <h5 class="svg-section-title mb-3">ベクター画像</h5>
            
            <!-- 色変更コントロール（カラーパレット方式） -->
            <div class="svg-controls mb-4">
                <div class="text-center mb-3">
                    <h6 class="text-primary mb-2">
                        <i class="bi bi-palette"></i> インタラクティブカラー編集
                    </h6>
                    <small class="text-muted">SVGの色をカスタマイズして、あなた好みのイラストにしましょう</small>
                </div>
                
                <!-- 色パレット表示エリア -->
                <div id="colorPalette" class="color-palette mb-4">
                    <div class="text-center text-muted">
                        <div class="mb-2">
                            <i class="bi bi-gear-fill rotating" style="font-size: 1.5rem; color: #4285f4;"></i>
                        </div>
                        <div>色を抽出しています...</div>
                        <small class="d-block mt-1">しばらくお待ちください</small>
                    </div>
                </div>
                
                <div class="text-center">
                    <div class="btn-group shadow-sm" style="border-radius: 12px; overflow: hidden;">
                        <button type="button" class="btn btn-outline-secondary" onclick="resetSvgColors()" style="border-radius: 12px 0 0 12px;">
                            <i class="bi bi-arrow-counterclockwise"></i> リセット
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="extractColorsFromSvg()">
                            <i class="bi bi-arrow-clockwise"></i> 再抽出
                        </button>
                        <button type="button" class="btn btn-success" onclick="downloadCustomSvg()" style="border-radius: 0 12px 12px 0;">
                            <i class="bi bi-download"></i> ダウンロード
                        </button>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            同じ色を何度でも違う色に変更できます。
                            <span class="badge bg-success ms-1"><i class="bi bi-arrow-repeat"></i></span> 変更済み、
                            <span class="badge bg-secondary ms-1">元</span> オリジナル色表示
                        </small>
                    </div>
                </div>
            </div>
            
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
                <div class="svg-info mt-2">
                    <small class="text-muted">
                        <i class="bi bi-vector-pen"></i> SVG形式 - 色を変更してダウンロード可能
                    </small>
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
            $shareText = urlencode($material['title'] . ' - ミニマルなフリーイラスト素材 #kawaiiilustrations #freeclipart #maruttoart');
            $twitterShareUrl = "https://twitter.com/intent/tweet?url={$currentUrl}&text={$shareText}";
            
            // Pinterest用のパラメータ
            $pinterestUrl = urldecode($currentUrl);
            $pinterestDescription = urlencode($material['title'] . ' - ミニマルなフリーイラスト素材（商用利用OK） #kawaiiilustrations #freeclipart #maruttoart');
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
    <?php if ($showRelatedSection): ?>
    <section class="related-materials mt-5">
        <div class="container">
            <h2 class="text-center mb-4">この子と仲良しのイラストたち</h2>
            <div class="row g-3">
                <?php foreach ($relatedMaterials as $relatedMaterial): ?>
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
                <?php endforeach; ?>
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

    <footer class="footer-custom mt-5 py-4">
        <div class="container">
            <div class="text-center">
                <div class="mb-2">
                    <a href="/terms-of-use.php" class="footer-text text-decoration-none me-3">利用規約</a>
                    <a href="/privacy-policy.php" class="footer-text text-decoration-none">プライバシーポリシー</a>
                </div>
                <div class="language-switcher mb-2">
                    <div class="gtranslate_wrapper"></div>
                    <script>window.gtranslateSettings = {"default_language":"ja","native_language_names":true,"url_structure":"sub_directory","languages":["ja","en","fr","es","nl"],"wrapper_selector":".gtranslate_wrapper"}</script>
                    <script src="https://cdn.gtranslate.net/widgets/latest/ln.js" defer></script>
                </div>
                <div>
                    <p class="footer-text mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- GDPR Cookie Banner (CDN対応・セッション不使用) -->
    <div id="gdpr-banner" class="hidden">
        <div class="container">
            <div style="display: flex; align-items: center; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <div class="gdpr-text">
                        当サイトではサイトの利便性向上のためCookieを使用しています。詳細は
                        <a href="/terms-of-use.php" style="color: #ffffff; text-decoration: underline;">利用規約</a>・
                        <a href="/privacy-policy.php" style="color: #ffffff; text-decoration: underline;">プライバシーポリシー</a>
                        をご確認ください。
                    </div>
                </div>
                <div style="margin-left: auto;">
                    <div class="gdpr-buttons">
                        <button id="gdpr-accept" class="btn btn-success btn-sm">同意する</button>
                        <button id="gdpr-decline" class="btn btn-outline-light btn-sm">拒否する</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- GDPR Cookie Consent Script (CDN対応・localStorage使用) -->
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
            
            // GTM読み込みイベントを発火
            const event = new CustomEvent('gdpr-consent-accepted');
            window.dispatchEvent(event);
        }
        
        // 拒否処理
        function declineConsent() {
            setGdprConsent('declined');
            hideBanner();
            
            // 拒否イベントを発火
            const event = new CustomEvent('gdpr-consent-declined');
            window.dispatchEvent(event);
        }
        
        // アナリティクス有効化（プレースホルダー）
        function enableAnalytics() {
            // ここに Google Analytics などの初期化コードを追加
        }
        
        // アナリティクス無効化（プレースホルダー）
        function disableAnalytics() {
            // ここにアナリティクス無効化のコードを追加する
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
    </script>

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
    
    // ページ読み込み時にオリジナルのSVGを保存し、色を抽出
    document.addEventListener('DOMContentLoaded', function() {
        const svg = document.getElementById('customizable-svg');
        if (svg) {
            originalSvgContent = svg.outerHTML;
            
            // 色を自動抽出
            setTimeout(extractColorsFromSvg, 500);
        }
    });
    

    

    
    // 元の色に戻す関数
    function resetSvgColors() {
        if (originalSvgContent) {
            const svgWrapper = document.querySelector('.svg-image-wrapper');
            if (svgWrapper) {
                svgWrapper.innerHTML = originalSvgContent;
                
                // 初期状態を完全に復元
                extractedColors = JSON.parse(JSON.stringify(initialColorStates));
                originalColors = JSON.parse(JSON.stringify(initialColorStates));
                
                // 色のマッピングを初期化
                colorMappings.clear();
                originalColors.forEach(colorInfo => {
                    colorMappings.set(colorInfo.color, colorInfo.color);
                });
                
                // カラーパレットを再生成
                setTimeout(() => {
                    displayColorPalette();
                    cancelColorChange();
                }, 100);
            }
        }

        // 色パレット選択をリセット
        selectedColorIndex = -1;
    }    // カスタム色でSVGをダウンロードする関数
    function downloadCustomSvg() {
        const svg = document.getElementById('customizable-svg');
        if (!svg) {
            alert('SVGが見つかりません');
            return;
        }
        
        // SVGの内容を取得
        const svgData = new XMLSerializer().serializeToString(svg);
        
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
        
        // 色スウォッチのみ更新（カラーピッカーUIは維持）
        let paletteHTML = '<div class="text-center mb-2"><small class="text-muted">SVGから抽出された色（クリックして変更）</small></div>';
        
        extractedColors.forEach((colorInfo, index) => {
            const originalColor = colorInfo.color;
            const currentColor = colorMappings.get(originalColor) || originalColor;
            const isChanged = currentColor !== originalColor;
            
            paletteHTML += `
                <div class="color-item" onclick="selectColor(${index})">
                    <div class="color-swatch-container">
                        <div class="color-swatch ${isChanged ? 'changed' : ''}" style="background-color: ${currentColor}" id="swatch-${index}">
                            <div class="usage-count">${colorInfo.count}</div>
                        </div>
                    </div>
                    <div class="color-code">${currentColor}</div>
                    ${isChanged ? `<div class="original-code">元: ${originalColor}</div>` : ''}
                </div>
            `;
        });
        
        // 既存のカラーピッカーUIを保持
        const existingPickerWrapper = document.getElementById('colorPickerWrapper');
        if (existingPickerWrapper) {
            paletteHTML += existingPickerWrapper.outerHTML;
        }
        
        paletteContainer.innerHTML = paletteHTML;
    }
    
    // カラーパレット表示
    function displayColorPalette() {
        const paletteContainer = document.getElementById('colorPalette');
        
        if (extractedColors.length === 0) {
            paletteContainer.innerHTML = `
                <div class="text-center text-muted">
                    <i class="bi bi-exclamation-circle"></i> 
                    このSVGには変更可能な色が見つかりませんでした
                </div>
            `;
            return;
        }
        
        let paletteHTML = '<div class="text-center mb-2"><small class="text-muted">SVGから抽出された色（クリックして変更）</small></div>';
        
        extractedColors.forEach((colorInfo, index) => {
            const originalColor = colorInfo.color;
            const currentColor = colorMappings.get(originalColor) || originalColor;
            const isChanged = currentColor !== originalColor;
            
            paletteHTML += `
                <div class="color-item" onclick="selectColor(${index})">
                    <div class="color-swatch-container">
                        <div class="color-swatch ${isChanged ? 'changed' : ''}" style="background-color: ${currentColor}" id="swatch-${index}">
                            <div class="usage-count">${colorInfo.count}</div>
                        </div>
                    </div>
                    <div class="color-code">${currentColor}</div>
                    ${isChanged ? `<div class="original-code">元: ${originalColor}</div>` : ''}
                </div>
            `;
        });
        
        paletteContainer.innerHTML = paletteHTML;
        paletteContainer.classList.add('loaded');
        
        // カラーピッカーUI追加
        paletteContainer.innerHTML += `
            <div class="color-picker-wrapper" id="colorPickerWrapper">
                <div class="text-center mb-3">
                    <h6 class="mb-2 text-primary">
                        <i class="bi bi-palette2"></i> 色を変更
                    </h6>
                    <small class="text-muted">新しい色を選択して適用してください</small>
                </div>
                
                <div class="color-picker-section justify-content-center mb-3">
                    <div class="text-center">
                        <div class="color-swatch-wrapper">
                            <div class="color-swatch" id="newColorSwatch" style="background-color: #ff0000;"></div>
                            <input type="color" class="hidden-color-input" id="newColorPicker" onchange="previewColorChange()">
                        </div>
                        <p class="color-label">新しい色を選択</p>
                    </div>
                </div>
                
                <div class="color-actions justify-content-center">
                    <button type="button" class="btn btn-primary" onclick="applyColorChange()">
                        <i class="bi bi-check-circle"></i> 変更を適用
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="cancelColorChange()">
                        <i class="bi bi-x-circle"></i> キャンセル
                    </button>
                </div>
            </div>
        `;
    }
    
    // 色を選択
    function selectColor(index) {
        selectedColorIndex = index;
        const originalColorInfo = originalColors[index]; // オリジナル色情報を参照
        
        // 全てのスウォッチからactiveクラスを削除
        document.querySelectorAll('.color-swatch').forEach(swatch => {
            swatch.classList.remove('active');
        });
        
        // 選択されたスウォッチにactiveクラスを追加
        document.getElementById(`swatch-${index}`).classList.add('active');
        
        // カラーピッカーを表示
        const pickerWrapper = document.getElementById('colorPickerWrapper');
        const newColorSwatch = document.getElementById('newColorSwatch');
        const newColorPicker = document.getElementById('newColorPicker');
        
        // 現在の色を表示（変更済みの場合は変更後の色）
        const currentColor = colorMappings.get(originalColorInfo.color) || originalColorInfo.color;
        newColorSwatch.style.backgroundColor = currentColor;
        newColorPicker.value = currentColor;
        pickerWrapper.classList.add('active');
    }
    
    // 色変更のプレビュー
    function previewColorChange() {
        if (selectedColorIndex === -1) return;
        
        const newColor = document.getElementById('newColorPicker').value;
        
        // 新しい色のスワッチを更新
        const newColorSwatch = document.getElementById('newColorSwatch');
        if (newColorSwatch) {
            newColorSwatch.style.backgroundColor = newColor;
        }
    }
    
    // 色選択をキャンセル
    function cancelColorChange() {
        selectedColorIndex = -1;
        document.getElementById('colorPickerWrapper').classList.remove('active');
        document.querySelectorAll('.color-swatch').forEach(swatch => {
            swatch.classList.remove('active');
        });
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
            cancelColorChange();
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
        
        // UIを閉じる
        cancelColorChange();
        
        // 完了メッセージ
        showMessage(`${currentColor} → ${newColor} に変更しました（${changeCount}箇所）`, 'success');
        
        // パレット表示を更新
        updateColorPalette();
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
    

    </script>

</body>
</html>
