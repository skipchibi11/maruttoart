<?php
require_once __DIR__ . '/config.php';

$pdo = getDB();

// 月の取得（デフォルトは現在月）
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($month < 1) {
    $month = 12;
} elseif ($month > 12) {
    $month = 1;
}

$year = (int)date('Y');

// 前月・次月
$prevMonth = $month - 1;
if ($prevMonth < 1) {
    $prevMonth = 12;
}
$nextMonth = $month + 1;
if ($nextMonth > 12) {
    $nextMonth = 1;
}

// 浮遊素材用にランダムに8件取得
$floatingMaterialsSql = "SELECT m.webp_small_path as image_path, m.structured_bg_color FROM materials m ORDER BY RAND() LIMIT 8";
$floatingMaterialsStmt = $pdo->prepare($floatingMaterialsSql);
$floatingMaterialsStmt->execute();
$floatingMaterials = $floatingMaterialsStmt->fetchAll();

// 日付ごとの件数
$countStmt = $pdo->prepare("SELECT day, COUNT(*) as total FROM everyone_calendar_items WHERE month = ? GROUP BY day");
$countStmt->execute([$month]);
$counts = [];
foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $counts[(int)$row['day']] = (int)$row['total'];
}

// 日付ごとの最新作品（各日1件のみ）
$latestStmt = $pdo->prepare("
    SELECT e.day, e.artwork_id, e.id, ca.file_path, ca.webp_path, c.name_ja AS country_name
    FROM everyone_calendar_items e
    JOIN (
        SELECT day, MAX(id) AS latest_id
        FROM everyone_calendar_items
        WHERE month = ?
        GROUP BY day
    ) latest ON latest.day = e.day AND latest.latest_id = e.id
    JOIN community_artworks ca ON ca.id = e.artwork_id AND ca.status = 'approved'
    LEFT JOIN countries c ON c.id = e.country_id
    WHERE e.month = ?
");
$latestStmt->execute([$month, $month]);
$latestByDay = [];
foreach ($latestStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $latestByDay[(int)$row['day']] = $row;
}

// カレンダーの日付情報を生成
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDay);
$firstDayOfWeek = (int)date('w', $firstDay); // 0 (日曜) から 6 (土曜)

$pageTitle = $month . '月のみんなのカレンダー';
$pageDescription = 'みんなの作品が日付ごとに集まるカレンダーです。';
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
            position: relative;
            z-index: 1;
        }

        .calendar-header {
            text-align: center;
            margin-bottom: 36px;
            margin-top: 20px;
        }

        .calendar-title {
            font-size: 1.2rem;
            font-weight: 500;
            letter-spacing: 0.2em;
            margin-bottom: 18px;
        }

        .month-navigation {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 40px;
            margin-bottom: 30px;
        }

        .month-navigation a {
            color: var(--text-dark);
            text-decoration: none;
            font-size: 0.9rem;
            transition: opacity 0.2s;
        }

        .month-navigation a:hover {
            opacity: 0.7;
        }

        .current-month {
            font-size: 1.1rem;
            color: var(--text-dark);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            overflow: hidden;
        }

        .calendar-day-header {
            text-align: center;
            font-weight: normal;
            padding: 12px 5px;
            color: var(--text-dark);
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(90, 74, 66, 0.2);
            background: rgba(255, 255, 255, 0.5);
        }

        .calendar-day-header.sunday {
            color: #FF6B6B;
        }

        .calendar-day-header.saturday {
            color: #4ECDC4;
        }

        .calendar-day {
            aspect-ratio: 1;
            padding: 10px;
            border-right: 1px solid rgba(90, 74, 66, 0.1);
            border-bottom: 1px solid rgba(90, 74, 66, 0.1);
            position: relative;
            background: transparent;
            transition: background 0.2s;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            overflow: hidden;
        }

        .calendar-day:nth-child(7n+14) {
            border-right: none;
        }

        .calendar-day.empty {
            background: transparent;
        }

        .calendar-day.has-item:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        .day-number {
            font-size: 0.9rem;
            margin-bottom: 8px;
            color: var(--text-dark);
            text-align: left;
            flex-shrink: 0;
        }


        .day-thumb-wrapper {
            flex: 1;
            min-height: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .calendar-day.sunday .day-number {
            color: #FF6B6B;
        }

        .calendar-day.saturday .day-number {
            color: #4ECDC4;
        }

        .day-thumbnail {
            width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        .day-count {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(90, 74, 66, 0.85);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
        }

        .day-country {
            margin-top: 6px;
            font-size: 0.7rem;
            color: rgba(90, 74, 66, 0.75);
            text-align: left;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

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
                opacity: 0.7;
            }
            90% {
                opacity: 0.7;
            }
            100% {
                transform: translateY(-100px) translateX(var(--drift)) scale(1) rotate(360deg);
                opacity: 0;
            }
        }

        .floating-material:nth-child(1) { left: 10%; width: 100px; height: 100px; animation-duration: 15s; animation-delay: 0s; --drift: 30px; }
        .floating-material:nth-child(2) { left: 25%; width: 85px; height: 85px; animation-duration: 18s; animation-delay: 2s; --drift: -20px; }
        .floating-material:nth-child(3) { left: 50%; width: 120px; height: 120px; animation-duration: 20s; animation-delay: 4s; --drift: 40px; }
        .floating-material:nth-child(4) { left: 70%; width: 95px; height: 95px; animation-duration: 16s; animation-delay: 1s; --drift: -30px; }
        .floating-material:nth-child(5) { left: 85%; width: 110px; height: 110px; animation-duration: 22s; animation-delay: 3s; --drift: 25px; }
        .floating-material:nth-child(6) { left: 15%; width: 80px; height: 80px; animation-duration: 19s; animation-delay: 5s; --drift: -35px; }
        .floating-material:nth-child(7) { left: 60%; width: 90px; height: 90px; animation-duration: 17s; animation-delay: 2.5s; --drift: 20px; }
        .floating-material:nth-child(8) { left: 40%; width: 105px; height: 105px; animation-duration: 21s; animation-delay: 4.5s; --drift: -25px; }

        @media (max-width: 768px) {
            .container {
                padding: 20px 10px;
            }

            .calendar-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .calendar-day-header {
                display: none;
            }

            .calendar-day {
                aspect-ratio: auto;
                min-height: 100px;
                padding: 15px;
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                border-right: none;
            }

            .calendar-day.empty {
                display: none;
            }

            .day-number {
                font-size: 1.2rem;
                margin-bottom: 0;
                min-width: 40px;
                flex-shrink: 0;
            }

            .day-number::before {
                content: attr(data-weekday) ' ';
                font-size: 0.85rem;
                margin-right: 5px;
            }

            .day-thumbnail {
                width: 100%;
                height: auto;
                max-height: 300px;
                flex-shrink: 0;
            }

            .day-thumb-wrapper {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/gtm-body.php'; ?>

    <div class="floating-container">
        <?php foreach ($floatingMaterials as $material):
            if (!empty($material['image_path'])):
                $floatingBgColor = !empty($material['structured_bg_color']) ? $material['structured_bg_color'] : '#ffffff';
        ?>
        <div class="floating-material" style="background-color: <?= h($floatingBgColor) ?>;">
            <img src="/<?= h($material['image_path']) ?>" alt="素材" loading="lazy">
        </div>
        <?php endif; endforeach; ?>
    </div>

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="container">
        <div class="calendar-header">
            <div class="calendar-title">Everyone Calendar</div>
            <div class="month-navigation">
                <a href="/everyone-calendars.php?month=<?= h($prevMonth) ?>">◀</a>
                <div class="current-month"><?= h(date('F', mktime(0, 0, 0, $month, 1, $year))) ?></div>
                <a href="/everyone-calendars.php?month=<?= h($nextMonth) ?>">▶</a>
            </div>
        </div>

        <div class="calendar-grid">
            <?php
            $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            foreach ($weekdays as $index => $dayName):
                $class = '';
                if ($index === 0) $class = 'sunday';
                if ($index === 6) $class = 'saturday';
            ?>
                <div class="calendar-day-header <?= $class ?>"><?= h($dayName) ?></div>
            <?php endforeach; ?>

            <?php for ($i = 0; $i < $firstDayOfWeek; $i++): ?>
                <div class="calendar-day empty"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                $count = $counts[$day] ?? 0;
                $latest = $latestByDay[$day] ?? null;
                $weekdayIndex = (($firstDayOfWeek + $day - 1) % 7);
                $weekdayNameEn = $weekdays[$weekdayIndex];
                $isSunday = ($weekdayIndex === 0);
                $isSaturday = ($weekdayIndex === 6);
                $dayClass = $isSunday ? 'sunday' : ($isSaturday ? 'saturday' : '');
                $link = $count > 0 ? "/everyone-calendar.php?month={$month}&day={$day}" : null;
                $imagePath = null;
                if ($latest) {
                    $imagePath = $latest['webp_path'] ?: $latest['file_path'];
                }
            ?>
                <?php if ($link): ?>
                    <a class="calendar-day has-item <?= h($dayClass) ?>" href="<?= h($link) ?>">
                <?php else: ?>
                    <div class="calendar-day <?= h($dayClass) ?>">
                <?php endif; ?>
                        <div class="day-number" data-weekday="<?= h($weekdayNameEn) ?>"><?= h($day) ?></div>
                        <?php if ($imagePath): ?>
                            <div class="day-thumb-wrapper">
                                <img src="/<?= h($imagePath) ?>" alt="<?= h($day) ?>日" class="day-thumbnail" loading="lazy">
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($latest['country_name'])): ?>
                            <div class="day-country"><?= h($latest['country_name']) ?></div>
                        <?php endif; ?>
                        <?php if ($count > 1): ?>
                            <span class="day-count">+<?= h($count - 1) ?></span>
                        <?php endif; ?>
                <?php if ($link): ?>
                    </a>
                <?php else: ?>
                    </div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
