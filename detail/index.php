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
    $tweetText = $title . 'のイラスト' . "\n";
    $tweetText .= '#フリー素材 #無料素材 #イラスト #clipart';
    return $tweetText;
}

// 構造化データ用画像のURLを取得
function getStructuredDataImageUrl($material) {
    $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'https';
    $host = $_SERVER['HTTP_HOST'];
    
    if (!empty($material['structured_image_path'])) {
        $absolutePath = dirname(__DIR__) . '/' . $material['structured_image_path'];
        if (file_exists($absolutePath)) {
            return "{$scheme}://{$host}/{$material['structured_image_path']}";
        }
    }
    
    return "{$scheme}://{$host}/{$material['image_path']}";
}

$tweetText = createTweetText($material['title']);
$structuredImageUrl = getStructuredDataImageUrl($material);

// 関連画像（類似度）を取得
$relatedMaterials = [];
$showRelatedSection = false;

try {
    $viewCheckStmt = $pdo->query("
        SELECT COUNT(*) as view_count 
        FROM information_schema.views 
        WHERE table_schema = DATABASE() 
        AND table_name = 'material_top_similarities'
    ");
    $viewResult = $viewCheckStmt->fetch();
    $viewExists = $viewResult['view_count'] > 0;
    
    if ($viewExists) {
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

// 全ての関連アイテムをマージしてシャッフル
$allRelatedItems = [];

// 関連素材を追加（type: 'material'）
foreach ($relatedMaterials as $item) {
    $item['item_type'] = 'material';
    $allRelatedItems[] = $item;
}

// 関連作品を追加（type: 'artwork'）
foreach ($relatedArtworks as $item) {
    $item['item_type'] = 'artwork';
    $allRelatedItems[] = $item;
}

// シャッフル
shuffle($allRelatedItems);

$showRelatedItemsSection = !empty($allRelatedItems);

// 背景浮遊用の素材を取得（8件）
$floatingMaterialsSql = "SELECT m.webp_small_path as image_path, m.structured_bg_color FROM materials m ORDER BY RAND() LIMIT 8";
$floatingMaterialsStmt = $pdo->prepare($floatingMaterialsSql);
$floatingMaterialsStmt->execute();
$floatingMaterials = $floatingMaterialsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google AdSense -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8053468089362860"
         crossorigin="anonymous"></script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($material['title']) ?> - ミニマルなフリーイラスト素材（商用利用OK）｜marutto.art</title>
    <meta name="description" content="<?= h($material['title']) ?>のミニマルなフリーイラスト素材（商用利用OK）。ブログや資料に使いやすい、主張しすぎないやさしいデザインです。">
    <link rel="icon" href="/favicon.ico">
    
    <!-- Canonical tag -->
    <link rel="canonical" href="https://marutto.art/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/">
    
    <!-- hreflang tags -->
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
    <meta property="og:description" content="<?= h($material['title']) ?>のミニマルなフリーイラスト素材（商用利用OK）。ブログや資料に使いやすい、主張しすぎないやさしいデザインです。">
    <meta property="og:image" content="<?= h($structuredImageUrl) ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/">
    <meta property="twitter:title" content="<?= h($material['title']) ?> - ミニマルなフリーイラスト素材（商用利用OK）">
    <meta property="twitter:description" content="<?= h($material['title']) ?>のミニマルなフリーイラスト素材（商用利用OK）。ブログや資料に使いやすい、主張しすぎないやさしいデザインです。">
    <meta property="twitter:image" content="<?= h($structuredImageUrl) ?>">
    
    <?php include __DIR__ . '/../includes/gtm-head.php'; ?>
    
    <style>
        :root {
            --primary-color: #E8A87C;
            --secondary-color: #C38E70;
            --text-dark: #5A4A42;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Noto Sans JP', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--text-dark);
            background: linear-gradient(180deg, #FFF0E5 0%, #FFF5F8 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* 浮遊素材背景 */
        .floating-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .floating-material {
            position: absolute;
            opacity: 0;
            animation: floatUp linear infinite;
            backdrop-filter: blur(8px);
            border-radius: 50%;
            padding: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .floating-material img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        @keyframes floatUp {
            0% {
                transform: translateY(100vh) translateX(0) scale(0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.6;
            }
            90% {
                opacity: 0.6;
            }
            100% {
                transform: translateY(-100px) translateX(var(--drift)) scale(1) rotate(360deg);
                opacity: 0;
            }
        }

        .floating-material:nth-child(1) {
            left: 10%;
            width: 100px;
            height: 100px;
            animation-duration: 15s;
            animation-delay: 0s;
            --drift: 30px;
        }

        .floating-material:nth-child(2) {
            left: 25%;
            width: 85px;
            height: 85px;
            animation-duration: 18s;
            animation-delay: 2s;
            --drift: -20px;
        }

        .floating-material:nth-child(3) {
            left: 50%;
            width: 120px;
            height: 120px;
            animation-duration: 20s;
            animation-delay: 4s;
            --drift: 40px;
        }

        .floating-material:nth-child(4) {
            left: 70%;
            width: 90px;
            height: 90px;
            animation-duration: 16s;
            animation-delay: 1s;
            --drift: -35px;
        }

        .floating-material:nth-child(5) {
            left: 85%;
            width: 110px;
            height: 110px;
            animation-duration: 19s;
            animation-delay: 3s;
            --drift: 25px;
        }

        .floating-material:nth-child(6) {
            left: 5%;
            width: 95px;
            height: 95px;
            animation-duration: 17s;
            animation-delay: 5s;
            --drift: -30px;
        }

        .floating-material:nth-child(7) {
            left: 40%;
            width: 105px;
            height: 105px;
            animation-duration: 21s;
            animation-delay: 6s;
            --drift: 35px;
        }

        .floating-material:nth-child(8) {
            left: 60%;
            width: 80px;
            height: 80px;
            animation-duration: 14s;
            animation-delay: 7s;
            --drift: -25px;
        }

        /* メインコンテンツ */
        .main-content {
            position: relative;
            z-index: 1;
            padding: 40px 0 80px;
        }

        /* 詳細セクション */
        .detail-section {
            margin-bottom: 40px;
            text-align: center;
        }

        .detail-image-container {
            max-width: 600px;
            margin: 0 auto 30px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        .detail-image {
            width: 100%;
            height: auto;
            display: block;
        }

        .detail-info {
            max-width: 800px;
            margin: 0 auto;
        }

        .detail-info h1 {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 20px;
            color: #A0675C;
        }

        .detail-description {
            font-size: 1rem;
            line-height: 1.8;
            color: #666;
            margin-bottom: 30px;
        }

        .detail-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 30px;
            justify-content: center;
        }

        .action-button {
            padding: 14px 28px;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .action-button-primary {
            background: var(--primary-color);
            color: white;
        }

        .action-button-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .action-button-secondary {
            background: rgba(255, 255, 255, 0.9);
            color: var(--text-dark);
            border: 2px solid var(--primary-color);
        }

        .action-button-secondary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .detail-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            padding: 20px 0;
            border-top: 1px solid rgba(0,0,0,0.1);
            border-bottom: 1px solid rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #666;
        }

        .meta-label {
            font-weight: 600;
            color: #8B7355;
        }

        /* タグ */
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 20px;
            justify-content: center;
        }

        .tag {
            padding: 6px 16px;
            background: rgba(232, 168, 124, 0.15);
            color: var(--secondary-color);
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .tag:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        /* セクションタイトル */
        .section-title {
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 600;
            margin-bottom: 30px;
            color: #A0675C;
            text-align: center;
        }

        /* マソンリーグリッド */
        .masonry-grid {
            column-count: 4;
            column-gap: 30px;
            padding: 0;
        }

        @media (max-width: 992px) {
            .masonry-grid {
                column-count: 3;
                column-gap: 24px;
            }
        }

        @media (max-width: 768px) {
            .masonry-grid {
                column-count: 2;
                column-gap: 20px;
            }
        }

        .masonry-item {
            break-inside: avoid;
            margin-bottom: 30px;
            display: inline-block;
            width: 100%;
        }

        .material-card {
            display: block;
            text-decoration: none;
            color: inherit;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 16px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .material-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            text-decoration: none;
            color: inherit;
        }

        .material-image-wrapper {
            width: 100%;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
        }

        .material-image {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.2s ease;
        }

        .material-card:hover .material-image {
            transform: scale(1.02);
        }

        .material-title {
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
            margin-top: 12px;
            margin-bottom: 0;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .material-card:hover .material-title {
            color: #A0675C;
        }

        /* 関連素材・作品セクション */
        .related-items-section {
            margin: 3rem 0;
            padding: 2rem 0;
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
        }

        .related-items-grid {
            column-count: 4;
            column-gap: 1.5rem;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        @media (max-width: 768px) {
            .related-items-grid {
                column-count: 2;
                column-gap: 1rem;
                padding: 0 0.5rem;
            }

            .related-item {
                margin-bottom: 1rem;
            }
        }

        .related-item {
            break-inside: avoid;
            margin-bottom: 30px;
            display: inline-block;
            width: 100%;
        }

        .related-item-link {
            display: block;
            text-decoration: none;
            color: inherit;
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 16px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .related-item-link:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            text-decoration: none;
            color: inherit;
        }

        .related-item-thumbnail {
            width: 100%;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .related-item-image {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.2s ease;
        }

        .related-item-link:hover .related-item-image {
            transform: scale(1.02);
        }

        .related-item-title {
            font-size: 0.9rem;
            font-weight: 500;
            color: #333;
            line-height: 1.4;
            margin-top: 12px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .related-item-link:hover .related-item-title {
            color: #A0675C;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/gtm-body.php'; ?>
    
    <!-- 浮遊素材背景 -->
    <div class="floating-container">
        <?php foreach ($floatingMaterials as $index => $material_bg): 
            if (!empty($material_bg['image_path'])): 
                $floatingBgColor = !empty($material_bg['structured_bg_color']) ? $material_bg['structured_bg_color'] : '#ffffff';
            ?>
        <div class="floating-material" style="background-color: <?= h($floatingBgColor) ?>;">
            <img src="/<?= h($material_bg['image_path']) ?>" alt="素材" loading="lazy">
        </div>
        <?php endif; endforeach; ?>
    </div>
    
    <?php 
    $currentPage = 'detail';
    include __DIR__ . '/../includes/header.php'; 
    ?>

    <div class="main-content">
        <div class="container">
            <!-- 詳細セクション -->
            <div class="detail-section">
                <!-- 作品画像 -->
                <div class="detail-image-container" style="background-color: <?= h($material['structured_bg_color'] ?? '#f0f0f0') ?>;">
                    <?php 
                    // structured_image_pathを優先
                    $imagePath = !empty($material['structured_image_path']) 
                        ? $material['structured_image_path'] 
                        : (!empty($material['webp_small_path']) ? $material['webp_small_path'] : $material['image_path']);
                    ?>
                    <img 
                        src="/<?= h($imagePath) ?>" 
                        alt="<?= h($material['title']) ?>"
                        class="detail-image"
                    >
                </div>

                <div class="detail-info">
                    <div class="detail-actions">
                        <a href="/<?= h($material['image_path']) ?>" download class="action-button action-button-primary">
                            PNGダウンロード
                        </a>
                        <?php if (!empty($material['svg_path'])): ?>
                        <a href="/<?= h($material['svg_path']) ?>" download class="action-button action-button-primary">
                            SVGダウンロード
                        </a>
                        <?php endif; ?>
                        <a href="/compose/?material_id=<?= h($material['id']) ?>" class="action-button action-button-secondary">
                            カスタマイズ
                        </a>
                    </div>

                    <h1><?= h($material['title']) ?></h1>
                    
                    <?php if (!empty($material['description'])): ?>
                    <p class="detail-description"><?= nl2br(h($material['description'])) ?></p>
                    <?php endif; ?>

                    <div class="detail-meta">
                        <div class="meta-item">
                            <span class="meta-label">カテゴリ:</span>
                            <a href="/<?= h($category['slug']) ?>/" style="color: var(--primary-color); text-decoration: none;">
                                <?= h($category['title']) ?>
                            </a>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">ライセンス:</span>
                            <span>商用利用OK・クレジット表記不要</span>
                        </div>
                    </div>

                    <?php if (!empty($materialTags)): ?>
                    <div class="tags-container">
                        <?php foreach ($materialTags as $tag): ?>
                        <a href="/tag/<?= h($tag['slug']) ?>/" class="tag">
                            #<?= h($tag['name']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 関連素材・作品 -->
            <?php if ($showRelatedItemsSection): ?>
            <div class="related-items-section">
                <h2 class="section-title">関連素材・作品</h2>
                
                <div class="related-items-grid">
                    <?php foreach ($allRelatedItems as $item): ?>
                    <div class="related-item">
                        <?php if ($item['item_type'] === 'material'): ?>
                            <!-- 素材 -->
                            <a href="/<?= h($item['category_slug']) ?>/<?= h($item['slug']) ?>/" 
                               class="related-item-link">
                                <div class="related-item-thumbnail" style="background-color: <?= h($item['structured_bg_color'] ?? '#f0f0f0') ?>;">
                                    <img src="/<?= h($item['webp_small_path'] ?? $item['image_path']) ?>" 
                                         alt="<?= h($item['title']) ?>" 
                                         class="related-item-image"
                                         loading="lazy">
                                </div>
                                <div class="related-item-title"><?= h($item['title']) ?></div>
                            </a>
                        <?php else: ?>
                            <!-- 作品 -->
                            <a href="/everyone-work.php?id=<?= h($item['community_artwork_id']) ?>" 
                               class="related-item-link">
                                <div class="related-item-thumbnail">
                                    <?php 
                                    $artworkImagePath = !empty($item['artwork_webp_path']) 
                                        ? $item['artwork_webp_path'] 
                                        : $item['artwork_image_path'];
                                    // フルURL（R2など）の場合はそのまま、相対パスの場合は先頭に / を追加
                                    $artworkImageUrl = (strpos($artworkImagePath, 'http://') === 0 || strpos($artworkImagePath, 'https://') === 0) ? $artworkImagePath : '/' . $artworkImagePath;
                                    ?>
                                    <img src="<?= h($artworkImageUrl) ?>" 
                                         alt="<?= h($item['artwork_title']) ?>" 
                                         class="related-item-image"
                                         loading="lazy">
                                </div>
                                <div class="related-item-title"><?= h($item['artwork_title']) ?></div>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
