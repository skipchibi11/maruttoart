<?php
require_once '../config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// キッズ版は5つまで表示
$perPage = 5;
$page = 1; // 1ページ目のみ
$offset = 0;

// 総件数を取得
$countSql = "SELECT COUNT(DISTINCT id) FROM materials WHERE svg_path IS NOT NULL AND svg_path != ''";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute();
$totalItems = $countStmt->fetchColumn();
$totalPages = 1; // キッズ版はページネーションなし

// 最新のSVG素材を5つ取得
$stmt = $pdo->prepare("
    SELECT DISTINCT id, title, slug, image_path, svg_path, webp_medium_path, category_id, created_at
    FROM materials 
    WHERE svg_path IS NOT NULL 
    AND svg_path != '' 
    ORDER BY RAND()
    LIMIT 5
");
$stmt->execute();
$materials = $stmt->fetchAll();

// みんなのアトリエから140文字以上の説明がある作品をランダムに3件取得
$storyStmt = $pdo->query("
    SELECT id, title, pen_name, description, webp_path, file_path, created_at
    FROM community_artworks
    WHERE status = 'approved'
    AND CHAR_LENGTH(description) >= 100
    ORDER BY RAND()
    LIMIT 3
");
$storyArtworks = $storyStmt->fetchAll();

// AJAX リクエストの場合はJSONを返す
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    // ページネーションHTMLを生成
    $paginationHtml = '';
    if ($totalPages > 1) {
        if ($page > 1) {
            $paginationHtml .= '<a href="?page=' . ($page - 1) . '#materials" class="pagination-btn">前へ</a>';
        }
        if ($page < $totalPages) {
            $paginationHtml .= '<a href="?page=' . ($page + 1) . '#materials" class="pagination-btn">次へ</a>';
        }
    }
    
    // ページ情報テキスト
    $pageInfo = $page . ' / ' . $totalPages . ' ページ （全 ' . $totalItems . ' 件）';
    
    // JSONレスポンス
    echo json_encode([
        'materials' => $materials,
        'pagination' => $paginationHtml,
        'pageInfo' => $pageInfo,
        'currentPage' => $page,
        'totalPages' => $totalPages
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8053468089362860"
     crossorigin="anonymous"></script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="format-detection" content="telephone=no">
    <title>こどもアトリエ - maruttoart</title>
    <meta name="description" content="えをかいて、たのしいさくひんをつくろう！かんたんにつかえるこども用のアトリエです。">
    
    <!-- カノニカルURL設定 -->
    <link rel="canonical" href="https://<?= $_SERVER['HTTP_HOST'] ?>/compose/kids.php">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- レイアウト専用CSS -->
    <link rel="stylesheet" href="assets/css/layout.css">

    <style>
        /* こども向けカラフルデザイン - スマホサイズ固定(PCでも480px) */
        * {
            box-sizing: border-box;
        }
        
        html {
            overflow-x: hidden;
            background: linear-gradient(135deg, #fff5f8 0%, #fff9e6 50%, #f0f8ff 100%);
        }
        
        body {
            background: linear-gradient(135deg, #fff5f8 0%, #fff9e6 50%, #f0f8ff 100%);
            width: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            position: relative;
            z-index: 0;
        }
        
        /* スマホサイズ（600px以下かつ高さ900px以下）のみ body を 480px に制限 */
        @media (max-width: 600px) and (max-height: 900px) {
            body {
                max-width: 480px;
                margin: 0 auto;
            }
        }
        
        /* コンテナの調整 */
        .container {
            max-width: 480px;
            width: 100%;
            padding-left: 10px;
            padding-right: 10px;
        }
        
        .main-content {
            max-width: 100%;
            width: 100%;
            overflow-x: hidden;
            position: relative;
            z-index: 1;
        }
        
        .materials-panel {
            max-width: 100%;
            width: 100%;
            overflow-x: hidden;
            position: relative;
            z-index: 10;
        }

        /* 使い方セクション */
        .how-to-use-section {
            background: linear-gradient(135deg, #FFD700 0%, #FF69B4 50%, #87CEEB 100%);
            padding: 3rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .how-to-content .page-title {
            color: white;
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 1rem;
            text-align: center;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
            letter-spacing: 2px;
        }

        .how-to-content h2 {
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .step-item {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            box-shadow: 0 4px 15px rgba(255, 105, 180, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 3px solid #FFD700;
        }

        .step-item:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 8px 25px rgba(255, 105, 180, 0.5);
        }

        .step-number {
            flex-shrink: 0;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #FF69B4 0%, #FFB6C1 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 1.5rem;
            box-shadow: 0 3px 10px rgba(255, 105, 180, 0.4);
        }

        .step-text {
            flex: 1;
            color: #333;
            line-height: 1.8;
            font-size: 1.1rem;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .how-to-use-section {
                padding: 2rem 0;
            }

            .how-to-content .page-title {
                font-size: 1.6rem;
                margin-bottom: 0.5rem;
            }

            .how-to-content h2 {
                font-size: 1.6rem;
                margin-bottom: 1.5rem;
            }

            .steps-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .step-item {
                padding: 1rem;
            }

            .step-number {
                width: 35px;
                height: 35px;
                font-size: 1.1rem;
            }

            .step-text {
                font-size: 0.95rem;
            }
        }

        /* 検索フォームのスタイル - こども向けカラフル */
        .search-form {
            background: linear-gradient(135deg, #FFE4E1 0%, #F0E68C 100%);
            padding: 1.5rem;
            border-radius: 20px;
            border: 4px solid #FF69B4;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(255, 105, 180, 0.3);
        }

        .search-form form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 100%;
        }

        .search-input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: 3px solid #FFD700;
            border-radius: 15px;
            font-size: 1.2rem;
            font-weight: 600;
            background-color: #fff;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: #FF69B4;
            outline: 0;
            box-shadow: 0 0 0 4px rgba(255, 105, 180, 0.3);
            transform: scale(1.02);
        }

        .search-input::placeholder {
            color: #adb5bd;
        }

        /* レスポンシブ対応 */
        @media (max-width: 576px) {
            .search-form {
                padding: 1rem;
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
            
            #clearSearch {
                margin-left: 0 !important;
                align-self: flex-start;
                width: auto;
            }
        }

        /* ページネーションのスタイル */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }

        .pagination-btn {
            background: linear-gradient(135deg, #FF69B4 0%, #FFB6C1 100%);
            color: white;
            border: 3px solid #FFD700;
            border-radius: 20px;
            padding: 1em 2.5em;
            font-size: 1.2rem;
            font-weight: 900;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(255, 105, 180, 0.4);
        }

        .pagination-btn:hover {
            background: linear-gradient(135deg, #FFB6C1 0%, #FF69B4 100%);
            transform: scale(1.1) translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 105, 180, 0.6);
            color: white;
            text-decoration: none;
        }

        .pagination-btn:focus {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(204, 204, 204, 0.3);
        }

        .pagination-info {
            color: #6c757d;
            font-size: 0.9rem;
            text-align: center;
            margin-top: 1rem;
        }

        /* カラーセクションのスタイル */
        .color-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            order: 3;
        }

        .color-section h3 {
            color: #2c5aa0;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .color-panel-content {
            transition: all 0.3s ease;
        }

        .color-palette {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            border: 2px solid #e3f2fd;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            overflow: visible;
        }

        .color-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .color-swatch-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .color-picker-input {
            width: 60px;
            height: 60px;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
        }

        /* カラー関連の基本スタイルはlayout.cssに移動 */
            -moz-appearance: none;
            appearance: none;
        }
        
        .color-picker-input:hover {
            border-color: #007bff;
            transform: scale(1.05);
        }
        
        .color-picker-input::-webkit-color-swatch-wrapper {
            padding: 0;
        }
        
        .color-picker-input::-webkit-color-swatch {
            border: none;
            border-radius: 6px;
        }

        /* 旧実装のCSSは削除（背景色仕様に合わせるため） */

        .color-label {
            font-size: 0.75rem;
            color: #666;
            text-align: center;
            max-width: 60px;
            word-break: break-all;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .color-palette.loaded {
            animation: fadeIn 0.5s ease-out;
        }

        /* SVG描画品質のみ設定（線形属性はJavaScriptで制御） */
        svg path, svg circle, svg rect, svg polygon, svg ellipse, svg line, svg polyline {
            shape-rendering: geometricPrecision;
        }
        
        /* SVGコンテナの高品質レンダリング */
        .layer-content svg {
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            image-rendering: pixelated;
        }

        .materials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            max-width: 100%;
            padding: 5px;
        }

        .material-item {
            position: relative;
            width: 100%;
            background: linear-gradient(145deg, #ffffff 0%, #f0f0f0 100%);
            border: 3px solid #FFD700;
            border-radius: 15px;
            cursor: grab;
            transition: all 0.3s ease;
            box-shadow: 0 6px 15px rgba(255, 105, 180, 0.3), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Safari対応: aspect-ratioの代わりにpadding-topを使用 */
        .material-item::before {
            content: '';
            display: block;
            padding-top: 100%;
        }
        
        .material-item > svg,
        .material-item > img {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: calc(100% - 16px);
            height: calc(100% - 16px);
            max-width: calc(100% - 16px);
            max-height: calc(100% - 16px);
            object-fit: contain;
        }

        .material-item:active {
            cursor: grabbing;
        }

        .material-item:hover {
            border-color: #FF69B4;
            background: linear-gradient(145deg, #fff5f8 0%, #ffe4e1 100%);
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 10px 25px rgba(255, 105, 180, 0.5), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
            box-shadow: 0 4px 15px rgba(44, 90, 160, 0.2);
        }

        .material-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 4px;
        }

        /* シャッフルボタンのスタイル */
        .shuffle-btn {
            background: linear-gradient(145deg, #fff9e6 0%, #ffe4b5 100%) !important;
            border: 4px solid #FFA500 !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .shuffle-btn:hover {
            border-color: #FF8C00 !important;
            background: linear-gradient(145deg, #fff5e1 0%, #ffd480 100%) !important;
            transform: translateY(-8px) scale(1.05) rotate(5deg) !important;
            box-shadow: 0 10px 25px rgba(255, 165, 0, 0.5), inset 0 -3px 8px rgba(0, 0, 0, 0.1) !important;
        }

        .shuffle-btn:active {
            transform: translateY(-4px) scale(1.02) !important;
        }

        .shuffle-btn > svg {
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            width: 60px !important;
            height: 60px !important;
            animation: shuffle-pulse 2s ease-in-out infinite;
        }

        @keyframes shuffle-pulse {
            0%, 100% {
                transform: translate(-50%, -50%) scale(1);
            }
            50% {
                transform: translate(-50%, -50%) scale(1.1);
            }
        }

        /* キャンバスエリア */
        .canvas-area {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            order: 4;
            position: relative;
            z-index: 10;
        }

        .canvas-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .canvas-header h3 {
            color: #2c5aa0;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* キャンバス関連の基本スタイルはlayout.cssに移動 */

        /* 操作コントロール関連の基本スタイルはlayout.cssに移動 */
        
        .manipulation-controls {
            position: relative;
            z-index: 10;
        }

        .manipulation-controls h3 {
            color: #2c5aa0;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }

        .selected-layer-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 15px 0; /* 上下に適切な間隔を追加 */
        }

        .selected-title {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 4px 8px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #e9ecef;
            min-width: 120px;
            text-align: center;
        }

        .selected-title.active {
            color: #2c5aa0;
            background: #e3f2fd;
            border-color: #2c5aa0;
        }

        .manipulation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .manipulation-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            max-width: 100%;
            padding: 5px;
        }
        
        .manipulation-buttons button {
            position: relative;
            min-width: 0;
            width: 100%;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        /* Safari対応: aspect-ratioの代わりにpadding-topを使用 */
        .manipulation-buttons button::before {
            content: '';
            display: block;
            padding-top: 100%;
        }
        
        .manipulation-buttons button > svg,
        .manipulation-buttons button > input {
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
        }
        
        /* 縮小ボタンは小さい星 */
        .btn-scale-down > svg {
            width: 50% !important;
            height: 50% !important;
        }
        
        /* 拡大ボタンは大きい星 */
        .btn-scale-up > svg {
            width: 90% !important;
            height: 90% !important;
        }
        
        /* 回転ボタンは風車アイコン */
        .btn-rotate > svg {
            width: 90% !important;
            height: 90% !important;
        }
        
        .manipulation-buttons button i {
            font-size: 2rem;
        }

        /* 出力・削除ボタンエリア */
        .action-controls {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            order: 7;
            position: relative;
            z-index: 10;
        }

        .info-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            order: 8;
            margin-top: 15px;
        }

        .info-section p {
            margin-bottom: 15px;
            line-height: 1.8;
            font-size: 0.95rem;
            color: #555;
        }

        .info-section p:last-child {
            margin-bottom: 0;
        }

        .info-section strong {
            color: #2c5aa0;
            font-size: 1rem;
        }

        .action-controls h3 {
            color: #2c5aa0;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            max-width: 100%;
            padding: 5px;
        }
        
        .action-buttons button {
            position: relative;
            min-width: 0;
            width: 100%;
            padding: 15px 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 5px;
            font-size: 1rem;
            font-weight: bold;
            line-height: 1.2;
        }
        
        .action-buttons button i {
            font-size: 2rem;
        }

        .controls {
            margin-top: 20px;
            display: flex;
            gap: 5px;
            justify-content: center;
            flex-wrap: wrap;
            padding: 0 5px;
        }
        
        .btn, .btn-export, .btn-clear {
            padding: 8px 12px;
            font-size: 0.9rem;
        }

        .btn-export {
            background: linear-gradient(145deg, #e3f2fd 0%, #bbdefb 100%);
            border: 3px solid #2c5aa0;
            color: #2c5aa0;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 15px rgba(44, 90, 160, 0.3), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }

        .btn-export:hover {
            border-color: #1e3d6f;
            background: linear-gradient(145deg, #bbdefb 0%, #90caf9 100%);
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 25px rgba(44, 90, 160, 0.5), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-clear {
            background: linear-gradient(145deg, #ffebee 0%, #ffcdd2 100%);
            border: 3px solid #dc3545;
            color: #dc3545;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 15px rgba(220, 53, 69, 0.3), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }

        .btn-clear:hover {
            border-color: #c82333;
            background: linear-gradient(145deg, #ffcdd2 0%, #ef9a9a 100%);
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 25px rgba(220, 53, 69, 0.5), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-upload {
            background: linear-gradient(145deg, #d4edda 0%, #a8d5ba 100%);
            border: 3px solid #28a745;
            color: #28a745;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 15px rgba(40, 167, 69, 0.3), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }

        .btn-upload:hover {
            border-color: #218838;
            background: linear-gradient(145deg, #c3e6cb 0%, #8fbc8f 100%);
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.5), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-rotate {
            background: #545480;
            border: 3px solid #3a3a5c;
            color: #ffffff;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 15px rgba(84, 84, 128, 0.3), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        
        .btn-rotate:hover {
            border-color: #2a2a4c;
            background: #6a6aa0;
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 25px rgba(84, 84, 128, 0.5), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
        }

        /* 背景パネルのスタイル */
        .background-controls {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            order: 6;
        }

        .background-controls h3 {
            margin-bottom: 15px;
            font-size: 1.1rem;
            color: #333;
        }

        .bg-color-section {
            margin-bottom: 15px;
        }

        .bg-color-palette {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .bg-color-btn {
            background: transparent;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 38px;
        }

        .bg-color-btn:hover {
            border-color: #007bff;
            transform: scale(1.05);
        }

        .bg-color-btn.active {
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }

        .bg-swatch {
            width: 30px;
            height: 22px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        .transparent-bg {
            background: linear-gradient(45deg, #ccc 25%, transparent 25%), 
                        linear-gradient(-45deg, #ccc 25%, transparent 25%), 
                        linear-gradient(45deg, transparent 75%, #ccc 75%), 
                        linear-gradient(-45deg, transparent 75%, #ccc 75%);
            background-size: 8px 8px;
            background-position: 0 0, 0 4px, 4px -4px, -4px 0px;
        }



        .btn-rotate:disabled {
            background: linear-gradient(145deg, #e0e0e0 0%, #d0d0d0 100%);
            border-color: #bdc3c7;
            color: #95a5a6;
            cursor: not-allowed;
            opacity: 0.6;
            transform: none;
            box-shadow: none;
        }

        .btn-rotate-left {
            background: linear-gradient(145deg, #fff9e6 0%, #ffe4b5 100%);
            border: 3px solid #FFA500;
            color: #FFA500;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 15px rgba(255, 165, 0, 0.3), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        
        .btn-rotate-left:hover {
            border-color: #FF8C00;
            background: linear-gradient(145deg, #fff5e6 0%, #ffd699 100%);
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 25px rgba(255, 165, 0, 0.5), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-rotate-left:disabled {
            background: linear-gradient(145deg, #e0e0e0 0%, #d0d0d0 100%);
            border-color: #bdc3c7;
            color: #95a5a6;
            cursor: not-allowed;
            opacity: 0.6;
            transform: none;
            box-shadow: none;
        }

        .btn-flip-horizontal {
            background: #8e44ad;
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
        }
        
        .btn-flip-horizontal:hover {
            background: #732d91;
            color: white;
        }

        .btn-flip-horizontal:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-flip-vertical {
            background: #9b59b6;
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
        }
        
        .btn-flip-vertical:hover {
            background: #8e44ad;
            color: white;
        }

        .btn-flip-vertical:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-scale-down {
            background: #545480;
            border: 3px solid #3a3a5c;
            color: #ffffff;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 15px rgba(84, 84, 128, 0.3), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        
        .btn-scale-down:hover {
            border-color: #2a2a4c;
            background: #6a6aa0;
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 25px rgba(84, 84, 128, 0.5), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-scale-down:disabled {
            background: linear-gradient(145deg, #e0e0e0 0%, #d0d0d0 100%);
            border-color: #bdc3c7;
            color: #95a5a6;
            cursor: not-allowed;
            opacity: 0.6;
            transform: none;
            box-shadow: none;
        }

        .btn-scale-up {
            background: #545480;
            border: 3px solid #3a3a5c;
            color: #ffffff;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 15px rgba(84, 84, 128, 0.3), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        
        .btn-scale-up:hover {
            border-color: #2a2a4c;
            background: #6a6aa0;
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 25px rgba(84, 84, 128, 0.5), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-scale-up:disabled {
            background: linear-gradient(145deg, #e0e0e0 0%, #d0d0d0 100%);
            border-color: #bdc3c7;
            color: #95a5a6;
            cursor: not-allowed;
            opacity: 0.6;
            transform: none;
            box-shadow: none;
        }

        .btn-bring-front {
            background: #3498db;
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
        }
        
        .btn-bring-front:hover {
            background: #2980b9;
            color: white;
        }

        .btn-bring-front:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-send-back {
            background: #95a5a6;
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
        }
        
        .btn-send-back:hover {
            background: #7f8c8d;
            color: white;
        }

        .btn-send-back:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-delete {
            background: #ffffbd;
            border: 3px solid #e0e060;
            color: #666666;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 15px rgba(224, 224, 96, 0.3), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        
        .btn-delete:hover {
            border-color: #d0d050;
            background: #ffffd0;
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 25px rgba(224, 224, 96, 0.5), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-delete:disabled {
            background: linear-gradient(145deg, #e0e0e0 0%, #d0d0d0 100%);
            border-color: #bdc3c7;
            color: #95a5a6;
            cursor: not-allowed;
            opacity: 0.6;
            transform: none;
            box-shadow: none;
        }

        .btn-spring-theme {
            background: #ffffbd;
            border: 3px solid #e0e060;
            color: #666666;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 15px rgba(224, 224, 96, 0.3), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        
        .btn-spring-theme:hover {
            border-color: #d0d050;
            background: #ffffd0;
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 25px rgba(224, 224, 96, 0.5), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-spring-theme:disabled {
            background: linear-gradient(145deg, #e0e0e0 0%, #d0d0d0 100%);
            border-color: #bdc3c7;
            color: #95a5a6;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-bg-color {
            background: #ffffbd;
            border: 3px solid #e0e060;
            color: #666666;
            border-radius: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 6px 15px rgba(224, 224, 96, 0.3), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            padding: 5px;
        }
        
        .btn-bg-color:hover {
            border-color: #d0d050;
            background: #ffffd0;
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 10px 25px rgba(224, 224, 96, 0.5), inset 0 -3px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-bg-color input[type="color"] {
            border-radius: 10px;
        }

        .btn-summer-theme {
            background: #3498db;
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
        }
        
        .btn-summer-theme:hover {
            background: #2980b9;
            color: white;
        }

        .btn-summer-theme:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-autumn-theme {
            background: #e67e22;
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
        }
        
        .btn-autumn-theme:hover {
            background: #d35400;
            color: white;
        }

        .btn-autumn-theme:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-winter-theme {
            background: #9b59b6;
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
        }
        
        .btn-winter-theme:hover {
            background: #8e44ad;
            color: white;
        }

        .btn-winter-theme:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-monochrome-theme {
            background: #34495e;
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
        }
        
        .btn-monochrome-theme:hover {
            background: #2c3e50;
            color: white;
        }

        .btn-monochrome-theme:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-sepia-theme {
            background: #d68910;
            border: none;
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
        }
        
        .btn-sepia-theme:hover {
            background: #b7740f;
            color: white;
        }

        .btn-sepia-theme:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* キャンバスエリアの基本スタイル */
        .canvas-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            position: relative;
            touch-action: manipulation;
            padding: 0;
        }

        #mainCanvas {
            width: 100%;
            height: auto;
            max-width: 500px;
            max-height: 500px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* PCでもスマホでも同じレイアウト - メディアクエリ不要 */
            .canvas-area,
            .manipulation-controls,
            .action-controls {
                padding: 15px;
            }
            
            .container {
                padding: 15px;
            }

        
        /* 検索スピナーアニメーション */
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .spin {
            animation: spin 1s linear infinite;
        }
        
        /* 検索結果のスタイリング */
        .material-category {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .material-tags {
            font-size: 0.75rem;
            color: #007bff;
            margin-top: 0.25rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* 素材がクリックされた時の視覚的フィードバック */
        .material-item.clicked {
            animation: clickEffect 0.3s ease;
        }

        @keyframes clickEffect {
            0% { transform: scale(1); }
            50% { transform: scale(0.95); }
            100% { transform: scale(1); }
        }

        /* レイヤー要素のタッチ制御 */
        .layer-element {
            touch-action: none; /* ドラッグ中はスクロールを無効 */
        }

        /* フッターのスタイル */
        .footer-custom {
            background-color: #fef9e7 !important;
            margin-top: 3rem;
        }

        /* フッター文字色の改善（コントラスト対応） */
        .footer-custom .footer-text {
            color: #1a1a1a !important;
        }

        .footer-custom .footer-text:hover {
            color: #000000 !important;
        }

        /* 作品アップロードモーダルのスタイル */
        .upload-preview-section {
            text-align: center;
        }
        
        .upload-preview-container {
            border: 2px dashed #28a745;
            border-radius: 12px;
            padding: 20px;
            background: #f8fff9;
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .upload-preview-container:hover {
            border-color: #218838;
            background: #f0fff0;
        }
        
        .upload-preview-container img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .preview-placeholder {
            text-align: center;
            color: #6c757d;
        }
        
        .preview-placeholder i {
            font-size: 3rem;
            margin-bottom: 10px;
            display: block;
        }
        
        .upload-file-info {
            background: #e8f5e8;
            border: 1px solid #c3e6c3;
            border-radius: 6px;
            padding: 10px;
        }
        
        .upload-progress {
            display: none;
            margin-top: 15px;
        }
        
        .upload-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-top: 15px;
        }
        
        .upload-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-top: 15px;
        }
        
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 0.5rem;
            }
            
            .modal-body .row {
                flex-direction: column-reverse;
            }
            
            .upload-preview-container {
                min-height: 150px;
                margin-top: 1rem;
            }
        }

        /* 一括色変更設定 */
        .bulk-color-settings {
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .bulk-color-settings .form-check-label {
            font-size: 0.9rem;
            color: #495057;
            cursor: pointer;
            font-weight: 500;
        }
        
        .bulk-color-settings .form-check-input {
            margin-top: 0.1rem;
        }



        /* ヘッダーをシンプルに */
        .header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        /* スムーススクロール */
        html {
            scroll-behavior: smooth;
            scroll-padding-top: 80px; /* ヘッダー分の余白 */
        }

        /* ストーリーセクション */
        .story-materials-section {
            background: linear-gradient(135deg, #fff8e1 0%, #ffe9c5 100%);
            border-radius: 16px;
            padding: 3rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .story-materials-section h2 {
            font-family: 'Hiragino Maru Gothic ProN', sans-serif;
            color: #d4a574;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .story-materials-section .text-muted {
            color: #a68b5b !important;
            font-size: 1rem;
        }

        .story-materials-list {
            max-width: 800px;
            margin: 0 auto;
        }

        .story-material-item {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 12px rgba(212, 165, 116, 0.12);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .story-material-item:last-child {
            margin-bottom: 0;
        }

        .story-material-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 24px rgba(212, 165, 116, 0.2);
        }

        .story-material-image-wrapper {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .story-material-image {
            display: inline-block;
            max-width: 300px;
            width: 100%;
        }

        .story-material-image img {
            width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .story-material-content {
            text-align: left;
        }

        .story-material-title {
            font-family: 'Hiragino Maru Gothic ProN', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .story-material-title a {
            color: #333;
            transition: color 0.2s ease;
        }

        .story-material-title a:hover {
            color: #d4a574;
        }

        .story-author {
            font-size: 0.9rem;
            color: #d47ca5;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .story-material-text {
            font-size: 1rem;
            line-height: 2;
            color: #555;
            font-family: 'Hiragino Maru Gothic ProN', sans-serif;
        }

        @media (max-width: 768px) {
            .story-materials-section {
                padding: 2rem 1rem;
            }

            .story-materials-section h2 {
                font-size: 1.6rem;
            }

            .story-material-item {
                padding: 1.5rem;
            }

            .story-material-title {
                font-size: 1.3rem;
            }
        }

        /* 背景に流れる素材のアニメーション（PC用） */
        .floating-materials-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
            overflow: hidden;
            /* iOS対策: ハードウェアアクセラレーションとスタッキングコンテキストの独立 */
            -webkit-transform: translate3d(0, 0, 0);
            transform: translate3d(0, 0, 0);
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            isolation: isolate;
        }

        .floating-material {
            position: absolute;
            opacity: 0.6;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
            pointer-events: auto;
            cursor: pointer;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .floating-material:hover {
            opacity: 0.85;
            transform: scale(1.1);
        }

        .floating-material:active {
            opacity: 1;
            transform: scale(1.05);
        }

        .floating-material.left-to-right {
            animation-name: floatLeftToRight;
        }

        .floating-material.right-to-left {
            animation-name: floatRightToLeft;
        }

        .floating-material img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            filter: brightness(1.1) saturate(0.8);
        }

        @keyframes floatLeftToRight {
            0% {
                transform: translateX(-300px) translateY(0);
            }
            100% {
                transform: translateX(calc(100vw + 300px)) translateY(30px);
            }
        }

        @keyframes floatRightToLeft {
            0% {
                transform: translateX(calc(100vw + 300px)) translateY(0);
            }
            100% {
                transform: translateX(-300px) translateY(30px);
            }
        }

        /* モバイルでは表示しない */
        @media (max-width: 768px) {
            .floating-materials-container {
                display: none;
            }
        }

        /* main-contentを前面に */
        .main-content {
            position: relative;
            z-index: 1;
            /* iOS対策: 確実に前面に表示 */
            -webkit-transform: translate3d(0, 0, 0);
            transform: translate3d(0, 0, 0);
            isolation: isolate;
        }
    </style>
    
    <?php include __DIR__ . '/../includes/analytics-script.php'; ?>
</head>
<body>
    <!-- PC用：背景に流れる素材 -->
    <div class="floating-materials-container" id="floatingMaterialsContainer"></div>
    
    <?php 
    $currentPage = 'kids';
    include '../includes/header-kids-nav.php'; 
    ?>

    <div class="container" style="margin-top: 2rem;">

        <!-- メインコンテンツ -->
        <div class="main-content">
            <!-- 素材選択エリア -->
            <div id="materials" class="materials-panel">
                <div class="materials-grid">
                    <?php foreach ($materials as $material): ?>
                        <div class="material-item" 
                             data-material-id="<?= htmlspecialchars($material['id']) ?>"
                             data-svg-path="<?= htmlspecialchars($material['svg_path']) ?>"
                             data-title="<?= htmlspecialchars($material['title']) ?>"
                             title="<?= htmlspecialchars($material['title']) ?>">
                            <img src="/<?= htmlspecialchars($material['webp_medium_path'] ?: $material['image_path']) ?>" 
                                 alt="<?= htmlspecialchars($material['title']) ?>"
                                 loading="lazy">
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- シャッフルボタン -->
                    <button class="material-item shuffle-btn" id="shuffleBtn" type="button" title="ほかのえをみる">
                        <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#f2b788" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-wand-sparkles-icon lucide-wand-sparkles"><path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72"/><path d="m14 7 3 3"/><path d="M5 6v4"/><path d="M19 14v4"/><path d="M10 2v2"/><path d="M7 8H3"/><path d="M21 16h-4"/><path d="M11 3H9"/></svg>
                    </button>
                </div>
            </div>

            <!-- キャンバスエリア -->
            <div class="canvas-area">
                <div class="canvas-container">
                    <svg id="mainCanvas" 
                         viewBox="0 0 1024 1024" 
                         xmlns="http://www.w3.org/2000/svg">
                        <!-- 背景 -->
                        <rect id="canvasBackground" 
                              x="0" y="0" 
                              width="1024" height="1024" 
                              fill="#a7d5e8"/>
                        <!-- レイヤーがここに追加されます -->
                    </svg>
                </div>
            </div>
                <!-- そうさボタンエリア -->
                <div class="manipulation-controls">
                <div class="manipulation-buttons">
                    <button id="scaleDownBtn" class="btn btn-scale-down" title="ちいさくする">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.15316 5.40838C10.4198 3.13613 11.0531 2 12 2C12.9469 2 13.5802 3.13612 14.8468 5.40837L15.1745 5.99623C15.5345 6.64193 15.7144 6.96479 15.9951 7.17781C16.2757 7.39083 16.6251 7.4699 17.3241 7.62805L17.9605 7.77203C20.4201 8.32856 21.65 8.60682 21.9426 9.54773C22.2352 10.4886 21.3968 11.4691 19.7199 13.4299L19.2861 13.9372C18.8096 14.4944 18.5713 14.773 18.4641 15.1177C18.357 15.4624 18.393 15.8341 18.465 16.5776L18.5306 17.2544C18.7841 19.8706 18.9109 21.1787 18.1449 21.7602C17.3788 22.3417 16.2273 21.8115 13.9243 20.7512L13.3285 20.4768C12.6741 20.1755 12.3469 20.0248 12 20.0248C11.6531 20.0248 11.3259 20.1755 10.6715 20.4768L10.0757 20.7512C7.77268 21.8115 6.62118 22.3417 5.85515 21.7602C5.08912 21.1787 5.21588 19.8706 5.4694 17.2544L5.53498 16.5776C5.60703 15.8341 5.64305 15.4624 5.53586 15.1177C5.42868 14.773 5.19043 14.4944 4.71392 13.9372L4.2801 13.4299C2.60325 11.4691 1.76482 10.4886 2.05742 9.54773C2.35002 8.60682 3.57986 8.32856 6.03954 7.77203L6.67589 7.62805C7.37485 7.4699 7.72433 7.39083 8.00494 7.17781C8.28555 6.96479 8.46553 6.64194 8.82547 5.99623L9.15316 5.40838Z" fill="#ffdc5c"></path></svg>
                    </button>
                    <button id="scaleUpBtn" class="btn btn-scale-up" title="おおきくする">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.15316 5.40838C10.4198 3.13613 11.0531 2 12 2C12.9469 2 13.5802 3.13612 14.8468 5.40837L15.1745 5.99623C15.5345 6.64193 15.7144 6.96479 15.9951 7.17781C16.2757 7.39083 16.6251 7.4699 17.3241 7.62805L17.9605 7.77203C20.4201 8.32856 21.65 8.60682 21.9426 9.54773C22.2352 10.4886 21.3968 11.4691 19.7199 13.4299L19.2861 13.9372C18.8096 14.4944 18.5713 14.773 18.4641 15.1177C18.357 15.4624 18.393 15.8341 18.465 16.5776L18.5306 17.2544C18.7841 19.8706 18.9109 21.1787 18.1449 21.7602C17.3788 22.3417 16.2273 21.8115 13.9243 20.7512L13.3285 20.4768C12.6741 20.1755 12.3469 20.0248 12 20.0248C11.6531 20.0248 11.3259 20.1755 10.6715 20.4768L10.0757 20.7512C7.77268 21.8115 6.62118 22.3417 5.85515 21.7602C5.08912 21.1787 5.21588 19.8706 5.4694 17.2544L5.53498 16.5776C5.60703 15.8341 5.64305 15.4624 5.53586 15.1177C5.42868 14.773 5.19043 14.4944 4.71392 13.9372L4.2801 13.4299C2.60325 11.4691 1.76482 10.4886 2.05742 9.54773C2.35002 8.60682 3.57986 8.32856 6.03954 7.77203L6.67589 7.62805C7.37485 7.4699 7.72433 7.39083 8.00494 7.17781C8.28555 6.96479 8.46553 6.64194 8.82547 5.99623L9.15316 5.40838Z" fill="#ffdc5c"></path></svg>
                    </button>
                    <button id="rotateBtn" class="btn btn-rotate" title="みぎにまわす">
                        <svg viewBox="-6.4 -6.4 76.80 76.80" xmlns="http://www.w3.org/2000/svg" fill="#000000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g id="a"></g> <g id="b"></g> <g id="c"></g> <g id="d"></g> <g id="e"></g> <g id="f"></g> <g id="g"></g> <g id="h"></g> <g id="i"></g> <g id="j"></g> <g id="k"></g> <g id="l"></g> <g id="m"></g> <g id="n"></g> <g id="o"></g> <g id="p"></g> <g id="q"></g> <g id="r"> <path d="M34.55,42.29l-1.77-5.61-3.73,.11v21.21c0,.55,.45,1,1,1h3.49c.55,0,1-.45,1-1v-15.71Z" fill="#9dacb9" id="s"></path> <path d="M27.44,18.97v9.13h9.12v-9.13h-9.12Z" fill="#f0f4f6" id="t"></path> <path d="M29.38,5.05c-.29-.07-.6-.07-.89,.02l-10.48,3.22c-.43,.13-.57,.68-.25,1l6.14,6.14,.68,4.34,4.48,.81h3.16l1.07-6.54-3.78-8.93s-.08-.05-.13-.07h0Z" fill="#fd91ba" id="u"></path> <path d="M29.51,5.12l2.71,15.47h2.73l4.59-1.5,.57-3.65L30.15,5.49c-.19-.17-.41-.29-.65-.37h0Z" fill="#ffa6c5" id="v"></path> <path d="M40.1,15.43l-5.16,5.15v3.16l7.95,.3,7.55-3.01c.11-.33,.11-.68,.01-1.01l-3.22-10.48c-.13-.43-.68-.57-1-.25l-6.14,6.14Z" fill="#0acffb" id="w"></path> <path d="M50.45,21.03l-15.5,2.71v2.73l1.62,3.45,3.54,1.71,9.95-9.95c.18-.18,.32-.41,.4-.65Z" fill="#62d9fa" id="x"></path> <path d="M40.1,31.63l-5.16-5.16h-3.16l-.94,6.89,3.71,8.92,11.44-3.51c.43-.13,.57-.68,.25-1l-6.14-6.14Z" fill="#fdda5c" id="y"></path> <path d="M34.55,42.29l-2.77-15.81h-2.73l-4.46,.44-.69,4.71,5.16,5.16,5.5,5.5Z" fill="#fce87b" id="a`"></path> <path d="M23.9,31.63l5.16-5.16v-3.16l-6.12-1.11-9.35,3.82c-.12,.33-.14,.68-.05,1.02l3.22,10.48c.13,.43,.68,.57,1,.25l6.14-6.14Z" fill="#6df4c0" id="aa"></path> <path d="M13.59,26.03l15.47-2.71v-2.73l-5.16-5.16-9.95,9.95c-.17,.19-.3,.41-.37,.65Z" fill="#9af6d3" id="ab"></path> <path d="M50.45,21.03l-15.5-.45v3.16l15.5-2.71Z" fill="#00bff8" id="ac"></path> <path d="M13.59,26.03l15.5,.45v-3.16l-15.5,2.71Z" fill="#3aedbc" id="ad"></path> <path d="M29.51,5.12l-.45,15.5h3.16l-2.71-15.5Z" fill="#fc76a8" id="ae"></path> <path d="M34.55,42.29l.39-15.81h-3.16l2.77,15.81Z" fill="#f8c228" id="af"></path> <path d="M51.42,19.73l-3.22-10.48c-.13-.41-.4-.75-.78-.95-.38-.2-.81-.24-1.22-.12-.25,.08-.48,.22-.66,.4l-5.43,5.43L30.86,4.78c-.7-.7-1.72-.95-2.67-.66h0l-10.48,3.22c-.41,.13-.75,.4-.95,.78-.2,.38-.24,.81-.12,1.22,.08,.25,.21,.47,.4,.66l5.43,5.43-9.24,9.24c-.7,.7-.95,1.72-.66,2.67l3.22,10.48c.13,.41,.4,.75,.78,.95,.38,.2,.81,.24,1.22,.12,.25-.08,.48-.22,.66-.4l5.43-5.43,4.16,4.16v20.79c0,1.1,.9,2,2,2h3.49c1.1,0,2-.9,2-2v-14.97l10.73-3.29c.41-.13,.75-.4,.95-.78,.2-.38,.24-.81,.12-1.22-.08-.25-.21-.47-.4-.66l-5.43-5.43,9.24-9.24c.7-.7,.95-1.72,.66-2.67Zm-4.94-9.26l2.99,9.72-13.52,2.37v-1.55l4.86-4.86h0l5.67-5.67Zm-13.43,9.12l-2.08-11.86,7.71,7.71-4.16,4.15h-1.48Zm.89,2v3.89h-3.89v-3.89h3.89Zm-15.01-12.54l9.72-2.99,2.37,13.52h-1.55l-4.86-4.86-5.67-5.67Zm4.97,7.79l4.16,4.16v1.48l-11.87,2.08,7.71-7.71Zm-6.38,19.75l-2.99-9.72,13.52-2.37v1.55l-4.86,4.86h0l-5.67,5.67Zm7.79-4.97l4.16-4.16h1.48l2.08,11.86-3.26-3.26h0l-4.45-4.45Zm4.74,26.36v-18.79l3.5,3.5v15.3h-3.5Zm15.01-19.99l-9.72,2.99-2.37-13.52h1.55l4.86,4.86h0l5.67,5.67Zm-4.97-7.79l-4.16-4.16v-1.48l11.87-2.08-7.71,7.71Z"></path> </g> <g id="ag"></g> <g id="ah"></g> <g id="ai"></g> <g id="aj"></g> <g id="ak"></g> <g id="al"></g> <g id="am"></g> <g id="an"></g> <g id="ao"></g> <g id="ap"></g> <g id="aq"></g> <g id="ar"></g> <g id="as"></g> <g id="at"></g> <g id="au"></g> <g id="av"></g> <g id="aw"></g> <g id="ax"></g> <g id="ay"></g> <g id="b`"></g> <g id="ba"></g> <g id="bb"></g> <g id="bc"></g> <g id="bd"></g> <g id="be"></g> <g id="bf"></g> <g id="bg"></g> <g id="bh"></g> <g id="bi"></g> <g id="bj"></g> <g id="bk"></g> <g id="bl"></g> </g></svg>
                    </button>
                    <button id="springThemeBtn" class="btn btn-spring-theme" title="いろをかえる">
                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 90%; height: 90%;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path fill-rule="evenodd" clip-rule="evenodd" d="M23 17C23 10.9249 18.0751 6 12 6C5.92487 6 1 10.9249 1 17H3C3 12.0294 7.02944 8 12 8C16.9706 8 21 12.0294 21 17H23Z" fill="#FF575B"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M21 17C21 12.0294 16.9706 8 12 8C7.02944 8 3 12.0294 3 17H5C5 13.134 8.13401 10 12 10C15.866 10 19 13.134 19 17H21Z" fill="#FABA2C"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M19 17C19 13.134 15.866 10 12 10C8.13401 10 5 13.134 5 17H7C7 14.2386 9.23858 12 12 12C14.7614 12 17 14.2386 17 17H19Z" fill="#7AC74D"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M17 17C17 14.2386 14.7614 12 12 12C9.23858 12 7 14.2386 7 17H9C9 15.3431 10.3431 14 12 14C13.6569 14 15 15.3431 15 17H17Z" fill="#00B0FF"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M15 17H13C13 16.4477 12.5523 16 12 16C11.4477 16 11 16.4477 11 17H9C9 15.3431 10.3431 14 12 14C13.6569 14 15 15.3431 15 17Z" fill="#B99FE4"></path> <path fill-rule="evenodd" clip-rule="evenodd" d="M13 17C13 16.4477 12.5523 16 12 16C11.4477 16 11 16.4477 11 17H13Z" fill="#E39BD1"></path> </g></svg>
                    </button>
                    <button id="bgColorBtn" class="btn btn-bg-color" title="はいけいのいろ">
                        <input type="color" id="customBgColor" value="#a7d5e8" style="width: 90%; height: 90%; border: none; background: none; cursor: pointer;">
                    </button>
                    <button id="deleteBtn" class="btn btn-delete" title="けす">
                        <svg viewBox="0 0 1024 1024" class="icon" version="1.1" xmlns="http://www.w3.org/2000/svg" fill="#000000" style="width: 90%; height: 90%;"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M512 454.4L270.933333 213.333333 554.666667 36.266667 817.066667 213.333333z" fill="#99caff"></path><path d="M512 454.4L270.933333 213.333333 362.666667 100.266667 817.066667 213.333333z" fill="#fbea79"></path><path d="M652.8 938.666667H371.2c-42.666667 0-78.933333-29.866667-85.333333-72.533334L192 234.666667h640l-96 631.466666c-6.4 42.666667-42.666667 72.533333-83.2 72.533334z" fill="#dbbf9e"></path><path d="M810.666667 277.333333H213.333333c-23.466667 0-42.666667-19.2-42.666666-42.666666s19.2-42.666667 42.666666-42.666667h597.333334c23.466667 0 42.666667 19.2 42.666666 42.666667s-19.2 42.666667-42.666666 42.666666z" fill="#9a794c"></path></g></svg>
                    </button>
                </div>
            </div>

            <!-- ダウンロード・とうこうボタン -->
            <div class="action-controls">
                <div class="action-buttons">
                    <button id="exportBtn" class="btn btn-export">
                        ダウンロード
                    </button>
                    <button id="uploadBtn" class="btn btn-upload">
                        みんなに<br />とどける
                    </button>
                    <button id="clearBtn" class="btn btn-clear">
                        ぜんぶけす
                    </button>
                </div>
            </div>

            <!-- 説明セクション -->
            <div class="info-section">
                <p><strong>とどける について</strong><br>
                みんなが よろこぶように、あなたの えから ちいさな おはなしを つくって、とどけます。とどいた えは、ほかの ひとが ダウンロードして つかう ことも できます。</p>
                
                <p><strong>ぜんぶ けす について</strong><br>
                いまの えを まっさらに して、さいしょから つくりなおせます。</p>
            </div>
        </div>
    </div>

    <script>
        console.log('シンプルSVG編集ツール開始');
        
        // 基本変数
        let layers = [];
        let nextLayerId = 1;
        let selectedLayerId = null;
        let isDragging = false;
        let dragStartPos = { x: 0, y: 0 };
        let dragStartTransform = { x: 0, y: 0 };
        let currentBackgroundColor = 'transparent'; // 現在の背景色
        
        // タッチデバイス判定
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;


        
        // 素材をキャンバス中央に追加
        function addMaterialToCanvas(elementOrData) {
            let materialId, svgPath, title;
            
            // DOM要素かデータオブジェクトかを判定
            if (elementOrData.dataset) {
                // DOM要素の場合
                materialId = elementOrData.dataset.materialId;
                svgPath = elementOrData.dataset.svgPath;
                title = elementOrData.dataset.title;
                
                // クリック効果
                elementOrData.classList.add('clicked');
                setTimeout(() => elementOrData.classList.remove('clicked'), 300);
            } else {
                // データオブジェクトの場合（APIから取得した素材）
                materialId = elementOrData.id;
                svgPath = elementOrData.svg_path;
                title = elementOrData.title;
            }

            console.log('素材追加:', title);

            // SVGファイルを読み込み
            const svgUrl = svgPath.startsWith('/') ? svgPath : '/' + svgPath;
            fetch(svgUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(svgText => {
                    const parser = new DOMParser();
                    const svgDoc = parser.parseFromString(svgText, 'image/svg+xml');
                    const svgElement = svgDoc.querySelector('svg');
                    
                    if (svgElement) {
                        // 一時的にSVG要素を作成してbounding boxを取得
                        const tempCanvas = document.getElementById('mainCanvas');
                        const tempGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                        tempGroup.innerHTML = svgElement.innerHTML;
                        tempCanvas.appendChild(tempGroup);
                        
                        const bbox = tempGroup.getBBox();
                        const centerX = bbox.x + bbox.width / 2;
                        const centerY = bbox.y + bbox.height / 2;
                        
                        // 一時的な要素を削除
                        tempCanvas.removeChild(tempGroup);

                        // レイヤーオブジェクトを作成
                        const layer = {
                            id: nextLayerId++,
                            materialId: materialId,
                            title: title,
                            svgContent: svgElement.innerHTML,
                            svgPath: svgPath,
                            originalCenter: { x: centerX, y: centerY }, // 元の中心点を保存
                            transform: {
                                x: 0, // 左上角（0,0）
                                y: 0, // 左上角（0,0）
                                scale: 0.7, // 70%サイズ
                                rotation: 0,
                                flipHorizontal: false, // 水平反転フラグ
                                flipVertical: false // 上下反転フラグ
                            },
                            visible: true
                        };

                        layers.push(layer);
                        renderLayer(layer);
                        console.log(`素材「${title}」をキャンバス中央に追加`);
                        
                        // ローカルストレージに保存
                        saveToLocalStorage();
                    } else {
                        throw new Error('SVG要素が見つかりません');
                    }
                })
                .catch(error => {
                    console.error('SVG読み込みエラー:', error);
                    alert(`素材の読み込みに失敗しました: ${error.message}`);
                });
        }

        // レイヤーをキャンバスに描画
        function renderLayer(layer) {
            const canvas = document.getElementById('mainCanvas');
            
            // 既存のレイヤー要素があれば削除
            const existingLayer = document.getElementById(`layer-${layer.id}`);
            if (existingLayer) {
                existingLayer.remove();
            }

            if (!layer.visible) return;

            // 新しいレイヤー要素を作成
            const layerGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            layerGroup.id = `layer-${layer.id}`;
            layerGroup.classList.add('layer-element');
            layerGroup.setAttribute('data-material-id', layer.materialId);
            layerGroup.innerHTML = layer.svgContent;

            // スケール変換後の実際の中心点を計算
            // rotate の中心座標は、scale変換前の座標系で指定する必要がある
            const centerX = layer.originalCenter.x;
            const centerY = layer.originalCenter.y;
            
            // 変換を適用: 移動→スケール→反転→中心回転
            let scaleX = layer.transform.scale;
            let scaleY = layer.transform.scale;
            
            // 選択状態の判定（スタイル適用用）
            const isSelected = (selectedLayerId === layer.id);
            
            // 水平反転の場合はscaleXを負にする
            if (layer.transform.flipHorizontal) {
                scaleX = -scaleX;
            }
            
            // 上下反転の場合はscaleYを負にする
            if (layer.transform.flipVertical) {
                scaleY = -scaleY;
            }
            
            // 統一された処理: すべて同じ順序で処理
            const transformString = `translate(${layer.transform.x}, ${layer.transform.y}) scale(${scaleX}, ${scaleY}) rotate(${layer.transform.rotation}, ${centerX}, ${centerY})`;
            layerGroup.setAttribute('transform', transformString);

            // layersの配列順に基づいて正しい位置に挿入
            const layerIndex = layers.findIndex(l => l.id === layer.id);
            const existingLayers = canvas.querySelectorAll('.layer-element');
            
            if (layerIndex === 0) {
                // 最初のレイヤーの場合、backgroundの後に挿入
                const background = canvas.getElementById('canvasBackground');
                canvas.insertBefore(layerGroup, background.nextSibling);
            } else if (layerIndex < existingLayers.length) {
                // 指定されたインデックスの位置に挿入
                let insertBefore = null;
                for (let i = layerIndex; i < layers.length; i++) {
                    const nextLayerElement = canvas.getElementById(`layer-${layers[i].id}`);
                    if (nextLayerElement) {
                        insertBefore = nextLayerElement;
                        break;
                    }
                }
                if (insertBefore) {
                    canvas.insertBefore(layerGroup, insertBefore);
                } else {
                    canvas.appendChild(layerGroup);
                }
            } else {
                // 最後のレイヤーの場合
                canvas.appendChild(layerGroup);
            }

            // ダブルクリックで前面に移動
            layerGroup.addEventListener('dblclick', function(e) {
                e.stopPropagation();
                bringLayerToFrontById(layer.id);
            });

            // ダブルタップで前面に移動（スマホ対応）
            let lastTap = 0;
            layerGroup.addEventListener('touchend', function(e) {
                const currentTime = new Date().getTime();
                const tapLength = currentTime - lastTap;
                if (tapLength < 300 && tapLength > 0) {
                    // ダブルタップ検出
                    e.preventDefault();
                    e.stopPropagation();
                    bringLayerToFrontById(layer.id);
                }
                lastTap = currentTime;
            });

            // マウスドラッグイベントを追加
            layerGroup.addEventListener('mousedown', function(e) {
                // タッチイベントから生成されたマウスイベントは無視
                if (e.sourceCapabilities && e.sourceCapabilities.firesTouchEvents) {
                    console.log(`mousedown ignored (from touch) on layer ${layer.id}`);
                    return;
                }
                e.stopPropagation();
                console.log(`mousedown on layer ${layer.id}`);
                prepareDrag(e, layer.id);
            });

            // タッチイベントを追加（スマホ対応）
            layerGroup.addEventListener('touchstart', function(e) {
                e.stopPropagation();
                e.preventDefault();
                console.log(`touchstart on layer ${layer.id}`);
                prepareDrag(e.touches[0], layer.id);
            }, { passive: false });

            // 選択状態に応じてカーソルのみ変更
            if (isSelected) {
                layerGroup.style.cursor = 'move';
            } else {
                layerGroup.style.cursor = 'pointer';
            }
            
            // SVG線形品質の属性を自動設定
            ensureSVGLineQuality(layerGroup);
            
            // 元の色情報をdata属性として保存（初回のみ）
            initializeOriginalColors(layerGroup);
            
            console.log(`Layer ${layer.id} rendered: ${transformString} (center: ${centerX}, ${centerY})`);
        }

        // レイヤー選択機能
        function selectLayer(layerId) {
            const previousSelectedId = selectedLayerId;
            selectedLayerId = layerId;
            console.log(`Layer ${layerId} selected - selectedLayerId is now: ${selectedLayerId}`);
            
            // 選択中の素材タイトルを更新
            updateSelectedLayerTitle();
            
            // 前回選択されていたレイヤーのスタイルをリセット
            if (previousSelectedId !== null && previousSelectedId !== layerId) {
                updateLayerSelectionStyle(previousSelectedId, false);
            }
            
            // 新しく選択されたレイヤーのスタイルを更新
            updateLayerSelectionStyle(layerId, true);

            // ボタンの状態を更新
            updateRotateButtonState();
            updateScaleDownButtonState();
            updateScaleUpButtonState();
            updateDeleteButtonState();
            updateSeasonalThemeButtonState();
            
            console.log(`Selection complete - selectedLayerId: ${selectedLayerId}`);
        }

        // レイヤーの選択状態スタイルを更新（再描画せずに）
        function updateLayerSelectionStyle(layerId, isSelected) {
            const layer = layers.find(l => l.id === layerId);
            if (!layer) return;
            
            const layerGroup = document.getElementById(`layer-${layerId}`);
            if (!layerGroup) return;
            
            const centerX = layer.originalCenter.x;
            const centerY = layer.originalCenter.y;
            
            // スケール計算
            let scaleX = layer.transform.scale;
            let scaleY = layer.transform.scale;
            
            // 水平反転・垂直反転を適用
            if (layer.transform.flipHorizontal) scaleX = -scaleX;
            if (layer.transform.flipVertical) scaleY = -scaleY;
            
            // transform属性を更新
            const transformString = `translate(${layer.transform.x}, ${layer.transform.y}) scale(${scaleX}, ${scaleY}) rotate(${layer.transform.rotation}, ${centerX}, ${centerY})`;
            layerGroup.setAttribute('transform', transformString);
            
            // スタイルを更新
            if (isSelected) {
                layerGroup.style.cursor = 'move';
                layerGroup.style.filter = 'drop-shadow(0 0 8px rgba(255, 215, 0, 0.9)) drop-shadow(0 0 15px rgba(255, 215, 0, 0.6))';
                console.log(`Layer ${layerId} selection style applied - golden glow`);
            } else {
                layerGroup.style.cursor = 'pointer';
                layerGroup.style.filter = '';
                console.log(`Layer ${layerId} selection style removed`);
            }
        }

        // レイヤーの選択を解除
        function deselectLayer() {
            if (selectedLayerId !== null) {
                const prevSelected = selectedLayerId;
                selectedLayerId = null;
                console.log(`Layer ${prevSelected} deselected`);
                
                // 全レイヤーを再描画して選択状態を反映
                layers.forEach(layer => {
                    renderLayer(layer);
                });

                // 選択中の素材タイトルを更新
                updateSelectedLayerTitle();

                // ボタンの状態を更新
                updateRotateButtonState();
                updateScaleDownButtonState();
                updateScaleUpButtonState();
                updateDeleteButtonState();
                updateSeasonalThemeButtonState();
            }
        }

        // ドラッグ準備（マウスdown時）
        let dragPreparationLayerId = null;
        let dragPreparationStartPos = { x: 0, y: 0 };
        
        function prepareDrag(e, layerId) {
            console.log(`prepareDrag called for layer ${layerId}`);
            dragPreparationLayerId = layerId;
            
            const canvas = document.getElementById('mainCanvas');
            const rect = canvas.getBoundingClientRect();
            
            // 座標を取得（マウス・タッチ・ポインターイベント対応）
            let clientX, clientY;
            if (e.touches && e.touches.length > 0) {
                // タッチイベント
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            } else {
                // マウスイベント・ポインターイベント
                clientX = e.clientX;
                clientY = e.clientY;
            }
            
            const svgPoint = screenToSVG(clientX - rect.left, clientY - rect.top, canvas);
            
            dragPreparationStartPos.x = svgPoint.x;
            dragPreparationStartPos.y = svgPoint.y;
            
            console.log(`Drag preparation complete - dragPreparationLayerId: ${dragPreparationLayerId}, pos:`, svgPoint);
        }
        
        // ドラッグ開始（実際にマウスが動いた時）
        function startDrag(layerId, currentPos) {
            console.log(`startDrag called for layer ${layerId}, selectedLayerId: ${selectedLayerId}`);
            
            if (selectedLayerId !== layerId) {
                console.log(`Selecting layer ${layerId}`);
                selectLayer(layerId);
            }
            
            isDragging = true;
            console.log(`isDragging set to true`);
            
            dragStartPos.x = dragPreparationStartPos.x;
            dragStartPos.y = dragPreparationStartPos.y;
            
            const layer = layers.find(l => l.id === layerId);
            if (layer) {
                dragStartTransform.x = layer.transform.x;
                dragStartTransform.y = layer.transform.y;
                console.log(`Drag start transform: x=${layer.transform.x}, y=${layer.transform.y}`);
            } else {
                console.error(`Layer ${layerId} not found!`);
            }
            
            console.log(`Drag actually started for layer ${layerId}`);
        }

        // 画面座標をSVG座標に変換
        function screenToSVG(screenX, screenY, svgElement) {
            const pt = svgElement.createSVGPoint();
            pt.x = screenX;
            pt.y = screenY;
            const svgP = pt.matrixTransform(svgElement.getScreenCTM().inverse());
            return { x: svgP.x, y: svgP.y };
        }

        // ドラッグ中の処理（マウス・タッチ・ポインター対応）
        function onDrag(e) {
            // ドラッグ準備中の場合、マウスが動いたら実際のドラッグを開始
            if (dragPreparationLayerId !== null && !isDragging) {
                console.log(`onDrag - checking if should start drag, dragPreparationLayerId: ${dragPreparationLayerId}`);
                const canvas = document.getElementById('mainCanvas');
                const rect = canvas.getBoundingClientRect();
                
                let clientX, clientY;
                if (e.touches && e.touches.length > 0) {
                    clientX = e.touches[0].clientX;
                    clientY = e.touches[0].clientY;
                } else {
                    clientX = e.clientX;
                    clientY = e.clientY;
                }
                
                const svgPoint = screenToSVG(clientX - rect.left, clientY - rect.top, canvas);
                
                // 一定距離以上動いたらドラッグ開始（クリックとの区別のため）
                const distance = Math.sqrt(
                    Math.pow(svgPoint.x - dragPreparationStartPos.x, 2) +
                    Math.pow(svgPoint.y - dragPreparationStartPos.y, 2)
                );
                
                console.log(`Distance moved: ${distance}px`);
                
                if (distance > 2) { // 2px以上動いたらドラッグとみなす
                    console.log(`Starting drag for layer ${dragPreparationLayerId}`);
                    startDrag(dragPreparationLayerId, svgPoint);
                    dragPreparationLayerId = null;
                }
            }
            
            if (!isDragging || selectedLayerId === null) {
                if (!isDragging) console.log('onDrag - not dragging');
                if (selectedLayerId === null) console.log('onDrag - no layer selected');
                return;
            }
            
            console.log(`onDrag - dragging layer ${selectedLayerId}`);
            
            const canvas = document.getElementById('mainCanvas');
            const rect = canvas.getBoundingClientRect();
            
            // 座標を取得（マウス・タッチ・ポインターイベント対応）
            let clientX, clientY;
            if (e.touches && e.touches.length > 0) {
                // タッチイベント
                clientX = e.touches[0].clientX;
                clientY = e.touches[0].clientY;
            } else {
                // マウスイベント・ポインターイベント
                clientX = e.clientX;
                clientY = e.clientY;
            }
            
            const svgPoint = screenToSVG(clientX - rect.left, clientY - rect.top, canvas);
            
            const deltaX = svgPoint.x - dragStartPos.x;
            const deltaY = svgPoint.y - dragStartPos.y;
            
            const layer = layers.find(l => l.id === selectedLayerId);
            if (layer) {
                layer.transform.x = dragStartTransform.x + deltaX;
                layer.transform.y = dragStartTransform.y + deltaY;
                renderLayer(layer);
            }
        }

        // ドラッグ終了
        function endDrag() {
            if (isDragging) {
                isDragging = false;
                console.log(`Drag ended for layer ${selectedLayerId}`);
                
                // ローカルストレージに保存
                saveToLocalStorage();
            } else if (dragPreparationLayerId !== null) {
                // ドラッグが発生しなかった場合（クリックのみ）、レイヤーを選択
                console.log(`Click detected on layer ${dragPreparationLayerId} - selecting`);
                selectLayer(dragPreparationLayerId);
            }
            
            // ドラッグ準備もリセット
            dragPreparationLayerId = null;
        }

        // 選択されたレイヤーを15度右回転
        function rotateSelectedLayer() {
            if (selectedLayerId === null) {
                alert('まずえをえらんでね！');
                return;
            }

            const layer = layers.find(l => l.id === selectedLayerId);
            if (layer) {
                layer.transform.rotation += 15;
                // 360度を超えた場合は0度に戻す
                if (layer.transform.rotation >= 360) {
                    layer.transform.rotation -= 360;
                }
                
                // 現在の選択IDを保存
                const currentSelectedId = selectedLayerId;
                
                // 全レイヤーを再描画（選択状態を保持）
                layers.forEach(l => {
                    renderLayer(l);
                });
                
                // 選択中の素材タイトルを更新
                updateSelectedLayerTitle();
                
                console.log(`Layer ${currentSelectedId} rotated right to ${layer.transform.rotation} degrees`);
                
                // ローカルストレージに保存
                saveToLocalStorage();
            }
        }

        // 選択されたレイヤーを20%縮小
        function scaleDownSelectedLayer() {
            if (selectedLayerId === null) {
                alert('まずえをえらんでね！');
                return;
            }

            const layer = layers.find(l => l.id === selectedLayerId);
            if (layer) {
                // 20%縮小（現在のサイズの80%にする）
                layer.transform.scale *= 0.8;
                
                // 最小サイズ制限（10%まで）
                if (layer.transform.scale < 0.1) {
                    layer.transform.scale = 0.1;
                    alert('もうこれいじょうちいさくできないよ！');
                    return;
                }
                
                // 現在の選択IDを保存
                const currentSelectedId = selectedLayerId;
                
                // 全レイヤーを再描画（選択状態を保持）
                layers.forEach(l => {
                    renderLayer(l);
                });
                
                // ボタンの状態を更新（スケール変更後に制限チェック）
                updateScaleDownButtonState();
                updateScaleUpButtonState();
                
                // 選択中の素材タイトルを更新
                updateSelectedLayerTitle();
                
                console.log(`Layer ${currentSelectedId} scaled down to ${(layer.transform.scale * 100).toFixed(1)}%`);
                
                // ローカルストレージに保存
                saveToLocalStorage();
            }
        }

        // 選択されたレイヤーを25%拡大
        function scaleUpSelectedLayer() {
            console.log(`scaleUpSelectedLayer called: selectedLayerId = ${selectedLayerId}`);
            
            if (selectedLayerId === null) {
                console.log('selectedLayerId is null - showing alert');
                alert('まずえをえらんでね！');
                return;
            }

            const layer = layers.find(l => l.id === selectedLayerId);
            if (layer) {
                // 25%拡大（現在のサイズの125%にする）
                layer.transform.scale *= 1.25;
                
                // 最大サイズ制限（500%まで）
                if (layer.transform.scale > 5.0) {
                    layer.transform.scale = 5.0;
                    alert('もうこれいじょうおおきくできないよ！');
                    return;
                }
                
                // 現在の選択IDを保存
                const currentSelectedId = selectedLayerId;
                
                // 全レイヤーを再描画（選択状態を保持）
                layers.forEach(l => {
                    renderLayer(l);
                });
                
                // ボタンの状態を更新（スケール変更後に制限チェック）
                updateScaleDownButtonState();
                updateScaleUpButtonState();
                
                // 選択中の素材タイトルを更新
                updateSelectedLayerTitle();
                
                console.log(`Layer ${currentSelectedId} scaled up to ${(layer.transform.scale * 100).toFixed(1)}%`);
                
                // ローカルストレージに保存
                saveToLocalStorage();
            }
        }

        // 指定されたレイヤーを1段だけ前面に移動（ダブルクリック/タップ用）
        function bringLayerToFrontById(layerId) {
            // レイヤーのインデックスを取得
            const currentIndex = layers.findIndex(l => l.id === layerId);
            if (currentIndex === -1 || currentIndex === layers.length - 1) {
                // レイヤーが見つからないか、既に最前面の場合
                return;
            }

            // レイヤーを1つ前に移動（配列の後ろが前面）
            const layer = layers[currentIndex];
            layers.splice(currentIndex, 1);
            layers.splice(currentIndex + 1, 0, layer);

            // 全レイヤーを再描画（Z-order更新のため）
            layers.forEach(l => {
                renderLayer(l);
            });
            
            // レイヤーを選択
            selectLayer(layerId);
            
            // 選択中の素材タイトルを更新
            updateSelectedLayerTitle();
            
            console.log(`Layer ${layerId} moved up one level (double-click/tap)`);
            
            // ローカルストレージに保存
            saveToLocalStorage();
        }

        // 選択されたレイヤーを1つ前面に移動
        function bringLayerToFront() {
            if (selectedLayerId === null) {
                alert('まずえをえらんでね！');
                return;
            }

            // 現在のレイヤーのインデックスを取得
            const currentIndex = layers.findIndex(l => l.id === selectedLayerId);
            if (currentIndex === -1 || currentIndex === layers.length - 1) {
                // レイヤーが見つからないか、既に最前面の場合
                return;
            }

            // レイヤーを1つ前に移動（配列の後ろが前面）
            const layer = layers[currentIndex];
            layers.splice(currentIndex, 1);
            layers.splice(currentIndex + 1, 0, layer);

            // 現在の選択IDを保存
            const currentSelectedId = selectedLayerId;
            
            // 全レイヤーを再描画（Z-order更新のため）
            layers.forEach(l => {
                renderLayer(l);
            });
            
            // レイヤー移動ボタンの状態を更新
            
            // 選択中の素材タイトルを更新
            updateSelectedLayerTitle();
            
            console.log(`Layer ${currentSelectedId} moved to front (index: ${currentIndex + 1})`);
            
            // ローカルストレージに保存
            saveToLocalStorage();
        }

        // 選択されたレイヤーを1つ背面に移動
        function sendLayerToBack() {
            if (selectedLayerId === null) {
                alert('まずえをえらんでね！');
                return;
            }

            // 現在のレイヤーのインデックスを取得
            const currentIndex = layers.findIndex(l => l.id === selectedLayerId);
            if (currentIndex === -1 || currentIndex === 0) {
                // レイヤーが見つからないか、既に最背面の場合
                return;
            }

            // レイヤーを1つ後ろに移動（配列の前が背面）
            const layer = layers[currentIndex];
            layers.splice(currentIndex, 1);
            layers.splice(currentIndex - 1, 0, layer);

            // 現在の選択IDを保存
            const currentSelectedId = selectedLayerId;
            
            // 全レイヤーを再描画（Z-order更新のため）
            layers.forEach(l => {
                renderLayer(l);
            });
            
            // レイヤー移動ボタンの状態を更新
            
            // 選択中の素材タイトルを更新
            updateSelectedLayerTitle();
            
            console.log(`Layer ${currentSelectedId} moved to back (index: ${currentIndex - 1})`);
            
            // ローカルストレージに保存
            saveToLocalStorage();
        }

        // 選択されたレイヤーを削除
        function deleteSelectedLayer() {
            if (selectedLayerId === null) {
                alert('まずえをえらんでね！');
                return;
            }

            // 現在のレイヤーのインデックスを取得
            const currentIndex = layers.findIndex(l => l.id === selectedLayerId);
            if (currentIndex === -1) {
                return;
            }

            // DOM要素を削除
            const layerElement = document.getElementById(`layer-${selectedLayerId}`);
            if (layerElement) {
                layerElement.remove();
            }

            // レイヤー配列から削除
            layers.splice(currentIndex, 1);

            // 選択状態をリセット
            selectedLayerId = null;
            
            // ボタンの状態を更新
            updateRotateButtonState();
            updateScaleDownButtonState();
            updateScaleUpButtonState();
            updateSelectedLayerTitle();
            updateDeleteButtonState();
            
            console.log(`Layer deleted (was at index: ${currentIndex})`);
            
            // ローカルストレージに保存
            saveToLocalStorage();
        }

        // 季節テーマを適用（選択されているレイヤーの色をランダムに変更）
        function applySeasonalTheme(season) {
            console.log(`=== applySeasonalTheme START (${season}) ===`);
            console.log('selectedLayerId:', selectedLayerId);
            
            // レイヤー未選択時は全レイヤーに適用
            if (!selectedLayerId) {
                console.log('No layer selected: applying to all layers');
                if (layers.length === 0) {
                    alert('さいしょにえをえらんでね！');
                    return;
                }
                applyThemeToAllLayers(season);
                
                // ユーザーに通知
                const seasonNames = {
                    spring: '春のやわらかパステル',
                    summer: '夏のやわらかパステル', 
                    autumn: '秋のやわらかパステル',
                    winter: '冬のやわらかパステル',
                    monochrome: 'やさしいモノクロ',
                    sepia: 'やさしいセピア'
                };
                
                const message = `${seasonNames[season]}を全レイヤーに適用しました！`;
                console.log(message);
                
                return;
            }
            
            // レイヤーが選択されている場合：選択レイヤーのみに適用
            
            // 季節テーマのカラーパレット定義（detail/index.phpと同じ）
            const seasonalPalettes = {
                spring: {
                    name: '春のやわらかパステル',
                    colors: [
                        '#F8CFCF', // 桜ピンク
                        '#FFF2B7', // レモンイエロー
                        '#D7EED1', // 若草グリーン
                        '#D7E8F8', // 空のあお
                        '#F3CFE8', // すみれピンク
                        '#F5E2C8', // クリームベージュ
                        '#D9EDD8', // 新芽グリーン
                        '#F6DCC3', // 木漏れ日ベージュ
                        '#F4C6D0', // 花びらピンク
                        '#FAF3E7'  // 春霞ホワイト
                    ]
                },
                summer: {
                    name: '夏のやわらかパステル',
                    colors: [
                        '#BDE7F7', // 空と海
                        '#FFF3A4', // ひまわりイエロー
                        '#C9F3E1', // ミント
                        '#D3EBFF', // そよ風ブルー
                        '#F7D5E8', // ラムネピンク
                        '#CFEAD2', // 木陰グリーン
                        '#FAEFD8', // 白砂ベージュ
                        '#FFF9D6', // ひだまり
                        '#BCE1F2', // 水しぶき
                        '#E3F2FA'  // 朝の空
                    ]
                },
                autumn: {
                    name: '秋のやわらかパステル',
                    colors: [
                        '#F7D6B3', // 木の実ベージュ
                        '#FFD9A6', // くりオレンジ
                        '#F2E0B9', // こがねいろ
                        '#E7D5C1', // ベージュグレー
                        '#E7B9A4', // 紅茶ピンク
                        '#FAE9D2', // ミルクキャラメル
                        '#E1C4A7', // ほうじ茶
                        '#F5CDBB', // 秋風オレンジ
                        '#EBC7A4', // 焼きたてパン
                        '#F8E5D6'  // 秋霧ホワイト
                    ]
                },
                winter: {
                    name: '冬のやわらかパステル',
                    colors: [
                        '#E5EEF5', // 雪の青
                        '#DAD7F0', // ラベンダー
                        '#F1ECE6', // ミルクホワイト
                        '#D3DDE0', // 氷の灰
                        '#CBDDE1', // 冬の空
                        '#F2EFE9', // 白銀
                        '#E1DBD5', // ウールグレー
                        '#D9E2EA', // 凍てつく風
                        '#E8E1DC', // ココアベージュ
                        '#F4F3F1'  // 冬霞
                    ]
                },
                monochrome: {
                    name: 'やさしいモノクロ',
                    colors: [
                        '#FFFFFF', // 白
                        '#FAFAFA', // オフホワイト
                        '#F3F3F3', // ライトグレー
                        '#E6E6E6', // ソフトグレー
                        '#D8D8D8', // グレージュ
                        '#C8C8C8', // ミディアムグレー
                        '#B0B0B0', // 穏やかグレー
                        '#999999', // まろやかグレー
                        '#7F7F7F', // スモーキーグレー
                        '#666666'  // 最暗トーン（真っ黒は使わない）
                    ]
                },
                sepia: {
                    name: 'やさしいセピア',
                    colors: [
                        '#FFFDF8', // ほぼ白
                        '#FBF4EA', // クリーム
                        '#F6EBDC', // ベージュ
                        '#F0E2CF', // ミルクティー
                        '#E8D7BD', // カフェオレ
                        '#E0C9A6', // ハニーベージュ
                        '#D1B68D', // キャメル
                        '#C19D72', // ミルクコーヒー
                        '#AD8C63', // モカ
                        '#937550'  // やわらかブラウン止まり（黒系なし）
                    ]
                }
            };

            if (!seasonalPalettes[season]) {
                console.error(`Unknown season: ${season}`);
                return;
            }

            const palette = seasonalPalettes[season];
            const themeColors = palette.colors;
            
            const layer = layers.find(l => l.id === selectedLayerId);
            console.log('layer found:', layer);
            if (!layer) {
                alert('選択されたレイヤーが見つかりません');
                return;
            }

            const layerElement = document.getElementById(`layer-${layer.id}`);
            console.log('layerElement:', layerElement);
            if (!layerElement) {
                alert('レイヤー要素が見つかりません');
                return;
            }

            // SVG要素を取得（compose/index.phpと同じ方式）
            const svgElements = layerElement.querySelectorAll('path, circle, rect, polygon, ellipse');
            console.log('svgElements count:', svgElements.length);
            
            let colorChangedCount = 0;
            const excludeGrayBlack = true; // 黒・グレー系を除外
            
            // 元の色とテーマ色のマッピングを作成（毎回ランダムに配置）
            const colorMapping = new Map();
            const shuffledColors = [...themeColors]; // 配列をコピー
            
            // Fisher-Yates アルゴリズムで配列をシャッフル
            for (let i = shuffledColors.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [shuffledColors[i], shuffledColors[j]] = [shuffledColors[j], shuffledColors[i]];
            }
            
            let colorIndex = 0;

            svgElements.forEach((element, index) => {
                console.log(`Processing element ${index}:`, element);
                
                // 初回のみ元の色情報をdata属性として保存
                const fillAttr = element.getAttribute('fill');
                const strokeAttr = element.getAttribute('stroke');
                
                if (fillAttr && !element.getAttribute('data-original-fill')) {
                    element.setAttribute('data-original-fill', fillAttr);
                }
                if (strokeAttr && strokeAttr !== 'none' && !element.getAttribute('data-original-stroke')) {
                    element.setAttribute('data-original-stroke', strokeAttr);
                }
                
                // style属性からも色情報を保存
                const styleAttr = element.getAttribute('style');
                if (styleAttr) {
                    const fillMatch = styleAttr.match(/fill:\s*([^;]+)/);
                    const strokeMatch = styleAttr.match(/stroke:\s*([^;]+)/);
                    
                    if (fillMatch && !element.getAttribute('data-original-style-fill')) {
                        element.setAttribute('data-original-style-fill', fillMatch[1].trim());
                    }
                    if (strokeMatch && strokeMatch[1].trim() !== 'none' && !element.getAttribute('data-original-style-stroke')) {
                        element.setAttribute('data-original-style-stroke', strokeMatch[1].trim());
                    }
                }
                
                // 元の色情報を取得（data-original-*属性から）
                let originalColor = element.getAttribute('data-original-fill') || 
                                  element.getAttribute('data-original-style-fill');
                
                if (!originalColor) {
                    originalColor = '#000000'; // fallback
                }
                
                console.log('Original color:', originalColor);
                
                // noneの場合は特別処理（fillはnone保持、strokeにテーマカラー適用）
                if (originalColor === 'none') {
                    console.log('Processing none fill element - applying theme to stroke only');
                    
                    // strokeの元の色を取得
                    let strokeOriginalColor = element.getAttribute('data-original-stroke') || 
                                            element.getAttribute('data-original-style-stroke');
                    
                    if (strokeOriginalColor && strokeOriginalColor !== 'none') {
                        // strokeにテーマカラーを適用
                        if (!colorMapping.has(strokeOriginalColor)) {
                            const newThemeColor = shuffledColors[colorIndex % shuffledColors.length];
                            colorMapping.set(strokeOriginalColor, newThemeColor);
                            console.log(`Stroke mapping: ${strokeOriginalColor} -> ${newThemeColor}`);
                            colorIndex++;
                        }
                        
                        const newStrokeColor = colorMapping.get(strokeOriginalColor);
                        
                        // stroke属性の更新
                        if (element.getAttribute('data-original-stroke')) {
                            element.setAttribute('stroke', newStrokeColor);
                        }
                        
                        // style属性のstrokeを更新、fillはnoneのまま保持
                        const styleAttr = element.getAttribute('style');
                        if (styleAttr && element.getAttribute('data-original-style-stroke')) {
                            let newStyle = styleAttr.replace(/stroke\s*:\s*[^;]+/, `stroke: ${newStrokeColor}`);
                            // fillがnoneでない場合はnoneに設定
                            if (!newStyle.includes('fill:none')) {
                                newStyle = newStyle.replace(/fill\s*:\s*[^;]+/, 'fill: none');
                            }
                            element.setAttribute('style', newStyle);
                        }
                    }
                    return; 
                }
                
                // 黒・グレー除外チェック
                if (excludeGrayBlack && (originalColor.includes('#000') || 
                    originalColor.includes('gray') || originalColor.includes('grey'))) {
                    console.log('Skipping gray/black color:', originalColor);
                    return; // 変更しない
                }

                // 同じ元の色のオブジェクトには同じランダムテーマ色を適用
                if (!colorMapping.has(originalColor)) {
                    const newThemeColor = shuffledColors[colorIndex % shuffledColors.length];
                    colorMapping.set(originalColor, newThemeColor);
                    console.log(`Mapping: ${originalColor} -> ${newThemeColor}`);
                    colorIndex++;
                }

                const newColor = colorMapping.get(originalColor);
                console.log('Setting new color:', newColor);
                
                // fill属性の更新（data-original-fillがある場合のみ）
                if (element.getAttribute('data-original-fill')) {
                    element.setAttribute('fill', newColor);
                    colorChangedCount++;
                }
                
                // stroke属性の更新（data-original-strokeがある場合のみ）
                if (element.getAttribute('data-original-stroke')) {
                    element.setAttribute('stroke', newColor);
                    console.log('Also set stroke:', newColor);
                }
                
                // style属性の更新
                if (styleAttr) {
                    let newStyle = styleAttr;
                    let styleChanged = false;
                    
                    if (element.getAttribute('data-original-style-fill')) {
                        newStyle = newStyle.replace(/fill\s*:\s*[^;]+/, `fill: ${newColor}`);
                        styleChanged = true;
                    }
                    
                    if (element.getAttribute('data-original-style-stroke')) {
                        newStyle = newStyle.replace(/stroke\s*:\s*[^;]+/, `stroke: ${newColor}`);
                        styleChanged = true;
                    }
                    
                    if (styleChanged) {
                        element.setAttribute('style', newStyle);
                    }
                }
            });

            console.log('Color mapping created:', colorMapping);

            // 変更されたSVGコンテンツをレイヤーデータに保存
            layer.svgContent = layerElement.innerHTML;
            console.log('Updated layer svgContent');
            
            // 選択中のレイヤーを再描画
            renderLayer(layer);
            
            // ローカルストレージに保存
            saveToLocalStorage();
            
            console.log(`${palette.name}が適用されました: ${colorChangedCount}個の色要素を変更`);
            console.log(`=== applySeasonalTheme END (${season}) ===`);
        }

        // 全レイヤーに季節テーマを一括適用
        function applyThemeToAllLayers(season) {
            console.log(`=== applyThemeToAllLayers START (${season}) ===`);
            
            // 季節テーマのカラーパレット定義
            const seasonalPalettes = {
                spring: {
                    name: '春のやわらかパステル',
                    colors: [
                        '#F8CFCF', '#FFF2B7', '#D7EED1', '#D7E8F8', '#F3CFE8', 
                        '#F5E2C8', '#D9EDD8', '#F6DCC3', '#F4C6D0', '#FAF3E7'
                    ]
                },
                summer: {
                    name: '夏のやわらかパステル',
                    colors: [
                        '#BDE7F7', '#FFF3A4', '#C9F3E1', '#D3EBFF', '#F7D5E8', 
                        '#CFEAD2', '#FAEFD8', '#FFF9D6', '#BCE1F2', '#E3F2FA'
                    ]
                },
                autumn: {
                    name: '秋のやわらかパステル',
                    colors: [
                        '#F7D6B3', '#FFD9A6', '#F2E0B9', '#E7D5C1', '#E7B9A4', 
                        '#FAE9D2', '#E1C4A7', '#F5CDBB', '#EBC7A4', '#F8E5D6'
                    ]
                },
                winter: {
                    name: '冬のやわらかパステル',
                    colors: [
                        '#E5EEF5', '#DAD7F0', '#F1ECE6', '#D3DDE0', '#CBDDE1', 
                        '#F2EFE9', '#E1DBD5', '#D9E2EA', '#E8E1DC', '#F4F3F1'
                    ]
                },
                monochrome: {
                    name: 'やさしいモノクロ',
                    colors: [
                        '#FFFFFF', '#FAFAFA', '#F3F3F3', '#E6E6E6', '#D8D8D8', 
                        '#C8C8C8', '#B0B0B0', '#999999', '#7F7F7F', '#666666'
                    ]
                },
                sepia: {
                    name: 'やさしいセピア',
                    colors: [
                        '#FFFDF8', '#FBF4EA', '#F6EBDC', '#F0E2CF', '#E8D7BD', 
                        '#E0C9A6', '#D1B68D', '#C19D72', '#AD8C63', '#937550'
                    ]
                }
            };

            if (!seasonalPalettes[season]) {
                console.error(`Unknown season: ${season}`);
                return;
            }

            const palette = seasonalPalettes[season];
            const themeColors = palette.colors;
            
            // シャッフルされたテーマカラー
            const shuffledColors = [...themeColors];
            for (let i = shuffledColors.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [shuffledColors[i], shuffledColors[j]] = [shuffledColors[j], shuffledColors[i]];
            }
            
            // グローバルな色マッピング（全レイヤー共通）
            const globalColorMapping = new Map();
            let colorIndex = 0;
            let totalColorChangedCount = 0;

            // 全レイヤーに適用
            layers.forEach(layer => {
                const layerElement = document.getElementById(`layer-${layer.id}`);
                if (!layerElement) return;

                const svgElements = layerElement.querySelectorAll('path, circle, rect, polygon, ellipse');
                let layerColorChangedCount = 0;
                
                svgElements.forEach(element => {
                    // 元の色情報を保存・取得
                    const fillAttr = element.getAttribute('fill');
                    const strokeAttr = element.getAttribute('stroke');
                    
                    if (fillAttr && !element.getAttribute('data-original-fill')) {
                        element.setAttribute('data-original-fill', fillAttr);
                    }
                    if (strokeAttr && strokeAttr !== 'none' && !element.getAttribute('data-original-stroke')) {
                        element.setAttribute('data-original-stroke', strokeAttr);
                    }
                    
                    const styleAttr = element.getAttribute('style');
                    if (styleAttr) {
                        const fillMatch = styleAttr.match(/fill:\s*([^;]+)/);
                        const strokeMatch = styleAttr.match(/stroke:\s*([^;]+)/);
                        
                        if (fillMatch && !element.getAttribute('data-original-style-fill')) {
                            element.setAttribute('data-original-style-fill', fillMatch[1].trim());
                        }
                        if (strokeMatch && strokeMatch[1].trim() !== 'none' && !element.getAttribute('data-original-style-stroke')) {
                            element.setAttribute('data-original-style-stroke', strokeMatch[1].trim());
                        }
                    }
                    
                    // 元の色情報を取得（fillとstrokeの両方をチェック）
                    const originalFillColor = element.getAttribute('data-original-fill') || 
                                            element.getAttribute('data-original-style-fill');
                    const originalStrokeColor = element.getAttribute('data-original-stroke') || 
                                              element.getAttribute('data-original-style-stroke');
                    
                    // Fill色の処理
                    if (originalFillColor && originalFillColor !== 'none') {
                        // 黒・グレー除外
                        if (!originalFillColor.includes('#000') && !originalFillColor.includes('gray') && !originalFillColor.includes('grey')) {
                            // グローバル色マッピング
                            if (!globalColorMapping.has(originalFillColor)) {
                                const newThemeColor = shuffledColors[colorIndex % shuffledColors.length];
                                globalColorMapping.set(originalFillColor, newThemeColor);
                                colorIndex++;
                            }
                            
                            const newColor = globalColorMapping.get(originalFillColor);
                            
                            // 色を適用
                            if (element.getAttribute('data-original-fill')) {
                                element.setAttribute('fill', newColor);
                                layerColorChangedCount++;
                            }
                            
                            if (styleAttr && element.getAttribute('data-original-style-fill')) {
                                let newStyle = styleAttr.replace(/fill\s*:\s*[^;]+/, `fill: ${newColor}`);
                                element.setAttribute('style', newStyle);
                                layerColorChangedCount++;
                            }
                        }
                    }
                    
                    // Stroke色の処理
                    if (originalStrokeColor && originalStrokeColor !== 'none') {
                        // 黒・グレー除外
                        if (!originalStrokeColor.includes('#000') && !originalStrokeColor.includes('gray') && !originalStrokeColor.includes('grey')) {
                            // グローバル色マッピング
                            if (!globalColorMapping.has(originalStrokeColor)) {
                                const newThemeColor = shuffledColors[colorIndex % shuffledColors.length];
                                globalColorMapping.set(originalStrokeColor, newThemeColor);
                                colorIndex++;
                            }
                            
                            const newColor = globalColorMapping.get(originalStrokeColor);
                            
                            // Stroke色を適用
                            if (element.getAttribute('data-original-stroke')) {
                                element.setAttribute('stroke', newColor);
                            }
                            
                            if (styleAttr && element.getAttribute('data-original-style-stroke')) {
                                let currentStyle = element.getAttribute('style');
                                let newStyle = currentStyle.replace(/stroke\s*:\s*[^;]+/, `stroke: ${newColor}`);
                                element.setAttribute('style', newStyle);
                            }
                        }
                    }
                });
                
                totalColorChangedCount += layerColorChangedCount;
                
                // SVGContentを更新（重要：データの永続化）
                const updatedSvgContent = layerElement.innerHTML;
                layer.svgContent = updatedSvgContent;
                
                // レイヤー更新
                renderLayer(layer);
            });
            
            console.log(`${palette.name}を全レイヤーに適用完了: ${totalColorChangedCount}個の色要素を変更`);
            console.log(`色マッピング: `, globalColorMapping);
            
            // データを保存
            saveToLocalStorage();
            
            console.log(`=== applyThemeToAllLayers END (${season}) ===`);
        }

        // テーマ適用後にカラーパレットを更新


        // SVG線形品質を確保する関数（現在は無効化 - 線が太くなる問題のため）
        function ensureSVGLineQuality(element) {
            // 一時的に機能を無効化して線が太くなる問題を調査
            console.log('ensureSVGLineQuality: 一時的に無効化中（線が太くなる問題の調査のため）');
            return;
            
            const svgElements = element.querySelectorAll('path, circle, rect, polygon, ellipse, line, polyline');
            
            svgElements.forEach(svgEl => {
                // shape-renderingのみ設定（線形属性は設定しない）
                if (!svgEl.getAttribute('shape-rendering')) {
                    svgEl.setAttribute('shape-rendering', 'geometricPrecision');
                }
            });
            
            console.log('SVG shape-rendering only applied for', svgElements.length, 'elements');
        }

        // 元の色情報をdata属性として保存する関数
        function initializeOriginalColors(element) {
            const svgElements = element.querySelectorAll('path, circle, rect, polygon, ellipse, line, polyline');
            
            svgElements.forEach((svgEl) => {
                // fill属性の保存
                const fillAttr = svgEl.getAttribute('fill');
                if (fillAttr && fillAttr !== 'none' && !svgEl.getAttribute('data-original-fill')) {
                    svgEl.setAttribute('data-original-fill', fillAttr);
                }
                
                // stroke属性の保存
                const strokeAttr = svgEl.getAttribute('stroke');
                if (strokeAttr && strokeAttr !== 'none' && !svgEl.getAttribute('data-original-stroke')) {
                    svgEl.setAttribute('data-original-stroke', strokeAttr);
                }
                
                // style属性からの色情報の保存
                const styleAttr = svgEl.getAttribute('style');
                if (styleAttr) {
                    const fillMatch = styleAttr.match(/fill:\s*([^;]+)/);
                    const strokeMatch = styleAttr.match(/stroke:\s*([^;]+)/);
                    
                    if (fillMatch && fillMatch[1].trim() !== 'none' && !svgEl.getAttribute('data-original-style-fill')) {
                        svgEl.setAttribute('data-original-style-fill', fillMatch[1].trim());
                    }
                    if (strokeMatch && strokeMatch[1].trim() !== 'none' && !svgEl.getAttribute('data-original-style-stroke')) {
                        svgEl.setAttribute('data-original-style-stroke', strokeMatch[1].trim());
                        // 線の終端と接続部分を滑らかに
                        if (!svgEl.getAttribute('stroke-linecap')) {
                            svgEl.setAttribute('stroke-linecap', 'round');
                        }
                        if (!svgEl.getAttribute('stroke-linejoin')) {
                            svgEl.setAttribute('stroke-linejoin', 'round');
                        }
                    }
                }
                
                // stroke属性がある場合も同様にチェック
                if (strokeAttr && strokeAttr !== 'none') {
                    // 線の終端と接続部分を滑らかに
                    if (!svgEl.getAttribute('stroke-linecap')) {
                        svgEl.setAttribute('stroke-linecap', 'round');
                    }
                    if (!svgEl.getAttribute('stroke-linejoin')) {
                        svgEl.setAttribute('stroke-linejoin', 'round');
                    }
                }
                
                // パスの複数の閉じた領域がある場合、fill-ruleを確実に設定
                if (svgEl.tagName === 'path') {
                    const pathData = svgEl.getAttribute('d');
                    if (pathData && pathData.includes('Z') && pathData.indexOf('Z') !== pathData.lastIndexOf('Z')) {
                        // 複数のZ（閉じたパス）がある場合はevenoddルールを適用
                        svgEl.setAttribute('fill-rule', 'evenodd');
                    }
                }
            });
            
            console.log('=== Original colors initialized for', svgElements.length, 'elements ===');
            svgElements.forEach((el, i) => {
                console.log(`Element ${i} (${el.tagName}):`, {
                    fill: el.getAttribute('fill'),
                    stroke: el.getAttribute('stroke'),
                    'stroke-width': el.getAttribute('stroke-width'),
                    style: el.getAttribute('style'),
                    'd': el.getAttribute('d')?.substring(0, 50) + '...'
                });
            });
        }

        // グレー・黒系の色かどうかを判定
        function isGrayOrBlackColor(color) {
            if (!color) return false;
            
            const normalizedColor = color.toLowerCase().trim();
            
            // 基本的なグレー・黒系の色名
            const grayKeywords = ['black', 'gray', 'grey', 'white', 'silver'];
            if (grayKeywords.some(keyword => normalizedColor.includes(keyword))) {
                return true;
            }
            
            // HEX色の場合の判定
            if (normalizedColor.startsWith('#')) {
                const hex = normalizedColor.substring(1);
                if (hex.length === 3) {
                    // 3桁HEX (#333 -> #333333)
                    const r = parseInt(hex[0] + hex[0], 16);
                    const g = parseInt(hex[1] + hex[1], 16);
                    const b = parseInt(hex[2] + hex[2], 16);
                    return isGrayish(r, g, b);
                } else if (hex.length === 6) {
                    // 6桁HEX
                    const r = parseInt(hex.substring(0, 2), 16);
                    const g = parseInt(hex.substring(2, 4), 16);
                    const b = parseInt(hex.substring(4, 6), 16);
                    return isGrayish(r, g, b);
                }
            }
            
            return false;
        }
        
        // RGB値がグレーっぽいかどうかを判定
        function isGrayish(r, g, b) {
            // RGBの差が小さい（グレーっぽい）かどうか
            const maxDiff = Math.max(Math.abs(r - g), Math.abs(g - b), Math.abs(r - b));
            return maxDiff < 30; // 差が30未満ならグレーっぽいと判定
        }

        // ローカルストレージに編集内容を保存
        function saveToLocalStorage() {
            try {
                console.log('保存前のlayers配列:', layers);
                console.log('保存前のlayers長さ:', layers.length);
                
                const editorData = {
                    layers: layers,
                    selectedLayerId: selectedLayerId,
                    nextLayerId: nextLayerId,
                    currentBackgroundColor: currentBackgroundColor,
                    timestamp: Date.now()
                };
                
                const jsonData = JSON.stringify(editorData);
                localStorage.setItem('compose_kids_editor_data', jsonData);
                console.log('編集内容を保存:', {
                    レイヤー数: layers.length,
                    背景色: currentBackgroundColor,
                    データサイズ: jsonData.length + 'バイト'
                });
                
                // 保存直後に確認
                const saved = localStorage.getItem('compose_kids_editor_data');
                const parsed = JSON.parse(saved);
                console.log('保存確認 - レイヤー数:', parsed.layers.length);
            } catch (error) {
                console.error('ローカルストレージ保存エラー:', error);
                if (error.name === 'QuotaExceededError') {
                    alert('ストレージの容量が不足しています。ブラウザのデータをクリアしてください。');
                }
            }
        }

        // ローカルストレージから編集内容を読み込み
        function loadFromLocalStorage() {
            try {
                // 新キーで取得
                let savedData = localStorage.getItem('compose_kids_editor_data');
                
                // 旧キーから移行
                if (!savedData) {
                    savedData = localStorage.getItem('compose2_editor_data');
                    if (savedData) {
                        console.log('旧キー(compose2)からデータを移行します');
                        // 新キーで保存
                        localStorage.setItem('compose_kids_editor_data', savedData);
                        // 旧キーを削除
                        localStorage.removeItem('compose2_editor_data');
                    }
                }
                
                console.log('localStorage読み込み:', savedData ? 'データあり' : 'データなし');
                
                if (savedData) {
                    const editorData = JSON.parse(savedData);
                    console.log('復元するデータ:', {
                        レイヤー数: editorData.layers?.length || 0,
                        背景色: editorData.currentBackgroundColor || 'なし',
                        タイムスタンプ: editorData.timestamp ? new Date(editorData.timestamp).toLocaleString() : 'なし'
                    });
                    
                    // データの復元
                    layers = editorData.layers || [];
                    selectedLayerId = editorData.selectedLayerId || null;
                    nextLayerId = editorData.nextLayerId || 1;
                    currentBackgroundColor = editorData.currentBackgroundColor || 'transparent';
                    
                    // レイヤーを再描画
                    layers.forEach(layer => {
                        console.log('レイヤー復元:', layer.id, layer.materialName);
                        renderLayer(layer);
                    });
                    
                    // UI状態を更新
                    updateSelectedLayerTitle();
                    updateRotateButtonState();
                    updateScaleDownButtonState();
                    updateScaleUpButtonState();
                    updateDeleteButtonState();
                    updateSeasonalThemeButtonState();
                    
                    // 背景色を復元
                    console.log('背景色を設定:', currentBackgroundColor);
                    setBackgroundColor(currentBackgroundColor);
                    updateBackgroundColorSelection(currentBackgroundColor);
                    
                    console.log(`${layers.length}個のレイヤーをローカルストレージから復元しました`);
                    return true;
                }
            } catch (error) {
                console.error('ローカルストレージ読み込みエラー:', error);
            }
            return false;
        }

        // ローカルストレージをクリア
        function clearLocalStorage() {
            try {
                localStorage.removeItem('compose_kids_editor_data');
                console.log('ローカルストレージをクリアしました');
            } catch (error) {
                console.error('ローカルストレージクリアエラー:', error);
            }
        }

        // 回転ボタンの状態を更新
        function updateRotateButtonState() {
            const rotateBtn = document.getElementById('rotateBtn');
            
            console.log(`updateRotateButtonState: selectedLayerId = ${selectedLayerId}`);
            
            if (selectedLayerId !== null) {
                rotateBtn.disabled = false;
                rotateBtn.title = '選択したレイヤーを15度右回転';
                console.log('Rotate buttons ENABLED');
            } else {
                rotateBtn.disabled = true;
                rotateBtn.title = 'レイヤーを選択してから回転できます';
                console.log('Rotate buttons DISABLED');
            }
        }

        // 縮小ボタンの状態を更新
        function updateScaleDownButtonState() {
            const scaleDownBtn = document.getElementById('scaleDownBtn');
            if (selectedLayerId !== null) {
                const layer = layers.find(l => l.id === selectedLayerId);
                if (layer && layer.transform.scale > 0.1) {
                    scaleDownBtn.disabled = false;
                    scaleDownBtn.title = '選択したレイヤーを20%縮小';
                } else {
                    scaleDownBtn.disabled = true;
                    scaleDownBtn.title = 'これ以上縮小できません（最小10%）';
                }
            } else {
                scaleDownBtn.disabled = true;
                scaleDownBtn.title = 'レイヤーを選択してから縮小できます';
            }
        }

        // 拡大ボタンの状態を更新
        function updateScaleUpButtonState() {
            const scaleUpBtn = document.getElementById('scaleUpBtn');
            if (selectedLayerId !== null) {
                const layer = layers.find(l => l.id === selectedLayerId);
                if (layer && layer.transform.scale < 5.0) {
                    scaleUpBtn.disabled = false;
                    scaleUpBtn.title = '選択したレイヤーを25%拡大';
                } else {
                    scaleUpBtn.disabled = true;
                    scaleUpBtn.title = 'これ以上拡大できません（最大500%）';
                }
            } else {
                scaleUpBtn.disabled = true;
                scaleUpBtn.title = 'レイヤーを選択してから拡大できます';
            }
        }

        // 削除ボタンの状態を更新
        function updateDeleteButtonState() {
            const deleteBtn = document.getElementById('deleteBtn');
            
            if (selectedLayerId !== null) {
                deleteBtn.disabled = false;
                deleteBtn.title = '選択したレイヤーを削除';
            } else {
                deleteBtn.disabled = true;
                deleteBtn.title = 'レイヤーを選択してから削除できます';
            }
        }

        // 季節テーマボタンの状態を更新
        function updateSeasonalThemeButtonState() {
            const springThemeBtn = document.getElementById('springThemeBtn');
            
            if (springThemeBtn) {
                if (selectedLayerId !== null) {
                    springThemeBtn.disabled = false;
                    springThemeBtn.title = 'いろをかえる';
                } else {
                    springThemeBtn.disabled = true;
                    springThemeBtn.title = 'レイヤーを選択してから色を変更できます';
                }
            }
        }

        // 選択中の素材タイトルを更新
        function updateSelectedLayerTitle() {
            const titleElement = document.getElementById('selectedLayerTitle');
            
            // 要素が存在しない場合は何もしない（kids.phpでは削除済み）
            if (!titleElement) {
                return;
            }
            
            console.log(`updateSelectedLayerTitle: selectedLayerId = ${selectedLayerId}`);
            
            if (selectedLayerId !== null) {
                const layer = layers.find(l => l.id === selectedLayerId);
                if (layer) {
                    titleElement.textContent = layer.title;
                    titleElement.classList.add('active');
                    console.log(`Title updated to: ${layer.title}`);
                } else {
                    titleElement.textContent = 'レイヤーを選択してください';
                    titleElement.classList.remove('active');
                    console.log('Layer not found in layers array');
                }
            } else {
                titleElement.textContent = 'レイヤーを選択してください';
                titleElement.classList.remove('active');
                console.log('selectedLayerId is null - showing default message');
            }
        }

        // PNG出力機能
        function exportToPNG() {
            if (layers.length === 0) {
                alert('さいしょにえをえらんでね！');
                return;
            }

            console.log('PNG出力開始 (2500px)');

            const canvas = document.getElementById('mainCanvas');
            
            // 選択状態を一時的に保存
            const currentSelectedId = selectedLayerId;
            
            // 一時的に選択を解除して選択枠を非表示にする
            if (selectedLayerId !== null) {
                selectedLayerId = null;
                layers.forEach(layer => {
                    renderLayer(layer);
                });
            }
            
            // 選択枠なしでSVGを取得
            const svgData = new XMLSerializer().serializeToString(canvas);
            
            // 高解像度出力用のキャンバスを作成
            const outputCanvas = document.createElement('canvas');
            const outputSize = 2500; // 2500px出力
            outputCanvas.width = outputSize;
            outputCanvas.height = outputSize;
            const ctx = outputCanvas.getContext('2d');
            
            // 高品質設定
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            
            // SVGをImageとして読み込み
            const img = new Image();
            img.onload = function() {
                ctx.drawImage(img, 0, 0, outputCanvas.width, outputCanvas.height);
                
                // PNGとしてダウンロード
                outputCanvas.toBlob(function(blob) {
                    if (blob) {
                        const url = URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = `svg-composition-2500px-${Date.now()}.png`;
                        
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        URL.revokeObjectURL(url);
                        console.log('PNG出力完了 (2500px)');
                        
                        // 選択状態を復元
                        if (currentSelectedId !== null) {
                            selectedLayerId = currentSelectedId;
                            layers.forEach(layer => {
                                renderLayer(layer);
                            });
                            updateSelectedLayerTitle();
                            updateRotateButtonState();
                            updateScaleDownButtonState();
                            updateScaleUpButtonState();
                            updateDeleteButtonState();
                            updateSeasonalThemeButtonState();
                        }
                    } else {
                        alert('PNG変換に失敗しました。');
                        
                        // エラー時も選択状態を復元
                        if (currentSelectedId !== null) {
                            selectedLayerId = currentSelectedId;
                            layers.forEach(layer => {
                                renderLayer(layer);
                            });
                        }
                    }
                }, 'image/png');
            };
            
            img.onerror = function(error) {
                console.error('PNG出力エラー:', error);
                alert('PNG出力に失敗しました。');
                
                // エラー時も選択状態を復元
                if (currentSelectedId !== null) {
                    selectedLayerId = currentSelectedId;
                    layers.forEach(layer => {
                        renderLayer(layer);
                    });
                }
            };
            
            // SVGデータをBlobとして作成
            const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const svgUrl = URL.createObjectURL(svgBlob);
            img.src = svgUrl;
            
            setTimeout(() => URL.revokeObjectURL(svgUrl), 1000);
        }

        // 作品アップロード機能
        function openUploadModal() {
            if (layers.length === 0) {
                alert('さいしょにえをえらんでね！');
                return;
            }

            // 1日3回制限のチェック
            const today = new Date().toDateString();
            const uploadCountKey = 'kidsUploadCount_' + today;
            const uploadCount = parseInt(localStorage.getItem(uploadCountKey) || '0');
            
            if (uploadCount >= 3) {
                alert('きょうは もう 3つ とどけたよ！\nまた あした きてね！');
                return;
            }

            // 確認ダイアログ
            if (!confirm('あなたの えを みんなに とどけますか？')) {
                return;
            }

            // 直接アップロード処理を実行
            submitArtworkDirectly();
        }

        // アップロード用プレビュー生成
        function generateUploadPreview() {
            const canvas = document.getElementById('mainCanvas');
            
            // 選択状態を一時的に保存・解除
            const currentSelectedId = selectedLayerId;
            if (selectedLayerId !== null) {
                selectedLayerId = null;
                layers.forEach(layer => renderLayer(layer));
            }
            
            // プレビュー用小サイズキャンバス作成
            const previewCanvas = document.createElement('canvas');
            const previewSize = 300;
            previewCanvas.width = previewSize;
            previewCanvas.height = previewSize;
            const ctx = previewCanvas.getContext('2d');
            
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            
            const svgData = new XMLSerializer().serializeToString(canvas);
            const img = new Image();
            
            img.onload = function() {
                ctx.drawImage(img, 0, 0, previewCanvas.width, previewCanvas.height);
                
                // プレビューコンテナに画像を表示
                const previewContainer = document.getElementById('uploadPreviewContainer');
                previewContainer.innerHTML = '';
                
                const previewImg = document.createElement('img');
                previewImg.src = previewCanvas.toDataURL('image/png');
                previewContainer.appendChild(previewImg);
                
                // 選択状態を復元
                if (currentSelectedId !== null) {
                    selectedLayerId = currentSelectedId;
                    layers.forEach(layer => renderLayer(layer));
                    updateSelectedLayerTitle();
                    updateRotateButtonState();
                    updateScaleDownButtonState();
                    updateScaleUpButtonState();
                    updateDeleteButtonState();
                }
            };
            
            const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const svgUrl = URL.createObjectURL(svgBlob);
            img.src = svgUrl;
            
            setTimeout(() => URL.revokeObjectURL(svgUrl), 1000);
        }

        // 高解像度PNG生成（アップロード用）
        function generatePNGForUpload(callback) {
            const canvas = document.getElementById('mainCanvas');
            
            // 選択状態を一時的に解除
            const currentSelectedId = selectedLayerId;
            if (selectedLayerId !== null) {
                selectedLayerId = null;
                layers.forEach(layer => renderLayer(layer));
            }
            
            // 高解像度出力用キャンバス作成（2500px）
            const outputCanvas = document.createElement('canvas');
            const outputSize = 2500;
            outputCanvas.width = outputSize;
            outputCanvas.height = outputSize;
            const ctx = outputCanvas.getContext('2d');
            
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            
            const svgData = new XMLSerializer().serializeToString(canvas);
            const img = new Image();
            
            img.onload = function() {
                ctx.drawImage(img, 0, 0, outputCanvas.width, outputCanvas.height);
                
                outputCanvas.toBlob(function(blob) {
                    // 選択状態を復元
                    if (currentSelectedId !== null) {
                        selectedLayerId = currentSelectedId;
                        layers.forEach(layer => renderLayer(layer));
                        updateSelectedLayerTitle();
                        updateRotateButtonState();
                        updateScaleDownButtonState();
                        updateScaleUpButtonState();
                        updateDeleteButtonState();
                    }
                    
                    if (callback) callback(blob);
                }, 'image/png');
            };
            
            img.onerror = function(error) {
                console.error('PNG生成エラー:', error);
                if (callback) callback(null);
            };
            
            const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const svgUrl = URL.createObjectURL(svgBlob);
            img.src = svgUrl;
            
            setTimeout(() => URL.revokeObjectURL(svgUrl), 1000);
        }

        // 使用されている素材IDを収集する関数
        function getUsedMaterialIds() {
            const usedIds = new Set();
            
            // 正しいキャンバス要素IDを使用（mainCanvasが正しいID）
            const canvas = document.getElementById('mainCanvas');
            if (canvas) {
                const materialElements = canvas.querySelectorAll('[data-material-id]');
                console.log(`Found ${materialElements.length} material elements on canvas`);
                
                materialElements.forEach(element => {
                    const materialId = element.getAttribute('data-material-id');
                    console.log(`Material element found with ID: ${materialId}`);
                    if (materialId && materialId !== '' && materialId !== 'null') {
                        usedIds.add(parseInt(materialId, 10));
                    }
                });
            } else {
                console.error('mainCanvas element not found');
            }
            
            const result = Array.from(usedIds).filter(id => !isNaN(id) && id > 0);
            console.log('Used material IDs detected:', result);
            
            return result;
        }

        // 作品アップロード実行（フォーム入力なし版）
        function submitArtworkDirectly() {
            // アップロード中メッセージ表示
            const loadingMessage = document.createElement('div');
            loadingMessage.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 10000; text-align: center;';
            loadingMessage.innerHTML = '<div style="font-size: 1.5rem; margin-bottom: 10px;">📤</div><div>えを とどけています...</div>';
            document.body.appendChild(loadingMessage);
            
            generatePNGForUpload(function(blob) {
                if (!blob) {
                    document.body.removeChild(loadingMessage);
                    alert('えの へんかんに しっぱい しました');
                    return;
                }
                
                // FormData作成（タイトルとペンネームはAIが生成）
                const formData = new FormData();
                const timestamp = new Date().toLocaleString('ja-JP');
                formData.append('title', ''); // 空にしてAI生成に任せる
                formData.append('pen_name', ''); // 空にしてAI生成に任せる
                formData.append('description', '');
                formData.append('artwork', blob, `kids-artwork-${Date.now()}.png`);
                
                // サーバーにアップロード
                fetch('/api/upload-kids-artwork.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    document.body.removeChild(loadingMessage);
                    
                    if (data.success) {
                        // アップロードカウントを増やす
                        const today = new Date().toDateString();
                        const uploadCountKey = 'kidsUploadCount_' + today;
                        const currentCount = parseInt(localStorage.getItem(uploadCountKey) || '0');
                        localStorage.setItem(uploadCountKey, (currentCount + 1).toString());
                        
                        const remaining = 3 - (currentCount + 1);
                        let successMsg = 'えを とどけました！\nありがとう！\n\nいま、おはなしを つくっています✨\nすこし まっててね！';
                        
                        if (remaining > 0) {
                            successMsg += `\n\nきょうは あと ${remaining}つ とどけられるよ！`;
                        } else {
                            successMsg += '\n\nまた あした きてね！';
                        }
                        
                        alert(successMsg);
                        
                        console.log('作品アップロード完了:', data);
                    } else {
                        let errorMsg = data.error || 'とどけるのに しっぱい しました';
                        alert(errorMsg);
                    }
                })
                .catch(error => {
                    document.body.removeChild(loadingMessage);
                    console.error('Upload error:', error);
                    alert('エラーが はっせい しました\nもう いちど ためして ください');
                });
            });
        }

        // 背景色を設定する関数
        function setBackgroundColor(color) {
            currentBackgroundColor = color;
            
            // localStorageに保存
            localStorage.setItem('kidsCanvasBackgroundColor', color);
            
            console.log('setBackgroundColor呼び出し:', color);
            
            const svg = document.getElementById('mainCanvas');
            if (!svg) {
                console.error('mainCanvas要素が見つかりません');
                return;
            }
            
            // 既存の背景rect要素を削除
            const existingBg = svg.querySelector('#svg-background');
            if (existingBg) {
                existingBg.remove();
            }
            
            // 既存のcanvasBackground要素を処理
            const canvasBackground = svg.querySelector('#canvasBackground');
            console.log('canvasBackground要素:', canvasBackground ? '存在する' : '存在しない');
            
            if (color === 'transparent') {
                // 透明背景の場合、canvasBackgroundを非表示にする
                if (canvasBackground) {
                    canvasBackground.style.display = 'none';
                    console.log('背景を透明に設定');
                }
            } else {
                // 色が指定された場合、canvasBackgroundの色を変更
                if (canvasBackground) {
                    console.log('背景色を変更:', color);
                    canvasBackground.setAttribute('fill', color);
                    canvasBackground.style.display = 'block';
                } else {
                    // canvasBackgroundが存在しない場合は新規作成
                    const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                    rect.setAttribute('id', 'canvasBackground');
                    rect.setAttribute('x', '0');
                    rect.setAttribute('y', '0');
                    rect.setAttribute('width', '1024');
                    rect.setAttribute('height', '1024');
                    rect.setAttribute('fill', color);
                    
                    // 最初の子要素として挿入（背景として）
                    svg.insertBefore(rect, svg.firstChild);
                }
            }
            
            // ローカルストレージに保存
            saveToLocalStorage();
            
            console.log(`Background color set to: ${color}`);
            console.log('canvasBackground element:', canvasBackground);
        }

        // 背景色選択状態を更新する関数
        function updateBackgroundColorSelection(color) {
            const transparentBtn = document.querySelector('.bg-color-btn[data-color="transparent"]');
            const customBgColorInput = document.getElementById('customBgColor');
            
            if (color === 'transparent') {
                // 透明背景ボタンをアクティブに
                if (transparentBtn) {
                    transparentBtn.classList.add('active');
                }
                // カスタムカラーピッカーをリセット
                if (customBgColorInput) {
                    customBgColorInput.value = '#ffffff';
                }
            } else {
                // 透明背景ボタンを非アクティブに
                if (transparentBtn) {
                    transparentBtn.classList.remove('active');
                }
                // カスタムカラーピッカーに色を設定
                if (customBgColorInput) {
                    customBgColorInput.value = color;
                }
            }
        }

        // 全削除機能
        function clearAll() {
            if (layers.length === 0) {
                alert('削除する素材がありません。');
                return;
            }

            layers = [];
            nextLayerId = 1;
            selectedLayerId = null;
            
            // DOM要素も削除
            const canvas = document.getElementById('mainCanvas');
            const layerElements = canvas.querySelectorAll('[id^="layer-"]');
            layerElements.forEach(element => element.remove());
            
            // 背景色をデフォルトの水色にリセット
            const defaultColor = '#a7d5e8';
            currentBackgroundColor = defaultColor;
            
            // 背景要素の色を直接変更（saveToLocalStorageを呼ばない）
            const canvasBackground = canvas.querySelector('#canvasBackground');
            if (canvasBackground) {
                canvasBackground.setAttribute('fill', defaultColor);
            }
            
            // カラーピッカーの値も更新
            const customBgColorInput = document.getElementById('customBgColor');
            if (customBgColorInput) {
                customBgColorInput.value = defaultColor;
            }
            
            updateBackgroundColorSelection(defaultColor);
            
            // ボタンの状態を更新（全て無効化）
            updateRotateButtonState();
            updateScaleDownButtonState();
            updateScaleUpButtonState();
            updateDeleteButtonState();
            updateSeasonalThemeButtonState();
            
            // localStorageをクリア（空の状態を保存しない）
            clearLocalStorage();
            
            console.log('全ての素材を削除し、編集データをクリアしました');
            
            // ローカルストレージに保存（空の状態を保存）
            saveToLocalStorage();
        }

        // 背景色パレットを生成する関数
        function generateBackgroundColorPalette() {
            const colorGrid = document.getElementById('backgroundColorGrid');
            if (!colorGrid) return;
            
            backgroundColors.forEach(color => {
                const colorItem = document.createElement('div');
                colorItem.className = 'bg-color-item';
                colorItem.style.backgroundColor = color;
                colorItem.setAttribute('data-color', color);
                colorItem.title = color;
                
                colorItem.addEventListener('click', function() {
                    setBackgroundColor(color);
                    updateBackgroundColorSelection(color);
                });
                
                colorGrid.appendChild(colorItem);
            });
        }
        
        // 背景色選択状態を更新する関数
        function updateBackgroundColorSelection(selectedColor) {
            // パレット内のアクティブ状態をクリア
            const bgColorItems = document.querySelectorAll('.bg-color-item');
            bgColorItems.forEach(item => item.classList.remove('active'));
            
            // 透明ボタンのアクティブ状態をクリア
            const transparentBtn = document.querySelector('.bg-color-btn[data-color="transparent"]');
            if (transparentBtn) {
                transparentBtn.classList.remove('active');
            }
            
            if (selectedColor === 'transparent') {
                // 透明背景が選択された場合
                if (transparentBtn) {
                    transparentBtn.classList.add('active');
                }
                // カスタムカラーピッカーをリセット
                const customBgColorInput = document.getElementById('customBgColor');
                if (customBgColorInput) {
                    customBgColorInput.value = '#ffffff';
                }
            } else {
                // 色が選択された場合、該当するパレット項目をアクティブに
                const targetItem = document.querySelector(`.bg-color-item[data-color="${selectedColor}"]`);
                if (targetItem) {
                    targetItem.classList.add('active');
                }
                
                // カスタムカラーピッカーを選択色に設定
                const customBgColorInput = document.getElementById('customBgColor');
                if (customBgColorInput) {
                    customBgColorInput.value = selectedColor;
                }
            }
        }

        // 素材検索機能（全件Ajax検索対応）
        function initializeMaterialSearch() {
            const searchInput = document.getElementById('materialSearch');
            const clearButton = document.getElementById('clearSearch');
            
            if (!searchInput) return;
            
            let searchTimeout = null;
            
            // 検索入力イベント（デバウンス付き）
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim();
                
                // クリアボタンの表示/非表示
                clearButton.style.display = searchTerm ? 'block' : 'none';
                
                // デバウンス処理（300ms）
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                searchTimeout = setTimeout(() => {
                    if (searchTerm) {
                        // Ajax全件検索を実行
                        performGlobalSearch(searchTerm);
                    } else {
                        // 検索クリア時はページリロード
                        window.location.reload();
                    }
                }, 300);
            });
            
            // クリアボタンイベント
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                this.style.display = 'none';
                hideSearchResultsMessage();
                // ページリロードで元の状態に戻す
                window.location.reload();
            });
            
            // Enterキーでの検索実行を防ぐ
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        }
        
        // Ajax全件検索を実行
        function performGlobalSearch(searchTerm) {
            console.log(`Performing global search for: ${searchTerm}`);
            
            // ローディング表示
            showSearchLoadingMessage();
            
            // Ajax検索APIを呼び出し
            fetch(`/admin/api/search-materials.php?q=${encodeURIComponent(searchTerm)}`)
                .then(response => {
                    console.log('API Response status:', response.status);
                    
                    if (!response.ok) {
                        throw new Error(`HTTPエラー: ${response.status} ${response.statusText}`);
                    }
                    
                    // レスポンスのテキストを取得してJSONパース前に確認
                    return response.text();
                })
                .then(responseText => {
                    console.log('API Response text:', responseText.substring(0, 200));
                    
                    try {
                        const data = JSON.parse(responseText);
                        
                        if (data.success) {
                            // 検索結果で素材グリッドを更新
                            updateMaterialsGrid(data.materials, data.query, data.total);
                            console.log(`Search completed: ${data.total} materials found`);
                        } else {
                            throw new Error(data.error || '検索エラーが発生しました');
                        }
                    } catch (jsonError) {
                        console.error('JSON parse error:', jsonError);
                        console.error('Response text:', responseText);
                        
                        // HTMLエラーページが返された場合の処理
                        if (responseText.includes('<br />') || responseText.includes('<b>')) {
                            throw new Error('サーバーでPHPエラーが発生しました。管理システムの設定を確認してください。');
                        } else {
                            throw new Error('サーバーから無効なJSONレスポンスが返されました');
                        }
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    showSearchErrorMessage(error.message);
                });
        }
        
        // 検索結果で素材グリッドを更新
        function updateMaterialsGrid(materials, searchQuery, totalCount) {
            const materialsGrid = document.querySelector('.materials-grid');
            if (!materialsGrid) {
                console.error('Materials grid not found');
                return;
            }
            
            // グリッドを空にする
            materialsGrid.innerHTML = '';
            
            if (materials.length === 0) {
                // 検索結果が0件の場合
                materialsGrid.innerHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #6c757d;">
                        <div style="font-size: 1.2rem; margin-bottom: 1rem;">
                            「${searchQuery}」に一致する素材が見つかりませんでした
                        </div>
                        <div style="font-size: 0.9rem;">
                            キーワードを変更して再検索してください
                        </div>
                    </div>
                `;
            } else {
                // 検索結果を表示
                materials.forEach(material => {
                    const materialItem = createMaterialItem(material);
                    materialsGrid.appendChild(materialItem);
                });
            }
            
            // 検索結果メッセージを更新
            showSearchResultsMessage(totalCount, searchQuery);
            
            // ページネーションを非表示
            hidePageNavigation();
        }
        
        // 素材アイテムのDOM要素を作成
        function createMaterialItem(material) {
            const item = document.createElement('div');
            item.className = 'material-item';
            item.setAttribute('data-material-id', material.id);
            item.setAttribute('data-svg-path', material.svg_path);
            item.setAttribute('data-title', material.title);
            
            // WebP優先で画像パスを選択
            let previewPath = '';
            if (material.webp_medium_path) {
                previewPath = material.webp_medium_path;
            } else if (material.image_path) {
                previewPath = material.image_path;
            } else {
                // フォールバック: SVGプレビューのパスを生成
                previewPath = material.svg_path.replace('.svg', '_preview.webp');
            }
            
            item.innerHTML = `
                <div class="material-image">
                    <img src="/${previewPath}" 
                         alt="${material.title}" 
                         loading="lazy"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div class="svg-fallback" style="display: none;">
                        <i class="bi bi-image"></i>
                    </div>
                </div>
            `;
            
            // クリックイベントを追加
            item.addEventListener('click', function() {
                addMaterialToCanvas(this);
            });
            
            return item;
        }
        
        // 検索結果メッセージの表示
        function showSearchResultsMessage(count, searchTerm) {
            let messageDiv = document.getElementById('searchResultsMessage');
            
            if (!messageDiv) {
                messageDiv = document.createElement('div');
                messageDiv.id = 'searchResultsMessage';
                messageDiv.style.cssText = `
                    text-align: center;
                    padding: 1rem;
                    color: #6c757d;
                    font-size: 0.9rem;
                    margin-bottom: 1rem;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border: 1px solid #e9ecef;
                `;
                
                const materialsGrid = document.querySelector('.materials-grid');
                materialsGrid.parentNode.insertBefore(messageDiv, materialsGrid);
            }
            
            if (searchTerm) {
                if (count === 0) {
                    messageDiv.innerHTML = `
                        <i class="bi bi-search"></i> 
                        「<strong>${searchTerm}</strong>」の検索結果: <strong>0件</strong>
                        <div style="margin-top: 0.5rem; font-size: 0.8rem;">
                            タイトル、カテゴリ、タグ、キーワードから検索しています
                        </div>
                    `;
                } else {
                    messageDiv.innerHTML = `
                        <i class="bi bi-search"></i> 
                        「<strong>${searchTerm}</strong>」の検索結果: <strong>${count}件</strong>
                        <div style="margin-top: 0.5rem; font-size: 0.8rem;">
                            スペース区切りでOR検索できます
                        </div>
                    `;
                }
                messageDiv.style.display = 'block';
            } else {
                messageDiv.style.display = 'none';
            }
        }
        
        // ローディングメッセージの表示
        function showSearchLoadingMessage() {
            showSearchResultsMessage(0, '検索中...');
            const messageDiv = document.getElementById('searchResultsMessage');
            if (messageDiv) {
                messageDiv.innerHTML = `
                    <i class="bi bi-arrow-clockwise spin"></i> 
                    検索中...
                    <div style="margin-top: 0.5rem; font-size: 0.8rem;">
                        全ての素材から検索しています
                    </div>
                `;
            }
        }
        
        // エラーメッセージの表示
        function showSearchErrorMessage(errorMessage) {
            const messageDiv = document.getElementById('searchResultsMessage');
            if (messageDiv) {
                messageDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle text-warning"></i> 
                    検索エラー: ${errorMessage}
                    <div style="margin-top: 0.5rem; font-size: 0.8rem;">
                        しばらく待ってから再度お試しください
                    </div>
                `;
                messageDiv.style.display = 'block';
            }
        }
        
        // 検索結果メッセージの非表示
        function hideSearchResultsMessage() {
            const messageDiv = document.getElementById('searchResultsMessage');
            if (messageDiv) {
                messageDiv.style.display = 'none';
            }
        }
        
        // ページネーションを非表示
        function hidePageNavigation() {
            const paginationContainer = document.querySelector('.pagination-container');
            const paginationInfo = document.querySelector('.pagination-info');
            
            if (paginationContainer) {
                paginationContainer.style.display = 'none';
            }
            if (paginationInfo) {
                paginationInfo.style.display = 'none';
            }
        }
        
        // ページネーションを表示
        function showPageNavigation() {
            const paginationContainer = document.querySelector('.pagination-container');
            const paginationInfo = document.querySelector('.pagination-info');
            
            if (paginationContainer) {
                paginationContainer.style.display = 'flex';
            }
            if (paginationInfo) {
                paginationInfo.style.display = 'block';
            }
        }
        
        // ページネーション非同期読み込み
        function initializePaginationSearch() {
            const paginationLinks = document.querySelectorAll('.pagination-btn');
            const searchInput = document.getElementById('materialSearch');
            
            // ページ変更を非同期で処理
            paginationLinks.forEach(link => {
                link.addEventListener('click', function(event) {
                    event.preventDefault(); // デフォルトのリンク動作を防止
                    
                    const url = this.href;
                    const pageMatch = url.match(/[?&]page=(\d+)/);
                    const page = pageMatch ? pageMatch[1] : 1;
                    
                    // 非同期で素材を読み込む
                    loadMaterialsPage(page);
                });
            });
            
            // 検索中はページネーションを非表示にする
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const paginationContainer = document.querySelector('.pagination-container');
                    const paginationInfo = document.querySelector('.pagination-info');
                    
                    if (this.value.trim()) {
                        // 検索中はページネーションを非表示
                        if (paginationContainer) paginationContainer.style.display = 'none';
                        if (paginationInfo) paginationInfo.style.display = 'none';
                    } else {
                        // 検索クリア時はページネーションを表示
                        if (paginationContainer) paginationContainer.style.display = 'flex';
                        if (paginationInfo) paginationInfo.style.display = 'block';
                    }
                });
            }
        }

        // 素材ページを非同期で読み込む
        async function loadMaterialsPage(page) {
            const materialsGrid = document.querySelector('.materials-grid');
            const paginationContainer = document.querySelector('.pagination-container');
            const paginationInfo = document.querySelector('.pagination-info');
            
            if (!materialsGrid) return;
            
            // ローディング表示
            materialsGrid.innerHTML = '<div style="text-align: center; padding: 3rem; color: #6c757d;"><div class="spinner-border" role="status"><span class="visually-hidden">読み込み中...</span></div><p class="mt-3">素材を読み込んでいます...</p></div>';
            
            try {
                // 素材データを取得
                const response = await fetch(`?page=${page}&ajax=1`);
                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                
                // 素材グリッドを更新
                if (data.materials && data.materials.length > 0) {
                    materialsGrid.innerHTML = data.materials.map(material => `
                        <div class="material-item" 
                             data-material-id="${material.id}"
                             data-svg-path="${material.svg_path}"
                             data-title="${material.title}"
                             title="${material.title}">
                            <img src="/${material.webp_medium_path || material.image_path}" 
                                 alt="${material.title}"
                                 loading="lazy">
                        </div>
                    `).join('');
                    
                    // クリックイベントを再設定
                    materialsGrid.querySelectorAll('.material-item').forEach(item => {
                        item.addEventListener('click', function() {
                            addMaterialToCanvas(this);
                        });
                    });
                } else {
                    materialsGrid.innerHTML = '<div style="text-align: center; padding: 2rem; color: #6c757d;"><p>素材が見つかりませんでした。</p></div>';
                }
                
                // ページネーションを更新
                if (paginationContainer && data.pagination) {
                    paginationContainer.innerHTML = data.pagination;
                    // ページネーションイベントを再初期化
                    initializePaginationSearch();
                }
                
                // ページ情報を更新
                if (paginationInfo && data.pageInfo) {
                    paginationInfo.textContent = data.pageInfo;
                }
                
                // 素材一覧の位置にスムーススクロール
                document.getElementById('materials').scrollIntoView({ behavior: 'smooth', block: 'start' });
                
            } catch (error) {
                console.error('Error loading materials:', error);
                materialsGrid.innerHTML = '<div style="text-align: center; padding: 2rem; color: #dc3545;"><p>素材の読み込みに失敗しました。ページをリロードしてください。</p></div>';
            }
        }





        // 旧実装（背景色仕様に合わせるため無効化）
        // function showColorTool() { /* 新実装では不要 */ }
        
        // 旧デスクトップ実装（背景色仕様に合わせるため無効化）
        // function showDesktopColorPicker() { /* 新実装では不要 */ }
        
        // 旧モバイル実装（背景色仕様に合わせるため無効化）
        // function showMobileColorPicker() { /* 新実装では不要 */ }



        // 旧リアルタイム色変更実装（背景色仕様に合わせるため無効化）
        // function changeColorRealtime() { /* 新実装では changeColorDirectly を使用 */ }

        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            console.log('ページ読み込み完了');
            
            // ローカルストレージから編集内容を復元（背景色も含む）
            const restored = loadFromLocalStorage();
            if (restored) {
                console.log('編集内容を復元しました');
            } else {
                console.log('新規セッションを開始します');
                
                // 復元データがない場合のみ、個別保存された背景色を確認
                const savedBgColor = localStorage.getItem('kidsCanvasBackgroundColor');
                if (savedBgColor) {
                    currentBackgroundColor = savedBgColor;
                    setBackgroundColor(savedBgColor);
                    console.log('背景色を復元しました:', savedBgColor);
                }
            }
            
            // 既存のすべてのレイヤーにSVG線形品質を適用し、元の色情報を保存
            setTimeout(() => {
                const allLayerElements = document.querySelectorAll('.layer-element');
                allLayerElements.forEach(layerElement => {
                    ensureSVGLineQuality(layerElement);
                    initializeOriginalColors(layerElement);
                });
                console.log('既存レイヤーのSVG線形品質と元の色情報を確保しました:', allLayerElements.length, 'レイヤー');
            }, 100);
            
        // 検索機能を初期化
        initializeMaterialSearch();
        
        // ページネーション使用時の検索状態管理
        initializePaginationSearch();
        
        // カラーパレットを初期化（初期状態は非表示）
        const colorPalette = document.getElementById('colorPalette');
        if (colorPalette) {
            // レイヤー未選択時は非表示
            colorPalette.style.display = 'none';
        }
        
        // 素材にクリックイベントを追加
            const materialItems = document.querySelectorAll('.material-item');
            materialItems.forEach(item => {
                item.addEventListener('click', function() {
                    addMaterialToCanvas(this);
                });
            });
            
            // ボタンイベントを追加（存在チェック付き）
            const rotateBtn = document.getElementById('rotateBtn');
            if (rotateBtn) {
                rotateBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    rotateSelectedLayer();
                });
            }
            
            const scaleDownBtn = document.getElementById('scaleDownBtn');
            if (scaleDownBtn) {
                scaleDownBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    scaleDownSelectedLayer();
                });
            }
            
            const scaleUpBtn = document.getElementById('scaleUpBtn');
            if (scaleUpBtn) {
                scaleUpBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    scaleUpSelectedLayer();
                });
            }
            
            const deleteBtn = document.getElementById('deleteBtn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    deleteSelectedLayer();
                });
            }
            
            const springThemeBtn = document.getElementById('springThemeBtn');
            if (springThemeBtn) {
                springThemeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    applySeasonalTheme('spring');
                });
            }
            
            const exportBtn = document.getElementById('exportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    exportToPNG();
                });
            }
            
            const clearBtn = document.getElementById('clearBtn');
            if (clearBtn) {
                clearBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    clearAll();
                });
            }
            
            // アップロードボタンのイベントリスナー
            const uploadBtn = document.getElementById('uploadBtn');
            if (uploadBtn) {
                uploadBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    openUploadModal();
                });
            }
            
            // アップロードモーダルのイベントリスナー
            const submitUploadBtn = document.getElementById('submitUploadBtn');
            if (submitUploadBtn) {
                submitUploadBtn.addEventListener('click', submitArtwork);
            }
            
            // フォームバリデーション
            const artworkTitle = document.getElementById('artworkTitle');
            const penName = document.getElementById('penName');
            const agreeUploadTerms = document.getElementById('agreeUploadTerms');
            
            if (artworkTitle && penName && agreeUploadTerms && submitUploadBtn) {
                function validateUploadForm() {
                    const titleValid = artworkTitle.value.trim().length > 0 && artworkTitle.value.trim().length <= 100;
                    const penNameValid = penName.value.trim().length > 0 && penName.value.trim().length <= 50;
                    const termsAgreed = agreeUploadTerms.checked;
                    
                    submitUploadBtn.disabled = !(titleValid && penNameValid && termsAgreed);
                }
                
                artworkTitle.addEventListener('input', validateUploadForm);
                penName.addEventListener('input', validateUploadForm);
                agreeUploadTerms.addEventListener('change', validateUploadForm);
            }
            
            // 文字数カウンター
            const descriptionTextarea = document.getElementById('artworkDescription');
            const descriptionCount = document.getElementById('descriptionCount');
            
            if (descriptionTextarea && descriptionCount) {
                descriptionTextarea.addEventListener('input', function() {
                    const count = this.value.length;
                    descriptionCount.textContent = count;
                    
                    if (count > 1000) {
                        descriptionCount.style.color = '#dc3545';
                    } else {
                        descriptionCount.style.color = '#6c757d';
                    }
                });
            }
            
            // モーダル表示時にフォームリセット
            const uploadArtworkModal = document.getElementById('uploadArtworkModal');
            if (uploadArtworkModal) {
                uploadArtworkModal.addEventListener('show.bs.modal', function() {
                    resetUploadModal();
                    validateUploadForm();
                });
            }
            
            // 背景色パネルのイベントリスナーを設定
            const transparentBtn = document.querySelector('.bg-color-btn[data-color="transparent"]');
            if (transparentBtn) {
                transparentBtn.addEventListener('click', function() {
                    setBackgroundColor('transparent');
                    updateBackgroundColorSelection('transparent');
                });
            }
            
            // カスタム背景色ピッカーのイベントリスナーを設定（即座適用）
            const customBgColorInput = document.getElementById('customBgColor');
            if (customBgColorInput) {
                customBgColorInput.addEventListener('input', function() {
                    const color = this.value;
                    setBackgroundColor(color);
                    updateBackgroundColorSelection(color);
                });
            }
            
            // グローバルマウスイベントを追加
            document.addEventListener('mousemove', onDrag);
            document.addEventListener('mouseup', endDrag);
            
            // グローバルタッチイベントを追加（スマホ対応）
            document.addEventListener('touchmove', function(e) {
                // ドラッグ中のみスクロールを防ぐ
                if (isDragging) {
                    e.preventDefault();
                    onDrag(e);
                }
            }, { passive: false });
            document.addEventListener('touchend', endDrag);
            
            // キャンバス領域でのタッチスクロール制御
            const canvasContainer = document.querySelector('.canvas-container');
            if (canvasContainer) {
                canvasContainer.addEventListener('touchmove', function(e) {
                    // キャンバス内でドラッグ中のみスクロールを防ぐ
                    if (isDragging) {
                        e.stopPropagation();
                    }
                }, { passive: true });
            }
            
            // ポインターイベントも追加（デベロッパーツール対応）
            if ('onpointermove' in window) {
                document.addEventListener('pointermove', onDrag);
                document.addEventListener('pointerup', endDrag);
            }
            
            // キャンバス外をクリックしたときの選択解除
            document.addEventListener('click', function(e) {
                const canvas = document.getElementById('mainCanvas');
                const manipulationControls = document.querySelector('.manipulation-controls');
                const actionControls = document.querySelector('.action-controls');
                const colorPalette = document.getElementById('colorPalette');
                
                // 旧モバイルピッカー関連の処理は削除（新実装では不要）
                
                // キャンバス、レイヤー要素、操作ボタンエリア、カラーパレットのクリックは除外
                if (!canvas.contains(e.target) && 
                    !e.target.closest('.layer-element') && 
                    !manipulationControls.contains(e.target) &&
                    !actionControls.contains(e.target) &&
                    !(colorPalette && colorPalette.contains(e.target))) {
                    deselectLayer();
                }
            });
            
            // キャンバス背景をクリックしたときの選択解除
            document.getElementById('canvasBackground').addEventListener('click', function() {
                deselectLayer();
            });
            
            // ボタンの初期状態を設定
            updateRotateButtonState();
            updateScaleDownButtonState();
            updateScaleUpButtonState();
            updateDeleteButtonState();
            updateSeasonalThemeButtonState();
            updateSelectedLayerTitle();

            console.log(`${materialItems.length}個の素材を読み込み完了`);
            console.log('素材をクリックしてキャンバスに配置してください');
            console.log('レイヤーをクリック/タップして選択し、ドラッグで移動できます（デベロッパーツール対応）');
            console.log('レイヤーを選択して各種操作ボタンで回転・拡大縮小・前面背面移動ができます');
            
            // PC用：背景に流れる素材を生成（APIから50件取得して8件表示）
            createFloatingMaterials();
        });
        
        // グローバル変数：取得した全素材データ
        let allFloatingMaterials = [];
        let floatingRotationInterval = null;

        // 配列からランダムにN件選択する関数
        function getRandomMaterials(materials, count) {
            const shuffled = [...materials].sort(() => Math.random() - 0.5);
            return shuffled.slice(0, Math.min(count, materials.length));
        }

        // 30秒ごとに素材をローテーション
        function startFloatingRotation() {
            // 既存のインターバルがあればクリア
            if (floatingRotationInterval) {
                clearInterval(floatingRotationInterval);
            }
            
            floatingRotationInterval = setInterval(() => {
                if (allFloatingMaterials.length > 0) {
                    const newMaterials = getRandomMaterials(allFloatingMaterials, 8);
                    console.log('素材をローテーション:', newMaterials.length + '件');
                    createFloatingMaterials(newMaterials);
                }
            }, 30000); // 30秒
        }

        // 背景に流れる素材を生成する関数（非同期対応）
        async function createFloatingMaterials(materialsToShow = null) {
            // スマホサイズ（600px以下かつ高さ900px以下）のみ実行しない
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const isSmallPhone = viewportWidth <= 600 && viewportHeight <= 900;
            
            if (isSmallPhone) {
                console.log('スマホ判定: 素材を流さない (width:', viewportWidth, 'height:', viewportHeight, ')');
                return;
            }
            
            console.log('タブレット/PC判定: 素材を流します (width:', viewportWidth, 'height:', viewportHeight, ')');
            
            const container = document.getElementById('floatingMaterialsContainer');
            if (!container) return;
            
            let materialsData = [];
            
            if (materialsToShow) {
                // 指定された素材を使用（ローテーション時）
                materialsData = materialsToShow;
            } else if (allFloatingMaterials.length > 0) {
                // 既に取得済みのデータからランダムに8件選択
                materialsData = getRandomMaterials(allFloatingMaterials, 8);
            } else {
                // 初回：APIから50件取得
                try {
                    const response = await fetch('/api/get-floating-materials.php');
                    
                    if (!response.ok) {
                        const errorText = await response.text();
                        console.error('API HTTPエラー:', response.status, errorText);
                        return;
                    }
                    
                    const data = await response.json();
                    
                    if (data.success && data.materials) {
                        allFloatingMaterials = data.materials;
                        console.log(`APIから${allFloatingMaterials.length}件のSVG素材を取得しました`);
                        
                        // その中からランダムに8件選択
                        materialsData = getRandomMaterials(allFloatingMaterials, 8);
                        
                        // 30秒ごとにローテーション開始
                        startFloatingRotation();
                    } else {
                        console.error('素材の取得に失敗しました:', data.error || 'Unknown error');
                        if (data.trace) {
                            console.error('Stack trace:', data.trace);
                        }
                        return;
                    }
                } catch (error) {
                    console.error('素材取得エラー:', error);
                    return;
                }
            }
            
            // 既存の素材をフェードアウト
            const existingMaterials = container.querySelectorAll('.floating-material');
            existingMaterials.forEach(el => {
                el.style.transition = 'opacity 1s ease';
                el.style.opacity = '0';
            });
            
            // 1秒後にクリアして新しい素材を追加
            setTimeout(() => {
                container.innerHTML = '';
                
                // 各素材に対して流れる要素を作成
                materialsData.forEach((materialData, index) => {
                    const floatingEl = document.createElement('div');
                    
                    // ランダムに方向を決定（50%の確率で左→右、50%で右→左）
                    const direction = Math.random() < 0.5 ? 'left-to-right' : 'right-to-left';
                    floatingEl.className = `floating-material ${direction}`;
                    
                    const floatingImg = document.createElement('img');
                    floatingImg.src = materialData.image_path;
                    floatingImg.alt = '';
                    
                    floatingEl.appendChild(floatingImg);
                    
                    // サイズを大きめにランダムに（200px～300px）
                    const size = 200 + Math.random() * 100;
                    floatingEl.style.width = size + 'px';
                    floatingEl.style.height = size + 'px';
                    
                    // 開始位置（高さはランダム、画面全体を使用）
                    // iPad対策: 複数の高さ取得方法を試して最大値を使用
                    const viewportHeight = Math.max(
                        document.documentElement.clientHeight,
                        document.body.clientHeight,
                        window.innerHeight
                    );
                    const startY = Math.random() * Math.max(0, viewportHeight - size);
                    floatingEl.style.top = startY + 'px';
                    floatingEl.style.left = '0';
                    
                    // デバッグ用
                    if (index === 0) {
                        console.log('素材配置 - viewport:', {
                            innerWidth: window.innerWidth,
                            innerHeight: window.innerHeight,
                            clientHeight: document.documentElement.clientHeight,
                            bodyHeight: document.body.clientHeight,
                            使用高さ: viewportHeight
                        });
                    }
                    
                    // アニメーション時間（60秒～120秒でゆっくり）
                    const duration = 60 + Math.random() * 60;
                    floatingEl.style.animationDuration = duration + 's';
                    
                    // 画面の途中からランダムに開始位置を設定（すぐに表示されるように）
                    const delay = -(Math.random() * duration);
                    floatingEl.style.animationDelay = delay + 's';
                    
                    // 透明度をランダムに（0.5～0.7）
                    const opacity = 0.5 + Math.random() * 0.2;
                    floatingEl.style.opacity = opacity;
                    
                    // データ属性を保存
                    floatingEl.setAttribute('data-material-id', materialData.id);
                    floatingEl.setAttribute('data-title', materialData.title);
                    floatingEl.setAttribute('data-svg-path', materialData.svg_path);
                    
                    // クリックイベントを追加（素材をキャンバスに追加）
                    floatingEl.addEventListener('click', function(e) {
                        e.stopPropagation();
                        
                        // DOM内の対応する素材アイテムを検索
                        const materialElement = document.querySelector(`.material-item[data-material-id="${materialData.id}"]`);
                        
                        if (materialElement) {
                            // 素材一覧に存在する場合はDOM要素を渡す
                            addMaterialToCanvas(materialElement);
                        } else {
                            // 素材一覧に存在しない場合はデータオブジェクトを直接渡す
                            addMaterialToCanvas(materialData);
                        }
                        
                        // クリック時の視覚効果
                        const originalFilter = this.style.filter || '';
                        this.style.filter = 'brightness(1.5) saturate(1.5) drop-shadow(0 0 20px rgba(255, 215, 0, 0.8))';
                        
                        setTimeout(() => {
                            this.style.filter = originalFilter;
                        }, 300);
                        
                        console.log('流れている素材をクリックして追加しました:', materialData.title);
                    });
                    
                    container.appendChild(floatingEl);
                });
                
                console.log(`背景に${materialsData.length}個のSVG素材を流しました`);
            }, 1000);
        }
        

        
        // ウィンドウリサイズ時に再生成（モバイル⇔PC切り替え対応）
        let resizeTimeout;
        let lastWidth = window.innerWidth;
        let lastHeight = window.innerHeight;
        
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                const currentWidth = window.innerWidth;
                const currentHeight = window.innerHeight;
                const widthDiff = Math.abs(currentWidth - lastWidth);
                const heightDiff = Math.abs(currentHeight - lastHeight);
                
                // iOSのアドレスバー表示/非表示を無視：幅が変わらず、高さが50px以下の変化は無視
                if (widthDiff === 0 && heightDiff < 50) {
                    return;
                }
                
                // 画面回転などの大きな変化のみ処理
                if (widthDiff < 50 && heightDiff < 50) {
                    return;
                }
                
                lastWidth = currentWidth;
                lastHeight = currentHeight;
                
                const isSmallPhone = currentWidth <= 600 && currentHeight <= 900;
                const container = document.getElementById('floatingMaterialsContainer');
                
                console.log('画面サイズが大きく変更されました:', currentWidth, 'x', currentHeight, 'スマホ判定:', isSmallPhone);
                
                if (!isSmallPhone && container && allFloatingMaterials.length > 0) {
                    // PC/タブレット横向き：素材を再生成
                    const newMaterials = getRandomMaterials(allFloatingMaterials, 8);
                    createFloatingMaterials(newMaterials);
                } else if (isSmallPhone && container) {
                    // スマホ：素材をクリア
                    container.innerHTML = '';
                    if (floatingRotationInterval) {
                        clearInterval(floatingRotationInterval);
                        floatingRotationInterval = null;
                    }
                }
            }, 500);
        });
    </script>

    <?php include '../includes/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- シャッフルボタン機能 -->
    <script>
        document.getElementById('shuffleBtn').addEventListener('click', function() {
            // キャッシュをバイパスしてページをリロード
            const timestamp = new Date().getTime();
            window.location.href = '/compose/kids.php?refresh=' + timestamp;
        });
    </script>
</body>
</html>