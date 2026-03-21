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

// 作品情報を取得（承認済みのみ）
$stmt = $pdo->prepare("
    SELECT * FROM community_artworks 
    WHERE id = ? AND status = 'approved'
");
$stmt->execute([$id]);
$artwork = $stmt->fetch();

if (!$artwork) {
    http_response_code(404);
    header('Location: /404.php');
    exit;
}

$backUrl = '/everyone-works.php';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($referer)) {
    $refererHost = parse_url($referer, PHP_URL_HOST);
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    if (empty($refererHost) || $refererHost === $currentHost) {
        $refererPath = parse_url($referer, PHP_URL_PATH);
        $refererQuery = parse_url($referer, PHP_URL_QUERY);
        if (!empty($refererPath)) {
            $backUrl = $refererPath . (!empty($refererQuery) ? '?' . $refererQuery : '');
        }
    }
}

// 閲覧数を増加
$updateViewStmt = $pdo->prepare("UPDATE community_artworks SET view_count = view_count + 1 WHERE id = ?");
$updateViewStmt->execute([$id]);

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

// 関連作品（類似度）を取得
$relatedArtworks = [];
$showRelatedSection = false;

try {
    $relatedStmt = $pdo->prepare("
        SELECT 
            cats.similar_artwork_id as id,
            cats.similar_artwork_title as title,
            cats.similar_artwork_pen_name as pen_name,
            cats.similar_artwork_file_path as file_path,
            cats.similar_artwork_webp_path as webp_path,
            cats.similarity_score
        FROM community_artwork_top_similarities cats
        WHERE cats.artwork_id = ?
        ORDER BY cats.similarity_score DESC
        LIMIT 8
    ");
    $relatedStmt->execute([$artwork['id']]);
    $relatedArtworks = $relatedStmt->fetchAll();
    
    $showRelatedSection = !empty($relatedArtworks);
} catch (Exception $e) {
    error_log("Related artworks query error: " . $e->getMessage());
    $relatedArtworks = [];
    $showRelatedSection = false;
}

// 関連素材を取得
$relatedMaterials = [];
$showRelatedMaterialsSection = false;

try {
    $stmt = $pdo->prepare("
        SELECT m.*, c.slug as category_slug
        FROM community_artwork_related_materials carm
        JOIN materials m ON carm.material_id = m.id
        LEFT JOIN categories c ON m.category_id = c.id
        WHERE carm.community_artwork_id = ?
        ORDER BY carm.similarity_score DESC
        LIMIT 12
    ");
    $stmt->execute([$artwork['id']]);
    $relatedMaterials = $relatedStmt->fetchAll();
    
    $showRelatedMaterialsSection = !empty($relatedMaterials);
} catch (Exception $e) {
    error_log("Related materials query error: " . $e->getMessage());
    $relatedMaterials = [];
    $showRelatedMaterialsSection = false;
}

// 全ての関連アイテムをマージしてシャッフル
$allRelatedItems = [];

// 使用した素材を追加（type: 'material'）
foreach ($usedMaterials as $material) {
    $material['item_type'] = 'material';
    $allRelatedItems[] = $material;
}

// 関連作品を追加（type: 'artwork'）
foreach ($relatedArtworks as $artwork_item) {
    $artwork_item['item_type'] = 'artwork';
    $allRelatedItems[] = $artwork_item;
}

// 関連素材を追加（type: 'material'）
foreach ($relatedMaterials as $material) {
    $material['item_type'] = 'material';
    $allRelatedItems[] = $material;
}

// シャッフル
shuffle($allRelatedItems);

$showRelatedItemsSection = !empty($allRelatedItems);

// 作品画像のURL（PNG優先）
$imagePath = !empty($artwork['file_path']) ? $artwork['file_path'] : $artwork['webp_path'];
// フルURL（R2など）の場合はそのまま、相対パスの場合は先頭に / を追加
$artworkImageUrl = (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) ? $imagePath : '/' . $imagePath;
$scheme = $_SERVER['REQUEST_SCHEME'] ?? 'https';
$host = $_SERVER['HTTP_HOST'];
$fullImageUrl = (strpos($artworkImageUrl, 'http://') === 0 || strpos($artworkImageUrl, 'https://') === 0) ? $artworkImageUrl : $scheme . '://' . $host . $artworkImageUrl;

// ダウンロード用のパス
$rawDownloadPath = !empty($artwork['file_path']) ? $artwork['file_path'] : $artwork['webp_path'];
// フルURL（R2など）の場合はそのまま、相対パスの場合は先頭に / を追加
$downloadPath = (strpos($rawDownloadPath, 'http://') === 0 || strpos($rawDownloadPath, 'https://') === 0) ? $rawDownloadPath : '/' . $rawDownloadPath;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="c5fko6zCuEianJGT3hyZsHgvNx5QAuuHKZ4TWgvV6J0">
    <title><?= h($artwork['title']) ?>｜無料イラスト素材（作成可）｜marutto.art</title>
    <meta name="description" content="<?= h($artwork['title']) ?>は、無料で組み合わせて作られています。ブログ・資料・SNSに使えるシンプルなイラスト素材です。">
    
    <!-- Site Icons -->
    <link rel="icon" href="/favicon.ico">
    
    <!-- Canonical tag -->
    <link rel="canonical" href="https://marutto.art/everyone-work.php?id=<?= $artwork['id'] ?>">
    
    <!-- hreflang tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/everyone-work.php?id=<?= $artwork['id'] ?>" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/everyone-work.php?id=<?= $artwork['id'] ?>" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/everyone-work.php?id=<?= $artwork['id'] ?>" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/everyone-work.php?id=<?= $artwork['id'] ?>" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/everyone-work.php?id=<?= $artwork['id'] ?>" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/everyone-work.php?id=<?= $artwork['id'] ?>" />
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h($scheme) ?>://<?= h($host) ?>/everyone-work.php?id=<?= $artwork['id'] ?>">
    <meta property="og:title" content="<?= h($artwork['title']) ?>｜無料イラスト素材（作成可）｜marutto.art">
    <meta property="og:description" content="<?= h($artwork['title']) ?>は、無料で組み合わせて作られています。ブログ・資料・SNSに使えるシンプルなイラスト素材です。">
    <meta property="og:image" content="<?= h($fullImageUrl) ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= h($scheme) ?>://<?= h($host) ?>/everyone-work.php?id=<?= $artwork['id'] ?>">
    <meta property="twitter:title" content="<?= h($artwork['title']) ?>｜無料イラスト素材（作成可）｜marutto.art">
    <meta property="twitter:description" content="<?= h($artwork['title']) ?>は、無料で組み合わせて作られています。ブログ・資料・SNSに使えるシンプルなイラスト素材です。">
    <meta property="twitter:image" content="<?= h($fullImageUrl) ?>">

    <!-- JSON-LD構造化データ -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ImageObject",
        "contentUrl": "<?= h($fullImageUrl) ?>",
        "license": "<?= h($scheme) ?>://<?= h($host) ?>/terms-of-use.php",
        "acquireLicensePage": "<?= h($scheme) ?>://<?= h($host) ?>/terms-of-use.php",
        "creditText": "marutto.art",
        "creator": {
            "@type": "Organization",
            "name": "marutto.art"
        },
        "copyrightNotice": "marutto.art"
    }
    </script>

    <!-- パンくずリスト構造化データ -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "name": "ホーム",
                "item": "<?= h($scheme) ?>://<?= h($host) ?>/"
            },
            {
                "@type": "ListItem",
                "position": 2,
                "name": "みんなのアトリエ",
                "item": "<?= h($scheme) ?>://<?= h($host) ?>/everyone-works.php"
            },
            {
                "@type": "ListItem",
                "position": 3,
                "name": "<?= h($artwork['title']) ?>",
                "item": "<?= h($scheme) ?>://<?= h($host) ?>/everyone-work.php?id=<?= $artwork['id'] ?>"
            }
        ]
    }
    </script>
    
    <?php include __DIR__ . '/includes/gtm-head.php'; ?>
    <?php include __DIR__ . '/includes/adsense-head.php'; ?>
    
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
                #F8FCFE 0%,
                #F0F8FC 25%,
                #E8F4FA 50%,
                #E0F0F7 75%,
                #D8ECF4 100%
            );
            min-height: 100vh;
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
            padding: 40px 0 30px;
        }

        /* 作品詳細セクション */
        .artwork-detail-section {
            max-width: 800px;
            margin: 0 auto 60px;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        /* 作品画像 */
        .artwork-image {
            width: 100%;
            max-width: 600px;
            height: auto;
            display: block;
            margin: 0 auto;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        /* 作品情報 */
        .artwork-info {
            display: flex;
            flex-direction: column;
            gap: 16px;
            text-align: center;
        }

        .artwork-title {
            font-size: clamp(1.5rem, 3vw, 2rem);
            font-weight: 600;
            color: #A0675C;
            line-height: 1.4;
        }

        .artwork-description {
            font-size: 1rem;
            line-height: 1.8;
            color: var(--text-dark);
            white-space: pre-wrap;
        }

        /* ボタン */
        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        @media (max-width: 576px) {
            .button-group {
                flex-direction: column;
            }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .btn-animation {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(232, 168, 124, 0.3);
        }

        .btn-animation:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(232, 168, 124, 0.4);
        }

        .back-link-container {
            margin-top: 20px;
            text-align: center;
        }

        /* セクションタイトル */
        .section-title {
            font-size: clamp(1.4rem, 3vw, 1.8rem);
            font-weight: 600;
            color: #A0675C;
            margin-bottom: 30px;
            text-align: center;
        }

        /* 関連素材・作品セクション */
        .related-items-section {
            margin: 3rem 0;
            padding: 2rem 0;
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 12px;
        }

        .related-items-section h2 {
            margin-bottom: 2rem;
            color: #A0675C;
            font-size: 1.5rem;
        }

        .related-items-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            max-width: 900px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .related-item {
            width: 100%;
        }

        .related-item-link {
            display: block;
            text-decoration: none;
            color: inherit;
            background: #FFFFFF;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .related-item-link:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            text-decoration: none;
            color: inherit;
        }

        .related-item-link:focus {
            outline: none;
            text-decoration: none;
            color: inherit;
        }

        .related-item-thumbnail {
            width: 100%;
            aspect-ratio: 1 / 1;
            background-color: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 8px;
        }

        .related-item-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            border-radius: 8px;
            transition: transform 0.2s ease;
        }

        .related-item-link:hover .related-item-image {
            transform: scale(1.02);
        }

        .related-item-info {
            padding-top: 12px;
        }

        .related-item-title {
            font-size: 0.9rem;
            font-weight: 500;
            color: #333;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .related-item-link:hover .related-item-title {
            color: #A0675C;
        }

        @media (max-width: 768px) {
            .related-items-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
                padding: 0 0.5rem;
            }
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
            width: 100%;
        }

        .material-card {
            display: block;
            text-decoration: none;
            color: inherit;
            background: #FFFFFF;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }

        .material-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            text-decoration: none;
            color: inherit;
        }

        .material-image-wrapper {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .material-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/gtm-body.php'; ?>
    
    <?php 
    $currentPage = 'everyone-works';
    include 'includes/header.php'; 
    ?>

    <div class="main-content">
        <div class="container">
            <!-- 作品詳細 -->
            <div class="artwork-detail-section">
                <!-- 作品画像 -->
                <img src="<?= h($artworkImageUrl) ?>" 
                     alt="<?= h($artwork['title']) ?>" 
                     class="artwork-image">

                <!-- ボタン -->
                <div class="button-group">
                    <button type="button"
                            class="btn btn-primary"
                            data-artwork-id="<?= $artwork['id'] ?>"
                            data-download-filename="works<?= $artwork['id'] ?>.png"
                            onclick="downloadArtwork(this)">
                        Download
                    </button>
                    <a href="/compose/?artwork_id=<?= $artwork['id'] ?>" 
                       class="btn btn-secondary">
                        Compose
                    </a>
                    <?php if (!empty($artwork['svg_data'])): ?>
                    <a href="/compose/animation.php?artwork_id=<?= $artwork['id'] ?>" 
                       class="btn btn-animation">
                        Animate
                    </a>
                    <?php endif; ?>
                </div>

                <!-- 作品情報 -->
                <div class="artwork-info">
                    <h1 class="artwork-title"><?= h($artwork['title']) ?></h1>
                    
                    <?php if (!empty($artwork['description'])): ?>
                    <div class="artwork-description"><?= h($artwork['description']) ?></div>
                    <?php endif; ?>

                    <div class="back-link-container">
                        <a href="<?= h($backUrl) ?>" class="btn btn-secondary">
                            前に戻る
                        </a>
                    </div>
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
                                <div class="related-item-thumbnail" style="background-color: <?= h($item['structured_bg_color'] ?? '#ffffff') ?>;">
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
                                <div class="related-item-info">
                                    <div class="related-item-title"><?= h($item['title']) ?></div>
                                </div>
                            </a>
                        <?php else: ?>
                            <!-- 作品 -->
                            <a href="/everyone-work.php?id=<?= h($item['id']) ?>" class="related-item-link">
                                <div class="related-item-thumbnail">
                                    <?php 
                                    $thumbnail = '';
                                    if (!empty($item['webp_path'])) {
                                        $thumbnail = $item['webp_path'];
                                    } elseif (!empty($item['file_path'])) {
                                        $thumbnail = $item['file_path'];
                                    }
                                    ?>
                                    <?php if (!empty($thumbnail)): ?>
                                    <?php 
                                    // フルURL（R2など）の場合はそのまま、相対パスの場合は先頭に / を追加
                                    $thumbnailUrl = (strpos($thumbnail, 'http://') === 0 || strpos($thumbnail, 'https://') === 0) ? $thumbnail : '/' . $thumbnail;
                                    ?>
                                    <img src="<?= h($thumbnailUrl) ?>" 
                                         alt="<?= h($item['title']) ?>" 
                                         class="related-item-image"
                                         loading="lazy">
                                    <?php endif; ?>
                                </div>
                                <div class="related-item-info">
                                    <div class="related-item-title"><?= h($item['title']) ?></div>
                                </div>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
    async function downloadArtwork(button) {
        const artworkId = button.dataset.artworkId;
        const filename = button.dataset.downloadFilename;
        const originalText = button.textContent;
        
        try {
            button.textContent = 'Downloading...';
            button.disabled = true;
            
            const url = `/download-artwork.php?id=${artworkId}&type=community`;
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error('Download failed');
            }
            
            const blob = await response.blob();
            const blobUrl = window.URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = blobUrl;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            
            window.URL.revokeObjectURL(blobUrl);
            document.body.removeChild(a);
            
            button.textContent = originalText;
            button.disabled = false;
        } catch (error) {
            console.error('Download error:', error);
            alert('ダウンロードに失敗しました。');
            button.textContent = originalText;
            button.disabled = false;
        }
    }
    </script>
</body>
</html>
