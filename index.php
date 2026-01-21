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

// ランダムにベクター素材を6件取得
$materialsSql = "SELECT m.*, c.slug as category_slug, m.webp_small_path, m.structured_bg_color FROM materials m 
        LEFT JOIN categories c ON m.category_id = c.id 
        WHERE m.svg_path IS NOT NULL AND m.svg_path != ''
        ORDER BY RAND() LIMIT 6";
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
            border-radius: 8px;
            overflow: hidden;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .artwork-image {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            border-radius: 8px;
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
            grid-template-columns: repeat(6, 1fr);
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

        /* スクロールアニメーション */
        .scroll-divider {
            overflow: hidden;
            position: relative;
            height: 280px;
            margin: 40px 0;
            display: flex;
            align-items: center;
        }

        .scroll-track {
            display: flex;
            gap: 20px;
            animation: scrollLeft 60s linear infinite;
            will-change: transform;
        }

        .scroll-item {
            height: 240px;
            flex-shrink: 0;
            padding: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background: white;
            position: relative;
            clip-path: url(#stampEdge);
        }

        .scroll-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 20px;
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
                grid-template-columns: repeat(3, 1fr);
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
    <svg width="0" height="0" style="position: absolute;">
        <defs>
            <clipPath id="stampEdge" clipPathUnits="objectBoundingBox">
                <path d="M 0.033,0 C 0.033,0.017 0.017,0.033 0,0.033 L 0,0.067 C 0.017,0.067 0.033,0.083 0.033,0.1 C 0.033,0.117 0.017,0.133 0,0.133 L 0,0.167 C 0.017,0.167 0.033,0.183 0.033,0.2 C 0.033,0.217 0.017,0.233 0,0.233 L 0,0.267 C 0.017,0.267 0.033,0.283 0.033,0.3 C 0.033,0.317 0.017,0.333 0,0.333 L 0,0.367 C 0.017,0.367 0.033,0.383 0.033,0.4 C 0.033,0.417 0.017,0.433 0,0.433 L 0,0.467 C 0.017,0.467 0.033,0.483 0.033,0.5 C 0.033,0.517 0.017,0.533 0,0.533 L 0,0.567 C 0.017,0.567 0.033,0.583 0.033,0.6 C 0.033,0.617 0.017,0.633 0,0.633 L 0,0.667 C 0.017,0.667 0.033,0.683 0.033,0.7 C 0.033,0.717 0.017,0.733 0,0.733 L 0,0.767 C 0.017,0.767 0.033,0.783 0.033,0.8 C 0.033,0.817 0.017,0.833 0,0.833 L 0,0.867 C 0.017,0.867 0.033,0.883 0.033,0.9 C 0.033,0.917 0.017,0.933 0,0.933 L 0,0.967 C 0.017,0.967 0.033,0.983 0.033,1 L 0.067,1 C 0.067,0.983 0.083,0.967 0.1,0.967 C 0.117,0.967 0.133,0.983 0.133,1 L 0.167,1 C 0.167,0.983 0.183,0.967 0.2,0.967 C 0.217,0.967 0.233,0.983 0.233,1 L 0.267,1 C 0.267,0.983 0.283,0.967 0.3,0.967 C 0.317,0.967 0.333,0.983 0.333,1 L 0.367,1 C 0.367,0.983 0.383,0.967 0.4,0.967 C 0.417,0.967 0.433,0.983 0.433,1 L 0.467,1 C 0.467,0.983 0.483,0.967 0.5,0.967 C 0.517,0.967 0.533,0.983 0.533,1 L 0.567,1 C 0.567,0.983 0.583,0.967 0.6,0.967 C 0.617,0.967 0.633,0.983 0.633,1 L 0.667,1 C 0.667,0.983 0.683,0.967 0.7,0.967 C 0.717,0.967 0.733,0.983 0.733,1 L 0.767,1 C 0.767,0.983 0.783,0.967 0.8,0.967 C 0.817,0.967 0.833,0.983 0.833,1 L 0.867,1 C 0.867,0.983 0.883,0.967 0.9,0.967 C 0.917,0.967 0.933,0.983 0.933,1 L 0.967,1 C 0.967,0.983 0.983,0.967 1,0.967 L 1,0.933 C 0.983,0.933 0.967,0.917 0.967,0.9 C 0.967,0.883 0.983,0.867 1,0.867 L 1,0.833 C 0.983,0.833 0.967,0.817 0.967,0.8 C 0.967,0.783 0.983,0.767 1,0.767 L 1,0.733 C 0.983,0.733 0.967,0.717 0.967,0.7 C 0.967,0.683 0.983,0.667 1,0.667 L 1,0.633 C 0.983,0.633 0.967,0.617 0.967,0.6 C 0.967,0.583 0.983,0.567 1,0.567 L 1,0.533 C 0.983,0.533 0.967,0.517 0.967,0.5 C 0.967,0.483 0.983,0.467 1,0.467 L 1,0.433 C 0.983,0.433 0.967,0.417 0.967,0.4 C 0.967,0.383 0.983,0.367 1,0.367 L 1,0.333 C 0.983,0.333 0.967,0.317 0.967,0.3 C 0.967,0.283 0.983,0.267 1,0.267 L 1,0.233 C 0.983,0.233 0.967,0.217 0.967,0.2 C 0.967,0.183 0.983,0.167 1,0.167 L 1,0.133 C 0.983,0.133 0.967,0.117 0.967,0.1 C 0.967,0.083 0.983,0.067 1,0.067 L 1,0.033 C 0.983,0.033 0.967,0.017 0.967,0 L 0.933,0 C 0.933,0.017 0.917,0.033 0.9,0.033 C 0.883,0.033 0.867,0.017 0.867,0 L 0.833,0 C 0.833,0.017 0.817,0.033 0.8,0.033 C 0.783,0.033 0.767,0.017 0.767,0 L 0.733,0 C 0.733,0.017 0.717,0.033 0.7,0.033 C 0.683,0.033 0.667,0.017 0.667,0 L 0.633,0 C 0.633,0.017 0.617,0.033 0.6,0.033 C 0.583,0.033 0.567,0.017 0.567,0 L 0.533,0 C 0.533,0.017 0.517,0.033 0.5,0.033 C 0.483,0.033 0.467,0.017 0.467,0 L 0.433,0 C 0.433,0.017 0.417,0.033 0.4,0.033 C 0.383,0.033 0.367,0.017 0.367,0 L 0.333,0 C 0.333,0.017 0.317,0.033 0.3,0.033 C 0.283,0.033 0.267,0.017 0.267,0 L 0.233,0 C 0.233,0.017 0.217,0.033 0.2,0.033 C 0.183,0.033 0.167,0.017 0.167,0 L 0.133,0 C 0.133,0.017 0.117,0.033 0.1,0.033 C 0.083,0.033 0.067,0.017 0.067,0 Z" />
            </clipPath>
        </defs>
    </svg>
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
                    
                    // 画像のアスペクト比を取得して幅を計算
                    $itemWidth = 240; // デフォルト（正方形）
                    $fullPath = __DIR__ . '/' . $imageUrl;
                    if (file_exists($fullPath)) {
                        $imageSize = @getimagesize($fullPath);
                        if ($imageSize && $imageSize[0] > 0 && $imageSize[1] > 0) {
                            $aspectRatio = $imageSize[0] / $imageSize[1];
                            $itemWidth = round(240 * $aspectRatio);
                            // 最小120px、最大480pxに制限
                            $itemWidth = max(120, min(480, $itemWidth));
                        }
                    }
            ?>
                <a href="<?= $link ?>" class="scroll-item" style="width: <?= $itemWidth ?>px; background-color: <?= h($scrollBgColor) ?>; backdrop-filter: none;">
                    <img src="/<?= h($imageUrl) ?>" alt="<?= h($item['title']) ?>" loading="lazy">
                </a>
            <?php 
                endforeach;
            endfor;
            ?>
        </div>
    </div>

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
                        <img src="/<?= h($imageUrl) ?>" alt="<?= h($artwork['title']) ?>" class="artwork-image" loading="lazy">
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
    });
    </script>
</body>
</html>
