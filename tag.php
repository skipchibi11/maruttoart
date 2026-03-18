<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$tag_slug = $_GET['slug'] ?? '';
if (empty($tag_slug)) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

$pdo = getDB();

// タグ情報を取得
$tagStmt = $pdo->prepare("SELECT * FROM tags WHERE slug = ?");
$tagStmt->execute([$tag_slug]);
$tag = $tagStmt->fetch();

if (!$tag) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

// ページネーション設定
$perPage = 20; // 1ページあたりの表示件数
$page = max(1, intval($_GET['page'] ?? 1)); // 現在のページ（最小値は1）
$offset = ($page - 1) * $perPage;

// そのタグが設定された素材を取得（カテゴリ情報も含める）
$countSql = "SELECT COUNT(DISTINCT m.id) FROM materials m 
             INNER JOIN material_tags mt ON m.id = mt.material_id 
             WHERE mt.tag_id = ?";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute([$tag['id']]);
$totalItems = $countStmt->fetchColumn();

// 5つ以上の素材がない場合は404ページに遷移
if ($totalItems < 5) {
    header('HTTP/1.0 404 Not Found');
    include '404.php';
    exit;
}

$totalPages = ceil($totalItems / $perPage);

$materialsSql = "SELECT DISTINCT m.*, c.slug as category_slug 
                 FROM materials m 
                 INNER JOIN material_tags mt ON m.id = mt.material_id 
                 LEFT JOIN categories c ON m.category_id = c.id 
                 WHERE mt.tag_id = ? 
                 ORDER BY m.created_at DESC 
                 LIMIT ? OFFSET ?";
$materialsStmt = $pdo->prepare($materialsSql);
$materialsStmt->execute([$tag['id'], $perPage, $offset]);
$materials = $materialsStmt->fetchAll();

// 背景浮遊用の素材を取得（8件）
$floatingMaterialsSql = "SELECT m.webp_small_path as image_path, m.structured_bg_color FROM materials m ORDER BY RAND() LIMIT 8";
$floatingMaterialsStmt = $pdo->prepare($floatingMaterialsSql);
$floatingMaterialsStmt->execute();
$floatingMaterials = $floatingMaterialsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($tag['name']) ?> - 無料イラスト素材｜商用利用OK｜marutto.art</title>
    <meta name="description" content="<?= h($tag['name']) ?>タグのフリーイラスト素材一覧。ミニマルなフリーイラスト素材を商用利用OK。<?= h($tag['name']) ?>に関連する高品質なフリー素材をダウンロードできます。">
    <link rel="icon" href="/favicon.ico">
    
    <!-- hreflang tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/tag/<?= h($tag['slug']) ?>/" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/tag/<?= h($tag['slug']) ?>/" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/tag/<?= h($tag['slug']) ?>/" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/tag/<?= h($tag['slug']) ?>/" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/tag/<?= h($tag['slug']) ?>/" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/tag/<?= h($tag['slug']) ?>/" />
    
    <?php include __DIR__ . '/includes/gtm-head.php'; ?>
    
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

        .page-title {
            text-align: center;
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 600;
            margin-bottom: 10px;
            color: #A0675C;
        }

        .page-subtitle {
            text-align: center;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            color: #8B7355;
            margin-bottom: 30px;
            font-weight: 500;
        }

        /* 結果表示 */
        .results-info {
            text-align: center;
            font-size: 1rem;
            color: #8B7355;
            margin-bottom: 30px;
        }

        .results-info strong {
            color: var(--primary-color);
            font-weight: 600;
        }

        /* マソンリーレイアウト */
        .masonry-grid {
            column-count: 5;
            column-gap: 30px;
            padding: 0;
        }

        @media (max-width: 992px) {
            .masonry-grid {
                column-count: 3;
                column-gap: 30px;
            }
        }

        @media (max-width: 768px) {
            .masonry-grid {
                column-count: 2;
                column-gap: 20px;
            }
        }

        @media (max-width: 576px) {
            .masonry-grid {
                column-count: 2;
                column-gap: 16px;
            }
        }

        .masonry-item {
            break-inside: avoid;
            margin-bottom: 40px;
            display: inline-block;
            width: 100%;
        }

        @media (max-width: 992px) {
            .masonry-item {
                margin-bottom: 30px;
            }
        }

        @media (max-width: 576px) {
            .masonry-item {
                margin-bottom: 24px;
            }
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

        .material-card:focus {
            outline: none;
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

        /* ページネーション */
        .pagination-container {
            margin-top: 60px;
            margin-bottom: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .pagination-button {
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            color: #8B7355;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .pagination-button:hover:not(.disabled) {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .pagination-button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .pagination-info {
            color: #8B7355;
            font-weight: 500;
            padding: 0 20px;
        }

        /* 結果なし */
        .no-results {
            text-align: center;
            padding: 80px 20px;
        }

        .no-results-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-results-title {
            font-size: 1.5rem;
            color: #8B7355;
            margin-bottom: 10px;
        }

        .no-results-text {
            color: #999;
            margin-bottom: 20px;
        }

        .no-results-button {
            display: inline-block;
            padding: 12px 32px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .no-results-button:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/gtm-body.php'; ?>
    
    <!-- 浮遊素材背景 -->
    <div class="floating-container">
        <?php foreach ($floatingMaterials as $index => $material): 
            if (!empty($material['image_path'])): 
                $floatingBgColor = !empty($material['structured_bg_color']) ? $material['structured_bg_color'] : '#ffffff';
                $isRemoteUrl = strpos($material['image_path'], 'http://') === 0 || strpos($material['image_path'], 'https://') === 0;
                $materialImageUrl = $isRemoteUrl ? $material['image_path'] : '/' . $material['image_path'];
            ?>
        <div class="floating-material" style="background-color: <?= h($floatingBgColor) ?>;">
            <img src="<?= h($materialImageUrl) ?>" alt="素材" loading="lazy">
        </div>
        <?php endif; endforeach; ?>
    </div>
    
    <?php 
    $currentPage = 'tag';
    include 'includes/header.php'; 
    ?>

    <div class="main-content">
        <div class="container">
            <h1 class="page-title"><?= h($tag['name']) ?></h1>
            <p class="page-subtitle">タグ別無料イラスト素材</p>

            <!-- 結果表示 -->
            <div class="results-info">
                全<strong><?= number_format($totalItems) ?></strong>件の素材
            </div>

            <!-- 素材一覧 -->
            <?php if (!empty($materials)): ?>
            <div class="masonry-grid">
                <?php foreach ($materials as $material): 
                    // 画像パス（webp_small_pathを優先）
                    $imagePath = !empty($material['webp_small_path']) ? $material['webp_small_path'] : $material['image_path'];
                    $isRemoteUrl = strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0;
                    $finalImageUrl = $isRemoteUrl ? $imagePath : '/' . $imagePath;
                    
                    // 背景色
                    $bgColor = !empty($material['structured_bg_color']) ? $material['structured_bg_color'] : '#f0f0f0';
                    
                    // リンク先
                    $detailUrl = "/{$material['category_slug']}/{$material['slug']}/";
                ?>
                <div class="masonry-item">
                    <a href="<?= h($detailUrl) ?>" class="material-card">
                        <div class="material-image-wrapper" style="background-color: <?= h($bgColor) ?>;">
                            <img 
                                src="<?= h($finalImageUrl) ?>" 
                                alt="<?= h($material['title']) ?>"
                                class="material-image"
                                loading="lazy"
                            >
                        </div>
                        <h2 class="material-title"><?= h($material['title']) ?></h2>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ページネーション -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <?php
                $prevUrl = $page > 1 ? '/tag/' . $tag['slug'] . '/?page=' . ($page - 1) : null;
                $nextUrl = $page < $totalPages ? '/tag/' . $tag['slug'] . '/?page=' . ($page + 1) : null;
                ?>
                
                <?php if ($prevUrl): ?>
                    <a href="<?= h($prevUrl) ?>" class="pagination-button">← 前へ</a>
                <?php else: ?>
                    <span class="pagination-button disabled">← 前へ</span>
                <?php endif; ?>
                
                <span class="pagination-info"><?= $page ?> / <?= $totalPages ?></span>
                
                <?php if ($nextUrl): ?>
                    <a href="<?= h($nextUrl) ?>" class="pagination-button">次へ →</a>
                <?php else: ?>
                    <span class="pagination-button disabled">次へ →</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <!-- 結果なし -->
            <div class="no-results">
                <div class="no-results-icon">🔍</div>
                <h2 class="no-results-title">素材が見つかりませんでした</h2>
                <p class="no-results-text">このタグに関連する素材はまだありません</p>
                <a href="/" class="no-results-button">ホームに戻る</a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
