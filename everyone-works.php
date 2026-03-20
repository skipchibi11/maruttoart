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

        /* グリッドレイアウト */
        .masonry-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            padding: 0;
        }

        @media (max-width: 992px) {
            .masonry-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 30px;
            }
        }

        @media (max-width: 576px) {
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
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
            aspect-ratio: 1 / 1;
            object-fit: contain;
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
            <h1 class="page-title">Everyone Works</h1>
            <p class="page-subtitle">無料・商用利用可能</p>
            
            <div class="masonry-grid">
                <?php foreach ($artworks as $index => $artwork): ?>
                <div class="masonry-item">
                    <a href="/everyone-work.php?id=<?= $artwork['id'] ?>" 
                       class="material-card" 
                       role="button" 
                       tabindex="0" 
                       aria-label="<?= h($artwork['title']) ?>の詳細を見る">
                        <?php 
                        $imgPath = $artwork['webp_path'] ?: $artwork['file_path'];
                        // フルURL（R2など）の場合はそのまま、相対パスの場合は先頭に / を追加
                        $imgUrl = (strpos($imgPath, 'http://') === 0 || strpos($imgPath, 'https://') === 0) ? $imgPath : '/' . $imgPath;
                        // 10件目まではeager、11件目以降はlazy
                        $loadingStrategy = ($index < 10) ? 'eager' : 'lazy';
                        ?>
                        <img src="<?= h($imgUrl) ?>" 
                             class="material-image" 
                             alt="<?= h($artwork['title']) ?>"
                             loading="<?= $loadingStrategy ?>">
                        
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

        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
