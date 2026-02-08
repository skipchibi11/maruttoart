<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// みんなの作品を6件取得
$artworksSql = "SELECT * FROM community_artworks WHERE status = 'approved' ORDER BY created_at DESC LIMIT 6";
$artworksStmt = $pdo->prepare($artworksSql);
$artworksStmt->execute();
$artworks = $artworksStmt->fetchAll();

// ランダムにベクター素材を20件取得
$materialsSql = "SELECT m.*, c.slug as category_slug, m.webp_small_path, m.structured_bg_color FROM materials m 
        LEFT JOIN categories c ON m.category_id = c.id 
        WHERE m.svg_path IS NOT NULL AND m.svg_path != ''
        ORDER BY RAND() LIMIT 20";
$materialsStmt = $pdo->prepare($materialsSql);
$materialsStmt->execute();
$randomMaterials = $materialsStmt->fetchAll();

// スクロールアニメーション用の作品を取得（15件）
$scrollArtworksSql = "SELECT 'artwork' as type, id, '' as slug, '' as category_slug, webp_path as image_path, title
    FROM community_artworks
    WHERE status = 'approved'
    ORDER BY RAND()
    LIMIT 15";
$scrollArtworksStmt = $pdo->prepare($scrollArtworksSql);
$scrollArtworksStmt->execute();
$scrollItems = $scrollArtworksStmt->fetchAll();

// 背景浮遊用の素材を取得（8件）
$floatingMaterialsSql = "SELECT m.webp_small_path as image_path, m.structured_bg_color FROM materials m ORDER BY RAND() LIMIT 8";
$floatingMaterialsStmt = $pdo->prepare($floatingMaterialsSql);
$floatingMaterialsStmt->execute();
$floatingMaterials = $floatingMaterialsStmt->fetchAll();

// カレンダー表示用データ取得（当月全日）
$today = new DateTime();
$currentYear = (int)$today->format('Y');
$currentMonth = (int)$today->format('n');
$currentDay = (int)$today->format('j');
$daysInMonth = (int)$today->format('t');

// 月初の曜日（0:日曜 〜 6:土曜）
$firstDayOfMonth = new DateTime("$currentYear-$currentMonth-1");
$firstDayOfWeek = (int)$firstDayOfMonth->format('w');

$calendarDays = [];
for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = new DateTime("$currentYear-$currentMonth-$day");
    $dayOfWeek = (int)$date->format('w');
    
    $calendarDays[] = [
        'year' => $currentYear,
        'month' => $currentMonth,
        'day' => $day,
        'is_today' => ($day === $currentDay),
        'day_of_week' => $dayOfWeek
    ];
}

// カレンダーアイテムを一括取得（当月分）
$calendarItemsMap = [];
$calendarSql = "SELECT * FROM calendar_items 
                WHERE is_published = 1 
                AND year = ? 
                AND month = ?";
$calendarStmt = $pdo->prepare($calendarSql);
$calendarStmt->execute([$currentYear, $currentMonth]);
$calendarItems = $calendarStmt->fetchAll();

foreach ($calendarItems as $item) {
    $key = $item['year'] . '-' . $item['month'] . '-' . $item['day'];
    $calendarItemsMap[$key] = $item;
}

// 各日付にアイテムを紐付け
foreach ($calendarDays as &$dayInfo) {
    $key = $dayInfo['year'] . '-' . $dayInfo['month'] . '-' . $dayInfo['day'];
    $dayInfo['item'] = $calendarItemsMap[$key] ?? null;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>無料で使えるイラスト素材と作成ツール｜marutto.art</title>
    <meta name="description" content="marutto.artは、イラストを無料で作って使える素材サイトです。組み合わせるだけで、やさしいイラストが完成します。">
    
    <link rel="icon" href="/favicon.ico">
    
    <!-- hreflang tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/" />
    
    <?php include __DIR__ . '/includes/gtm-head.php'; ?>
    
    <style>
        :root {
            --primary-color: #E8A87C;
            --secondary-color: #C38E70;
            --bg-cream: #FFF8F0;
            --bg-pink: #FFF0F5;
            --text-dark: #5A4A42;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
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

        /* ヒーローセクション */
        .hero {
            padding: 60px 20px 80px;
            overflow: hidden;
            position: relative;
        }

        .hero-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero-text {
            flex: 0 1 auto;
            text-align: left;
            min-width: 280px;
        }

        .hero h1 {
            font-size: clamp(1.5rem, 4vw, 2.8rem);
            font-weight: 600;
            margin-bottom: 12px;
            line-height: 1.4;
            color: #A0675C;
        }

        .hero-subtitle {
            font-size: clamp(0.9rem, 2vw, 1.3rem);
            color: #8B7355;
            margin-bottom: 30px;
            font-weight: 500;
        }

        .hero-cta-pc {
            margin-top: 50px;
            position: relative;
            width: 100%;
        }

        .hero-cta-mobile {
            display: none;
            position: relative;
            width: 100%;
            margin-top: 50px;
        }

        .cta-button {
            display: inline-block;
            padding: 20px 60px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 600;
            box-shadow: var(--shadow);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            z-index: 2;
            overflow: visible;
            width: 100%;
            text-align: center;
        }

        .peek-animal {
            position: absolute;
            width: 80px;
            height: 80px;
            pointer-events: none;
            z-index: 1;
            opacity: 0;
        }

        .peek-right {
            left: 50%;
            bottom: 80%;
            transform: translateX(-50%);
        }

        .peek-animal img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        @keyframes peekFromBottom {
            0% {
                transform: translateY(100%);
                opacity: 0;
            }
            15% {
                transform: translateY(15px);
                opacity: 1;
            }
            85% {
                transform: translateY(15px);
                opacity: 1;
            }
            100% {
                transform: translateY(100%);
                opacity: 0;
            }
        }

        .peek-animal.active {
            animation: peekFromBottom 3s ease-in-out forwards;
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .hero-image-container {
            flex: 0 1 auto;
            max-width: 600px;
            width: 100%;
        }

        .hero-image {
            width: 100%;
            height: auto;
            border-radius: 20px;
            display: block;
        }

        /* セクション共通 */
        .section {
            padding: 60px 20px;
            position: relative;
        }

        .section .container {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
        }

        .section-title {
            text-align: center;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 600;
            margin-bottom: 50px;
            color: #A0675C;
        }

        /* みんなの作品セクション */
        .artworks-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 40px;
            width: 90%;
            margin-left: auto;
            margin-right: auto;
            /* CLS対策: 最小高さを確保 */
            min-height: 240px;
        }

        .artwork-card {
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            border-radius: 12px;
            overflow: visible;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            transition: transform 0.3s, box-shadow 0.3s;
            padding: 16px;
        }

        .artwork-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .artwork-image-wrapper {
            width: 100%;
            aspect-ratio: 1 / 1;
            max-height: 200px;
            min-height: 180px;
            border-radius: 8px;
            overflow: hidden;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            /* CLS対策: 初期状態でも領域を確保 */
            position: relative;
        }

        .artwork-image {
            max-width: 100%;
            max-height: 100%;
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            border-radius: 8px;
            /* CLS対策: 画像読み込み前も領域を確保 */
        }

        .artwork-info {
            padding: 16px 0 0 0;
            text-align: left;
            background: white;
        }

        .artwork-title {
            font-size: 0.9rem;
            font-weight: 500;
            color: #666;
            line-height: 1.4;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* 好きな素材をみつけようセクション */
        .materials-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 16px;
            margin-bottom: 30px;
            width: 100%;
            box-sizing: border-box;
        }

        .material-item {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
            box-sizing: border-box;
        }

        .material-item:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .material-item img {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
        }

        .more-button {
            display: block;
            width: fit-content;
            margin: 0 auto;
            padding: 14px 48px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.05rem;
            box-shadow: var(--shado6);
            transition: all 0.3s;
        }

        .more-button:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
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

        /* カレンダーセクション */
        .calendar-section {
            max-width: 1200px;
            margin: 80px auto 60px auto;
            padding: 60px 40px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 16px;
        }

        .calendar-section-title {
            text-align: center;
            font-size: 1.5rem;
            margin-bottom: 40px;
            color: var(--text-dark);
            letter-spacing: 0.1em;
        }

        .calendar-grid-container {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 40px;
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

        /* 土曜日（7列目）の右ボーダーを消す */
        .calendar-day:nth-child(7n+14) {
            border-right: none;
        }

        .calendar-day.empty {
            background: transparent;
        }

        .calendar-day.has-item:hover {
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
        }

        .calendar-day.today {
            background: rgba(255, 229, 217, 0.5);
        }

        .calendar-day.today:hover {
            background: rgba(255, 229, 217, 0.7);
        }

        .calendar-day-date {
            font-size: 0.85rem;
            margin-bottom: 5px;
            color: var(--text-dark);
            text-align: left;
            flex-shrink: 0;
            white-space: nowrap;
        }

        .calendar-day-weekday {
            display: none; /* PC版では非表示 */
        }

        .calendar-day.sunday .calendar-day-date {
            color: #FF6B6B;
        }

        .calendar-day.saturday .calendar-day-date {
            color: #4ECDC4;
        }

        .calendar-day.today .calendar-day-date {
            font-weight: bold;
            color: var(--primary-color);
        }

        .calendar-day-image {
            width: 100%;
            height: auto;
            max-height: 80%;
            object-fit: contain;
            display: block;
            flex: 1;
            min-height: 0;
        }

        .calendar-day-title {
            display: none;
        }

        .calendar-day-empty {
            font-size: 0.7rem;
            color: rgba(90, 74, 66, 0.3);
            text-align: center;
            margin-top: auto;
        }

        .calendar-button {
            display: block;
            width: fit-content;
            margin: 0 auto;
            padding: 15px 40px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .calendar-button:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .calendar-section {
                padding: 40px 0;
            }

            .calendar-day-header {
                display: none;
            }
            
            .calendar-grid-container {
                display: flex;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                gap: 10px;
                padding: 0 20px;
                margin-bottom: 30px;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            
            .calendar-grid-container::-webkit-scrollbar {
                display: none;
            }
            
            .calendar-day {
                flex: 0 0 calc(33.333% - 10px);
                scroll-snap-align: center;
                min-width: calc(33.333% - 10px);
                border: 1px solid rgba(90, 74, 66, 0.1);
                border-radius: 8px;
            }

            .calendar-day.empty {
                display: flex;
            }
            
            .calendar-day-date {
                font-size: 0.8rem;
            }
            
            .calendar-day-weekday {
                display: inline; /* スマホ版では表示 */
                font-size: 0.65rem;
                margin-left: 3px;
                opacity: 0.7;
            }
            
            .calendar-day-title {
                font-size: 0.7rem;
            }
        }

        /* スクロールアニメーション */
        .scroll-divider {
            overflow: hidden;
            position: relative;
            height: 140px;
            margin: 40px 0;
            display: flex;
            align-items: center;
        }

        .scroll-track {
            display: flex;
            gap: 10px;
            animation: scrollLeft 60s linear infinite;
            will-change: transform;
        }

        .scroll-item {
            width: 120px;
            height: 120px;
            flex-shrink: 0;
            padding: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background: white;
            position: relative;
            border-radius: 12px;
            aspect-ratio: 1;
        }

        .scroll-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        @keyframes scrollLeft {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
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

        /* レスポンシブ */
        @media (max-width: 768px) {
            .hero {
                padding: 40px 16px 30px;
            }

            .hero-content {
                flex-direction: column;
                gap: 0;
                text-align: center;
            }

            .hero-text {
                text-align: center;
                min-width: auto;
                width: 100%;
                order: 1;
            }

            .hero-cta-pc {
                display: none;
            }

            .hero h1 {
                font-size: 1.6rem;
            }

            .hero-subtitle {
                font-size: 0.95rem;
                margin-bottom: 0;
            }

            .hero-image-container {
                max-width: 100%;
                width: 100%;
                order: 2;
                margin-top: 40px;
            }

            .hero-image {
                max-width: 350px;
                width: 100%;
                margin: 0 auto;
            }

            .hero-cta-mobile {
                display: flex;
                justify-content: center;
                order: 3;
                margin-top: 50px;
                width: 100%;
            }

            .cta-button {
                padding: 12px 36px;
                font-size: 0.95rem;
            }

            .peek-right {
                left: 50%;
                bottom: 100%;
                transform: translateX(-50%);
            }

            .peek-animal {
                width: 60px;
                height: 60px;
            }

            .section {
                padding: 60px 0;
            }

            .section .container {
                padding: 20px 8px;
                border-radius: 16px;
                width: 100%;
            }

            .artworks-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .materials-grid {
                grid-template-columns: repeat(5, 1fr);
                gap: 6px;
                width: 100%;
                max-width: 100%;
            }

            .material-item {
                padding: 6px;
                aspect-ratio: 1;
                width: 100%;
                max-width: 100%;
                min-width: 0;
            }
        }

        @media (max-width: 480px) {
            .hero h1 {
                font-size: 1.4rem;
            }

            .material-item {
                padding: 8px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .hero-content {
                gap: 30px;
            }

            .artworks-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
    $currentPage = 'home';
    include 'includes/header.php'; 
    ?>

    <!-- ヒーローセクション -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>3つ選ぶだけで、<br>物語が生まれる。</h1>
                    <p class="hero-subtitle">今日はどんな一枚をつくる？</p>
                    <div class="hero-cta-pc">
                        <a href="/compose/" class="cta-button">今すぐつくる</a>
                        <div class="peek-animal peek-right" id="peek-animal-pc">
                            <img src="" alt="">
                        </div>
                    </div>
                </div>
                
                <div class="hero-image-container">
                    <!-- ヒーロー画像を後で配置 -->
                    <img src="/assets/images/hero-illustration.png" alt="3つのイラストを組み合わせて物語を作る" class="hero-image">
                </div>
                
                <div class="hero-cta-mobile">
                    <a href="/compose/" class="cta-button">今すぐつくる</a>
                    <div class="peek-animal peek-right" id="peek-animal-mobile">
                        <img src="" alt="">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- スクロールアニメーション -->
    <div class="scroll-divider">
        <div class="scroll-track">
            <?php 
            // 2回繰り返して途切れないようにする
            for ($i = 0; $i < 2; $i++):
                foreach ($scrollItems as $item): 
                    $imageUrl = $item['image_path'] ?? '';
                    if (empty($imageUrl)) continue;
                    
                    // 正方形の素材のみに絞る（CLS対策）
                    $fullPath = __DIR__ . '/' . $imageUrl;
                    if (file_exists($fullPath)) {
                        $imageSize = @getimagesize($fullPath);
                        if ($imageSize && $imageSize[0] > 0 && $imageSize[1] > 0) {
                            $aspectRatio = $imageSize[0] / $imageSize[1];
                            // アスペクト比が0.9〜1.1の範囲（ほぼ正方形）のみ表示
                            if ($aspectRatio < 0.9 || $aspectRatio > 1.1) {
                                continue;
                            }
                        } else {
                            continue;
                        }
                    } else {
                        continue;
                    }
                    
                    $scrollBgColor = '';
                    if ($item['type'] === 'material' && !empty($item['category_slug']) && !empty($item['slug'])) {
                        $link = '/' . h($item['category_slug']) . '/' . h($item['slug']) . '/';
                        $scrollBgColor = !empty($item['structured_bg_color']) ? $item['structured_bg_color'] : '#ffffff';
                    } elseif ($item['type'] === 'artwork') {
                        $link = '/everyone-work.php?id=' . h($item['id']);
                        $scrollBgColor = '#ffffff';
                    } else {
                        continue;
                    }
            ?>
                <a href="<?= $link ?>" class="scroll-item" style="background-color: <?= h($scrollBgColor) ?>; backdrop-filter: none;">
                    <img src="/<?= h($imageUrl) ?>" alt="<?= h($item['title']) ?>" loading="lazy" width="100" height="100">
                </a>
            <?php 
                endforeach;
            endfor;
            ?>
        </div>
    </div>

    <!-- カレンダーセクション -->
    <section class="calendar-section">
        <h2 class="calendar-section-title">MARUTTO.ART CALENDAR</h2>
        <div class="calendar-grid-container" id="calendarGrid">
            <!-- 曜日ヘッダー -->
            <div class="calendar-day-header sunday">Sun</div>
            <div class="calendar-day-header">Mon</div>
            <div class="calendar-day-header">Tue</div>
            <div class="calendar-day-header">Wed</div>
            <div class="calendar-day-header">Thu</div>
            <div class="calendar-day-header">Fri</div>
            <div class="calendar-day-header saturday">Sat</div>
            
            <!-- 月初前の空白 -->
            <?php for ($i = 0; $i < $firstDayOfWeek; $i++): ?>
                <div class="calendar-day empty"></div>
            <?php endfor; ?>
            
            <!-- カレンダーの日付 -->
            <?php 
            $weekdayNames = ['日', '月', '火', '水', '木', '金', '土'];
            $weekdayNamesEn = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            foreach ($calendarDays as $dayInfo): 
                $item = $dayInfo['item'];
                $todayClass = $dayInfo['is_today'] ? 'today' : '';
                $dayOfWeek = $dayInfo['day_of_week'];
                $weekdayName = $weekdayNames[$dayOfWeek];
                $weekdayNameEn = $weekdayNamesEn[$dayOfWeek];
                $weekdayClass = '';
                if ($dayOfWeek === 0) {
                    $weekdayClass = 'sunday';
                } elseif ($dayOfWeek === 6) {
                    $weekdayClass = 'saturday';
                }
            ?>
                <?php if ($item): ?>
                    <a href="/calendar-detail/?year=<?= h($item['year']) ?>&month=<?= h($item['month']) ?>&day=<?= h($item['day']) ?>" 
                       class="calendar-day has-item <?= $todayClass ?> <?= $weekdayClass ?>">
                        <div class="calendar-day-date"><?= h($dayInfo['day']) ?><span class="calendar-day-weekday">(<?= h($weekdayNameEn) ?>)</span></div>
                        <?php 
                        // サムネイルまたは画像を表示
                        $imagePath = $item['thumbnail_path'] ?? $item['image_path'];
                        if ($imagePath):
                        ?>
                            <img src="/<?= h($imagePath) ?>" 
                                 alt="<?= h($item['title']) ?>" 
                                 class="calendar-day-image">
                        <?php endif; ?>
                        <div class="calendar-day-title"><?= h($item['title']) ?></div>
                    </a>
                <?php else: ?>
                    <div class="calendar-day <?= $todayClass ?> <?= $weekdayClass ?>">
                        <div class="calendar-day-date"><?= h($dayInfo['day']) ?><span class="calendar-day-weekday">(<?= h($weekdayNameEn) ?>)</span></div>
                        <div class="calendar-day-empty">準備中</div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <a href="/calendar/" class="calendar-button">View Calendar →</a>
    </section>

    <!-- みんなの作品セクション -->
    <section class="section">
        <div class="container">
            <h2 class="section-title">みんなの作品</h2>
            
            <div class="artworks-grid">
                <?php foreach ($artworks as $artwork): ?>
                <a href="/everyone-work.php?id=<?= h($artwork['id']) ?>" class="artwork-card">
                    <?php
                    $imageUrl = !empty($artwork['webp_path']) ? $artwork['webp_path'] : $artwork['file_path'];
                    ?>
                    <div class="artwork-image-wrapper">
                        <img src="/<?= h($imageUrl) ?>" 
                             alt="<?= h($artwork['title']) ?>" 
                             class="artwork-image" 
                             loading="lazy"
                             width="200"
                             height="200">
                    </div>
                    <div class="artwork-info">
                        <div class="artwork-title"><?= h($artwork['title']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
            </div>

            <a href="/everyone-works.php" class="more-button">もっと見る →</a>
        </div>
    </section>
    <!-- 好きな素材をみつけよう��クション -->
    <section class="section">
        <div class="container">
            <h2 class="section-title">好きな素材をみつけよう</h2>
            
            <div class="materials-grid">
                <?php foreach ($randomMaterials as $material): ?>
                    <?php
                    $materialImageUrl = !empty($material['webp_small_path']) ? $material['webp_small_path'] : $material['image_path'];
                    $bgColor = !empty($material['structured_bg_color']) ? $material['structured_bg_color'] : '#ffffff';
                    ?>
                    <a href="/<?= h($material['category_slug']) ?>/<?= h($material['slug']) ?>/" 
                       class="material-item" 
                       style="background-color: <?= h($bgColor) ?>; backdrop-filter: none;">
                        <img src="/<?= h($materialImageUrl) ?>" alt="<?= h($material['title']) ?>" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>

            <a href="/list.php" class="more-button">他の素材を探す →</a>
        </div>
    </section>

    <!-- 広告ユニット -->
    <div class="container">
        <div class="ad-container">
            <?php include __DIR__ . '/includes/ad-display.php'; ?>
            <div class="ad-desktop-only">
                <?php include __DIR__ . '/includes/ad-display.php'; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
    // 動物がランダムに顔を出すアニメーション
    const animals = [
        '/assets/images/penguin.png',
        '/assets/images/shimaenaga.png'
    ];

    function showPeekAnimal(elementId) {
        const peekElement = document.getElementById(elementId);
        if (!peekElement) return;

        // ランダムな動物を選択
        const randomAnimal = animals[Math.floor(Math.random() * animals.length)];
        const img = peekElement.querySelector('img');
        img.src = randomAnimal;
        img.alt = 'かわいい動物';

        // ランダムな位置（left: 20% 〜 80%）
        const randomLeft = Math.floor(Math.random() * 61) + 20; // 20〜80の範囲
        peekElement.style.left = `${randomLeft}%`;

        // アニメーション開始
        peekElement.classList.add('active');

        // アニメーション終了後にクラスを削除
        setTimeout(() => {
            peekElement.classList.remove('active');
        }, 3000);
    }

    function scheduleNextPeek(elementId) {
        // ランダムな間隔（3秒〜8秒）
        const randomDelay = Math.floor(Math.random() * 5000) + 3000;
        
        setTimeout(() => {
            showPeekAnimal(elementId);
            scheduleNextPeek(elementId); // 次の表示をスケジュール
        }, randomDelay);
    }

    // ページ読み込み後に開始
    document.addEventListener('DOMContentLoaded', () => {
        // PC版とモバイル版で独立してアニメーション
        scheduleNextPeek('peek-animal-pc');
        scheduleNextPeek('peek-animal-mobile');
        
        // カレンダーのスワイプ機能（スマホのみ）
        const calendarGrid = document.getElementById('calendarGrid');
        if (calendarGrid && window.innerWidth <= 768) {
            // 当日の要素を取得
            const todayElement = calendarGrid.querySelector('.calendar-day.today');
            
            if (todayElement) {
                // 当日を中央に配置（少し遅延させて確実にレンダリング後に実行）
                setTimeout(() => {
                    const containerWidth = calendarGrid.offsetWidth;
                    const elementWidth = todayElement.offsetWidth;
                    const elementLeft = todayElement.offsetLeft;
                    
                    // 要素を中央に配置するスクロール位置を計算
                    const scrollPosition = elementLeft - (containerWidth / 2) + (elementWidth / 2);
                    
                    calendarGrid.scrollTo({
                        left: scrollPosition,
                        behavior: 'smooth'
                    });
                }, 100);
            }
        }
    });
    </script>
</body>
</html>
