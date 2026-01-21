<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// ページネーション設定
$perPage = 20; // 1ページあたりの表示件数
$page = max(1, intval($_GET['page'] ?? 1)); // 現在のページ（最小値は1）
$offset = ($page - 1) * $perPage;

// 承認済み作品のみ表示
$whereClause = "WHERE status = 'approved'";

// 総件数を取得
$countSql = "SELECT COUNT(*) FROM community_artworks " . $whereClause;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute();
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// データを取得
$sql = "SELECT * FROM community_artworks " . 
        $whereClause . " ORDER BY is_featured DESC, created_at DESC LIMIT ? OFFSET ?";
$params = [$perPage, $offset];
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$artworks = $stmt->fetchAll();

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
    <title>無料イラスト作品一覧｜組み合わせて作られた例｜marutto.art</title>
    <meta name="description" content="marutto.artで作られたイラスト作品一覧。すべて無料で利用可能。ブログや資料での使い方の参考にも。">
    <link rel="icon" href="/favicon.ico">
    
    <!-- hreflang tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/everyone-works.php" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/everyone-works.php" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/everyone-works.php" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/everyone-works.php" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/everyone-works.php" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/everyone-works.php" />
    
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
            width: 95px;
            height: 95px;
            animation-duration: 16s;
            animation-delay: 1s;
            --drift: -30px;
        }

        .floating-material:nth-child(5) {
            left: 85%;
            width: 110px;
            height: 110px;
            animation-duration: 22s;
            animation-delay: 3s;
            --drift: 25px;
        }

        .floating-material:nth-child(6) {
            left: 15%;
            width: 80px;
            height: 80px;
            animation-duration: 19s;
            animation-delay: 5s;
            --drift: -35px;
        }

        .floating-material:nth-child(7) {
            left: 60%;
            width: 90px;
            height: 90px;
            animation-duration: 17s;
            animation-delay: 2.5s;
            --drift: 20px;
        }

        .floating-material:nth-child(8) {
            left: 40%;
            width: 105px;
            height: 105px;
            animation-duration: 21s;
            animation-delay: 4.5s;
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
            margin-bottom: 40px;
            font-weight: 500;
        }

        /* マソンリーレイアウト */
        .masonry-grid {
            column-count: 4;
            column-gap: 40px;
            padding: 0;
        }

        @media (max-width: 992px) {
            .masonry-grid {
                column-count: 2;
                column-gap: 30px;
            }
        }

        @media (max-width: 576px) {
            .masonry-grid {
                column-count: 2;
                column-gap: 20px;
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

        .material-image {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 8px;
            transition: transform 0.2s ease;
        }

        .material-card:hover .material-image {
            transform: scale(1.02);
        }

        .artwork-title {
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

        .material-card:hover .artwork-title {
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
        }

        .pagination-info {
            color: #8B7355;
            font-weight: 500;
            padding: 0 20px;
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

        .ad-container {
            display: flex;
            justify-content: center;
            gap: 100px;
            flex-wrap: wrap;
            margin: 60px 0;
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
            ?>
        <div class="floating-material" style="background-color: <?= h($floatingBgColor) ?>;">
            <img src="/<?= h($material['image_path']) ?>" alt="素材" loading="lazy">
        </div>
        <?php endif; endforeach; ?>
    </div>
    
    <?php 
    $currentPage = 'everyone-works';
    include 'includes/header.php'; 
    ?>

    <div class="main-content">
        <div class="container">
            <h1 class="page-title">Everyone Works</h1>
            <p class="page-subtitle">無料・商用利用可能</p>
            
            <div class="masonry-grid">
                <?php foreach ($artworks as $artwork): ?>
                <div class="masonry-item">
                    <a href="/everyone-work.php?id=<?= $artwork['id'] ?>" 
                       class="material-card" 
                       role="button" 
                       tabindex="0" 
                       aria-label="<?= h($artwork['title']) ?>の詳細を見る">
                        <img src="/<?= h($artwork['webp_path'] ?: $artwork['file_path']) ?>" 
                             class="material-image" 
                             alt="<?= h($artwork['title']) ?>">
                        
                        <h3 class="artwork-title"><?= h($artwork['title']) ?></h3>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ページネーション -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="pagination-button">← 前へ</a>
                <?php else: ?>
                    <span class="pagination-button disabled">← 前へ</span>
                <?php endif; ?>
                
                <span class="pagination-info"><?= $page ?> / <?= $totalPages ?></span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="pagination-button">次へ →</a>
                <?php else: ?>
                    <span class="pagination-button disabled">次へ →</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- 広告ユニット -->
            <div class="ad-container">
                <?php include __DIR__ . '/includes/ad-display.php'; ?>
                <div class="ad-desktop-only">
                    <?php include __DIR__ . '/includes/ad-display.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
