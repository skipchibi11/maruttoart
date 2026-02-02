<?php
require_once __DIR__ . '/../config.php';

$pdo = getDB();

// year, month, dayからカレンダーアイテムを取得
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$day = isset($_GET['day']) ? (int)$_GET['day'] : 0;

if (empty($year) || empty($month) || empty($day)) {
    header('Location: /calendar/');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM calendar_items WHERE year = ? AND month = ? AND day = ? AND is_published = 1");
$stmt->execute([$year, $month, $day]);
$item = $stmt->fetch();

if (!$item) {
    header('HTTP/1.0 404 Not Found');
    include __DIR__ . '/../404.php';
    exit;
}

// メタ情報
$pageTitle = $item['title'] . ' | marutto.art';
$pageDescription = $item['description'] ?: $item['title'];
$ogImage = $item['image_path'] ? 'https://marutto.art/' . $item['image_path'] : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <meta name="description" content="<?= h($pageDescription) ?>">
    <link rel="icon" href="/favicon.ico">
    
    <!-- OGP -->
    <meta property="og:title" content="<?= h($item['title']) ?>">
    <meta property="og:description" content="<?= h($pageDescription) ?>">
    <?php if ($ogImage): ?>
        <meta property="og:image" content="<?= h($ogImage) ?>">
    <?php endif; ?>
    <meta property="og:type" content="article">
    <meta property="og:url" content="https://marutto.art/calendar/<?= h($item['slug']) ?>">
    
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
            line-height: 1.8;
        }
        
        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .article-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .date-badge {
            display: inline-block;
            color: rgba(90, 74, 66, 0.6);
            padding: 0;
            font-size: 0.85rem;
            margin-bottom: 12px;
            font-weight: normal;
            letter-spacing: 0.05em;
        }
        
        .article-title {
            font-size: 1.3rem;
            font-weight: 400;
            margin-bottom: 0;
            color: var(--text-dark);
            letter-spacing: 0.05em;
        }
        
        .article-content {
            background: transparent;
            padding: 0;
        }
        
        .main-image {
            width: 100%;
            max-width: 500px;
            height: auto;
            border-radius: 8px;
            margin: 0 auto 30px auto;
            display: block;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .description {
            font-size: 0.95rem;
            line-height: 1.9;
            margin-top: 40px;
            margin-bottom: 30px;
            white-space: pre-wrap;
            color: rgba(90, 74, 66, 0.8);
            text-align: center;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            letter-spacing: 0;
        }
        
        .gif-section {
            margin-top: 50px;
            padding-top: 50px;
            border-top: 1px solid rgba(90, 74, 66, 0.1);
        }
        
        .section-title {
            font-size: 0.85rem;
            margin-bottom: 20px;
            color: rgba(90, 74, 66, 0.6);
            font-weight: normal;
            text-align: center;
            letter-spacing: 0.1em;
        }
        
        .download-button {
            display: inline-block;
            padding: 10px 24px;
            background: transparent;
            color: var(--text-dark);
            text-decoration: none;
            border: 1px solid rgba(90, 74, 66, 0.2);
            border-radius: 20px;
            transition: all 0.3s;
            margin: 20px auto 0 auto;
            font-weight: normal;
            font-size: 0.85rem;
            display: block;
            width: fit-content;
        }
        
        .download-button:hover {
            background: rgba(90, 74, 66, 0.05);
            border-color: var(--text-dark);
        }
        
        .back-link {
            display: inline-block;
            margin: 50px auto 0 auto;
            padding: 8px 20px;
            background: transparent;
            color: rgba(90, 74, 66, 0.6);
            text-decoration: none;
            border-radius: 20px;
            transition: all 0.3s;
            font-size: 0.85rem;
            display: block;
            width: fit-content;
        }
        
        .back-link:hover {
            color: var(--text-dark);
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
        
        @media (max-width: 768px) {
            .container {
                padding: 30px 15px;
            }
            
            .article-title {
                font-size: 1.1rem;
            }
            
            .description {
                font-size: 0.9rem;
            }
            
            .main-image {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/gtm-body.php'; ?>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container">
        <div class="article-header">
            <div class="date-badge">
                <?= h($item['year']) ?>.<?= sprintf('%02d', $item['month']) ?>.<?= sprintf('%02d', $item['day']) ?>
            </div>
            <h1 class="article-title"><?= h($item['title']) ?></h1>
        </div>
        
        <div class="article-content">
            <!-- 画像 -->
            <?php if ($item['image_path']): ?>
                <img src="/<?= h($item['image_path']) ?>" 
                     alt="<?= h($item['title']) ?>" 
                     class="main-image">
                <a href="/<?= h($item['image_path']) ?>" 
                   download 
                   class="download-button">
                    Download Image
                </a>
            <?php endif; ?>
            
            <!-- 説明 -->
            <?php if ($item['description']): ?>
                <div class="description"><?= nl2br(h(trim($item['description']))) ?></div>
            <?php endif; ?>
            
            <!-- GIF -->
            <?php if ($item['gif_path']): ?>
                <img src="/<?= h($item['gif_path']) ?>" 
                     alt="<?= h($item['title']) ?> アニメーション" 
                     class="main-image">
                <a href="/<?= h($item['gif_path']) ?>" 
                   download 
                   class="download-button">
                    Download GIF
                </a>
            <?php endif; ?>
        </div>
        
        <a href="/calendar/?year=<?= h($item['year']) ?>&month=<?= h($item['month']) ?>" class="back-link">
            ← Back to Calendar
        </a>
    </div>
    
    <!-- 広告ユニット -->
    <div class="container">
        <div class="ad-container">
            <?php include __DIR__ . '/../includes/ad-display.php'; ?>
            <div class="ad-desktop-only">
                <?php include __DIR__ . '/../includes/ad-display.php'; ?>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
