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
            background: linear-gradient(
                to bottom,
                #FFF9F5 0%,
                #FFF3EB 25%,
                #FFEDE1 50%,
                #FFE7D7 75%,
                #FFE1CD 100%
            );
            min-height: 100vh;
            overflow-x: clip;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
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
            margin: 30px 0;
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
            margin: 30px 0;
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

        /* ペンギンの吹き出し */
        .remix-tip {
            max-width: 600px;
            margin: 30px auto 0;
            padding: 0 20px;
        }

        .remix-tip-content {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .remix-tip-character {
            flex-shrink: 0;
            width: 60px;
            height: 60px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }

        .remix-tip-character img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .remix-tip-bubble {
            position: relative;
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            flex: 1;
        }

        .remix-tip-bubble::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 20px;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 10px 10px 10px 0;
            border-color: transparent white transparent transparent;
        }

        .remix-tip-text {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .remix-tip-content {
                gap: 12px;
            }

            .remix-tip-character {
                width: 50px;
                height: 50px;
            }

            .remix-tip-bubble {
                padding: 14px 16px;
                border-radius: 14px;
            }

            .remix-tip-bubble::before {
                left: -8px;
                top: 16px;
                border-width: 8px 8px 8px 0;
            }

            .remix-tip-text {
                font-size: 0.85rem;
            }
        }

        /* セクションタイトル */
        .section-title {
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 600;
            margin-bottom: 30px;
            color: #A0675C;
            text-align: center;
        }

        /* グリッドレイアウト */
        .masonry-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            padding: 0;
        }

        @media (max-width: 992px) {
            .masonry-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 24px;
            }
        }

        @media (max-width: 768px) {
            .masonry-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }

        .masonry-item {
            display: block;
            width: 100%;
        }

        .material-card {
            display: block;
            text-decoration: none;
            color: inherit;
            background: #FFFFFF;
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
            aspect-ratio: 1 / 1;
        }

        .material-image {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
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
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        @media (max-width: 768px) {
            .related-items-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
                padding: 0 0.5rem;
            }
        }

        .related-item {
            display: block;
            width: 100%;
        }

        .related-item-link {
            display: block;
            text-decoration: none;
            color: inherit;
            background: #FFFFFF;
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
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .related-item-image {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
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
                    $isRemoteUrl = strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0;
                    $finalImageUrl = $isRemoteUrl ? $imagePath : '/' . $imagePath;
                    ?>
                    <img 
                        src="<?= h($finalImageUrl) ?>" 
                        alt="<?= h($material['title']) ?>"
                        class="detail-image"
                    >
                </div>

                <div class="detail-info">
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

                    <div class="detail-actions">
                        <?php
                        $downloadImagePath = $material['image_path'];
                        $isRemoteDownloadUrl = strpos($downloadImagePath, 'http://') === 0 || strpos($downloadImagePath, 'https://') === 0;
                        $finalDownloadUrl = $isRemoteDownloadUrl ? $downloadImagePath : '/' . $downloadImagePath;
                        ?>
                        <a href="<?= h($finalDownloadUrl) ?>" download class="action-button action-button-primary">
                            PNGダウンロード
                        </a>
                        <?php if (!empty($material['svg_path'])): ?>
                        <?php
                        $svgPath = $material['svg_path'];
                        $isRemoteSvgUrl = strpos($svgPath, 'http://') === 0 || strpos($svgPath, 'https://') === 0;
                        $finalSvgUrl = $isRemoteSvgUrl ? $svgPath : '/' . $svgPath;
                        ?>
                        <a href="<?= h($finalSvgUrl) ?>" download class="action-button action-button-primary">
                            SVGダウンロード
                        </a>
                        <a href="/compose/?material_id=<?= h($material['id']) ?>" class="action-button action-button-secondary">
                            reMix
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($material['svg_path'])): ?>
                    <div class="remix-tip">
                        <div class="remix-tip-content">
                            <div class="remix-tip-character">
                                <img src="https://assets.marutto.art/characters/penguin.webp" alt="ペンギン">
                            </div>
                            <div class="remix-tip-bubble">
                                <p class="remix-tip-text">reMixで、色を変えたり、他の素材と組み合わせて遊べるよ。</p>
                            </div>
                        </div>
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
                                    <?php
                                    $relatedItemPath = $item['webp_small_path'] ?? $item['image_path'];
                                    $isRemoteRelated = strpos($relatedItemPath, 'http://') === 0 || strpos($relatedItemPath, 'https://') === 0;
                                    $relatedItemUrl = $isRemoteRelated ? $relatedItemPath : '/' . $relatedItemPath;
                                    ?>
                                    <img src="<?= h($relatedItemUrl) ?>" 
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
