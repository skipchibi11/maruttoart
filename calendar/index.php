<?php
require_once __DIR__ . '/../config.php';

$pdo = getDB();

// 年月の取得（デフォルトは現在の年月）
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

// 月の範囲チェック
if ($month < 1) {
    $month = 12;
    $year--;
} elseif ($month > 12) {
    $month = 1;
    $year++;
}

// 公開素材がある前後の年月を取得
$prevYear = null;
$prevMonth = null;
$nextYear = null;
$nextMonth = null;

$prevMonthStmt = $pdo->prepare("
    SELECT DISTINCT year, month
    FROM calendar_items
    WHERE is_published = 1
    AND (year < ? OR (year = ? AND month < ?))
    ORDER BY year DESC, month DESC
    LIMIT 1
");
$prevMonthStmt->execute([$year, $year, $month]);
$prevMonthData = $prevMonthStmt->fetch(PDO::FETCH_ASSOC);
if ($prevMonthData) {
    $prevYear = (int)$prevMonthData['year'];
    $prevMonth = (int)$prevMonthData['month'];
}

$nextMonthStmt = $pdo->prepare("
    SELECT DISTINCT year, month
    FROM calendar_items
    WHERE is_published = 1
    AND (year > ? OR (year = ? AND month > ?))
    ORDER BY year ASC, month ASC
    LIMIT 1
");
$nextMonthStmt->execute([$year, $year, $month]);
$nextMonthData = $nextMonthStmt->fetch(PDO::FETCH_ASSOC);
if ($nextMonthData) {
    $nextYear = (int)$nextMonthData['year'];
    $nextMonth = (int)$nextMonthData['month'];
}

// カレンダーアイテム取得
$stmt = $pdo->prepare("
    SELECT * FROM calendar_items 
    WHERE year = ? AND month = ? AND is_published = 1
    ORDER BY day ASC
");
$stmt->execute([$year, $month]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 日付ごとにグループ化
$itemsByDay = [];
foreach ($items as $item) {
    $itemsByDay[$item['day']] = $item;
}

// カレンダーの日付情報を生成
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$firstDayOfWeek = date('w', $firstDay); // 0 (日曜) から 6 (土曜)

// メタ情報
$pageTitle = $year . '年' . $month . '月のカレンダー';
$pageDescription = 'marutto.artの' . $year . '年' . $month . '月のカレンダー。毎日の素材やイラストをチェックしよう！';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | marutto.art</title>
    <meta name="description" content="<?= h($pageDescription) ?>">
    <link rel="icon" href="/favicon.ico">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://marutto.art/calendar/?year=<?= h($year) ?>&month=<?= h($month) ?>">
    
    <!-- hreflang tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/calendar/?year=<?= h($year) ?>&month=<?= h($month) ?>" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/calendar/?year=<?= h($year) ?>&month=<?= h($month) ?>" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/calendar/?year=<?= h($year) ?>&month=<?= h($month) ?>" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/calendar/?year=<?= h($year) ?>&month=<?= h($month) ?>" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/calendar/?year=<?= h($year) ?>&month=<?= h($month) ?>" />
    <link rel="alternate" hreflang="zh-CN" href="https://marutto.art/zh-CN/calendar/?year=<?= h($year) ?>&month=<?= h($month) ?>" />
    <link rel="alternate" hreflang="ko" href="https://marutto.art/ko/calendar/?year=<?= h($year) ?>&month=<?= h($month) ?>" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/calendar/?year=<?= h($year) ?>&month=<?= h($month) ?>" />
    
    <?php include __DIR__ . '/../includes/gtm-head.php'; ?>
    <?php include __DIR__ . '/../includes/adsense-head.php'; ?>
    
    <style>
        :root {
            --primary-color: #E8A87C;
            --secondary-color: #C38E70;
            --bg-color: #E8DDD2;
            --text-dark: #5A4A42;
            --text-muted: #999;
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
                #FFFEF5 0%,
                #FFF9E8 25%,
                #FFF4DC 50%,
                #FFEFD0 75%,
                #FFE8C5 100%
            );
            min-height: 100vh;
            line-height: 1.6;
            position: relative;
            overflow-x: clip;
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
            margin-bottom: 40px;
            margin-top: 20px;
        }
        
        .calendar-title {
            font-size: 1.2rem;
            font-weight: normal;
            letter-spacing: 0.3em;
            margin-bottom: 30px;
            text-transform: uppercase;
        }
        
        .month-navigation {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 40px;
            margin-bottom: 30px;
        }

        .month-navigation.bottom {
            margin-top: 30px;
            margin-bottom: 0;
        }
        
        .month-navigation a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 999px;
            border: 1px solid rgba(195, 142, 112, 0.35);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 10px rgba(90, 74, 66, 0.12);
            color: var(--text-dark);
            text-decoration: none;
            font-size: 1.1rem;
            line-height: 1;
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
        }
        
        .month-navigation a:hover {
            transform: translateY(-1px);
            background: #fff;
            box-shadow: 0 6px 14px rgba(90, 74, 66, 0.16);
        }

        .month-nav-disabled {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 999px;
            border: 1px solid rgba(195, 142, 112, 0.2);
            background: rgba(255, 255, 255, 0.7);
            box-shadow: 0 2px 6px rgba(90, 74, 66, 0.05);
            font-size: 1.1rem;
            line-height: 1;
            color: var(--text-muted);
            opacity: 0.55;
            user-select: none;
            pointer-events: none;
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
            padding: 15px 5px;
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
            transition: background 0.3s;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            overflow: hidden;
        }
        
        /* aタグとして使用する場合のスタイル */
        a.calendar-day {
            display: flex;
        }
        
        /* 土曜日（7列目）の右ボーダーを消す - ヘッダー7個の後、7n番目 */
        .calendar-day:nth-child(7n+14) {
            border-right: none;
        }
        
        .calendar-day.empty {
            background: transparent;
        }
        
        .calendar-day.has-item {
            cursor: pointer;
            box-shadow: 0 1px 4px rgba(160, 103, 92, 0.1);
            transition: all 0.3s ease;
        }
        
        .calendar-day.has-item:hover {
            background: rgba(255, 255, 255, 0.5);
            box-shadow: 0 2px 8px rgba(160, 103, 92, 0.15);
            transform: translateY(-2px);
        }
        
        .day-number {
            font-size: 0.9rem;
            margin-bottom: 8px;
            color: var(--text-dark);
            text-align: left;
            flex-shrink: 0;
        }
        
        .day-number::after {
            content: '';
        }
        
        .calendar-day.sunday .day-number {
            color: #FF6B6B;
        }
        
        .calendar-day.saturday .day-number {
            color: #4ECDC4;
        }
        
        .day-thumbnail {
            width: 100%;
            max-width: 100%;
            max-height: 100%;
            height: auto;
            object-fit: contain;
            display: block;
            transition: all 0.3s ease;
        }
        
        .calendar-day.has-item .day-thumbnail {
            filter: drop-shadow(0 2px 6px rgba(160, 103, 92, 0.2));
        }
        
        .calendar-day.has-item:hover .day-thumbnail {
            filter: drop-shadow(0 4px 12px rgba(160, 103, 92, 0.3));
            transform: scale(1.05);
        }
        
        .day-content {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
            position: relative;
        }
        
        .day-title {
            display: none;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px 10px;
            }
            
            .calendar-title {
                font-size: 1rem;
                letter-spacing: 0.2em;
            }

            .month-navigation {
                gap: 20px;
            }

            .month-navigation a,
            .month-nav-disabled {
                width: 50px;
                height: 50px;
                font-size: 1.15rem;
            }
            
            .calendar-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
                background: transparent;
                border-radius: 0;
            }
            
            .calendar-day-header {
                display: none;
            }
            
            .calendar-day {
                aspect-ratio: auto;
                min-height: 120px;
                padding: 0;
                display: flex;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                border-right: none;
                border-bottom: none;
                background: rgba(255, 255, 255, 0.7);
                backdrop-filter: blur(10px);
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(90, 74, 66, 0.1);
            }
            
            .calendar-day.has-item {
                box-shadow: 0 2px 6px rgba(160, 103, 92, 0.15);
            }
            
            .calendar-day.has-item:hover {
                background: rgba(255, 255, 255, 0.85);
                box-shadow: 0 3px 10px rgba(160, 103, 92, 0.2);
            }
            
            .calendar-day.empty {
                display: none;
            }
            
            .day-number {
                font-size: 0.95rem;
                margin-bottom: 0;
                padding: 8px 10px;
                min-width: auto;
                flex-shrink: 0;
                text-align: center;
                font-weight: 600;
                background: rgba(255, 255, 255, 0.5);
            }
            
            .day-number::after {
                content: ' (' attr(data-weekday) ')';
                font-weight: normal;
            }
            
            .day-thumbnail {
                width: 100%;
                height: auto;
                max-height: 200px;
                flex-shrink: 0;
                object-fit: contain;
                background: white;
                display: block;
            }
            
            .calendar-day.has-item .day-thumbnail {
                filter: drop-shadow(0 3px 8px rgba(160, 103, 92, 0.25));
            }
            
            .day-content {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            
            .day-title {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/gtm-body.php'; ?>
    
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container">
        <div class="calendar-header">
            <h1 class="calendar-title">MARUTTO.ART CALENDAR</h1>
            
            <div class="month-navigation">
                <?php if ($prevYear !== null && $prevMonth !== null): ?>
                    <a href="?year=<?= h($prevYear) ?>&month=<?= h($prevMonth) ?>">◀</a>
                <?php else: ?>
                    <span class="month-nav-disabled" aria-hidden="true">◀</span>
                <?php endif; ?>
                <div class="current-month"><?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?></div>
                <?php if ($nextYear !== null && $nextMonth !== null): ?>
                    <a href="?year=<?= h($nextYear) ?>&month=<?= h($nextMonth) ?>">▶</a>
                <?php else: ?>
                    <span class="month-nav-disabled" aria-hidden="true">▶</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="calendar-grid">
            <!-- 曜日ヘッダー -->
            <div class="calendar-day-header sunday">Sun</div>
            <div class="calendar-day-header">Mon</div>
            <div class="calendar-day-header">Tue</div>
            <div class="calendar-day-header">Wed</div>
            <div class="calendar-day-header">Thu</div>
            <div class="calendar-day-header">Fri</div>
            <div class="calendar-day-header saturday">Sat</div>
            
            <!-- 月初前の空白（日曜始まり） -->
            <?php for ($i = 0; $i < $firstDayOfWeek; $i++): ?>
                <div class="calendar-day empty"></div>
            <?php endfor; ?>
            
            <!-- 日付 -->
            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php 
                $hasItem = isset($itemsByDay[$day]); 
                $item = $hasItem ? $itemsByDay[$day] : null;
                $currentDate = mktime(0, 0, 0, $month, $day, $year);
                $dayOfWeek = date('w', $currentDate); // 0=日曜, 6=土曜
                $dayClass = '';
                if ($dayOfWeek == 0) $dayClass = 'sunday';
                if ($dayOfWeek == 6) $dayClass = 'saturday';
                
                // 曜日名を取得
                $weekdayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                $weekdayName = $weekdayNames[$dayOfWeek];
                ?>
                
                <?php if ($hasItem): ?>
                    <a href="/calendar-detail/?year=<?= h($item['year']) ?>&month=<?= h($item['month']) ?>&day=<?= h($item['day']) ?>" class="calendar-day has-item <?= $dayClass ?>">
                        <div class="day-number" data-weekday="<?= $weekdayName ?>"><?= $day ?></div>
                        <div class="day-content"><?php 
                            // 一覧では静止画サムネイルのみ表示
                            $displayPath = null;
                            if (!empty($item['thumbnail_path'])) {
                                // 静止画サムネイル優先
                                $displayPath = $item['thumbnail_path'];
                            } elseif (!empty($item['image_path'])) {
                                // 静止画オリジナル
                                $displayPath = $item['image_path'];
                            }
                            
                            if ($displayPath): 
                                // R2 URL対応
                                $isRemoteCalUrl = (strpos($displayPath, 'http://') === 0 || strpos($displayPath, 'https://') === 0);
                                $finalCalUrl = $isRemoteCalUrl ? $displayPath : '/' . $displayPath;
                            ?>
                                <img src="<?= h($finalCalUrl) ?>" 
                                     alt="<?= h($item['title']) ?>" 
                                     class="day-thumbnail">
                            <?php endif; ?>
                            <div class="day-title"><?= h($item['title']) ?></div>
                        </div>
                    </a>
                <?php else: ?>
                    <div class="calendar-day <?= $dayClass ?>">
                        <div class="day-number" data-weekday="<?= $weekdayName ?>"><?= $day ?></div>
                        <div class="day-content">
                            <img src="https://assets.marutto.art/placeholders/calendar/penguin-placeholder.webp" 
                                 alt="Coming soon" 
                                 class="day-thumbnail"
                                 style="opacity: 0.3;">
                        </div>
                    </div>
                <?php endif; ?>
            <?php endfor; ?>
        </div>

        <div class="month-navigation bottom">
            <?php if ($prevYear !== null && $prevMonth !== null): ?>
                <a href="?year=<?= h($prevYear) ?>&month=<?= h($prevMonth) ?>">◀</a>
            <?php else: ?>
                <span class="month-nav-disabled" aria-hidden="true">◀</span>
            <?php endif; ?>
            <div class="current-month"><?= date('F Y', mktime(0, 0, 0, $month, 1, $year)) ?></div>
            <?php if ($nextYear !== null && $nextMonth !== null): ?>
                <a href="?year=<?= h($nextYear) ?>&month=<?= h($nextMonth) ?>">▶</a>
            <?php else: ?>
                <span class="month-nav-disabled" aria-hidden="true">▶</span>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
