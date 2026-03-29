<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// みんなの作品を10件取得
$artworksSql = "SELECT * FROM community_artworks WHERE status = 'approved' ORDER BY created_at DESC LIMIT 10";
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

// スクロールアニメーション用の作品を取得（最新15件）
$scrollArtworksSql = "SELECT 'artwork' as type, id, '' as slug, '' as category_slug, webp_path as image_path, title, image_width, image_height
    FROM community_artworks
    WHERE status = 'approved'
    ORDER BY created_at DESC
    LIMIT 15";
$scrollArtworksStmt = $pdo->prepare($scrollArtworksSql);
$scrollArtworksStmt->execute();
$scrollItems = $scrollArtworksStmt->fetchAll();

// カレンダー表示用データ取得（今日から7日分）
$todayTimestamp = strtotime('today');
$weekCalendarItems = [];

for ($i = 0; $i < 7; $i++) {
    $targetTimestamp = strtotime("+{$i} day", $todayTimestamp);
    $targetYear = (int)date('Y', $targetTimestamp);
    $targetMonth = (int)date('n', $targetTimestamp);
    $targetDay = (int)date('j', $targetTimestamp);
    $targetDayOfWeek = (int)date('w', $targetTimestamp);
    
    // その日のカレンダーアイテムを取得
    $itemSql = "SELECT * FROM calendar_items 
                WHERE is_published = 1 
                AND year = ? 
                AND month = ? 
                AND day = ?";
    $itemStmt = $pdo->prepare($itemSql);
    $itemStmt->execute([$targetYear, $targetMonth, $targetDay]);
    $item = $itemStmt->fetch();
    
    $weekCalendarItems[] = [
        'timestamp' => $targetTimestamp,
        'year' => $targetYear,
        'month' => $targetMonth,
        'day' => $targetDay,
        'dayOfWeek' => $targetDayOfWeek,
        'isToday' => ($i === 0),
        'item' => $item ? $item : null
    ];
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
    
    <!-- Preload hero image for better LCP -->
    <link rel="preload" as="image" href="https://assets.marutto.art/hero/main.webp" fetchpriority="high">
    
    <!-- hreflang tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/" />
    
    <?php include __DIR__ . '/includes/gtm-head.php'; ?>
    <?php include __DIR__ . '/includes/adsense-head.php'; ?>
    
    <style>
        :root {
            --primary-color: #FFD4A3;
            --secondary-color: #FFABC5;
            --bg-cream: #FFF8F0;
            --bg-pink: #FFF0F5;
            --text-dark: #5A4A42;
            --shadow: 0 6px 20px rgba(255, 171, 197, 0.5);
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
                #FFF8F4 0%,
                #FFF5F0 25%,
                #FFF3ED 50%,
                #F8EDE5 75%,
                #F5E8E0 100%
            );
            min-height: 100vh;
            overflow-x: clip;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* ヒーローセクション */
        .hero {
            padding: 60px 20px 40px;
            overflow: hidden;
            position: relative;
        }

        .hero .container {
            padding: 0 20px;
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
            width: 60px;
            height: 60px;
            pointer-events: none;
            z-index: 1;
            opacity: 1;
            left: 50%;
            top: -50%;
            transform: translateX(-50%);
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }

        .peek-animal img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            animation: penguinFloat 3s ease-in-out infinite;
        }

        @keyframes penguinFloat {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        /* ペンギンポイント */
        .penguin-tip {
            position: relative;
            max-width: 800px;
            margin: 60px auto;
            padding: 0 20px;
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .penguin-tip.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .penguin-tip-content {
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }

        .penguin-tip-character {
            flex-shrink: 0;
            width: 80px;
            height: 80px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }

        .penguin-tip-character img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .penguin-tip-bubble {
            position: relative;
            background: white;
            border-radius: 20px;
            padding: 20px 24px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            flex: 1;
        }

        .penguin-tip-bubble::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 30px;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 10px 10px 10px 0;
            border-color: transparent white transparent transparent;
        }

        .penguin-tip-number {
            display: inline-block;
            background: linear-gradient(135deg, #E8A87C 0%, #C38E70 100%);
            color: white;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 12px;
            margin-bottom: 8px;
        }

        .penguin-tip-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #5A4A42;
            margin-bottom: 8px;
        }

        .penguin-tip-description {
            font-size: 0.95rem;
            color: #666;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .penguin-tip {
                margin: 40px auto;
            }

            .penguin-tip-content {
                gap: 12px;
            }

            .penguin-tip-character {
                width: 60px;
                height: 60px;
            }

            .penguin-tip-bubble {
                padding: 16px 18px;
                border-radius: 16px;
            }

            .penguin-tip-bubble::before {
                left: -8px;
                top: 20px;
                border-width: 8px 8px 8px 0;
            }

            .penguin-tip-number {
                font-size: 0.75rem;
                padding: 3px 10px;
            }

            .penguin-tip-title {
                font-size: 1rem;
            }

            .penguin-tip-description {
                font-size: 0.85rem;
            }
        }

        .cta-button:hover {
            background: linear-gradient(135deg, #FFE0B8 0%, #FFC0D8 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 171, 197, 0.6);
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

        /* SNSセクション */
        .hero-social {
            margin-top: 25px;
            text-align: center;
        }

        .hero-social-text {
            font-size: 0.85rem;
            color: #8B7355;
            margin-bottom: 12px;
            font-weight: 400;
            line-height: 1.6;
        }

        .hero-social-icons {
            display: flex;
            justify-content: center;
            gap: 18px;
            align-items: center;
        }

        .hero-social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-decoration: none;
        }

        .hero-social-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .hero-social-link svg {
            width: 22px;
            height: 22px;
        }

        .hero-social-link.x-link svg {
            fill: #000000;
        }

        .hero-social-link.pinterest-link svg {
            fill: #E60023;
        }

        .hero-social-link.youtube-link svg {
            fill: #FF0000;
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
            margin-bottom: 10px;
            color: #A0675C;
        }

        .section-subtitle {
            text-align: center;
            font-size: clamp(0.9rem, 2vw, 1.1rem);
            color: #8B7355;
            margin-bottom: 40px;
            font-weight: 400;
        }

        /* みんなの作品セクション - グリッドレイアウト */
        .artworks-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 40px;
            width: 90%;
            margin-left: auto;
            margin-right: auto;
        }

        .artwork-card {
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.3s;
        }

        .artwork-card:hover {
            transform: scale(1.02);
        }

        .artwork-image-wrapper {
            width: 100%;
            aspect-ratio: 1 / 1;
            border-radius: 12px;
            overflow: hidden;
            display: block;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .artwork-card:hover .artwork-image-wrapper {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .artwork-image {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: contain;
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
            background: #C38E70;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.05rem;
            box-shadow: var(--shado6);
            transition: all 0.3s;
        }

        .more-button:hover {
            background: #A0675C;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        

        /* カレンダーセクション */
        .calendar-section {
            padding: 60px 20px;
            position: relative;
        }

        .calendar-section .container {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
        }

        .calendar-section-title {
            text-align: center;
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--text-dark);
            letter-spacing: 0.1em;
        }

        .calendar-section-subtitle {
            text-align: center;
            font-size: 0.95rem;
            color: #8B7355;
            margin-bottom: 30px;
            font-weight: 400;
        }

        .calendar-grid-container {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
            margin-bottom: 40px;
        }

        .calendar-day {
            aspect-ratio: 1;
            padding: 0;
            position: relative;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 2px 8px rgba(90, 74, 66, 0.1);
            transition: all 0.3s ease;
        }

        .calendar-day.has-item {
            cursor: pointer;
        }

        .calendar-day.has-item:hover {
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 16px rgba(90, 74, 66, 0.15);
            transform: translateY(-2px);
        }

        .calendar-day-date {
            font-size: 0.95rem;
            padding: 8px 10px;
            color: var(--text-dark);
            text-align: center;
            flex-shrink: 0;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.5);
        }

        .calendar-day-weekday {
            font-size: 0.7rem;
            margin-left: 3px;
            font-weight: normal;
            opacity: 0.7;
        }

        .calendar-day.sunday .calendar-day-date {
            color: #FF6B6B;
        }

        .calendar-day.saturday .calendar-day-date {
            color: #4ECDC4;
        }

        .calendar-day-image {
            width: 100%;
            height: auto;
            max-height: 100%;
            object-fit: contain;
            display: block;
            flex: 1;
            background: white;
            filter: drop-shadow(0 2px 6px rgba(160, 103, 92, 0.2));
        }

        .calendar-day.has-item:hover .calendar-day-image {
            filter: drop-shadow(0 4px 12px rgba(160, 103, 92, 0.3));
            transform: scale(1.05);
        }

        .calendar-day-title {
            display: none;
        }

        .calendar-day-empty {
            padding: 20px;
            font-size: 0.7rem;
            color: rgba(90, 74, 66, 0.3);
            text-align: center;
            margin: auto;
        }

        .calendar-button {
            display: block;
            width: fit-content;
            margin: 0 auto;
            padding: 15px 40px;
            background: #C38E70;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .calendar-button:hover {
            background: #A0675C;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .calendar-section {
                padding: 40px 0;
            }

            .calendar-section .container {
                padding: 20px 10px;
            }

            .calendar-section-title {
                padding: 0 10px;
            }
            
            .calendar-grid-container {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
            
            .calendar-day {
                aspect-ratio: auto;
                min-height: 140px;
            }
            
            .calendar-day-date {
                font-size: 0.85rem;
                padding: 8px;
            }
            
            .calendar-day-empty {
                padding: 15px;
                font-size: 0.65rem;
            }
        }

        /* スクロールアニメーション */
        .scroll-divider {
            overflow: hidden;
            position: relative;
            height: 140px;
            margin: 0 0;
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
            min-width: 120px;
            max-width: 150px;
            height: 120px;
            flex-shrink: 0;
            padding: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background: white;
            position: relative;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .scroll-item img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        @media (max-width: 768px) {
            .scroll-divider {
                height: 100px;
            }

            .scroll-item {
                min-width: 90px;
                max-width: 120px;
                height: 90px;
                padding: 8px;
            }

            .scroll-track {
                gap: 8px;
            }
        }

        @keyframes scrollLeft {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
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

            /* PC版のSNSアイコンを非表示 */
            .hero-text > .hero-social {
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
                flex-direction: column;
                align-items: center;
                justify-content: center;
                order: 3;
                margin-top: 50px;
                width: 100%;
                position: relative;
            }

            .cta-button {
                padding: 12px 36px;
                font-size: 0.95rem;
            }

            .hero-cta-mobile .peek-animal {
                width: 50px;
                height: 50px;
                top: -25px;
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
                grid-template-columns: repeat(2, 1fr);
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

            .artworks-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 12px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .hero-content {
                gap: 30px;
            }

            .artworks-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/gtm-body.php'; ?>
    
    <?php 
    $currentPage = 'home';
    include 'includes/header.php'; 
    ?>

    <main role="main">
    <!-- ヒーローセクション -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1>3つ選ぶだけで、<br>物語が生まれる。</h1>
                    <p class="hero-subtitle">今日はどんな一枚をつくる？</p>
                    <div class="hero-cta-pc" style="position: relative;">
                        <a href="/compose/" class="cta-button">今すぐつくる</a>
                        <div class="peek-animal" id="peek-animal-pc">
                            <img src="https://assets.marutto.art/characters/penguin.webp" alt="ペンギン" loading="lazy">
                        </div>
                    </div>
                    
                    <!-- SNSアイコン（PC版） -->
                    <div class="hero-social">
                        <p class="hero-social-text">X、Pinterest、YouTubeでも、新しいイラストをのんびり紹介しています</p>
                        <div class="hero-social-icons">
                            <a href="https://twitter.com/marutto_art" target="_blank" rel="noopener noreferrer" class="hero-social-link x-link" aria-label="X (Twitter)">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                </svg>
                            </a>
                            <a href="https://www.pinterest.jp/maruttoart/" target="_blank" rel="noopener noreferrer" class="hero-social-link pinterest-link" aria-label="Pinterest">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738.098.119.112.224.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/>
                                </svg>
                            </a>
                            <a href="https://www.youtube.com/@marutto_art" target="_blank" rel="noopener noreferrer" class="hero-social-link youtube-link" aria-label="YouTube">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="hero-image-container">
                    <!-- ヒーロー画像を後で配置 -->
                    <img src="https://assets.marutto.art/hero/main.webp" 
                         alt="3つのイラストを組み合わせて物語を作る" 
                         class="hero-image"
                         width="600"
                         height="600"
                         fetchpriority="high"
                         loading="eager"
                         decoding="async">
                </div>
                
                <div class="hero-cta-mobile" style="position: relative;">
                    <a href="/compose/" class="cta-button">今すぐつくる</a>
                    <div class="peek-animal" id="peek-animal-mobile">
                        <img src="https://assets.marutto.art/characters/penguin.webp" alt="ペンギン" loading="lazy">
                    </div>
                    
                    <!-- SNSアイコン（モバイル版） -->
                    <div class="hero-social">
                        <p class="hero-social-text">X、Pinterest、YouTubeでも、新しいイラストをのんびり紹介しています</p>
                        <div class="hero-social-icons">
                            <a href="https://twitter.com/marutto_art" target="_blank" rel="noopener noreferrer" class="hero-social-link x-link" aria-label="X (Twitter)">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                </svg>
                            </a>
                            <a href="https://www.pinterest.com/maruttoart/" target="_blank" rel="noopener noreferrer" class="hero-social-link pinterest-link" aria-label="Pinterest">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738.098.119.112.224.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/>
                                </svg>
                            </a>
                            <a href="https://www.youtube.com/@marutto_art" target="_blank" rel="noopener noreferrer" class="hero-social-link youtube-link" aria-label="YouTube">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                </svg>
                            </a>
                        </div>
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
                    // フルURL（R2など）の場合はそのまま、相対パスの場合は先頭に / を追加
                    $finalImageUrl = (strpos($imageUrl, 'http://') === 0 || strpos($imageUrl, 'https://') === 0) ? $imageUrl : '/' . $imageUrl;
            ?>
                <a href="<?= $link ?>" class="scroll-item" style="background-color: <?= h($scrollBgColor) ?>; backdrop-filter: none;">
                    <img src="<?= h($finalImageUrl) ?>" alt="<?= h($item['title']) ?>" loading="lazy">
                </a>
            <?php 
                endforeach;
            endfor;
            ?>
        </div>
    </div>

    <!-- ペンギンポイント1 -->
    <div class="penguin-tip" data-penguin-tip="1">
        <div class="penguin-tip-content">
            <div class="penguin-tip-character">
                <img src="https://assets.marutto.art/characters/penguin.webp" alt="ペンギン">
            </div>
            <div class="penguin-tip-bubble">
                <span class="penguin-tip-number">POINT 1</span>
                <h3 class="penguin-tip-title">たくさんの素材が無料で使えるよ！</h3>
                <p class="penguin-tip-description">すべての素材が無料でダウンロードできて、商用利用もOK。自由に使って楽しんでね✨</p>
            </div>
        </div>
    </div>

    <!-- みんなの作品セクション -->
    <section class="section">
        <div class="container">
            <h2 class="section-title">Works</h2>
            <p class="section-subtitle">みんなのMix作品</p>
            
            <div class="artworks-grid">
                <?php foreach ($artworks as $artwork): ?>
                <a href="/everyone-work.php?id=<?= h($artwork['id']) ?>" class="artwork-card">
                    <?php
                    $imageUrl = !empty($artwork['webp_path']) ? $artwork['webp_path'] : $artwork['file_path'];
                    // フルURL（R2など）の場合はそのまま、相対パスの場合は先頭に / を追加
                    $finalImageUrl = (strpos($imageUrl, 'http://') === 0 || strpos($imageUrl, 'https://') === 0) ? $imageUrl : '/' . $imageUrl;
                    ?>
                    <div class="artwork-image-wrapper">
                        <img src="<?= h($finalImageUrl) ?>" 
                             alt="<?= h($artwork['title']) ?>" 
                             class="artwork-image" 
                             loading="lazy">
                    </div>
                </a>
            <?php endforeach; ?>
            </div>

            <a href="/everyone-works.php" class="more-button">View Works →</a>
        </div>
    </section>

    <!-- ペンギンポイント2 -->
    <div class="penguin-tip" data-penguin-tip="2">
        <div class="penguin-tip-content">
            <div class="penguin-tip-character">
                <img src="https://assets.marutto.art/characters/penguin.webp" alt="ペンギン">
            </div>
            <div class="penguin-tip-bubble">
                <span class="penguin-tip-number">POINT 2</span>
                <h3 class="penguin-tip-title">素材を組み合わせてオリジナル作品に！</h3>
                <p class="penguin-tip-description">お気に入りの素材を自由に組み合わせて、世界に一つだけの作品を作れるよ。好きな色に塗ったり、配置を変えたり🎨</p>
            </div>
        </div>
    </div>

    <!-- カレンダーセクション -->
    <section class="calendar-section">
        <div class="container">
            <h2 class="calendar-section-title">Calendar</h2>
            <p class="calendar-section-subtitle">maruttoのカレンダー作品</p>
            <div class="calendar-grid-container">
            <?php 
            $weekdayNamesEn = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            
            foreach ($weekCalendarItems as $dayData):
                $item = $dayData['item'];
                $hasItem = ($item !== null);
                $dayOfWeek = $dayData['dayOfWeek'];
                $weekdayNameEn = $weekdayNamesEn[$dayOfWeek];
                
                $todayClass = $dayData['isToday'] ? 'today' : '';
                $weekdayClass = '';
                if ($dayOfWeek == 0) {
                    $weekdayClass = 'sunday';
                } elseif ($dayOfWeek == 6) {
                    $weekdayClass = 'saturday';
                }
            ?>
                <?php if ($hasItem): ?>
                    <a href="/calendar-detail/?year=<?= h($item['year']) ?>&month=<?= h($item['month']) ?>&day=<?= h($item['day']) ?>" 
                       class="calendar-day has-item <?= $todayClass ?> <?= $weekdayClass ?>">
                        <div class="calendar-day-date"><?= h($dayData['day']) ?><span class="calendar-day-weekday">(<?= h($weekdayNameEn) ?>)</span></div>
                        <?php 
                        // サムネイルまたは画像を表示
                        $imagePath = $item['thumbnail_path'] ?? $item['image_path'];
                        if ($imagePath):
                            // R2 URL対応
                            $isRemoteCalImageUrl = (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0);
                            $finalCalImageUrl = $isRemoteCalImageUrl ? $imagePath : '/' . $imagePath;
                        ?>
                            <img src="<?= h($finalCalImageUrl) ?>" 
                                 alt="<?= h($item['title']) ?>" 
                                 class="calendar-day-image"
                                 width="200"
                                 height="200"
                                 loading="lazy">
                        <?php endif; ?>
                    </a>
                <?php else: ?>
                    <div class="calendar-day <?= $todayClass ?> <?= $weekdayClass ?>">
                        <div class="calendar-day-date"><?= h($dayData['day']) ?><span class="calendar-day-weekday">(<?= h($weekdayNameEn) ?>)</span></div>
                        <div class="calendar-day-empty">準備中</div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
            <a href="/calendar/" class="calendar-button">View Calendar →</a>
        </div>
    </section>

    <!-- ペンギンポイント3 -->
    <div class="penguin-tip" data-penguin-tip="3">
        <div class="penguin-tip-content">
            <div class="penguin-tip-character">
                <img src="https://assets.marutto.art/characters/penguin.webp" alt="ペンギン">
            </div>
            <div class="penguin-tip-bubble">
                <span class="penguin-tip-number">POINT 3</span>
                <h3 class="penguin-tip-title">みんなの作品を見て、アレンジもできる！</h3>
                <p class="penguin-tip-description">他のユーザーの作品をリミックスして、さらに素敵な作品に。お気に入りの作品を見つけたらアレンジしてみよう🌟</p>
            </div>
        </div>
    </div>

    <!-- 好きな素材をみつけよう��クション -->
    <section class="section">
        <div class="container">
            <h2 class="section-title">Items</h2>
            
            <div class="materials-grid">
                <?php foreach ($randomMaterials as $material): ?>
                    <?php
                    $materialImageUrl = !empty($material['webp_small_path']) ? $material['webp_small_path'] : $material['image_path'];
                    $bgColor = !empty($material['structured_bg_color']) ? $material['structured_bg_color'] : '#ffffff';
                    $isRemoteUrl = strpos($materialImageUrl, 'http://') === 0 || strpos($materialImageUrl, 'https://') === 0;
                    $finalMaterialUrl = $isRemoteUrl ? $materialImageUrl : '/' . $materialImageUrl;
                    ?>
                    <a href="/<?= h($material['category_slug']) ?>/<?= h($material['slug']) ?>/" 
                       class="material-item" 
                       style="background-color: <?= h($bgColor) ?>; backdrop-filter: none;">
                        <img src="<?= h($finalMaterialUrl) ?>" alt="<?= h($material['title']) ?>" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>

            <a href="/list.php" class="more-button">View Items →</a>
        </div>
    </section>

    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // ペンギンポイントのスクロールアニメーション
        const observerOptions = {
            threshold: 0.2,
            rootMargin: '0px 0px -10% 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    // 一度表示したら監視を解除
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // すべてのペンギンポイントを監視
        document.querySelectorAll('.penguin-tip').forEach(tip => {
            observer.observe(tip);
        });
    });
    </script>
</body>
</html>
