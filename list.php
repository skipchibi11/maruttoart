<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// ページネーション設定
$perPage =  50; // 1ページあたりの表示件数
$page = max(1, intval($_GET['page'] ?? 1)); // 現在のページ（最小値は1）
$offset = ($page - 1) * $perPage;

// 検索処理（qパラメータを使用、下位互換のためsearchも受け取る）
$search = $_GET['q'] ?? $_GET['search'] ?? '';
$whereClause = "WHERE 1=1";
$params = [];
$countParams = [];

if (!empty($search)) {
    $whereClause .= " AND (m.title LIKE ? OR m.description LIKE ? OR m.search_keywords LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
    $countParams = $params;
}

// 総件数を取得
$countSql = "SELECT COUNT(*) FROM materials m " . $whereClause;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// データを取得（カテゴリ情報と背景色も含める）
$sql = "SELECT m.*, c.slug as category_slug FROM materials m 
        LEFT JOIN categories c ON m.category_id = c.id " . 
        $whereClause . " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();

// 現在表示されている素材からミニストーリーがあるものをランダムに3件取得
$storyMaterials = [];
if (!empty($materials)) {
    // 表示中の素材のIDを取得
    $materialIds = array_column($materials, 'id');
    if (!empty($materialIds)) {
        $placeholders = implode(',', array_fill(0, count($materialIds), '?'));
        $storyStmt = $pdo->prepare("
            SELECT m.id, m.title, m.slug, m.mini_story,
                   m.image_path, m.webp_small_path, m.structured_bg_color,
                   c.slug as category_slug
            FROM materials m
            LEFT JOIN categories c ON m.category_id = c.id
            WHERE m.id IN ($placeholders)
            AND m.mini_story IS NOT NULL
            ORDER BY RAND()
            LIMIT 3
        ");
        $storyStmt->execute($materialIds);
        $storyMaterials = $storyStmt->fetchAll();
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
    <title>ミニマルなフリーイラスト素材｜maruttoart（商用利用OK）</title>
    <meta name="description" content="ミニマルなフリーイラスト素材の一覧ページ！ミニマルに描かれた動物、植物、食べ物などの素材を商用利用OK。個人・法人問わずご利用いただける無料素材集です。">
    <link rel="icon" href="/favicon.ico">
    
    <!-- カノニカルタグ -->
    <?php
    $canonicalUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . '/list.php';
    if (!empty($search)) {
        // 検索がある場合は検索クエリを含める
        $canonicalUrl .= '?search=' . urlencode($search);
        if ($page > 1) {
            $canonicalUrl .= '&page=' . $page;
        }
    } else {
        // 検索がない場合はページのみ
        if ($page > 1) {
            $canonicalUrl .= '?page=' . $page;
        }
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

        /* 検索フォームのスタイル */
        .search-form {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            margin-bottom: 2rem;
        }

        .search-form form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            background-color: #fff;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            border-color: #0d6efd;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        .search-input::placeholder {
            color: #adb5bd;
        }

        .search-button {
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
            white-space: nowrap;
        }

        .search-button:hover {
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
        }

        .search-button:focus {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(204, 204, 204, 0.3);
        }

        /* クリアボタンのスタイル */
        .search-form .btn-secondary {
            background-color: #ffffff;
            color: #444;
            border: 2px solid #ccc;
            border-radius: 12px;
            padding: 0.75em 1.25em;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
        }

        .search-form .btn-secondary:hover {
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
        }

        /* レスポンシブ対応 */
        @media (max-width: 576px) {
            .search-form {
                padding: 1.25rem;
                border-radius: 10px;
            }
            
            .search-form form {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }
            
            .search-input {
                margin-bottom: 0;
            }
            
            .search-button,
            .search-form .btn-secondary {
                width: 100%;
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

        /* バナー作成機能のスタイル */
        .banner-creator-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem 0;
            border-radius: 12px;
        }

        /* バナーサイズ選択のスタイル */
        .banner-size-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .size-control-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .size-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #495057;
            margin: 0;
        }

        .banner-size-select {
            background-color: #ffffff;
            color: #444;
            border: 2px solid #ccc;
            border-radius: 12px;
            padding: 0.75em 1em;
            font-size: 1rem;
            font-weight: 500;
            min-width: 120px;
            display: inline-block;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            text-align: center;
        }

        .banner-size-select:hover {
            background-color: #f5f5f5;
            border-color: #999;
        }

        .banner-size-select:focus {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(204, 204, 204, 0.3);
            border-color: #999;
        }

        .size-separator {
            font-size: 1.5rem;
            font-weight: bold;
            color: #6c757d;
            margin: 0 0.5rem;
        }



        /* バナーダウンロードボタンのスタイル */
        .banner-download-button {
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
            white-space: nowrap;
            cursor: pointer;
        }

        .banner-download-button:hover {
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
        }

        .banner-download-button:focus {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(204, 204, 204, 0.3);
        }

        .banner-download-button:disabled {
            background-color: #f8f9fa;
            color: #6c757d;
            border-color: #dee2e6;
            cursor: not-allowed;
        }

        .banner-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .banner-preview-container {
            position: relative;
            text-align: center;
        }

        #bannerCanvas {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        #bannerCanvas.generating {
            filter: blur(2px);
            opacity: 0.7;
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

        @media (max-width: 768px) {
            .banner-creator-card {
                padding: 1.5rem;
            }
            
            .banner-controls {
                flex-direction: column;
                gap: 1rem;
            }

            .banner-size-controls {
                flex-direction: row;
                gap: 0.8rem;
                justify-content: center;
            }

            .banner-size-select {
                min-width: 90px;
                font-size: 0.85rem;
                padding: 0.6em 0.5em;
            }

            .size-separator {
                font-size: 1.2rem;
                margin: 0 0.2rem;
                align-self: flex-end;
                margin-bottom: 0.5rem;
            }



            .banner-download-button {
                font-size: 0.9rem;
                padding: 0.7em 1.8em;
            }
        }

        /* ストーリーのある素材セクション */
        .story-materials-section {
            background: linear-gradient(135deg, #fff8e1 0%, #ffe9c5 100%);
            padding: 3rem 2rem;
            border-radius: 1rem;
            margin: 2rem 0;
        }

        .story-materials-section h2 {
            color: #d4a574;
            font-weight: 700;
        }

        .story-materials-section .text-muted {
            color: #a68b6a !important;
        }

        .story-materials-list {
            display: flex;
            flex-direction: column;
            gap: 3rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .story-material-item {
            background: #ffffff;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .story-material-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .story-item-image-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
            padding: 2rem;
        }

        .story-item-image {
            width: 100%;
            max-width: 300px;
            aspect-ratio: 1;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .story-item-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .story-item-content {
            padding: 0 2rem 2rem 2rem;
        }

        .story-item-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #d4a574;
            margin-bottom: 1.25rem;
            text-align: center;
        }

        .story-item-text {
            font-size: 1rem;
            line-height: 2;
            color: #555;
            font-family: 'Hiragino Maru Gothic ProN', 'ヒラギノ丸ゴ ProN', 'メイリオ', Meiryo, sans-serif;
            background: #fff9f0;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border-left: 4px solid #d4a574;
        }

        @media (max-width: 768px) {
            .story-materials-section {
                padding: 2rem 1rem;
            }

            .story-materials-list {
                gap: 2rem;
            }

            .story-item-image-wrapper {
                padding: 1.5rem;
            }

            .story-item-image {
                max-width: 250px;
            }

            .story-item-content {
                padding: 0 1.5rem 1.5rem 1.5rem;
            }

            .story-item-title {
                font-size: 1.1rem;
            }

            .story-item-text {
                font-size: 0.95rem;
                padding: 1rem;
            }
        }
    </style>
    
    <?php include __DIR__ . '/includes/gtm-head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/includes/gtm-body.php'; ?>
    
    <?php 
    $currentPage = 'list';
    include 'includes/header.php'; 
    ?>

    <div class="container mt-4" id="materials">
        <div class="row">
            <div class="col-12">
                <?php if (!empty($search)): ?>
                    <h1 class="mb-2">検索結果: "<?= h($search) ?>"</h1>
                    <p class="text-muted mb-4">
                        <?= number_format($totalItems) ?>件中 
                        <?= number_format(($page - 1) * $perPage + 1) ?>-<?= number_format(min($page * $perPage, $totalItems)) ?>件目を表示 
                        (<?= $page ?>/<?= $totalPages ?>ページ)
                    </p>
                <?php else: ?>
                    <h1 class="mb-2">ミニマルなフリーイラスト素材集</h1>
                    <p class="text-muted mb-4">
                        全<?= number_format($totalItems) ?>件中 
                        <?= number_format(($page - 1) * $perPage + 1) ?>-<?= number_format(min($page * $perPage, $totalItems)) ?>件目を表示 
                        (<?= $page ?>/<?= $totalPages ?>ページ)
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- 検索フォーム -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="search-form">
                    <form method="GET" action="/list.php" class="d-flex align-items-center">
                        <input type="text" 
                               name="q" 
                               value="<?= h($search) ?>" 
                               placeholder="素材を検索（例：猫、花、食べ物など）" 
                               class="search-input form-control me-2">
                        <button type="submit" class="search-button btn btn-primary">検索</button>
                        <?php if (!empty($search)): ?>
                            <a href="/list.php" class="btn btn-outline-secondary ms-2">クリア</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="row">
            <?php foreach ($materials as $material): ?>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2 col-xxl-2 mb-4">
                <?php 
                // 詳細ページのURLを生成（検索パラメータ付き）
                $detailUrl = '';
                if (!empty($material['category_slug'])) {
                    $detailUrl = "/{$material['category_slug']}/{$material['slug']}/";
                } else {
                    $detailUrl = "/detail/{$material['slug']}";
                }
                
                // 検索クエリがあってもパラメータは追加しない（シンプル形式）
                
                // AIが指定した背景色を取得（フォールバックは従来の色）
                $backgroundColor = $material['structured_bg_color'] ?? '#F9F5E9';
                ?>
                <a href="<?= h($detailUrl) ?>" 
                   class="card material-card h-100" 
                   role="button" 
                   tabindex="0" 
                   aria-label="<?= h($material['title']) ?>の詳細を見る">
                    <?php
                    // レスポンシブ画像の設定
                    $smallImage = $material['webp_small_path'] ?? $material['image_path'];
                    $mediumImage = $material['webp_medium_path'] ?? $material['image_path'];
                    ?>
                    <picture>
                        <!-- スマホ: 180x180のWebP画像 -->
                        <source media="(max-width: 768px)" srcset="/<?= h($smallImage) ?>" type="image/webp">
                        <!-- PC: 300x300のWebP画像 -->
                        <source media="(min-width: 769px)" srcset="/<?= h($mediumImage) ?>" type="image/webp">
                        <!-- フォールバック -->
                        <img src="/<?= h($material['image_path']) ?>" 
                             class="material-image" 
                             alt="<?= h($material['title']) ?>のイラスト"
                             loading="lazy"
                             decoding="async"
                             style="background-color: <?= h($backgroundColor) ?>;">
                    </picture>
                    
                    <div class="card-body">
                        <h3 class="card-title"><?= h($material['title']) ?></h3>
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
                            <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
                               class="pagination-btn">
                                前へ
                            </a>
                        <?php endif; ?>

                        <!-- 次のページ -->
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
                               class="pagination-btn">
                                次へ
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
        <?php endif; ?>

        <!-- ストーリーのある素材セクション -->
        <?php if (!empty($storyMaterials)): ?>
        <section class="story-materials-section mt-5 mb-5">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center mb-2">おはなしのある子たち</h2>
                    <p class="text-center text-muted mb-4">ちいさな物語とともに、やさしい時間をどうぞ</p>
                </div>
            </div>
            
            <div class="story-materials-list">
                <?php foreach ($storyMaterials as $storyMat): ?>
                <div class="story-material-item">
                    <!-- 画像（リンク） -->
                    <a href="/<?= h($storyMat['category_slug']) ?>/<?= h($storyMat['slug']) ?>/" class="text-decoration-none">
                        <div class="story-item-image-wrapper">
                            <?php
                            $storyImageSrc = !empty($storyMat['webp_small_path']) 
                                ? '/' . h($storyMat['webp_small_path'])
                                : '/' . h($storyMat['image_path']);
                            $storyBgColor = !empty($storyMat['structured_bg_color']) 
                                ? h($storyMat['structured_bg_color']) 
                                : '#ffffff';
                            ?>
                            <div class="story-item-image" style="background-color: <?= $storyBgColor ?>;">
                                <img src="<?= $storyImageSrc ?>" 
                                     alt="<?= h($storyMat['title']) ?>"
                                     loading="lazy"
                                     decoding="async">
                            </div>
                        </div>
                    </a>
                    
                    <!-- ストーリー（リンクなし） -->
                    <div class="story-item-content">
                        <h3 class="story-item-title"><?= h($storyMat['title']) ?></h3>
                        <div class="story-item-text">
                            <?= nl2br(h($storyMat['mini_story'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- バナー作成機能 -->
        <?php if (!empty($materials)): ?>
        <div class="banner-creator-section mt-5 mb-5">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="text-muted mb-4">表示中の全イラストをタイル状に並べたバナーを作成</p>
                    <div class="mb-4">
                        <div class="banner-size-controls">
                            <div class="size-control-group">
                                <select id="bannerWidth" class="banner-size-select">
                                    <option value="500">500px</option>
                                    <option value="600">600px</option>
                                    <option value="700">700px</option>
                                    <option value="800">800px</option>
                                    <option value="900">900px</option>
                                    <option value="1000">1000px</option>
                                    <option value="1100">1100px</option>
                                    <option value="1200">1200px</option>
                                    <option value="1300" selected>1300px</option>
                                    <option value="1400">1400px</option>
                                    <option value="1500">1500px</option>
                                </select>
                            </div>
                            <div class="size-separator">×</div>
                            <div class="size-control-group">
                                <select id="bannerHeight" class="banner-size-select">
                                    <option value="500" selected>500px</option>
                                    <option value="600">600px</option>
                                    <option value="700">700px</option>
                                    <option value="800">800px</option>
                                    <option value="900">900px</option>
                                    <option value="1000">1000px</option>
                                    <option value="1100">1100px</option>
                                    <option value="1200">1200px</option>
                                    <option value="1300">1300px</option>
                                    <option value="1400">1400px</option>
                                    <option value="1500">1500px</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="banner-download-button" id="downloadBannerBtn">
                        バナーをダウンロード
                    </button>
                    <div id="bannerStatus" class="mt-3 text-muted"></div>
                    <canvas id="bannerCanvas" width="1300" height="500" style="display: none;"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($materials)): ?>
        <div class="row">
            <div class="col-12 text-center">
                <p class="text-muted">
                    <?php if (!empty($search)): ?>
                        「<?= h($search) ?>」に該当する素材が見つかりませんでした。
                    <?php else: ?>
                        素材が見つかりませんでした。
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    
    <!-- カードのキーボードナビゲーション対応 -->
    <script>
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

    // バナー作成機能
    document.addEventListener('DOMContentLoaded', function() {
        const downloadBtn = document.getElementById('downloadBannerBtn');
        const canvas = document.getElementById('bannerCanvas');
        const ctx = canvas.getContext('2d');
        const statusDiv = document.getElementById('bannerStatus');
        const widthSelect = document.getElementById('bannerWidth');
        const heightSelect = document.getElementById('bannerHeight');

        // 素材データを JavaScript に渡す
        const materials = <?= json_encode(array_map(function($material) {
            return [
                'title' => $material['title'],
                'image_path' => '/' . ($material['webp_medium_path'] ?? $material['image_path']),
                'bg_color' => $material['structured_bg_color'] ?? '#F9F5E9'
            ];
        }, $materials)) ?>;

        // タイル設定を計算
        function calculateTileSettings(width, height) {
            // 固定タイルサイズ
            const tileSize = 130; // 130px固定
            
            const cols = Math.ceil(width / tileSize);
            const rows = Math.ceil(height / tileSize);
            const totalTiles = cols * rows;
            
            return { tileSize, cols, rows, totalTiles };
        }

        // バナー生成＆ダウンロード機能
        async function generateAndDownloadBanner() {
            downloadBtn.disabled = true;
            
            // 選択されたサイズを取得
            const selectedWidth = parseInt(widthSelect.value);
            const selectedHeight = parseInt(heightSelect.value);
            
            // キャンバスサイズを更新
            canvas.width = selectedWidth;
            canvas.height = selectedHeight;
            
            // タイル設定を計算
            const { tileSize, cols, rows, totalTiles } = calculateTileSettings(selectedWidth, selectedHeight);
            
            statusDiv.textContent = `バナーを生成しています... (${selectedWidth}×${selectedHeight}px, ${totalTiles}タイル)`;

            // キャンバスをクリア
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // 素材をランダムに並べ、足りない場合は繰り返し
            const tilesData = [];
            for (let i = 0; i < totalTiles; i++) {
                const material = materials[i % materials.length];
                tilesData.push(material);
            }
            
            // シャッフル
            tilesData.sort(() => Math.random() - 0.5);

            let loadedCount = 0;

            // 画像を読み込んで配置
            tilesData.forEach((material, index) => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                
                img.onload = function() {
                    const col = index % cols;
                    const row = Math.floor(index / cols);
                    
                    const x = col * tileSize;
                    const y = row * tileSize;
                    
                    // 個々の素材の背景色を使用
                    ctx.fillStyle = material.bg_color;
                    ctx.fillRect(x, y, tileSize, tileSize);
                    
                    // 画像をタイルサイズに合わせて描画（中央に配置）
                    const imgSize = tileSize * 0.8; // タイルの80%のサイズ
                    const imgX = x + (tileSize - imgSize) / 2;
                    const imgY = y + (tileSize - imgSize) / 2;
                    
                    ctx.drawImage(img, imgX, imgY, imgSize, imgSize);
                    
                    loadedCount++;
                    statusDiv.textContent = `画像を読み込み中... (${loadedCount}/${totalTiles})`;
                    
                    if (loadedCount === totalTiles) {
                        // 完了後、即座にダウンロード
                        canvas.toBlob(function(blob) {
                            const url = URL.createObjectURL(blob);
                            const link = document.createElement('a');
                            link.href = url;
                            link.download = `maruttoart_${selectedWidth}x${selectedHeight}_banner_${Date.now()}.png`;
                            
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            
                            URL.revokeObjectURL(url);
                            
                            downloadBtn.disabled = false;
                            statusDiv.textContent = `バナーのダウンロードが完了しました！ (${selectedWidth}×${selectedHeight}px, ${totalTiles}タイル)`;
                            
                            // ダウンロード追跡
                            if (typeof gtag !== 'undefined') {
                                gtag('event', 'download', {
                                    'event_category': 'Banner',
                                    'event_label': `${selectedWidth}x${selectedHeight}_banner`,
                                    'value': totalTiles
                                });
                            }
                        }, 'image/png');
                    }
                };
                
                img.onerror = function() {
                    console.error('画像の読み込みに失敗:', material.image_path);
                    loadedCount++;
                    if (loadedCount === totalTiles) {
                        downloadBtn.disabled = false;
                        statusDiv.textContent = `バナーのダウンロードが完了しました！ (${selectedWidth}×${selectedHeight}px, 読み込み済み: ${loadedCount}/${totalTiles})`;
                    }
                };
                
                img.src = material.image_path;
            });
        }

        // イベントリスナー
        downloadBtn.addEventListener('click', generateAndDownloadBanner);
    });
    </script>
</body>
</html>
