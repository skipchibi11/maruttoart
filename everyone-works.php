<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

// アクセスログ記録
logAccess();

$pdo = getDB();

// ページネーション設定
$perPage =  50; // 1ページあたりの表示件数
$page = max(1, intval($_GET['page'] ?? 1)); // 現在のページ（最小値は1）
$offset = ($page - 1) * $perPage;

// 承認済み作品のみ表示（検索機能なし）
$whereClause = "WHERE status = 'approved'";
$params = [];

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

// 表示している作品の中から140文字以上の説明がある作品をランダムに3件取得
$kindStories = [];
if (!empty($artworks)) {
    // 140文字以上の説明がある作品をフィルタリング
    $longDescriptionArtworks = array_filter($artworks, function($artwork) {
        return mb_strlen($artwork['description'] ?? '') >= 100;
    });
    
    // ランダムに並び替えて最大3件取得
    if (!empty($longDescriptionArtworks)) {
        // array_valuesで配列を再インデックス化してからshuffle
        $longDescriptionArtworks = array_values($longDescriptionArtworks);
        shuffle($longDescriptionArtworks);
        $kindStories = array_slice($longDescriptionArtworks, 0, 3);
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8053468089362860"
     crossorigin="anonymous"></script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>みんなのアトリエ｜maruttoart</title>
    <meta name="description" content="みんなが作った素敵な作品を集めたアトリエです。フリー素材として公開された作品もダウンロードできます。">
    <link rel="icon" href="/favicon.ico">
    
    <!-- カノニカルタグ -->
    <?php
    $canonicalUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . '/everyone-works.php';
    if ($page > 1) {
        $canonicalUrl .= '?page=' . $page;
    }
    ?>
    <link rel="canonical" href="<?= h($canonicalUrl) ?>">
    
    <style>
        /* リセットCSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            line-height: 1.5;
            color: #212529;
        }

        /* コンテナシステム */
        .container {
            width: 100%;
            max-width: 1140px;
            margin: 0 auto;
            padding-left: 15px;
            padding-right: 15px;
        }

        /* グリッドシステム */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-left: -15px;
            margin-right: -15px;
        }

        /* 基本カラム設定 */
        [class*="col-"] {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-12 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
            flex: 0 0 100%;
            max-width: 100%;
        }

        .col-6 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
            flex: 0 0 50%;
            max-width: 50%;
        }

        .col-md-4 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-lg-3 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-xl-2 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-xxl-2 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        /* レスポンシブブレークポイント */
        /* ~576px: 2列表示 (col-6: 50%) */
        /* 768px~: 3列表示 (col-md-4: 33.33%) */
        /* 992px~: 4列表示 (col-lg-3: 25%) */
        /* 1200px~: 6列表示 (col-xl-2: 16.67%) */
        /* 1400px~: 6列表示維持 (col-xxl-2: 16.67%) */
        
        @media (min-width: 768px) {
            .col-md-4 {
                flex: 0 0 33.333333% !important;
                max-width: 33.333333% !important;
            }
        }

        @media (min-width: 992px) {
            .col-lg-3 {
                flex: 0 0 25% !important;
                max-width: 25% !important;
            }
        }

        /* 1200px以上: 6列表示 (16.666%) */
        @media (min-width: 1200px) {
            .col-xl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
        }

        /* 1400px以上: 6列表示を維持 */
        @media (min-width: 1400px) {
            .col-xxl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
            
            /* コンテナの最大幅を拡張 */
            .container {
                max-width: 1320px;
            }
        }

        /* 1600px以上: さらに大きな画面向け調整 */
        @media (min-width: 1600px) {
            .container {
                max-width: 1500px;
            }
            
            /* 大型画面で確実に6列表示 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
        }

        /* 1800px以上: 超大型画面向け調整 */
        @media (min-width: 1800px) {
            .container {
                max-width: 1680px;
            }
            
            /* 大型画面でも確実に6列表示 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
        }

        /* 2000px以上: 4K画面等の超大型画面 */
        @media (min-width: 2000px) {
            .container {
                max-width: 1860px;
            }
            
            /* 超大型画面でも確実に6列表示 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
        }



        /* カードコンポーネント */
        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.25rem;
        }

        .material-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            position: relative;
            border-radius: 8px;
            will-change: transform, box-shadow;
            background-color: #F9F5E9;
            margin-bottom: 0.5rem;
            padding: 20px;
            overflow: hidden;
        }

        .material-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: inherit;
            text-decoration: none;
        }

        .material-card:focus {
            outline: none;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: inherit;
            text-decoration: none;
        }

        .card-body {
            flex: 1 1 auto;
            padding: 0.5rem 1rem 0.1rem 1rem;
        }

        .card-title {
            color: #666;
            font-weight: 300;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 0;
        }

        /* h3のcard-titleで既存のh5と同じ見た目を維持 */
        h3.card-title {
            color: #666;
            font-weight: 300;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 0;
            margin-top: 0;
        }

        .material-card:hover .card-title,
        .material-card:hover h3.card-title {
            color: #666;
        }

        .artwork-meta {
            text-align: center;
            font-size: 0.8rem;
            color: #888;
            margin-top: 0.5rem;
        }

        .pen-name {
            color: #667eea;
            font-weight: 500;
        }

        .material-provider {
            color: #aaa;
            font-size: 0.75rem;
            margin-top: 0.2rem;
        }

        .material-image {
            width: 100%;
            height: auto;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            border-radius: 4px;
            transition: opacity 0.3s ease-in-out;
        }
        
        /* aspect-ratioをサポートしていないブラウザ向けフォールバック */
        @supports not (aspect-ratio: 1 / 1) {
            .material-image {
                height: 0;
                padding-bottom: 100%;
            }
        }

        /* Lazyload用のスタイル */
        .material-image[loading="lazy"] {
            opacity: 0;
        }

        .material-image[loading="lazy"].loaded {
            opacity: 1;
        }

        /* 読み込み中のプレースホルダー */
        .material-image:not(.loaded) {
            background-image: linear-gradient(45deg, #f8f9fa 25%, transparent 25%, transparent 75%, #f8f9fa 75%, #f8f9fa),
                              linear-gradient(45deg, #f8f9fa 25%, transparent 25%, transparent 75%, #f8f9fa 75%, #f8f9fa);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
        }

        /* ユーティリティクラス */
        .mt-4 { margin-top: 1.5rem !important; }
        .mt-5 { margin-top: 3rem !important; }
        .mb-2 { margin-bottom: 0.5rem !important; }
        .mb-4 { margin-bottom: 1.5rem !important; }
        .mb-0 { margin-bottom: 0 !important; }
        .me-2 { margin-right: 0.5rem !important; }
        .ms-2 { margin-left: 0.5rem !important; }
        .text-muted { color: #6c757d !important; }
        .text-center { text-align: center !important; }
        .text-white { color: #fff !important; }
        .text-decoration-underline { text-decoration: underline !important; }
        .bg-light { background-color: #f8f9fa !important; }
        .py-4 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
        .h-100 { height: 100% !important; }
        .d-flex { display: flex !important; }
        .align-items-center { align-items: center !important; }
        .form-control {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #fff;
            background-image: none;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .form-control:focus {
            color: #212529;
            background-color: #fff;
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        /* ボタンスタイル */
        .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
        }

        .btn-outline-secondary:hover {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
            text-decoration: none;
        }

        .btn-outline-secondary.disabled {
            color: #adb5bd;
            background-color: transparent;
            border-color: #dee2e6;
            pointer-events: none;
        }

        /* ページネーションボタン（トップページの「もっと見る」と同じスタイル） */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .pagination-btn {
            background-color: #ffffff;
            color: #444;
            border: 2px solid #ccc;
            border-radius: 12px;
            padding: 0.75em 2em;
            font-size: 1rem;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease-in-out;
        }

        .pagination-btn:hover {
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
            text-decoration: none;
        }
        }

        .page-link:hover {
            z-index: 2;
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
            text-decoration: none;
        }

        .page-link:focus {
            z-index: 3;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(204, 204, 204, 0.3);
        }
        }

        /* ページネーション全体のコンテナ */
        nav[aria-label="ページネーション"] {
            margin-top: 3rem;
            margin-bottom: 2rem;
        }

        /* ボタン */
        .btn {
            display: inline-block;
            font-weight: 400;
            color: #212529;
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            text-decoration: none;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .btn:hover {
            color: #212529;
            text-decoration: none;
        }

        .btn:focus {
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .btn-success {
            color: #fff;
            background-color: #198754;
            border-color: #198754;
        }

        .btn-success:hover {
            color: #fff;
            background-color: #157347;
            border-color: #146c43;
        }

        .btn-primary {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-primary:hover {
            color: #fff;
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
        }

        .btn-outline-secondary:hover {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-outline-light {
            color: #f8f9fa;
            border-color: #f8f9fa;
        }

        .btn-outline-light:hover {
            color: #000;
            background-color: #f8f9fa;
            border-color: #f8f9fa;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }

        /* フッター */
        .align-items-center {
            align-items: center !important;
        }

        .col-md-8, .col-md-4, .col-md-12 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        @media (min-width: 768px) {
            .col-md-8 {
                flex: 0 0 66.666667%;
                max-width: 66.666667%;
            }
            .col-md-4 {
                flex: 0 0 33.333333%;
                max-width: 33.333333%;
            }
            .col-md-12 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            .text-md-end {
                text-align: right !important;
            }
        }

        /* レスポンシブ調整 */
        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1.5rem;
            }
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            /* スマホでのカラムパディング調整 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                padding-left: 10px;
                padding-right: 10px;
            }
            .row {
                margin-left: -10px;
                margin-right: -10px;
            }
        }

        @media (max-width: 576px) {
            .card-body {
                padding: 0.5rem 0.75rem 0.1rem 0.75rem;
            }
            .card-title {
                font-size: 1rem;
            }
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            /* より小さなスマホでのカラムパディング調整 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                padding-left: 8px;
                padding-right: 8px;
            }
            .row {
                margin-left: -8px;
                margin-right: -8px;
            }
        }


        /* フッターのスタイル */
        .footer-custom {
            background-color: #fef9e7 !important;
        }

        /* フッター文字色の改善（コントラスト対応） */
        .footer-custom .footer-text {
            color: #1a1a1a !important;
        }

        .footer-custom .footer-text:hover {
            color: #000000 !important;
        }



        .btn-primary, .btn-success {
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary:hover, .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* 優しい出会いセクション */
        .kind-stories-section {
            background: linear-gradient(135deg, #ffe9f3 0%, #ffebf0 100%);
            border-radius: 16px;
            padding: 3rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .kind-stories-section h2 {
            font-family: 'Hiragino Maru Gothic ProN', sans-serif;
            color: #d47ca5;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .kind-stories-section .text-muted {
            color: #9a6b8b !important;
            font-size: 1rem;
        }

        .kind-stories-list {
            max-width: 800px;
            margin: 0 auto;
        }

        .kind-story-item {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 12px rgba(212, 124, 165, 0.12);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .kind-story-item:last-child {
            margin-bottom: 0;
        }

        .kind-story-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 24px rgba(212, 124, 165, 0.2);
        }

        .kind-story-image-wrapper {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .kind-story-image {
            display: inline-block;
            max-width: 300px;
            width: 100%;
        }

        .kind-story-image img {
            width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .kind-story-content {
            text-align: left;
        }

        .kind-story-title {
            font-family: 'Hiragino Maru Gothic ProN', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .kind-story-author {
            font-size: 0.9rem;
            color: #d47ca5;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .kind-story-text {
            font-size: 1rem;
            line-height: 2;
            color: #555;
            font-family: 'Hiragino Maru Gothic ProN', sans-serif;
        }

        @media (max-width: 768px) {
            .kind-stories-section {
                padding: 2rem 1rem;
            }

            .kind-stories-section h2 {
                font-size: 1.6rem;
            }

            .kind-story-item {
                padding: 1.5rem;
            }

            .kind-story-title {
                font-size: 1.3rem;
            }
        }


    </style>
</head>
<body>
    <?php 
    $currentPage = 'everyone-works';
    include 'includes/header.php'; 
    ?>

    <div class="container mt-4" id="materials">
        <div class="row">
            <div class="col-12">

                    <h1 class="mb-2">みんなのアトリエ</h1>
                    <p class="text-muted mb-4">
                        全<?= number_format($totalItems) ?>件の作品 
                        <?= number_format(($page - 1) * $perPage + 1) ?>-<?= number_format(min($page * $perPage, $totalItems)) ?>件目を表示 
                        (<?= $page ?>/<?= $totalPages ?>ページ)
                    </p>
            </div>
        </div>



        <div class="row">
            <?php foreach ($artworks as $artwork): ?>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2 col-xxl-2 mb-4">

                <a href="/everyone-work.php?id=<?= $artwork['id'] ?>" 
                   class="card material-card h-100" 
                   role="button" 
                   tabindex="0" 
                   aria-label="<?= h($artwork['title']) ?>の詳細を見る">
                    <img src="/<?= h($artwork['webp_path'] ?: $artwork['file_path']) ?>" 
                         class="material-image" 
                         alt="<?= h($artwork['title']) ?>"
                         loading="lazy"
                         decoding="async"
                         style="background-color: #F9F5E9;">
                    
                    <div class="card-body">
                        <div class="artwork-meta">
                            <div class="pen-name">作：<?= h($artwork['pen_name']) ?></div>
                            <div class="material-provider">素材提供：marutto.art</div>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>



        <!-- ページネーション -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="ページネーション" class="mt-5">
            <div class="row">
                <div class="col-12 text-center">
                    <div class="pagination-container">
                        <!-- 前のページ -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" 
                               class="pagination-btn">
                                前へ
                            </a>
                        <?php endif; ?>

                        <!-- 次のページ -->
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>" 
                               class="pagination-btn">
                                次へ
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
        <?php endif; ?>

        <!-- 優しい出会いセクション -->
        <?php if (!empty($kindStories)): ?>
        <section class="kind-stories-section mt-5 mb-5">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center mb-2">優しい出会い</h2>
                    <p class="text-center text-muted mb-4">作者の想いが込められた作品たち</p>
                </div>
            </div>
            
            <div class="kind-stories-list">
                <?php foreach ($kindStories as $story): ?>
                <div class="kind-story-item">
                    <!-- 画像（リンク） -->
                    <a href="/everyone-work.php?id=<?= h($story['id']) ?>" class="text-decoration-none">
                        <div class="kind-story-image-wrapper">
                            <?php
                            $storyImagePath = !empty($story['webp_path']) 
                                ? '/' . h($story['webp_path'])
                                : '/' . h($story['file_path']);
                            ?>
                            <div class="kind-story-image">
                                <img src="<?= $storyImagePath ?>" 
                                     alt="<?= h($story['title']) ?>"
                                     loading="lazy"
                                     decoding="async">
                            </div>
                        </div>
                    </a>
                    
                    <!-- 説明（リンクなし） -->
                    <div class="kind-story-content">
                        <h3 class="kind-story-title"><?= h($story['title']) ?></h3>
                        <p class="kind-story-author">by <?= h($story['pen_name']) ?></p>
                        <div class="kind-story-text">
                            <?= nl2br(h($story['description'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (empty($artworks)): ?>
        <div class="row">
            <div class="col-12 text-center">
                <p class="text-muted">
                    まだ作品が投稿されていません。
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <script>
    // カードのキーボードナビゲーション対応
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.material-card');
        
        cards.forEach(function(card) {
            card.addEventListener('keydown', function(e) {
                // Enterキーまたはスペースキーで詳細ページに遷移
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    window.location.href = card.href;
                }
            });
        });
    });

    // Lazyload画像の読み込み完了処理
    document.addEventListener('DOMContentLoaded', function() {
        const lazyImages = document.querySelectorAll('img[loading="lazy"]');
        
        lazyImages.forEach(img => {
            // 画像が既に読み込まれている場合
            if (img.complete && img.naturalHeight !== 0) {
                img.classList.add('loaded');
            } else {
                // 画像の読み込み完了を待機
                img.addEventListener('load', function() {
                    this.classList.add('loaded');
                });
                
                // 読み込みエラーの場合
                img.addEventListener('error', function() {
                    this.classList.add('loaded'); // エラーでも表示状態にする
                });
            }
        });
    });
    </script>
</body>
</html>
