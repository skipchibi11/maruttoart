<?php
require_once __DIR__ . '/config.php';

$pdo = getDB();

$currentYear = (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$day = isset($_GET['day']) ? (int)$_GET['day'] : 0;

if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || !checkdate($month, $day, 2000)) {
    header('Location: /everyone-calendars.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT e.id, e.date_reason, c.name_ja AS country_name,
           ca.id AS artwork_id, ca.title, ca.file_path, ca.webp_path
    FROM everyone_calendar_items e
    JOIN community_artworks ca ON ca.id = e.artwork_id AND ca.status = 'approved'
    LEFT JOIN countries c ON c.id = e.country_id
    WHERE e.month = ? AND e.day = ?
    ORDER BY e.id DESC
");
$stmt->execute([$month, $day]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = $month . '月' . $day . '日｜みんなのカレンダー';
$pageDescription = 'みんなの作品が集まる日付ページです。';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | marutto.art</title>
    <meta name="description" content="<?= h($pageDescription) ?>">
    <link rel="icon" href="/favicon.ico">

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
            background: linear-gradient(180deg, #FFF0E5 0%, #FFF5F8 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 32px;
            margin-top: 20px;
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

        .page-subtitle {
            margin-top: 10px;
            font-size: 0.85rem;
            color: rgba(90, 74, 66, 0.7);
        }

        .works-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }

        .work-card {
            background: rgba(255, 255, 255, 0.7);
            border-radius: 12px;
            padding: 12px;
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }

        .work-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
        }

        .work-thumb {
            width: 100%;
            height: 160px;
            object-fit: contain;
            border-radius: 8px;
            background: white;
            display: block;
        }

        .work-meta {
            margin-top: 8px;
            font-size: 0.8rem;
            color: rgba(90, 74, 66, 0.7);
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .work-reason {
            margin-top: 8px;
            font-size: 0.75rem;
            color: rgba(90, 74, 66, 0.65);
            line-height: 1.4;
            white-space: pre-wrap;
        }

        .empty-state {
            text-align: center;
            color: rgba(90, 74, 66, 0.6);
            padding: 60px 0;
        }

        .back-link {
            display: inline-block;
            margin: 40px auto 0 auto;
            padding: 8px 20px;
            background: transparent;
            color: rgba(90, 74, 66, 0.7);
            text-decoration: none;
            border-radius: 20px;
            border: 1px solid rgba(90, 74, 66, 0.2);
            transition: all 0.2s;
            font-size: 0.85rem;
            display: block;
            width: fit-content;
        }

        .back-link:hover {
            color: var(--text-dark);
            border-color: var(--text-dark);
        }

        @media (max-width: 768px) {
            .container {
                padding: 24px 14px;
            }

            .works-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 12px;
            }

            .work-thumb {
                height: 140px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/gtm-body.php'; ?>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="container">
        <div class="page-header">
            <div class="date-badge"><?= h($currentYear) ?>.<?= sprintf('%02d', $month) ?>.<?= sprintf('%02d', $day) ?></div>
            <div class="page-subtitle">Everyone Calendar</div>
        </div>

        <?php if (empty($items)): ?>
            <div class="empty-state">この日付の作品はまだありません。</div>
        <?php else: ?>
            <div class="works-grid">
                <?php foreach ($items as $item):
                    $imagePath = $item['webp_path'] ?: $item['file_path'];
                ?>
                    <a class="work-card" href="/everyone-work.php?id=<?= h($item['artwork_id']) ?>">
                        <img src="/<?= h($imagePath) ?>" alt="作品" class="work-thumb" loading="lazy">
                        <div class="work-meta">
                            <span><?= h($item['country_name'] ?: '地域未設定') ?></span>
                            <span>→</span>
                        </div>
                        <?php if (!empty($item['date_reason'])): ?>
                            <div class="work-reason"><?= h($item['date_reason']) ?></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a class="back-link" href="/everyone-calendars.php?month=<?= h($month) ?>">← 月のカレンダーへ</a>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
