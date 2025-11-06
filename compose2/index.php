<?php
require_once '../config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// ページネーション設定
$perPage = 20; // 1ページあたりの表示件数
$page = max(1, intval($_GET['page'] ?? 1)); // 現在のページ（最小値は1）
$offset = ($page - 1) * $perPage;

// 総件数を取得
$countSql = "SELECT COUNT(DISTINCT id) FROM materials WHERE svg_path IS NOT NULL AND svg_path != ''";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute();
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// ページネーション付きでSVG素材を取得
$stmt = $pdo->prepare("
    SELECT DISTINCT id, title, slug, image_path, svg_path, webp_medium_path, category_id, created_at
    FROM materials 
    WHERE svg_path IS NOT NULL 
    AND svg_path != '' 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$perPage, $offset]);
$materials = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>あなたのアトリエ - maruttoart</title>
    <meta name="description" content="SVG素材を組み合わせて作品を作成できるシンプルな編集ツールです。">
    
    <!-- カノニカルURL設定（アトリエツール用 - 1つのツールとして統一） -->
    <link rel="canonical" href="https://<?= $_SERVER['HTTP_HOST'] ?>/compose2/">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- レイアウト専用CSS -->
    <link rel="stylesheet" href="assets/css/layout.css">

    <style>
        /* 検索フォームのスタイル */
        .search-form {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            margin-bottom: 1.5rem;
        }

        .search-form form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 100%;
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
            min-height: 160px;
            max-height: 160px;
            overflow-y: auto;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            border: 2px solid #e3f2fd;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
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
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 15px;
            max-width: 100%;
        }

        .material-item {
            aspect-ratio: 1;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .material-item:hover {
            border-color: #2c5aa0;
            background: #e3f2fd;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(44, 90, 160, 0.2);
        }

        .material-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 4px;
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
        }

        .canvas-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* 出力・削除ボタンエリア */
        .action-controls {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            order: 7;
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
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .controls {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn-export {
            background: #2c5aa0;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            background: #1e3d6f;
            color: white;
        }

        .btn-clear {
            background: #dc3545;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-clear:hover {
            background: #c82333;
            color: white;
        }

        .btn-upload {
            background: #28a745;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-upload:hover {
            background: #218838;
            color: white;
        }

        .btn-rotate {
            background: #f39c12;
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
        
        .btn-rotate:hover {
            background: #e67e22;
            color: white;
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
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-rotate-left {
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
        
        .btn-rotate-left:hover {
            background: #d35400;
            color: white;
        }

        .btn-rotate-left:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-scale-down {
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
        
        .btn-scale-down:hover {
            background: #8e44ad;
            color: white;
        }

        .btn-scale-down:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-scale-up {
            background: #27ae60;
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
        
        .btn-scale-up:hover {
            background: #2ecc71;
            color: white;
        }

        .btn-scale-up:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
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
            background: #e74c3c;
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
        
        .btn-delete:hover {
            background: #c0392b;
            color: white;
        }

        .btn-delete:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-spring-theme {
            background: #2ecc71;
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
        
        .btn-spring-theme:hover {
            background: #27ae60;
            color: white;
        }

        .btn-spring-theme:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            opacity: 0.6;
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
            min-height: 60vh;
            position: relative;
            touch-action: manipulation;
            padding: 10px;
        }

        #mainCanvas {
            width: 100%;
            height: 100%;
            max-width: 500px;
            max-height: 500px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            aspect-ratio: 1;
        }

        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .canvas-container {
                min-height: 45vh; /* スマホでは画面の45%に調整 */
                margin-bottom: 15px;
                padding: 5px; /* スマホでは内側余白をさらに減らす */
            }
            
            #mainCanvas {
                max-width: min(85vw, 450px); /* 画面幅の85%かつ最大450px */
                max-height: min(40vh, 450px); /* 画面高の40%かつ最大450px */
                width: auto;
                height: auto;
            }
            
            .materials-grid {
                grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
                gap: 10px;
            }
            
            /* スマホでのボタンサイズ最適化 */
            .manipulation-buttons,
            .action-buttons {
                gap: 8px;
            }
            
            /* 操作ボタンは指でタッチしやすいサイズを維持 */
            .manipulation-buttons button {
                min-width: 50px;
                min-height: 50px;
                padding: 14px;
            }
            
            /* 出力・削除ボタンはテキストがあるのでサイズ調整 */
            .action-buttons button {
                padding: 10px 16px;
                font-size: 14px;
            }
            
            /* コンテンツ全体のパディング調整 */
            .materials-panel,
            .canvas-area,
            .manipulation-controls,
            .action-controls {
                padding: 15px;
            }
            
            .container {
                padding: 15px;
            }

            /* スマホでのセクションタイトルサイズ調整 */
            .manipulation-controls h3,
            .action-controls h3 {
                font-size: 1rem;
            }

            /* スマホでのヘッダー調整 */
            .manipulation-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .selected-title {
                font-size: 0.8rem;
                min-width: 100px;
            }
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

        /* ナビゲーション */
        .navbar {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
            background-color: #ffffff;
            border-bottom: 1px solid rgba(0,0,0,.125);
        }

        .navbar-brand {
            display: inline-block;
            padding-top: 0.3125rem;
            padding-bottom: 0.3125rem;
            margin-right: 1rem;
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }

        .navbar-brand:hover {
            color: #333;
            text-decoration: none;
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

        /* GDPR Cookie Banner のスタイル */
        #gdpr-banner {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            background-color: #212529 !important;
            color: #ffffff !important;
            padding: 1rem !important;
            z-index: 1050 !important;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3) !important;
        }
        
        #gdpr-banner.hidden,
        .gdpr-cookie-banner.hidden {
            display: none !important;
        }
        
        .gdpr-text {
            font-size: 0.9rem !important;
            line-height: 1.4 !important;
            color: #ffffff !important;
        }
        
        .gdpr-text a {
            color: #ffffff !important;
            text-decoration: underline !important;
        }
        
        .gdpr-text a:hover {
            color: #e9ecef !important;
        }
        
        .gdpr-buttons {
            margin-top: 1rem !important;
            display: flex !important;
            gap: 0.5rem !important;
            flex-wrap: wrap !important;
        }
        
        .gdpr-buttons .btn {
            flex: 0 0 auto !important;
            white-space: nowrap !important;
        }
        
        /* GDPR専用のボタンスタイル（より強力な優先度） */
        #gdpr-banner .btn-outline-light {
            color: #ffffff !important;
            border-color: #ffffff !important;
            background-color: transparent !important;
            border-width: 1px !important;
            border-style: solid !important;
        }

        #gdpr-banner .btn-outline-light:hover {
            color: #212529 !important;
            background-color: #ffffff !important;
            border-color: #ffffff !important;
        }

        #gdpr-banner .btn-success {
            color: #000000 !important;
            background-color: #ffffff !important;
            border-color: #ffffff !important;
        }

        #gdpr-banner .btn-success:hover {
            color: #000000 !important;
            background-color: #f8f9fa !important;
            border-color: #f8f9fa !important;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="/">maruttoart</a>
        </div>
    </nav>

    <div class="container">
        <!-- ヘッダー -->
        <div class="header">
            <h1>あなたのアトリエ</h1>
        </div>

        <!-- メインコンテンツ -->
        <div class="main-content">
            <!-- 素材選択エリア -->
            <div class="materials-panel">
                <h3><i class="bi bi-collection"></i> 素材一覧</h3>
                
                <!-- 検索フォーム -->
                <div class="search-form">
                    <form class="d-flex align-items-center" onsubmit="return false;">
                        <input type="text" 
                               id="materialSearch" 
                               placeholder="全ての素材から検索（スペース区切りでOR検索可）" 
                               class="search-input form-control me-2">
                        <button type="button" 
                                id="clearSearch" 
                                class="btn btn-outline-secondary ms-2" 
                                style="display: none;">クリア</button>
                    </form>
                </div>
                
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
                </div>
                
                <!-- ページネーション -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <!-- 前のページ -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="pagination-btn">
                            前へ
                        </a>
                    <?php endif; ?>
                    
                    <!-- 次のページ -->
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="pagination-btn">
                            次へ
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- ページ情報 -->
                <div class="pagination-info">
                    <?= $page ?> / <?= $totalPages ?> ページ （全 <?= $totalItems ?> 件）
                </div>
                <?php endif; ?>
                
                <?php if (empty($materials)): ?>
                <!-- 素材が見つからない場合のメッセージ -->
                <div style="text-align: center; padding: 2rem; color: #6c757d;">
                    <p>素材が見つかりませんでした。</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- カラーセクション -->
            <div id="color-section" class="color-section">
                <h3><i class="bi bi-palette"></i> 色を変更 <small class="text-muted">（リアルタイム即時反映）</small></h3>
                <div id="color-panel-content" class="color-panel-content">
                    <div id="colorPalette" class="color-palette">
                        <div class="text-center text-muted">
                            <div class="mb-2">素材を選択してください</div>
                            <div>選択した素材の色を変更できます</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- キャンバスエリア -->
            <div class="canvas-area">
                <div class="canvas-header">
                    <h3>あなたのキャンバス</h3>
                </div>
                
                <div class="canvas-container">
                    <svg id="mainCanvas" 
                         viewBox="0 0 1024 1024" 
                         xmlns="http://www.w3.org/2000/svg">
                        <!-- 背景 -->
                        <rect id="canvasBackground" 
                              x="0" y="0" 
                              width="1024" height="1024" 
                              fill="white"/>
                        <!-- レイヤーがここに追加されます -->
                    </svg>
                </div>
            </div>
                <!-- 操作ボタンエリア -->
                <div class="manipulation-controls">
                <div class="manipulation-header">
                    <h3><i class="bi bi-gear"></i> レイヤー操作</h3>
                    <div class="selected-layer-info">
                        <span id="selectedLayerTitle" class="selected-title">レイヤーを選択してください</span>
                    </div>
                </div>
                <div class="manipulation-buttons">
                    <button id="scaleDownBtn" class="btn btn-scale-down" title="選択したレイヤーを20%縮小">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" x2="16.65" y1="21" y2="16.65"/><line x1="8" x2="14" y1="11" y2="11"/></svg>
                    </button>
                    <button id="scaleUpBtn" class="btn btn-scale-up" title="選択したレイヤーを25%拡大">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" x2="16.65" y1="21" y2="16.65"/><line x1="8" x2="14" y1="11" y2="11"/><line x1="11" x2="11" y1="8" y2="14"/></svg>
                    </button>
                    <button id="rotateLeftBtn" class="btn btn-rotate-left" title="選択したレイヤーを15度左回転">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-rotate-ccw-icon lucide-rotate-ccw"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                    </button>
                    <button id="rotateBtn" class="btn btn-rotate" title="選択したレイヤーを15度右回転">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
                    </button>
                    <button id="bringFrontBtn" class="btn btn-bring-front" title="選択したレイヤーを1つ前面に移動">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bring-to-front-icon lucide-bring-to-front"><rect x="8" y="8" width="8" height="8" rx="2"/><path d="M4 10a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2"/><path d="M14 20a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2"/></svg>
                    </button>
                    <button id="sendBackBtn" class="btn btn-send-back" title="選択したレイヤーを1つ背面に移動">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-send-to-back-icon lucide-send-to-back"><rect x="14" y="14" width="8" height="8" rx="2"/><rect x="2" y="2" width="8" height="8" rx="2"/><path d="M7 14v1a2 2 0 0 0 2 2h1"/><path d="M14 7h1a2 2 0 0 1 2 2v1"/></svg>
                    </button>
                    <button id="deleteBtn" class="btn btn-delete" title="選択したレイヤーを削除">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2-icon lucide-trash-2"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                    <button id="springThemeBtn" class="btn btn-spring-theme" title="春テーマを適用">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flower-icon lucide-flower"><circle cx="12" cy="12" r="3"/><path d="M12 16.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 1 1 4.5 4.5 4.5 4.5 0 1 1-4.5 4.5"/><path d="M12 7.5V9"/><path d="M7.5 12H9"/><path d="M16.5 12H15"/><path d="M12 16.5V15"/><path d="m8 8 1.88 1.88"/><path d="M14.12 9.88 16 8"/><path d="m8 16 1.88-1.88"/><path d="M14.12 14.12 16 16"/></svg>
                    </button>
                    <button id="summerThemeBtn" class="btn btn-summer-theme" title="夏テーマを適用">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sun"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
                    </button>
                    <button id="autumnThemeBtn" class="btn btn-autumn-theme" title="秋テーマを適用">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-leaf"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>
                    </button>
                    <button id="winterThemeBtn" class="btn btn-winter-theme" title="冬テーマを適用">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-snowflake-icon lucide-snowflake"><path d="m10 20-1.25-2.5L6 18"/><path d="M10 4 8.75 6.5 6 6"/><path d="m14 20 1.25-2.5L18 18"/><path d="m14 4 1.25 2.5L18 6"/><path d="m17 21-3-6h-4"/><path d="m17 3-3 6 1.5 3"/><path d="M2 12h6.5L10 9"/><path d="m20 10-1.5 2 1.5 2"/><path d="M22 12h-6.5L14 15"/><path d="m4 10 1.5 2L4 14"/><path d="m7 21 3-6-1.5-3"/><path d="m7 3 3 6h4"/></svg>
                    </button>
                    <button id="monochromeThemeBtn" class="btn btn-monochrome-theme" title="白黒テーマを適用">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-panda-icon lucide-panda"><path d="M11.25 17.25h1.5L12 18z"/><path d="m15 12 2 2"/><path d="M18 6.5a.5.5 0 0 0-.5-.5"/><path d="M20.69 9.67a4.5 4.5 0 1 0-7.04-5.5 8.35 8.35 0 0 0-3.3 0 4.5 4.5 0 1 0-7.04 5.5C2.49 11.2 2 12.88 2 14.5 2 19.47 6.48 22 12 22s10-2.53 10-7.5c0-1.62-.48-3.3-1.3-4.83"/><path d="M6 6.5a.495.495 0 0 1 .5-.5"/><path d="m9 12-2 2"/></svg>
                    </button>
                    <button id="sepiaThemeBtn" class="btn btn-sepia-theme" title="セピアテーマを適用">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-coffee-icon lucide-coffee"><path d="M10 2v2"/><path d="M14 2v2"/><path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1"/><path d="M6 2v2"/></svg>
                    </button>
                </div>
            </div>

            <!-- 背景パネル -->
            <div class="background-controls">
                <h3><i class="bi bi-palette"></i> 背景</h3>
                <div class="background-panel">
                    <!-- 透明背景ボタン -->
                    <div class="bg-color-section mb-3">
                        <div class="bg-color-palette d-flex justify-content-center align-items-center gap-2">
                            <button type="button" class="bg-color-btn active" data-color="transparent" title="透明（背景なし）">
                                <div class="bg-swatch transparent-bg"></div>
                            </button>
                            
                            <!-- カスタムカラーピッカー -->
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" id="customBgColor" class="form-control form-control-color" 
                                       style="width: 50px; height: 38px;" title="カスタム背景色を選択" value="#ffffff">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 出力・削除ボタンエリア -->
            <div class="action-controls">
                <h3><i class="bi bi-tools"></i> 出力・削除</h3>
                <div class="action-buttons">
                    <button id="exportBtn" class="btn btn-export">
                        <i class="bi bi-download"></i> PNG出力
                    </button>
                    <button id="uploadBtn" class="btn btn-upload">
                        <i class="bi bi-cloud-upload"></i> みんなの作品集に投稿
                    </button>
                    <button id="clearBtn" class="btn btn-clear">
                        <i class="bi bi-trash"></i> 全て削除
                    </button>
                </div>
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
        function addMaterialToCanvas(element) {
            const materialId = element.dataset.materialId;
            const svgPath = element.dataset.svgPath;
            const title = element.dataset.title;

            console.log('素材追加:', title);

            // クリック効果
            element.classList.add('clicked');
            setTimeout(() => element.classList.remove('clicked'), 300);

            // SVGファイルを読み込み
            fetch('/' + svgPath)
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
                                rotation: 0
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
            
            // 変換を適用: 移動→スケール→中心回転
            const transformString = `translate(${layer.transform.x}, ${layer.transform.y}) scale(${layer.transform.scale}) rotate(${layer.transform.rotation}, ${centerX}, ${centerY})`;
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

            // レイヤークリックイベントを追加
            layerGroup.addEventListener('click', function(e) {
                e.stopPropagation();
                selectLayer(layer.id);
            });

            // マウスドラッグイベントを追加
            layerGroup.addEventListener('mousedown', function(e) {
                e.stopPropagation();
                startDrag(e, layer.id);
            });

            // タッチイベントを追加（スマホ対応）
            layerGroup.addEventListener('touchstart', function(e) {
                e.stopPropagation();
                // レイヤーをタッチした時のみスクロールを防ぐ
                e.preventDefault();
                startDrag(e.touches[0], layer.id); // 最初のタッチポイントを使用
            }, { passive: false });

            // ポインターイベントも追加（デベロッパーツール対応）
            if ('onpointerdown' in window) {
                layerGroup.addEventListener('pointerdown', function(e) {
                    e.stopPropagation();
                    startDrag(e, layer.id);
                });
            }

            // 選択状態に応じてスタイルを設定
            if (selectedLayerId === layer.id) {
                layerGroup.style.cursor = 'move';
                layerGroup.style.filter = 'drop-shadow(0 0 3px rgba(0,123,255,0.8))';
                console.log(`Layer ${layer.id} is SELECTED - applying blue glow`);
            } else {
                layerGroup.style.cursor = 'pointer';
                layerGroup.style.filter = '';
                console.log(`Layer ${layer.id} is not selected (selectedLayerId: ${selectedLayerId})`);
            }
            
            // SVG線形品質の属性を自動設定
            ensureSVGLineQuality(layerGroup);
            
            // 元の色情報をdata属性として保存（初回のみ）
            initializeOriginalColors(layerGroup);
            
            console.log(`Layer ${layer.id} rendered: ${transformString} (center: ${centerX}, ${centerY})`);
        }

        // レイヤー選択機能
        function selectLayer(layerId) {
            selectedLayerId = layerId;
            console.log(`Layer ${layerId} selected - selectedLayerId is now: ${selectedLayerId}`);
            
            // 選択中の素材タイトルを更新
            updateSelectedLayerTitle();
            
            // 全レイヤーを再描画して選択状態を反映
            layers.forEach(layer => {
                renderLayer(layer);
            });

            // ボタンの状態を更新
            updateRotateButtonState();
            updateScaleDownButtonState();
            updateScaleUpButtonState();
            updateLayerMoveButtonState();
            updateDeleteButtonState();
            
            // カラーパネルを表示
            const selectedLayer = layers.find(layer => layer.id === layerId);
            if (selectedLayer) {
                showColorPanel(selectedLayer);
            }
            
            console.log(`Selection complete - selectedLayerId: ${selectedLayerId}`);
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
                updateLayerMoveButtonState();
                updateDeleteButtonState();
                
                // カラーパネルを非表示
                hideColorPanel();
            }
        }

        // ドラッグ開始（マウス・タッチ・ポインター対応）
        function startDrag(e, layerId) {
            if (selectedLayerId !== layerId) {
                selectLayer(layerId);
            }
            
            isDragging = true;
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
            
            dragStartPos.x = svgPoint.x;
            dragStartPos.y = svgPoint.y;
            
            const layer = layers.find(l => l.id === layerId);
            if (layer) {
                dragStartTransform.x = layer.transform.x;
                dragStartTransform.y = layer.transform.y;
            }
            
            console.log(`Drag started for layer ${layerId} at SVG coordinates:`, svgPoint, `Event type: ${e.type}`);
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
            if (!isDragging || selectedLayerId === null) return;
            
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
            }
        }

        // 選択されたレイヤーを15度右回転
        function rotateSelectedLayer() {
            if (selectedLayerId === null) {
                alert('回転させるレイヤーを選択してください。');
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

        // 選択されたレイヤーを15度左回転
        function rotateLeftSelectedLayer() {
            if (selectedLayerId === null) {
                alert('回転させるレイヤーを選択してください。');
                return;
            }

            const layer = layers.find(l => l.id === selectedLayerId);
            if (layer) {
                layer.transform.rotation -= 15;
                // 0度未満の場合は360度から引く
                if (layer.transform.rotation < 0) {
                    layer.transform.rotation += 360;
                }
                
                // 現在の選択IDを保存
                const currentSelectedId = selectedLayerId;
                
                // 全レイヤーを再描画（選択状態を保持）
                layers.forEach(l => {
                    renderLayer(l);
                });
                
                // 選択中の素材タイトルを更新
                updateSelectedLayerTitle();
                
                console.log(`Layer ${currentSelectedId} rotated left to ${layer.transform.rotation} degrees`);
                
                // ローカルストレージに保存
                saveToLocalStorage();
            }
        }

        // 選択されたレイヤーを20%縮小
        function scaleDownSelectedLayer() {
            if (selectedLayerId === null) {
                alert('縮小させるレイヤーを選択してください。');
                return;
            }

            const layer = layers.find(l => l.id === selectedLayerId);
            if (layer) {
                // 20%縮小（現在のサイズの80%にする）
                layer.transform.scale *= 0.8;
                
                // 最小サイズ制限（10%まで）
                if (layer.transform.scale < 0.1) {
                    layer.transform.scale = 0.1;
                    alert('これ以上縮小できません。（最小10%）');
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
                alert('拡大させるレイヤーを選択してください。');
                return;
            }

            const layer = layers.find(l => l.id === selectedLayerId);
            if (layer) {
                // 25%拡大（現在のサイズの125%にする）
                layer.transform.scale *= 1.25;
                
                // 最大サイズ制限（500%まで）
                if (layer.transform.scale > 5.0) {
                    layer.transform.scale = 5.0;
                    alert('これ以上拡大できません。（最大500%）');
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

        // 選択されたレイヤーを1つ前面に移動
        function bringLayerToFront() {
            if (selectedLayerId === null) {
                alert('前面に移動させるレイヤーを選択してください。');
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
            updateLayerMoveButtonState();
            
            // 選択中の素材タイトルを更新
            updateSelectedLayerTitle();
            
            console.log(`Layer ${currentSelectedId} moved to front (index: ${currentIndex + 1})`);
            
            // ローカルストレージに保存
            saveToLocalStorage();
        }

        // 選択されたレイヤーを1つ背面に移動
        function sendLayerToBack() {
            if (selectedLayerId === null) {
                alert('背面に移動させるレイヤーを選択してください。');
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
            updateLayerMoveButtonState();
            
            // 選択中の素材タイトルを更新
            updateSelectedLayerTitle();
            
            console.log(`Layer ${currentSelectedId} moved to back (index: ${currentIndex - 1})`);
            
            // ローカルストレージに保存
            saveToLocalStorage();
        }

        // 選択されたレイヤーを削除
        function deleteSelectedLayer() {
            if (selectedLayerId === null) {
                alert('削除するレイヤーを選択してください。');
                return;
            }

            // 削除の確認
            if (!confirm('選択したレイヤーを削除しますか？この操作は元に戻せません。')) {
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
            updateLayerMoveButtonState();
            updateDeleteButtonState();
            
            console.log(`Layer deleted (was at index: ${currentIndex})`);
            
            // ローカルストレージに保存
            saveToLocalStorage();
        }

        // 季節テーマを適用（選択されているレイヤーの色をランダムに変更）
        function applySeasonalTheme(season) {
            console.log(`=== applySeasonalTheme START (${season}) ===`);
            console.log('selectedLayerId:', selectedLayerId);
            
            if (!selectedLayerId) {
                alert('レイヤーを選択してください');
                return;
            }
            
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
            
            // カラーパレットを更新（色見本に変更を反映）
            updateColorPaletteAfterThemeChange(layer);
            
            console.log(`${palette.name}が適用されました: ${colorChangedCount}個の色要素を変更`);
            console.log('カラーパレットも更新されました');
            console.log(`=== applySeasonalTheme END (${season}) ===`);
        }

        // テーマ適用後にカラーパレットを更新
        function updateColorPaletteAfterThemeChange(layer) {
            console.log(`Updating color palette after theme change for layer ${layer.id}`);
            
            // 現在のカラーパレットを取得
            const colorPalette = document.getElementById('colorPalette');
            if (!colorPalette || !colorPalette.classList.contains('loaded')) {
                console.log('Color palette not loaded, skipping update');
                return;
            }
            
            // 古いカラーパレットをクリア（固定サイズ維持）
            colorPalette.innerHTML = `
                <div class="text-center text-muted" style="width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px;">
                    <div class="mb-2"><i class="bi bi-arrow-clockwise"></i> 色を更新中...</div>
                    <div>テーマ適用後の新しい色を取得しています</div>
                </div>
            `;
            
            // 少し遅延してから新しい色を抽出
            setTimeout(() => {
                extractColorsFromLayer(layer);
            }, 300);
        }

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
            });
            
            console.log('Original colors initialized and line caps applied for', svgElements.length, 'elements');
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
                const editorData = {
                    layers: layers,
                    selectedLayerId: selectedLayerId,
                    nextLayerId: nextLayerId,
                    currentBackgroundColor: currentBackgroundColor,
                    timestamp: Date.now()
                };
                
                localStorage.setItem('compose2_editor_data', JSON.stringify(editorData));
                console.log('編集内容をローカルストレージに保存しました');
            } catch (error) {
                console.error('ローカルストレージ保存エラー:', error);
            }
        }

        // ローカルストレージから編集内容を読み込み
        function loadFromLocalStorage() {
            try {
                const savedData = localStorage.getItem('compose2_editor_data');
                if (savedData) {
                    const editorData = JSON.parse(savedData);
                    
                    // データの復元
                    layers = editorData.layers || [];
                    selectedLayerId = editorData.selectedLayerId || null;
                    nextLayerId = editorData.nextLayerId || 1;
                    currentBackgroundColor = editorData.currentBackgroundColor || 'transparent';
                    
                    // レイヤーを再描画
                    layers.forEach(layer => {
                        renderLayer(layer);
                    });
                    
                    // UI状態を更新
                    updateSelectedLayerTitle();
                    updateRotateButtonState();
                    updateScaleDownButtonState();
                    updateScaleUpButtonState();
                    updateLayerMoveButtonState();
                    updateDeleteButtonState();
                    updateSeasonalThemeButtonState();
                    
                    // 背景色を復元
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
                localStorage.removeItem('compose2_editor_data');
                console.log('ローカルストレージをクリアしました');
            } catch (error) {
                console.error('ローカルストレージクリアエラー:', error);
            }
        }

        // 回転ボタンの状態を更新
        function updateRotateButtonState() {
            const rotateBtn = document.getElementById('rotateBtn');
            const rotateLeftBtn = document.getElementById('rotateLeftBtn');
            
            console.log(`updateRotateButtonState: selectedLayerId = ${selectedLayerId}`);
            
            if (selectedLayerId !== null) {
                rotateBtn.disabled = false;
                rotateBtn.title = '選択したレイヤーを15度右回転';
                rotateLeftBtn.disabled = false;
                rotateLeftBtn.title = '選択したレイヤーを15度左回転';
                console.log('Rotate buttons ENABLED');
            } else {
                rotateBtn.disabled = true;
                rotateBtn.title = 'レイヤーを選択してから回転できます';
                rotateLeftBtn.disabled = true;
                rotateLeftBtn.title = 'レイヤーを選択してから回転できます';
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

        // レイヤー移動ボタンの状態を更新
        function updateLayerMoveButtonState() {
            const bringFrontBtn = document.getElementById('bringFrontBtn');
            const sendBackBtn = document.getElementById('sendBackBtn');
            
            if (selectedLayerId !== null) {
                const currentIndex = layers.findIndex(l => l.id === selectedLayerId);
                
                // 前面移動ボタン（最前面でない場合は有効）
                if (currentIndex >= 0 && currentIndex < layers.length - 1) {
                    bringFrontBtn.disabled = false;
                    bringFrontBtn.title = '選択したレイヤーを1つ前面に移動';
                } else {
                    bringFrontBtn.disabled = true;
                    bringFrontBtn.title = '既に最前面です';
                }
                
                // 背面移動ボタン（最背面でない場合は有効）
                if (currentIndex > 0) {
                    sendBackBtn.disabled = false;
                    sendBackBtn.title = '選択したレイヤーを1つ背面に移動';
                } else {
                    sendBackBtn.disabled = true;
                    sendBackBtn.title = '既に最背面です';
                }
            } else {
                bringFrontBtn.disabled = true;
                bringFrontBtn.title = 'レイヤーを選択してから移動できます';
                sendBackBtn.disabled = true;
                sendBackBtn.title = 'レイヤーを選択してから移動できます';
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
            const themeButtons = [
                { id: 'springThemeBtn', title: '春テーマを適用' },
                { id: 'summerThemeBtn', title: '夏テーマを適用' },
                { id: 'autumnThemeBtn', title: '秋テーマを適用' },
                { id: 'winterThemeBtn', title: '冬テーマを適用' },
                { id: 'monochromeThemeBtn', title: '白黒テーマを適用' },
                { id: 'sepiaThemeBtn', title: 'セピアテーマを適用' }
            ];
            
            themeButtons.forEach(buttonInfo => {
                const button = document.getElementById(buttonInfo.id);
                if (button) {
                    // 季節テーマボタンは常に有効
                    button.disabled = false;
                    button.title = buttonInfo.title;
                }
            });
        }

        // 選択中の素材タイトルを更新
        function updateSelectedLayerTitle() {
            const titleElement = document.getElementById('selectedLayerTitle');
            
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
                alert('素材を追加してからPNG出力してください。');
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
                            updateLayerMoveButtonState();
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
                alert('素材を追加してから作品を投稿してください。');
                return;
            }

            // プレビュー画像を生成
            generateUploadPreview();
            
            // モーダルを表示
            const modal = new bootstrap.Modal(document.getElementById('uploadArtworkModal'));
            modal.show();
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
                    updateLayerMoveButtonState();
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
            
            // 高解像度出力用キャンバス作成（1024px）
            const outputCanvas = document.createElement('canvas');
            const outputSize = 1024;
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
                        updateLayerMoveButtonState();
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

        // 作品アップロード実行
        function submitArtwork() {
            const form = document.getElementById('uploadArtworkForm');
            const formData = new FormData(form);
            
            // バリデーション
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const agreeTerms = document.getElementById('agreeUploadTerms').checked;
            if (!agreeTerms) {
                alert('利用規約への同意が必要です。');
                return;
            }
            
            // アップロード処理開始
            const submitBtn = document.getElementById('submitUploadBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> アップロード中...';
            
            // プログレスバー表示
            showUploadProgress();
            
            generatePNGForUpload(function(blob) {
                if (!blob) {
                    showUploadError('PNG変換に失敗しました。');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    return;
                }
                
                // FormDataに画像を追加
                formData.append('artwork', blob, `artwork-${Date.now()}.png`);
                
                // 投稿=フリー素材提供同意とみなす
                formData.append('free_material_consent', '1');
                
                // 使用素材IDを収集してFormDataに追加
                const usedMaterialIds = getUsedMaterialIds();
                if (usedMaterialIds.length > 0) {
                    formData.append('used_material_ids', usedMaterialIds.join(','));
                }
                
                // サーバーにアップロード
                fetch('/api/upload-artwork.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(responseText => {
                    console.log('Server response:', responseText);
                    
                    let data;
                    try {
                        data = JSON.parse(responseText);
                    } catch (parseError) {
                        console.error('JSON parse error:', parseError);
                        console.error('Response text:', responseText);
                        throw new Error('サーバーレスポンスの解析に失敗しました');
                    }
                    
                    if (data.success) {
                        showUploadSuccess(data.data);
                        
                        // 3秒後にモーダルを閉じる
                        setTimeout(() => {
                            const modal = bootstrap.Modal.getInstance(document.getElementById('uploadArtworkModal'));
                            modal.hide();
                            
                            // フォームリセット
                            form.reset();
                            resetUploadModal();
                        }, 3000);
                        
                        console.log('作品アップロード完了:', data);
                    } else {
                        let errorMsg = data.error || 'アップロードに失敗しました';
                        if (data.details) {
                            console.error('Error details:', data.details);
                        }
                        showUploadError(errorMsg);
                    }
                })
                .catch(error => {
                    console.error('アップロードエラー:', error);
                    let errorMessage = 'アップロードに失敗しました';
                    if (error.message.includes('JSON')) {
                        errorMessage += '（サーバーエラーが発生しています）';
                    } else {
                        errorMessage += ': ' + error.message;
                    }
                    showUploadError(errorMessage);
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
            });
        }

        // アップロード進捗表示
        function showUploadProgress() {
            const progressHtml = `
                <div class="upload-progress">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 100%">アップロード中...</div>
                    </div>
                </div>
            `;
            
            const container = document.getElementById('uploadPreviewContainer');
            container.insertAdjacentHTML('afterend', progressHtml);
        }

        // アップロード成功表示
        function showUploadSuccess(data) {
            // 既存のメッセージを削除
            removeUploadMessages();
            
            const successHtml = `
                <div class="upload-success">
                    <h6><i class="bi bi-check-circle-fill"></i> アップロード完了</h6>
                    <p class="mb-1">作品「${data.title}」のアップロードが完了しました！</p>
                    <small class="text-muted">
                        管理者による承認後に公開されます。<br>
                        本日の残り投稿可能数: ${data.remaining_uploads}件
                    </small>
                </div>
            `;
            
            const container = document.getElementById('uploadPreviewContainer');
            container.insertAdjacentHTML('afterend', successHtml);
        }

        // アップロードエラー表示
        function showUploadError(errorMessage) {
            // 既存のメッセージを削除
            removeUploadMessages();
            
            const errorHtml = `
                <div class="upload-error">
                    <h6><i class="bi bi-exclamation-triangle-fill"></i> アップロードエラー</h6>
                    <p class="mb-0">${errorMessage}</p>
                </div>
            `;
            
            const container = document.getElementById('uploadPreviewContainer');
            container.insertAdjacentHTML('afterend', errorHtml);
        }

        // アップロードメッセージを削除
        function removeUploadMessages() {
            const progress = document.querySelector('.upload-progress');
            const success = document.querySelector('.upload-success');
            const error = document.querySelector('.upload-error');
            
            if (progress) progress.remove();
            if (success) success.remove();
            if (error) error.remove();
        }

        // アップロードモーダルリセット
        function resetUploadModal() {
            removeUploadMessages();
            
            const previewContainer = document.getElementById('uploadPreviewContainer');
            previewContainer.innerHTML = `
                <div class="preview-placeholder">
                    <i class="bi bi-image"></i>
                    <p>作品のプレビューがここに表示されます</p>
                </div>
            `;
            
            document.getElementById('descriptionCount').textContent = '0';
            document.getElementById('submitUploadBtn').disabled = true;
        }

        // 背景色を設定する関数
        function setBackgroundColor(color) {
            currentBackgroundColor = color;
            
            const svg = document.getElementById('mainCanvas');
            if (!svg) {
                return;
            }
            
            // 既存の背景rect要素を削除
            const existingBg = svg.querySelector('#svg-background');
            if (existingBg) {
                existingBg.remove();
            }
            
            // 既存のcanvasBackground要素を処理
            const canvasBackground = svg.querySelector('#canvasBackground');
            
            if (color === 'transparent') {
                // 透明背景の場合、canvasBackgroundを非表示にする
                if (canvasBackground) {
                    canvasBackground.style.display = 'none';
                }
            } else {
                // 色が指定された場合、canvasBackgroundの色を変更
                if (canvasBackground) {
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

            if (confirm('全ての素材を削除しますか？')) {
                layers = [];
                nextLayerId = 1;
                
                // DOM要素も削除
                const canvas = document.getElementById('mainCanvas');
                const layerElements = canvas.querySelectorAll('[id^="layer-"]');
                layerElements.forEach(element => element.remove());
                
                // 背景色もリセット
                currentBackgroundColor = 'transparent';
                setBackgroundColor('transparent');
                
                console.log('全ての素材を削除しました');
                
                // ローカルストレージに保存（空の状態を保存）
                saveToLocalStorage();
            }
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
        
        // ページネーション時の検索状態管理
        function initializePaginationSearch() {
            const paginationLinks = document.querySelectorAll('.pagination-btn');
            const searchInput = document.getElementById('materialSearch');
            
            // ページ変更時は検索をリセット
            paginationLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (searchInput && searchInput.value.trim()) {
                        // 検索中の場合は確認ダイアログを表示
                        const confirm = window.confirm('ページを移動すると検索がリセットされます。続行しますか？');
                        if (!confirm) {
                            event.preventDefault();
                            return false;
                        }
                    }
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

        // カラーパネル表示
        function showColorPanel(layer) {
            console.log(`showColorPanel called for layer ${layer.id}`);
            const colorPanelContent = document.getElementById('color-panel-content');
            if (!colorPanelContent) {
                console.log('color-panel-content element not found');
                return;
            }
            
            console.log('Extracting colors from layer...');
            extractColorsFromLayer(layer);
        }

        // カラーパネル非表示（デフォルトメッセージを表示）
        function hideColorPanel() {
            const colorPalette = document.getElementById('colorPalette');
            if (colorPalette) {
                colorPalette.innerHTML = `
                    <div class="text-center text-muted" style="width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px;">
                        <div class="mb-2">素材を選択してください</div>
                        <div>選択した素材の色を変更できます</div>
                    </div>
                `;
                colorPalette.classList.remove('loaded');
            }
        }

        // レイヤーから色を抽出
        function extractColorsFromLayer(layer) {
            console.log(`extractColorsFromLayer called for layer ${layer.id}`);
            const colorPalette = document.getElementById('colorPalette');
            if (!colorPalette) {
                console.log('colorPalette element not found');
                return;
            }
            
            // ローディング状態を表示（固定サイズ）
            colorPalette.innerHTML = `
                <div class="text-center text-muted" style="width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px;">
                    <div class="mb-2">読み込み中...</div>
                    <div>色を抽出しています...</div>
                    <small class="d-block mt-1">しばらくお待ちください</small>
                </div>
            `;
            
            // レイヤーのSVG要素を取得
            const layerElement = document.getElementById(`layer-${layer.id}`);
            console.log(`Looking for layer element with id: layer-${layer.id}`);
            if (!layerElement) {
                console.log(`Layer element layer-${layer.id} not found`);
                return;
            }
            console.log(`Found layer element:`, layerElement);
            
            // SVG内の全要素から色を抽出
            const colors = new Set();
            const allElements = layerElement.querySelectorAll('*');
            
            allElements.forEach(element => {
                const fill = element.getAttribute('fill');
                const stroke = element.getAttribute('stroke');
                
                if (fill && fill !== 'none' && fill !== 'transparent') {
                    colors.add(convertToHex(fill));
                }
                if (stroke && stroke !== 'none' && stroke !== 'transparent') {
                    colors.add(convertToHex(stroke));
                }
                
                // style属性からも抽出
                const style = element.getAttribute('style');
                if (style) {
                    const fillMatch = style.match(/fill\s*:\s*([^;]+)/);
                    const strokeMatch = style.match(/stroke\s*:\s*([^;]+)/);
                    
                    if (fillMatch && fillMatch[1] !== 'none' && fillMatch[1] !== 'transparent') {
                        colors.add(convertToHex(fillMatch[1].trim()));
                    }
                    if (strokeMatch && strokeMatch[1] !== 'none' && strokeMatch[1] !== 'transparent') {
                        colors.add(convertToHex(strokeMatch[1].trim()));
                    }
                }
            });
            
            // カラーパレットを生成（遅延なしで即座に）
            generateColorPalette(Array.from(colors), layer);
        }

        // カラーパレットを生成
        function generateColorPalette(colors, layer) {
            const colorPalette = document.getElementById('colorPalette');
            if (!colorPalette) return;
            
            // 既存のグローバル隠しカラーピッカーをクリア（テーマ変更後の古い参照を削除）
            const globalHiddenPicker = document.getElementById('global-hidden-color-picker');
            if (globalHiddenPicker) {
                globalHiddenPicker.remove();
                console.log('Removed existing global hidden color picker');
            }
            
            if (colors.length === 0) {
                colorPalette.innerHTML = `
                    <div class="text-center text-muted" style="width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 120px;">
                        <div>変更可能な色が見つかりませんでした</div>
                        <small class="d-block mt-1">この素材には色情報がないか、すべて透明です</small>
                    </div>
                `;
                return;
            }
            
            colorPalette.innerHTML = '';
            colorPalette.classList.add('loaded');
            
            colors.forEach((originalColor, index) => {
                const colorItem = document.createElement('div');
                colorItem.className = 'color-item';
                
                colorItem.innerHTML = `
                    <div class="color-swatch-container" data-original-color="${originalColor}" data-layer-id="${layer.id}">
                        <input type="color" 
                               class="color-picker-input" 
                               value="${originalColor}" 
                               oninput="changeColorDirectly('${originalColor}', this.value, ${layer.id}, this)"
                               title="色を選択: ${originalColor}">
                        <div class="color-label">${originalColor}</div>
                    </div>
                `;
                
                colorPalette.appendChild(colorItem);
            });
            
            // デバッグ: 生成されたカラーピッカーの数を確認
            const generatedPickers = colorPalette.querySelectorAll('.color-picker');
            console.log(`Generated ${generatedPickers.length} color pickers for layer ${layer.id}`);
        }

        // 色を16進数に変換
        function convertToHex(color) {
            if (color.startsWith('#')) {
                return color.toUpperCase();
            }
            
            if (color.startsWith('rgb')) {
                const matches = color.match(/\d+/g);
                if (matches && matches.length >= 3) {
                    const r = parseInt(matches[0]);
                    const g = parseInt(matches[1]);
                    const b = parseInt(matches[2]);
                    return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase()}`;
                }
            }
            
            return color;
        }

        // レイヤーの色を変更
        function changeLayerColor(oldColor, newColor, layerId) {
            const layer = layers.find(l => l.id === layerId);
            if (!layer) return;
            
            const layerElement = document.getElementById(`layer-${layerId}`);
            if (!layerElement) return;
            
            // SVG内の該当する色を全て変更
            const allElements = layerElement.querySelectorAll('*');
            let changeCount = 0;
            
            allElements.forEach(element => {
                // fill属性をチェック
                const fillAttr = element.getAttribute('fill');
                if (fillAttr && convertToHex(fillAttr) === convertToHex(oldColor)) {
                    element.setAttribute('fill', newColor);
                    changeCount++;
                }
                
                // stroke属性をチェック
                const strokeAttr = element.getAttribute('stroke');
                if (strokeAttr && convertToHex(strokeAttr) === convertToHex(oldColor)) {
                    element.setAttribute('stroke', newColor);
                    changeCount++;
                }
                
                // style属性をチェック
                const styleAttr = element.getAttribute('style');
                if (styleAttr) {
                    let newStyle = styleAttr;
                    let styleChanged = false;
                    
                    const fillMatch = styleAttr.match(/fill\s*:\s*([^;]+)/);
                    const strokeMatch = styleAttr.match(/stroke\s*:\s*([^;]+)/);
                    
                    if (fillMatch && convertToHex(fillMatch[1].trim()) === convertToHex(oldColor)) {
                        newStyle = newStyle.replace(/fill\s*:\s*[^;]+/, `fill: ${newColor}`);
                        styleChanged = true;
                        changeCount++;
                    }
                    
                    if (strokeMatch && convertToHex(strokeMatch[1].trim()) === convertToHex(oldColor)) {
                        newStyle = newStyle.replace(/stroke\s*:\s*[^;]+/, `stroke: ${newColor}`);
                        styleChanged = true;
                        changeCount++;
                    }
                    
                    if (styleChanged) {
                        element.setAttribute('style', newStyle);
                    }
                }
            });
            
            // レイヤーデータを更新
            const parser = new DOMParser();
            const layerSvg = layerElement.cloneNode(true);
            layer.svgContent = layerSvg.innerHTML;
            
            // ローカルストレージに保存
            saveToLocalStorage();
            
            console.log(`Color changed from ${oldColor} to ${newColor}, ${changeCount} elements updated`);
        }

        // デバウンス用のタイマー管理
        let colorChangeTimeout = null;
        let lastColorChange = null;
        
        // 直接色変更（即時反映・デバウンス対応）
        function changeColorDirectly(originalColor, newColor, layerId, inputElement) {
            console.log(`Changing color directly from ${originalColor} to ${newColor} on layer ${layerId}`);
            
            // 現在の色を取得（連続変更時は前回の新色が基準）
            const container = inputElement.closest('.color-swatch-container');
            const currentOriginalColor = container ? container.getAttribute('data-original-color') : originalColor;
            
            // UI は即座に更新
            updateColorUI(currentOriginalColor, newColor, layerId, inputElement);
            
            // レイヤーの色変更はデバウンスして実行
            if (colorChangeTimeout) {
                clearTimeout(colorChangeTimeout);
            }
            
            // 最新の色変更情報を保存
            lastColorChange = {
                originalColor: currentOriginalColor,
                newColor: newColor,
                layerId: layerId
            };
            
            colorChangeTimeout = setTimeout(() => {
                if (lastColorChange) {
                    changeLayerColor(lastColorChange.originalColor, lastColorChange.newColor, lastColorChange.layerId);
                    lastColorChange = null;
                }
                colorChangeTimeout = null;
            }, 100); // 100ms のデバウンス（スムーズな操作感）
        }
        
        // UI の即座更新
        function updateColorUI(originalColor, newColor, layerId, inputElement) {
            
            // コンテナを取得して情報を更新
            const container = inputElement.closest('.color-swatch-container');
            if (container) {
                const label = container.querySelector('.color-label');
                
                // ラベルを更新
                if (label) {
                    label.textContent = newColor.toUpperCase();
                }
                
                // カラーピッカーのタイトルを更新
                inputElement.title = `色を選択: ${newColor}`;
                
                // データ属性を更新
                container.setAttribute('data-original-color', newColor);
                
                // 他の同じ色のカラーピッカーも更新（同期）
                updateOtherPickersOfSameColor(originalColor, newColor, layerId);
                
                console.log(`Color picker updated from ${originalColor} to ${newColor} for layer ${layerId}`);
            }
        }
        
        // 同じ色の他のカラーピッカーを同期更新
        function updateOtherPickersOfSameColor(originalColor, newColor, layerId) {
            const allContainers = document.querySelectorAll('.color-swatch-container');
            allContainers.forEach(container => {
                const containerOriginalColor = container.getAttribute('data-original-color');
                const containerLayerId = container.getAttribute('data-layer-id');
                
                if (convertToHex(containerOriginalColor) === convertToHex(originalColor) && 
                    containerLayerId == layerId && 
                    container.getAttribute('data-original-color') !== newColor) {
                    
                    const label = container.querySelector('.color-label');
                    const input = container.querySelector('.color-picker-input');
                    
                    if (label) {
                        label.textContent = newColor.toUpperCase();
                    }
                    if (input) {
                        input.value = newColor;
                        input.title = `色を選択: ${newColor}`;
                    }
                    
                    container.setAttribute('data-original-color', newColor);
                }
            });
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
            
            // ローカルストレージから編集内容を復元
            const restored = loadFromLocalStorage();
            if (restored) {
                console.log('編集内容を復元しました');
            } else {
                console.log('新規セッションを開始します');
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
        
        // カラーセクションを初期化
        const colorSection = document.getElementById('color-section');
        if (colorSection) {
            // カラーセクションを常に表示状態にする
            colorSection.style.display = 'block';
        }
        
        // 素材にクリックイベントを追加
            const materialItems = document.querySelectorAll('.material-item');
            materialItems.forEach(item => {
                item.addEventListener('click', function() {
                    addMaterialToCanvas(this);
                });
            });
            
            // ボタンイベントを追加
            document.getElementById('rotateBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                rotateSelectedLayer();
            });
            document.getElementById('rotateLeftBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                rotateLeftSelectedLayer();
            });
            document.getElementById('scaleDownBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                scaleDownSelectedLayer();
            });
            document.getElementById('scaleUpBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                scaleUpSelectedLayer();
            });
            document.getElementById('bringFrontBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                bringLayerToFront();
            });
            document.getElementById('sendBackBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                sendLayerToBack();
            });
            document.getElementById('deleteBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                deleteSelectedLayer();
            });
            document.getElementById('springThemeBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                applySeasonalTheme('spring');
            });
            document.getElementById('summerThemeBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                applySeasonalTheme('summer');
            });
            document.getElementById('autumnThemeBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                applySeasonalTheme('autumn');
            });
            document.getElementById('winterThemeBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                applySeasonalTheme('winter');
            });
            document.getElementById('monochromeThemeBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                applySeasonalTheme('monochrome');
            });
            document.getElementById('sepiaThemeBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                applySeasonalTheme('sepia');
            });
            document.getElementById('exportBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                exportToPNG();
            });
            document.getElementById('clearBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                clearAll();
            });
            
            // アップロードボタンのイベントリスナー
            document.getElementById('uploadBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                openUploadModal();
            });
            
            // アップロードモーダルのイベントリスナー
            document.getElementById('submitUploadBtn').addEventListener('click', submitArtwork);
            
            // フォームバリデーション
            const artworkTitle = document.getElementById('artworkTitle');
            const penName = document.getElementById('penName');
            const agreeUploadTerms = document.getElementById('agreeUploadTerms');
            const submitUploadBtn = document.getElementById('submitUploadBtn');
            
            function validateUploadForm() {
                const titleValid = artworkTitle.value.trim().length > 0 && artworkTitle.value.trim().length <= 100;
                const penNameValid = penName.value.trim().length > 0 && penName.value.trim().length <= 50;
                const termsAgreed = agreeUploadTerms.checked;
                
                submitUploadBtn.disabled = !(titleValid && penNameValid && termsAgreed);
            }
            
            artworkTitle.addEventListener('input', validateUploadForm);
            penName.addEventListener('input', validateUploadForm);
            agreeUploadTerms.addEventListener('change', validateUploadForm);
            
            // 文字数カウンター
            const descriptionTextarea = document.getElementById('artworkDescription');
            const descriptionCount = document.getElementById('descriptionCount');
            
            descriptionTextarea.addEventListener('input', function() {
                const count = this.value.length;
                descriptionCount.textContent = count;
                
                if (count > 1000) {
                    descriptionCount.style.color = '#dc3545';
                } else {
                    descriptionCount.style.color = '#6c757d';
                }
            });
            
            // モーダル表示時にフォームリセット
            document.getElementById('uploadArtworkModal').addEventListener('show.bs.modal', function() {
                resetUploadModal();
                validateUploadForm();
            });
            
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
                const colorSection = document.getElementById('color-section');
                
                // 旧モバイルピッカー関連の処理は削除（新実装では不要）
                
                // キャンバス、レイヤー要素、操作ボタンエリア、カラーセクションのクリックは除外
                if (!canvas.contains(e.target) && 
                    !e.target.closest('.layer-element') && 
                    !manipulationControls.contains(e.target) &&
                    !actionControls.contains(e.target) &&
                    !(colorSection && colorSection.contains(e.target))) {
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
            updateLayerMoveButtonState();
            updateDeleteButtonState();
            updateSeasonalThemeButtonState();
            updateSelectedLayerTitle();

            console.log(`${materialItems.length}個の素材を読み込み完了`);
            console.log('素材をクリックしてキャンバスに配置してください');
            console.log('レイヤーをクリック/タップして選択し、ドラッグで移動できます（デベロッパーツール対応）');
            console.log('レイヤーを選択して各種操作ボタンで回転・拡大縮小・前面背面移動ができます');
        });
    </script>

    <!-- 作品アップロードモーダル -->
    <div class="modal fade" id="uploadArtworkModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="uploadModalLabel">
                        <i class="bi bi-cloud-upload"></i> みんなの作品集に投稿
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="upload-preview-section">
                                <h6><i class="bi bi-image"></i> 作品プレビュー</h6>
                                <div id="uploadPreviewContainer" class="upload-preview-container">
                                    <div class="preview-placeholder">
                                        <i class="bi bi-image"></i>
                                        <p>作品のプレビューがここに表示されます</p>
                                    </div>
                                </div>
                                <div class="upload-file-info mt-3">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        PNG形式で出力された作品が投稿されます。最大2MB、1024px以内に自動調整されます。
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <form id="uploadArtworkForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="artworkTitle" class="form-label">作品タイトル <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="artworkTitle" name="title" required maxlength="100" 
                                           placeholder="例：春の花畑">
                                    <div class="form-text">100文字以内で入力してください</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="penName" class="form-label">ペンネーム <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="penName" name="pen_name" required maxlength="50" 
                                           placeholder="例：花子">
                                    <div class="form-text">50文字以内で入力してください</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="artworkDescription" class="form-label">作品説明（任意）</label>
                                    <textarea class="form-control" id="artworkDescription" name="description" rows="3" 
                                              maxlength="1000" placeholder="作品について簡単に説明してください（1000文字以内）"></textarea>
                                    <div class="form-text">
                                        <span id="descriptionCount">0</span>/1000文字
                                    </div>
                                </div>
                                
                                <!-- フリー素材同意は投稿時に自動的に同意したものとみなす -->
                                
                                <div class="alert alert-warning">
                                    <h6><i class="bi bi-info-circle"></i> 重要：投稿に関する同意事項</h6>
                                    <ul class="mb-0 small">
                                        <li><strong>投稿すると、作品をフリー素材として公開することに同意したものとみなします</strong></li>
                                        <li>他のユーザーがあなたの作品をダウンロード・利用できるようになります</li>
                                        <li>投稿された作品は管理者による承認後に公開されます</li>
                                        <li>marutto素材を使用した作品のみ投稿可能です</li>
                                        <li>不適切な内容は削除される場合があります</li>
                                        <li>1日3件まで投稿可能です</li>
                                    </ul>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="agreeUploadTerms" required>
                                    <label class="form-check-label" for="agreeUploadTerms">
                                        <a href="/terms-of-use.php" target="_blank">利用規約</a>に同意し、作品をフリー素材として公開することを許諾します <span class="text-danger">*</span>
                                    </label>
                                </div>

                                <!-- 隠しファイル入力 -->
                                <input type="file" id="artworkFile" name="artwork" accept="image/png,image/webp,image/svg+xml" style="display: none;">
                            </form>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> キャンセル
                    </button>
                    <button type="button" class="btn btn-success" id="submitUploadBtn" disabled>
                        <i class="bi bi-cloud-upload"></i> 作品を投稿
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- GDPR Cookie Banner (CDN対応・セッション不使用) -->
    <div id="gdpr-banner" class="gdpr-cookie-banner hidden" style="position: fixed; bottom: 0; left: 0; right: 0; background-color: #212529; color: #ffffff; padding: 1rem; z-index: 1050; box-shadow: 0 -2px 10px rgba(0,0,0,0.3);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 col-md-8">
                    <div class="gdpr-text" style="color: #ffffff;">
                        当サイトではサイトの利便性向上のためCookieを使用しています。詳細は
                        <a href="/terms-of-use.php" class="text-white text-decoration-underline" style="color: #ffffff; text-decoration: underline;">利用規約</a>・
                        <a href="/privacy-policy.php" class="text-white text-decoration-underline" style="color: #ffffff; text-decoration: underline;">プライバシーポリシー</a>
                        をご確認ください。
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="gdpr-buttons text-md-end" style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button id="gdpr-accept" class="btn btn-success btn-sm" style="color: #000000; background-color: #ffffff; border-color: #ffffff;">同意する</button>
                        <button id="gdpr-decline" class="btn btn-outline-light btn-sm" style="color: #ffffff; border-color: #ffffff; background-color: transparent;">拒否する</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer-custom mt-5 py-4">
        <div class="container">
            <div class="text-center">
                <div class="mb-2">
                    <a href="/terms-of-use.php" class="footer-text text-decoration-none me-3">利用規約</a>
                    <a href="/privacy-policy.php" class="footer-text text-decoration-none">プライバシーポリシー</a>
                </div>
                <div>
                    <p class="footer-text mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- GDPR Cookie Consent Script (CDN対応・localStorage使用) -->
    <script>
    // GDPR Cookie Consent (セッション・Cookie不使用版)
    (function() {
        const GDPR_KEY = 'gdpr_consent_v1';
        let isInitialized = false;
        
        // 初期化関数
        function initGDPR() {
            if (isInitialized) return;
            isInitialized = true;
            
            const banner = document.getElementById('gdpr-banner');
            const acceptBtn = document.getElementById('gdpr-accept');
            const declineBtn = document.getElementById('gdpr-decline');
            
            console.log('GDPR initialization started');
            console.log('Banner element:', banner);
            console.log('Accept button:', acceptBtn);
            console.log('Decline button:', declineBtn);
            
            if (!banner || !acceptBtn || !declineBtn) {
                console.error('GDPR elements not found');
                return;
            }
            
            // localStorage から同意状況をチェック
            function getGdprConsent() {
                try {
                    return localStorage.getItem(GDPR_KEY);
                } catch (e) {
                    console.warn('localStorage not available:', e);
                    return null;
                }
            }
            
            // 同意状況を保存
            function setGdprConsent(value) {
                try {
                    localStorage.setItem(GDPR_KEY, value);
                    console.log('GDPR consent saved:', value);
                    return true;
                } catch (e) {
                    console.warn('localStorage save failed:', e);
                    return false;
                }
            }
            
            // バナーを表示
            function showBanner() {
                if (banner) {
                    banner.classList.remove('hidden');
                    console.log('GDPR banner shown');
                }
            }
            
            // バナーを非表示
            function hideBanner() {
                if (banner) {
                    banner.classList.add('hidden');
                    console.log('GDPR banner hidden');
                }
            }
            
            // 同意処理
            function acceptConsent() {
                console.log('Accept consent clicked');
                setGdprConsent('accepted');
                hideBanner();
                enableAnalytics();
                
                // GTM読み込みイベントを発火
                const event = new CustomEvent('gdpr-consent-accepted');
                window.dispatchEvent(event);
            }
            
            // 拒否処理
            function declineConsent() {
                console.log('Decline consent clicked');
                setGdprConsent('declined');
                hideBanner();
                disableAnalytics();
            }
            
            // アナリティクス有効化（プレースホルダー）
            function enableAnalytics() {
                console.log('Analytics enabled');
                
                // GTMが未読み込みの場合は読み込み
                if (!window.gtmLoaded) {
                    const event = new CustomEvent('gdpr-consent-accepted');
                    window.dispatchEvent(event);
                }
            }
            
            // アナリティクス無効化（プレースホルダー）
            function disableAnalytics() {
                console.log('Analytics disabled');
                // アナリティクス無効化のコードをここに追加
            }
            
            // イベントリスナーを設定
            acceptBtn.addEventListener('click', acceptConsent);
            declineBtn.addEventListener('click', declineConsent);
            console.log('GDPR event listeners attached');
            
            // 同意状況をチェックして初期化
            const consent = getGdprConsent();
            console.log('Current GDPR consent:', consent);
            
            if (consent === null) {
                // 未設定の場合はバナーを表示
                console.log('No consent found, showing banner');
                showBanner();
            } else if (consent === 'accepted') {
                // 同意済みの場合はアナリティクスを有効化
                console.log('Consent already accepted');
                hideBanner();
                enableAnalytics();
            } else if (consent === 'declined') {
                // 拒否済みの場合はアナリティクスを無効化
                console.log('Consent declined');
                hideBanner();
                disableAnalytics();
            }
        }
        
        // 複数の初期化方法を試行
        function tryInit() {
            console.log('Document ready state:', document.readyState);
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initGDPR);
            } else {
                // DOMが既に読み込まれている場合は即座に実行
                setTimeout(initGDPR, 0);
            }
            
            // フォールバック: window.onloadでも試行
            window.addEventListener('load', function() {
                if (!isInitialized) {
                    console.log('Fallback initialization on window load');
                    initGDPR();
                }
            });
        }
        
        tryInit();
    })();
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>