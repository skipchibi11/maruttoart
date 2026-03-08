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

// 背景浮遊用の素材を取得（8件）
$floatingMaterialsSql = "SELECT m.webp_small_path as image_path, m.structured_bg_color FROM materials m ORDER BY RAND() LIMIT 8";
$floatingMaterialsStmt = $pdo->prepare($floatingMaterialsSql);
$floatingMaterialsStmt->execute();
$floatingMaterials = $floatingMaterialsStmt->fetchAll();

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
            background: linear-gradient(180deg, #FFF0E5 0%, #FFF5F8 100%);
            min-height: 100vh;
            line-height: 1.6;
            position: relative;
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
            
            .day-content {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .day-title {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/gtm-body.php'; ?>
    
    <!-- 浮遊素材背景 -->
    <div class="floating-container">
        <?php foreach ($floatingMaterials as $index => $material): 
            $floatingBgColor = !empty($material['structured_bg_color']) ? $material['structured_bg_color'] : '#ffffff';
        ?>
        <div class="floating-material" style="background-color: <?= h($floatingBgColor) ?>;">
            <img src="/<?= h($material['image_path']) ?>" alt="" loading="lazy">
        </div>
        <?php endforeach; ?>
    </div>
    
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
                            ?>
                                <img src="/<?= h($displayPath) ?>" 
                                     alt="<?= h($item['title']) ?>" 
                                     class="day-thumbnail">
                            <?php endif; ?>
                            <div class="day-title"><?= h($item['title']) ?></div>
                        </div>
                    </a>
                <?php else: ?>
                    <div class="calendar-day <?= $dayClass ?>">
                        <div class="day-number" data-weekday="<?= $weekdayName ?>"><?= $day ?></div>
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
