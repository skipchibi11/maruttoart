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
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8053468089362860"
     crossorigin="anonymous"></script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>あなたのアトリエ - maruttoart</title>
    <meta name="description" content="任意のサイズでベクター素材を組み合わせて作品を作成できるシンプルな編集ツールです。">
    
    <!-- カノニカルURL設定 -->
    <link rel="canonical" href="https://<?= $_SERVER['HTTP_HOST'] ?>/compose/">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700&family=Noto+Serif+JP:wght@400;700&family=Yuji+Syuku&family=Zen+Maru+Gothic:wght@400;700&family=Kosugi+Maru&display=swap" rel="stylesheet">
    <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- レイアウト専用CSS -->
    <link rel="stylesheet" href="assets/css/layout.css">

    <style>
        /* 使い方セクション */
        .how-to-use-section {
            background: linear-gradient(135deg, #ffe9f3 0%, #ffebf0 100%);
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .how-to-content .page-title {
            color: #d47ca5;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-align: center;
        }

        .how-to-toggle {
            background: white;
            border: 2px solid #d47ca5;
            color: #d47ca5;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin: 0 auto;
            max-width: 200px;
            transition: all 0.3s ease;
        }

        .how-to-toggle:hover {
            background: #d47ca5;
            color: white;
        }

        .how-to-toggle i {
            transition: transform 0.3s ease;
        }

        .how-to-toggle.active i {
            transform: rotate(180deg);
        }

        .how-to-details {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
        }

        .how-to-details.active {
            max-height: 2000px;
            padding-top: 2rem;
        }

        .how-to-content h2 {
            color: #d47ca5;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
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
            border-radius: 12px;
            padding: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .step-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.15);
        }

        .step-number {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #d47ca5 0%, #e99bb8 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .step-text {
            flex: 1;
            color: #333;
            line-height: 1.6;
            font-size: 1rem;
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

        /* スピンアニメーション（ローディング用） */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* カスタムサイズ設定パネルのスタイル */
        .canvas-size-controls {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid #e9ecef;
            margin-bottom: 1.5rem;
        }

        .canvas-size-controls h4 {
            color: #2c5aa0;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }

        .size-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .size-input {
            width: 70px;
            padding: 6px 8px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 0.9rem;
            text-align: center;
            transition: border-color 0.2s ease;
        }

        .size-input:focus {
            border-color: #2c5aa0;
            outline: none;
            box-shadow: 0 0 0 2px rgba(44, 90, 160, 0.1);
        }

        .size-label {
            font-weight: 500;
            color: #495057;
            font-size: 0.9rem;
        }

        .size-separator {
            font-weight: bold;
            color: #6c757d;
        }

        .size-unit {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .size-presets {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 10px;
            align-items: center;
        }

        .size-presets strong {
            font-size: 0.9rem;
            margin-right: 8px;
        }

        .preset-btn {
            padding: 4px 8px;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            background: white;
            color: #495057;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .preset-btn:hover {
            border-color: #2c5aa0;
            background: #e3f2fd;
            color: #2c5aa0;
        }

        .apply-size-btn {
            background: #2c5aa0;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .apply-size-btn:hover {
            background: #1e4086;
            transform: translateY(-1px);
        }

        .current-size-info {
            background: #fff;
            border-radius: 4px;
            padding: 6px;
            font-size: 0.8rem;
            color: #6c757d;
            border: 1px solid #e9ecef;
        }

        /* テキストコントロールセクション */
        .text-controls {
        }

        /* テキスト追加ボタンのスタイル */
        .add-text-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .add-text-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .add-text-btn i {
            font-size: 1.2rem;
        }

        /* テキスト編集モーダルのスタイル */
        .text-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .text-modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .text-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .text-modal-header h3 {
            color: #2c5aa0;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .text-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #999;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .text-modal-close:hover {
            background: #f8f9fa;
            color: #333;
        }

        .text-modal-body {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .text-input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .text-input-group label {
            font-weight: 600;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .text-input-group input[type="text"],
        .text-input-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
            font-family: 'Noto Sans JP', sans-serif;
        }

        .text-input-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .text-input-group input:focus,
        .text-input-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .text-input-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            transition: border-color 0.2s ease;
        }

        .text-input-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .text-size-slider-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .text-size-slider-group label {
            font-weight: 600;
            color: #495057;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .text-size-value {
            font-weight: 700;
            color: #667eea;
        }

        .text-size-slider {
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: #e9ecef;
            outline: none;
            -webkit-appearance: none;
        }

        .text-size-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .text-size-slider::-webkit-slider-thumb:hover {
            transform: scale(1.2);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        }

        .text-size-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }

        .text-size-slider::-moz-range-thumb:hover {
            transform: scale(1.2);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        }

        .text-color-picker-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .text-color-picker-group label {
            font-weight: 600;
            color: #495057;
        }

        .text-color-picker {
            width: 60px;
            height: 40px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .text-color-picker:hover {
            border-color: #667eea;
            transform: scale(1.05);
        }

        .text-modal-footer {
            display: flex;
            gap: 12px;
            margin-top: 10px;
        }

        .text-modal-btn {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .text-modal-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .text-modal-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .text-modal-btn-secondary {
            background: #f8f9fa;
            color: #495057;
            border: 2px solid #e9ecef;
        }

        .text-modal-btn-secondary:hover {
            background: #e9ecef;
        }

        /* レスポンシブ対応 */
        @media (max-width: 576px) {
            .search-form {
                padding: 0.75rem;
                border-radius: 10px;
            }
            
            .search-form form {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .search-input {
                flex: 1;
            }
            
            #searchButton {
                flex-shrink: 0;
            }
            
            #clearSearch {
                flex-shrink: 0;
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

        .btn-text {
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
        
        .btn-text:hover {
            background: #8e44ad;
            color: white;
        }
        
        .btn-text:disabled {
            background: #d1c4e9;
            color: #9e9e9e;
            cursor: not-allowed;
        }

        /* 背景パネルのスタイル */
        .background-controls {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            position: relative;
            touch-action: manipulation;
            padding: 10px;
        }

        #mainCanvas {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: min(70vw, 50vh);
            max-height: min(50vh, 70vw);
            width: auto;
            height: auto;
        }

        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .canvas-container {
                margin-bottom: 15px;
                padding: 5px; /* スマホでは内側余白をさらに減らす */
            }
            
            #mainCanvas {
                max-width: min(85vw, 35vh);
                max-height: min(35vh, 85vw);
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
    </style>
    
    <?php include __DIR__ . '/../includes/analytics-script.php'; ?>
</head>
<body>
    <?php 
    $currentPage = 'custom-size';
    include '../includes/header.php'; 
    ?>

    <!-- 使い方セクション -->
    <div class="how-to-use-section">
        <div class="container">
            <div class="how-to-content">
                <h1 class="page-title">あなたのアトリエ</h1>
                <button class="how-to-toggle" onclick="toggleHowTo()">
                    <i class="bi bi-chevron-down"></i>
                    使い方を見る
                </button>
                <div class="how-to-details" id="howToDetails">
                    <h2><i class="bi bi-book"></i> 使い方</h2>
                    <div class="steps-grid">
                        <div class="step-item">
                            <div class="step-number">1</div>
                            <div class="step-text">最初に、キャンバスのサイズを指定します。</div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">2</div>
                            <div class="step-text">素材一覧から、使いたい素材をクリックしてキャンバスに追加します。</div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">3</div>
                            <div class="step-text">追加した素材は、ドラッグして好きな位置へ動かせます。</div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">4</div>
                            <div class="step-text">レイヤーを選択して「変形」ボタンでサイズ変更や回転ができます。</div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">5</div>
                            <div class="step-text">素材を選択すると、色の変更ができます。</div>
                        </div>
                        <div class="step-item">
                            <div class="step-number">6</div>
                            <div class="step-text">完成したら、PNGでダウンロードして完了です。みんなのアトリエにも1日1回まで投稿できます。</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">

        <!-- メインコンテンツ -->
        <div class="main-content">
            <!-- キャンバスサイズ設定パネル -->
            <div class="canvas-size-controls">
                <h4><i class="bi bi-aspect-ratio"></i> キャンバスサイズ設定</h4>
                
                <div class="size-input-group">
                    <span class="size-label">幅:</span>
                    <input type="number" id="canvasWidth" class="size-input" value="1024" min="500" max="2000">
                    <span class="size-unit">px</span>
                    
                    <span class="size-separator">×</span>
                    
                    <span class="size-label">高さ:</span>
                    <input type="number" id="canvasHeight" class="size-input" value="1024" min="500" max="2000">
                    <span class="size-unit">px</span>
                    
                    <button class="apply-size-btn" onclick="applyCanvasSize()">適用</button>
                </div>
                
                <div class="size-presets">
                    <strong>プリセット:</strong>
                    <button class="preset-btn" onclick="setCanvasSize(1080, 1080)">SNS（1080×1080）</button>
                    <button class="preset-btn" onclick="setCanvasSize(1280, 720)">YouTube/ブログ（1280×720）</button>
                    <button class="preset-btn" onclick="setCanvasSize(1920, 1080)">PC壁紙（1920×1080）</button>
                    <button class="preset-btn" onclick="setCanvasSize(1080, 1920)">スマホ壁紙（1080×1920）</button>
                    <button class="preset-btn" onclick="setCanvasSize(1000, 1500)">ポスター風（1000×1500）</button>
                </div>
                
                <div class="current-size-info">
                    現在のサイズ: <span id="currentSizeDisplay">1024 × 1024 px</span>
                </div>
            </div>

            <!-- 素材選択エリア -->
            <div class="materials-panel">
                <h3><i class="bi bi-collection"></i> 素材一覧</h3>

                <!-- 検索フォーム -->
                <div class="search-form">
                    <form class="d-flex align-items-center" onsubmit="return false;">
                        <input type="text" 
                               id="materialSearch" 
                               placeholder="全ての素材から検索（スペース区切りでOR検索可）" 
                               class="search-input form-control">
                        <button type="button" 
                                id="searchButton" 
                                class="btn btn-primary ms-2">検索</button>
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
                        <button type="button" class="pagination-btn" data-page="<?= $page - 1 ?>" onclick="loadPage(<?= $page - 1 ?>)">
                            前へ
                        </button>
                    <?php endif; ?>
                    
                    <!-- 次のページ -->
                    <?php if ($page < $totalPages): ?>
                        <button type="button" class="pagination-btn" data-page="<?= $page + 1 ?>" onclick="loadPage(<?= $page + 1 ?>)">
                            次へ
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- ページ情報 -->
                <div class="pagination-info">
                    <span id="currentPage"><?= $page ?></span> / <span id="totalPages"><?= $totalPages ?></span> ページ （全 <span id="totalItems"><?= $totalItems ?></span> 件）
                </div>
                <?php endif; ?>
                
                <?php if (empty($materials)): ?>
                <!-- 素材が見つからない場合のメッセージ -->
                <div style="text-align: center; padding: 2rem; color: #6c757d;">
                    <p>素材が見つかりませんでした。</p>
                </div>
                <?php endif; ?>
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
                
                <!-- 色変更セクション -->
                <div id="color-panel-content" class="color-panel-content" style="margin-bottom: 15px;">
                    <div id="colorPalette" class="color-palette"></div>
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
                    <button id="textBtn" class="btn btn-text" onclick="handleTextButton()" title="テキストを追加・編集">
                        <span style="font-family: 'Noto Sans JP', sans-serif; font-weight: 700; font-size: 20px;">T</span>
                    </button>
                    <button id="flipHorizontalBtn" class="btn btn-flip-horizontal" title="選択したレイヤーを水平反転">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flip-horizontal2-icon lucide-flip-horizontal-2"><path d="m3 7 5 5-5 5V7"/><path d="m21 7-5 5 5 5V7"/><path d="M12 20v2"/><path d="M12 14v2"/><path d="M12 8v2"/><path d="M12 2v2"/></svg>
                    </button>
                    <button id="flipVerticalBtn" class="btn btn-flip-vertical" title="選択したレイヤーを上下反転">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flip-vertical2-icon lucide-flip-vertical-2"><path d="m17 3-5 5-5-5h10"/><path d="m17 21-5-5-5 5h10"/><path d="M4 12H2"/><path d="M10 12H8"/><path d="M16 12h-2"/><path d="M22 12h-2"/></svg>
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
                
                <!-- 一括色変更設定 -->
                <div class="bulk-color-settings">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="bulkColorChange">
                        <label class="form-check-label" for="bulkColorChange">
                            一括色変更許可
                        </label>
                    </div>
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
                        <i class="bi bi-cloud-upload"></i> 作品を投稿
                    </button>
                    <button id="clearBtn" class="btn btn-clear">
                        <i class="bi bi-trash"></i> 全て削除
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- テキスト編集モーダル -->
    <div id="textModal" class="text-modal">
        <div class="text-modal-content">
            <div class="text-modal-header">
                <h3><i class="bi bi-fonts"></i> テキストを追加</h3>
                <button class="text-modal-close" onclick="closeTextModal()">&times;</button>
            </div>
            <div class="text-modal-body">
                <div class="text-input-group">
                    <label><i class="bi bi-type"></i> テキスト内容</label>
                    <textarea id="textContent" placeholder="ここにテキストを入力してください"></textarea>
                </div>
                <div class="text-input-group">
                    <label><i class="bi bi-fonts"></i> フォント</label>
                    <select id="textFont">
                        <option value="Noto Sans JP">ゴシック体</option>
                        <option value="Noto Serif JP">明朝体</option>
                        <option value="Yuji Syuku">手書き風</option>
                        <option value="Zen Maru Gothic">丸ゴシック</option>
                        <option value="Kosugi Maru">小杉丸</option>
                    </select>
                </div>
                <div class="text-input-group">
                    <label><i class="bi bi-type-bold"></i> 太さ</label>
                    <select id="textWeight">
                        <option value="300">細字（Light）</option>
                        <option value="400" selected>標準（Regular）</option>
                        <option value="500">中字（Medium）</option>
                        <option value="600">半太字（SemiBold）</option>
                        <option value="700">太字（Bold）</option>
                    </select>
                </div>
                <div class="text-size-slider-group">
                    <label>
                        <span><i class="bi bi-text-left"></i> フォントサイズ</span>
                        <span class="text-size-value" id="textSizeValue">48px</span>
                    </label>
                    <input type="range" id="textSize" class="text-size-slider" min="20" max="200" value="48">
                </div>
                <div class="text-color-picker-group">
                    <label><i class="bi bi-palette"></i> 文字色</label>
                    <input type="color" id="textColor" class="text-color-picker" value="#000000">
                </div>
            </div>
            <div class="text-modal-footer">
                <button class="text-modal-btn text-modal-btn-secondary" onclick="closeTextModal()">キャンセル</button>
                <button class="text-modal-btn text-modal-btn-primary" onclick="addTextToCanvas()">追加</button>
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
        let mouseDownPos = { x: 0, y: 0, layerId: null }; // マウスダウン時の位置とレイヤーID
        let dragThreshold = 20; // ドラッグと判定する最小移動距離（ピクセル）
        let currentBackgroundColor = 'transparent'; // 現在の背景色
        
        // タッチデバイス判定
        const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

        // テキスト編集中のレイヤーID
        let editingTextLayerId = null;

        // テキストボタンをクリックしたときの処理
        function handleTextButton() {
            if (selectedLayerId !== null) {
                const layer = layers.find(l => l.id === selectedLayerId);
                if (layer && layer.type === 'text') {
                    // テキストレイヤーが選択されている場合は編集
                    openTextModal(selectedLayerId);
                    return;
                }
            }
            // レイヤー未選択またはテキストレイヤー以外の場合は新規追加
            openTextModal();
        }

        // テキストモーダルを開く
        function openTextModal(layerId = null) {
            const modal = document.getElementById('textModal');
            editingTextLayerId = layerId;
            
            if (layerId) {
                // 既存テキストの編集
                const layer = layers.find(l => l.id === layerId);
                if (layer && layer.type === 'text') {
                    document.getElementById('textContent').value = layer.text || '';
                    document.getElementById('textFont').value = layer.fontFamily || 'Noto Sans JP';
                    document.getElementById('textWeight').value = layer.fontWeight || '400';
                    document.getElementById('textSize').value = layer.fontSize || 48;
                    document.getElementById('textSizeValue').textContent = `${layer.fontSize || 48}px`;
                    document.getElementById('textColor').value = layer.color || '#000000';
                }
            } else {
                // 新規テキスト追加
                document.getElementById('textContent').value = '';
                document.getElementById('textFont').value = 'Noto Sans JP';
                document.getElementById('textWeight').value = '400';
                document.getElementById('textSize').value = 48;
                document.getElementById('textSizeValue').textContent = '48px';
                document.getElementById('textColor').value = '#000000';
            }
            
            modal.style.display = 'block';
        }

        // テキストモーダルを閉じる
        function closeTextModal() {
            const modal = document.getElementById('textModal');
            modal.style.display = 'none';
            editingTextLayerId = null;
        }

        // テキストをキャンバスに追加
        function addTextToCanvas() {
            const textContent = document.getElementById('textContent').value.trim();
            const fontFamily = document.getElementById('textFont').value;
            const fontWeight = document.getElementById('textWeight').value;
            const fontSize = parseInt(document.getElementById('textSize').value);
            const color = document.getElementById('textColor').value;

            if (!textContent) {
                alert('テキストを入力してください');
                return;
            }

            if (editingTextLayerId) {
                // 既存テキストの編集
                updateTextLayer(editingTextLayerId, textContent, fontFamily, fontWeight, fontSize, color);
            } else {
                // 新規テキストの追加
                createTextLayer(textContent, fontFamily, fontWeight, fontSize, color);
            }

            closeTextModal();
        }

        // 新規テキストレイヤーを作成
        function createTextLayer(text, fontFamily, fontWeight, fontSize, color) {
            const canvas = document.getElementById('mainCanvas');
            const viewBox = canvas.viewBox.baseVal;
            const centerX = viewBox.width / 2;
            const centerY = viewBox.height / 2;

            const layer = {
                id: nextLayerId++,
                type: 'text',
                text: text,
                fontFamily: fontFamily,
                fontWeight: fontWeight,
                fontSize: fontSize,
                color: color,
                x: centerX,
                y: centerY,
                rotation: 0,
                scale: 1,
                flipX: 1,
                flipY: 1,
                transform: {
                    x: centerX,
                    y: centerY,
                    rotation: 0,
                    scale: 1,
                    flipHorizontal: false,
                    flipVertical: false
                },
                visible: true
            };

            layers.push(layer);
            renderTextLayer(layer);
            selectLayer(layer.id);
            saveToLocalStorage();

            console.log('テキストレイヤー追加:', text);
        }

        // テキストレイヤーを描画（初回追加時）
        function renderTextLayer(layer) {
            const canvas = document.getElementById('mainCanvas');
            
            // レイヤーグループを作成
            const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            group.id = `layer-${layer.id}`;
            group.setAttribute('class', 'layer-element');
            group.setAttribute('data-layer-id', layer.id);
            group.style.cursor = 'move';

            // テキスト要素を作成
            const textElement = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            textElement.setAttribute('x', '0');
            textElement.setAttribute('y', '0');
            textElement.setAttribute('font-family', layer.fontFamily);
            textElement.setAttribute('font-weight', layer.fontWeight || '400');
            textElement.setAttribute('font-size', layer.fontSize);
            textElement.setAttribute('fill', layer.color);
            textElement.setAttribute('text-anchor', 'middle');
            textElement.setAttribute('dominant-baseline', 'middle');
            
            // 改行をtspanで処理
            const lines = layer.text.split('\n');
            const lineHeight = layer.fontSize * 1.2;
            const totalHeight = lineHeight * (lines.length - 1);
            const startY = -totalHeight / 2;
            
            lines.forEach((line, index) => {
                const tspan = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
                tspan.setAttribute('x', '0');
                tspan.setAttribute('dy', index === 0 ? startY : lineHeight);
                tspan.textContent = line;
                textElement.appendChild(tspan);
            });

            group.appendChild(textElement);

            // transform属性を設定
            const transform = `translate(${layer.x}, ${layer.y}) rotate(${layer.rotation}) scale(${layer.scale * layer.flipX}, ${layer.scale * layer.flipY})`;
            group.setAttribute('transform', transform);

            // レイヤークリックイベントを追加
            group.addEventListener('click', function(e) {
                e.stopPropagation();
                selectLayer(layer.id);
            });

            // マウスドラッグイベントを追加
            group.addEventListener('mousedown', function(e) {
                e.stopPropagation();
                startDrag(e, layer.id);
            });

            // タッチイベントを追加
            group.addEventListener('touchstart', function(e) {
                e.stopPropagation();
                e.preventDefault();
                startDrag(e.touches[0], layer.id);
            }, { passive: false });

            // 選択状態に応じてスタイルを設定
            if (selectedLayerId === layer.id) {
                group.style.cursor = 'move';
                group.style.filter = 'drop-shadow(0 0 3px rgba(0,123,255,0.8))';
            } else {
                group.style.cursor = 'pointer';
                group.style.filter = '';
            }

            canvas.appendChild(group);
        }

        // テキストレイヤーを変形を適用して再描画（レイヤー操作時）
        function renderTextLayerWithTransform(layer) {
            const canvas = document.getElementById('mainCanvas');
            
            // 既存のレイヤー要素があれば削除
            const existingLayer = document.getElementById(`layer-${layer.id}`);
            if (existingLayer) {
                existingLayer.remove();
            }

            // レイヤーグループを作成
            const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            group.id = `layer-${layer.id}`;
            group.setAttribute('class', 'layer-element');
            group.setAttribute('data-layer-id', layer.id);

            // テキスト要素を作成
            const textElement = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            textElement.setAttribute('x', '0');
            textElement.setAttribute('y', '0');
            textElement.setAttribute('font-family', layer.fontFamily);
            textElement.setAttribute('font-weight', layer.fontWeight || '400');
            textElement.setAttribute('font-size', layer.fontSize);
            textElement.setAttribute('fill', layer.color);
            textElement.setAttribute('text-anchor', 'middle');
            textElement.setAttribute('dominant-baseline', 'middle');
            
            // 改行をtspanで処理
            const lines = layer.text.split('\n');
            const lineHeight = layer.fontSize * 1.2;
            const totalHeight = lineHeight * (lines.length - 1);
            const startY = -totalHeight / 2;
            
            lines.forEach((line, index) => {
                const tspan = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
                tspan.setAttribute('x', '0');
                tspan.setAttribute('dy', index === 0 ? startY : lineHeight);
                tspan.textContent = line;
                textElement.appendChild(tspan);
            });

            group.appendChild(textElement);

            // transform属性を設定（transform構造を使用）
            if (layer.transform) {
                let scaleX = layer.transform.scale || 1;
                let scaleY = layer.transform.scale || 1;
                
                if (layer.transform.flipHorizontal) {
                    scaleX = -scaleX;
                }
                if (layer.transform.flipVertical) {
                    scaleY = -scaleY;
                }
                
                const transform = `translate(${layer.transform.x}, ${layer.transform.y}) scale(${scaleX}, ${scaleY}) rotate(${layer.transform.rotation})`;
                group.setAttribute('transform', transform);
            } else {
                // 古い形式の互換性
                const transform = `translate(${layer.x}, ${layer.y}) rotate(${layer.rotation}) scale(${layer.scale * (layer.flipX || 1)}, ${layer.scale * (layer.flipY || 1)})`;
                group.setAttribute('transform', transform);
            }

            // レイヤークリックイベントを追加
            group.addEventListener('click', function(e) {
                e.stopPropagation();
                selectLayer(layer.id);
            });

            // マウスドラッグイベントを追加
            group.addEventListener('mousedown', function(e) {
                e.stopPropagation();
                startDrag(e, layer.id);
            });

            // タッチイベントを追加
            group.addEventListener('touchstart', function(e) {
                e.stopPropagation();
                e.preventDefault();
                startDrag(e.touches[0], layer.id);
            }, { passive: false });

            // 選択状態に応じてスタイルを設定
            if (selectedLayerId === layer.id) {
                group.style.cursor = 'move';
                group.style.filter = 'drop-shadow(0 0 3px rgba(0,123,255,0.8))';
            } else {
                group.style.cursor = 'pointer';
                group.style.filter = '';
            }

            // レイヤーの順序に従って挿入
            const layerIndex = layers.findIndex(l => l.id === layer.id);
            const existingLayers = canvas.querySelectorAll('.layer-element');
            
            if (layerIndex === 0) {
                const background = canvas.getElementById('canvasBackground');
                canvas.insertBefore(group, background.nextSibling);
            } else if (layerIndex < existingLayers.length) {
                let insertBefore = null;
                for (let i = layerIndex; i < layers.length; i++) {
                    const nextLayerElement = canvas.getElementById(`layer-${layers[i].id}`);
                    if (nextLayerElement) {
                        insertBefore = nextLayerElement;
                        break;
                    }
                }
                if (insertBefore) {
                    canvas.insertBefore(group, insertBefore);
                } else {
                    canvas.appendChild(group);
                }
            } else {
                canvas.appendChild(group);
            }
        }

        // テキストレイヤーを更新
        function updateTextLayer(layerId, text, fontFamily, fontWeight, fontSize, color) {
            const layer = layers.find(l => l.id === layerId);
            if (!layer || layer.type !== 'text') return;

            layer.text = text;
            layer.fontFamily = fontFamily;
            layer.fontWeight = fontWeight;
            layer.fontSize = fontSize;
            layer.color = color;

            // DOMを更新
            const layerElement = document.querySelector(`.layer-element[data-layer-id="${layerId}"]`);
            if (layerElement) {
                const textElement = layerElement.querySelector('text');
                if (textElement) {
                    // 既存のtspanをすべて削除
                    while (textElement.firstChild) {
                        textElement.removeChild(textElement.firstChild);
                    }
                    
                    // 改行をtspanで処理
                    const lines = text.split('\n');
                    const lineHeight = fontSize * 1.2;
                    const totalHeight = lineHeight * (lines.length - 1);
                    const startY = -totalHeight / 2;
                    
                    lines.forEach((line, index) => {
                        const tspan = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
                        tspan.setAttribute('x', '0');
                        tspan.setAttribute('dy', index === 0 ? startY : lineHeight);
                        tspan.textContent = line;
                        textElement.appendChild(tspan);
                    });
                    
                    textElement.setAttribute('font-family', fontFamily);
                    textElement.setAttribute('font-weight', fontWeight);
                    textElement.setAttribute('font-size', fontSize);
                    textElement.setAttribute('fill', color);
                }
            }

            saveToLocalStorage();
            console.log('テキストレイヤー更新:', text);
        }

        
        // 素材をキャンバス中央に追加
        function addMaterialToCanvas(element) {
            const materialId = element.dataset.materialId;
            const svgPath = element.dataset.svgPath;
            const title = element.dataset.title;

            // タイトルをエスケープしてログ出力
            const safeTitle = title.replace(/[`\\]/g, '\\$&');
            console.log('素材追加:', safeTitle);

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
                            type: 'svg', // レイヤータイプを明示
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
                        
                        // 全レイヤーを再描画（既存レイヤーが消えないように）
                        layers.forEach(l => renderLayer(l));
                        
                        // タイトルをエスケープしてログ出力
                        const safeTitle = title.replace(/[`\\]/g, '\\$&');
                        console.log(`素材「${safeTitle}」をキャンバス中央に追加 (ID: ${layer.id}, 全レイヤー数: ${layers.length})`);
                        
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

            // テキストレイヤーの場合は専用の描画関数を使用
            if (layer.type === 'text') {
                renderTextLayerWithTransform(layer);
                return;
            }

            // 新しいレイヤー要素を作成
            const layerGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            layerGroup.id = `layer-${layer.id}`;
            layerGroup.classList.add('layer-element');
            layerGroup.setAttribute('data-material-id', layer.materialId);
            layerGroup.innerHTML = layer.svgContent;

            // スケール変換後の実際の中心点を計算
            // rotate の中心座標は、scale変換前の座標系で指定する必要がある
            const centerX = layer.originalCenter ? layer.originalCenter.x : 0;
            const centerY = layer.originalCenter ? layer.originalCenter.y : 0;
            
            // transformオブジェクトまたは直接プロパティから値を取得
            const x = layer.transform ? layer.transform.x : (layer.x || 0);
            const y = layer.transform ? layer.transform.y : (layer.y || 0);
            const scale = layer.transform ? layer.transform.scale : (layer.scale || 1);
            const rotation = layer.transform ? layer.transform.rotation : (layer.rotation || 0);
            const flipHorizontal = layer.transform ? layer.transform.flipHorizontal : false;
            const flipVertical = layer.transform ? layer.transform.flipVertical : false;
            const flipX = layer.flipX !== undefined ? layer.flipX : 1;
            const flipY = layer.flipY !== undefined ? layer.flipY : 1;
            
            // 変換を適用: 移動→スケール→反転→中心回転
            let scaleX = scale * flipX;
            let scaleY = scale * flipY;
            
            // 水平反転の場合はscaleXを負にする
            if (flipHorizontal) {
                scaleX = -scaleX;
            }
            
            // 上下反転の場合はscaleYを負にする
            if (flipVertical) {
                scaleY = -scaleY;
            }
            
            // 統一された処理: すべて同じ順序で処理
            const transformString = `translate(${x}, ${y}) scale(${scaleX}, ${scaleY}) rotate(${rotation}, ${centerX}, ${centerY})`;
            layerGroup.setAttribute('transform', transformString);

            // layersの配列順に基づいて正しい位置に挿入
            const layerIndex = layers.findIndex(l => l.id === layer.id);
            const existingLayers = canvas.querySelectorAll('.layer-element');
            
            if (layerIndex === 0) {
                // 最初のレイヤーの場合、backgroundの後に挿入
                const background = document.getElementById('canvasBackground');
                if (background && background.nextSibling) {
                    canvas.insertBefore(layerGroup, background.nextSibling);
                } else {
                    canvas.appendChild(layerGroup);
                }
            } else if (layerIndex < existingLayers.length) {
                // 指定されたインデックスの位置に挿入
                let insertBefore = null;
                for (let i = layerIndex + 1; i < layers.length; i++) {
                    const nextLayerElement = document.getElementById(`layer-${layers[i].id}`);
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
            updateTextButtonState();
            
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
                updateTextButtonState();
                
                // カラーパネルを非表示
                hideColorPanel();
            }
        }

        // ドラッグ開始（マウス・タッチ・ポインター対応）
        function startDrag(e, layerId) {
            // 選択状態の更新は遅延させる（ダブルクリック判定のため）
            // ドラッグが実際に開始されたときに選択する
            
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
            
            // マウスダウン時の画面座標を保存
            mouseDownPos.x = clientX;
            mouseDownPos.y = clientY;
            mouseDownPos.layerId = layerId; // レイヤーIDも保存
            
            const svgPoint = screenToSVG(clientX - rect.left, clientY - rect.top, canvas);
            
            dragStartPos.x = svgPoint.x;
            dragStartPos.y = svgPoint.y;
            
            const layer = layers.find(l => l.id === layerId);
            if (layer) {
                dragStartTransform.x = layer.transform.x;
                dragStartTransform.y = layer.transform.y;
            }
            
            // ドラッグフラグはまだセットしない（移動してから判定）
            console.log(`Mouse down for layer ${layerId} at SVG coordinates:`, svgPoint, `Event type: ${e.type}`);
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
            if (!mouseDownPos.layerId) return; // マウスダウンしていない
            
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
            
            // まだドラッグ開始していない場合、移動距離をチェック
            if (!isDragging) {
                const distanceX = Math.abs(clientX - mouseDownPos.x);
                const distanceY = Math.abs(clientY - mouseDownPos.y);
                const distance = Math.sqrt(distanceX * distanceX + distanceY * distanceY);
                
                // 閾値を超えた場合のみドラッグ開始
                if (distance > dragThreshold) {
                    isDragging = true;
                    const layerId = mouseDownPos.layerId;
                    
                    // ドラッグ開始時に選択状態を更新
                    if (selectedLayerId !== layerId) {
                        selectLayer(layerId);
                    }
                    
                    console.log(`Drag started for layer ${layerId} after moving ${distance.toFixed(2)}px`);
                } else {
                    return; // まだドラッグ開始しない
                }
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
            } else if (mouseDownPos.layerId) {
                // ドラッグしなかった場合（単純なクリック）
                // 選択状態を更新
                if (selectedLayerId !== mouseDownPos.layerId) {
                    selectLayer(mouseDownPos.layerId);
                }
            }
            
            // マウスダウン情報をクリア
            mouseDownPos.layerId = null;
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

        // 選択されたレイヤーを水平反転
        function flipHorizontalSelectedLayer() {
            if (selectedLayerId === null) {
                alert('反転させるレイヤーを選択してください。');
                return;
            }

            const layer = layers.find(l => l.id === selectedLayerId);
            if (layer) {
                // 反転前の状態を保存
                const wasFlipped = layer.transform.flipHorizontal;
                
                // 水平反転フラグを切り替え
                layer.transform.flipHorizontal = !layer.transform.flipHorizontal;
                
                // 反転による位置のズレを補正
                if (!wasFlipped && layer.transform.flipHorizontal) {
                    // 通常→反転: 中心を基準とした位置補正
                    layer.transform.x += 2 * layer.originalCenter.x * layer.transform.scale;
                } else if (wasFlipped && !layer.transform.flipHorizontal) {
                    // 反転→通常: 位置を元に戻す
                    layer.transform.x -= 2 * layer.originalCenter.x * layer.transform.scale;
                }
                
                // 現在の選択IDを保存
                const currentSelectedId = selectedLayerId;
                
                // 全レイヤーを再描画（選択状態を保持）
                layers.forEach(l => {
                    renderLayer(l);
                });
                
                // 選択中の素材タイトルを更新
                updateSelectedLayerTitle();
                
                console.log(`Layer ${currentSelectedId} horizontal flip: ${layer.transform.flipHorizontal}`);
                
                // ローカルストレージに保存
                saveToLocalStorage();
            }
        }

        // 選択されたレイヤーを上下反転
        function flipVerticalSelectedLayer() {
            if (selectedLayerId === null) {
                alert('反転させるレイヤーを選択してください。');
                return;
            }

            const layer = layers.find(l => l.id === selectedLayerId);
            if (layer) {
                // 反転前の状態を保存
                const wasFlipped = layer.transform.flipVertical;
                
                // 上下反転フラグを切り替え
                layer.transform.flipVertical = !layer.transform.flipVertical;
                
                // 反転による位置のズレを補正
                if (!wasFlipped && layer.transform.flipVertical) {
                    // 通常→反転: 中心を基準とした位置補正
                    layer.transform.y += 2 * layer.originalCenter.y * layer.transform.scale;
                } else if (wasFlipped && !layer.transform.flipVertical) {
                    // 反転→通常: 位置を元に戻す
                    layer.transform.y -= 2 * layer.originalCenter.y * layer.transform.scale;
                }
                
                // 現在の選択IDを保存
                const currentSelectedId = selectedLayerId;
                
                // 全レイヤーを再描画（選択状態を保持）
                layers.forEach(l => {
                    renderLayer(l);
                });
                
                // 選択中の素材タイトルを更新
                updateSelectedLayerTitle();
                
                console.log(`Layer ${currentSelectedId} vertical flip: ${layer.transform.flipVertical}`);
                
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
            updateTextButtonState();
            
            console.log(`Layer deleted (was at index: ${currentIndex})`);
            
            // ローカルストレージに保存
            saveToLocalStorage();
        }

        // 季節テーマを適用（選択されているレイヤーの色をランダムに変更）
        function applySeasonalTheme(season) {
            console.log(`=== applySeasonalTheme START (${season}) ===`);
            console.log('selectedLayerId:', selectedLayerId);
            
            const bulkColorChange = document.getElementById('bulkColorChange').checked;
            console.log('bulkColorChange:', bulkColorChange);
            
            // レイヤー未選択時の処理分岐
            if (!selectedLayerId) {
                if (bulkColorChange) {
                    // 一括色変更許可：全レイヤーに適用
                    console.log('Bulk color change mode: applying to all layers');
                    if (layers.length === 0) {
                        alert('素材を追加してからテーマを適用してください');
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
                } else {
                    // 従来モード：レイヤー選択要求
                    alert('レイヤーを選択してください（一括適用する場合は「一括色変更許可」をONにしてください）');
                    return;
                }
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
            
            // カラーパレットを更新（色見本に変更を反映）
            updateColorPaletteAfterThemeChange(layer);
            
            console.log(`${palette.name}が適用されました: ${colorChangedCount}個の色要素を変更`);
            console.log('カラーパレットも更新されました');
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
                const editorData = {
                    layers: layers,
                    selectedLayerId: selectedLayerId,
                    nextLayerId: nextLayerId,
                    currentBackgroundColor: currentBackgroundColor,
                    canvasWidth: currentCanvasWidth,
                    canvasHeight: currentCanvasHeight,
                    timestamp: Date.now()
                };
                
                localStorage.setItem('compose_editor_data', JSON.stringify(editorData));
                console.log('編集内容をローカルストレージに保存しました');
            } catch (error) {
                console.error('ローカルストレージ保存エラー:', error);
            }
        }

        // ローカルストレージから編集内容を読み込み
        function loadFromLocalStorage() {
            try {
                // 新キーで取得
                let savedData = localStorage.getItem('compose_editor_data');
                
                // 旧キーから移行
                if (!savedData) {
                    savedData = localStorage.getItem('compose2_custom_size_editor_data');
                    if (savedData) {
                        console.log('旧キー(compose2)からデータを移行します');
                        // 新キーで保存
                        localStorage.setItem('compose_editor_data', savedData);
                        // 旧キーを削除
                        localStorage.removeItem('compose2_custom_size_editor_data');
                    }
                }
                
                if (savedData) {
                    const editorData = JSON.parse(savedData);
                    
                    // データの復元
                    layers = editorData.layers || [];
                    selectedLayerId = editorData.selectedLayerId || null;
                    nextLayerId = editorData.nextLayerId || 1;
                    currentBackgroundColor = editorData.currentBackgroundColor || 'transparent';
                    
                    // キャンバスサイズを復元
                    if (editorData.canvasWidth && editorData.canvasHeight) {
                        currentCanvasWidth = editorData.canvasWidth;
                        currentCanvasHeight = editorData.canvasHeight;
                        
                        // 入力欄とキャンバスに反映
                        document.getElementById('canvasWidth').value = currentCanvasWidth;
                        document.getElementById('canvasHeight').value = currentCanvasHeight;
                        
                        const svgCanvas = document.getElementById('mainCanvas');
                        if (svgCanvas) {
                            svgCanvas.setAttribute('width', currentCanvasWidth);
                            svgCanvas.setAttribute('height', currentCanvasHeight);
                            svgCanvas.setAttribute('viewBox', `0 0 ${currentCanvasWidth} ${currentCanvasHeight}`);
                            
                            const canvasBackground = document.getElementById('canvasBackground');
                            if (canvasBackground) {
                                canvasBackground.setAttribute('width', currentCanvasWidth);
                                canvasBackground.setAttribute('height', currentCanvasHeight);
                            }
                        }
                        
                        // 現在のサイズ表示を更新
                        document.getElementById('currentSizeDisplay').textContent = `${currentCanvasWidth} × ${currentCanvasHeight} px`;
                    }
                    
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
                    
                    console.log(`${layers.length}個のレイヤーをローカルストレージから復元しました (${currentCanvasWidth}×${currentCanvasHeight})`);
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
                localStorage.removeItem('compose_editor_data');
                console.log('ローカルストレージをクリアしました');
            } catch (error) {
                console.error('ローカルストレージクリアエラー:', error);
            }
        }

        // 回転ボタンの状態を更新
        function updateRotateButtonState() {
            const rotateBtn = document.getElementById('rotateBtn');
            const rotateLeftBtn = document.getElementById('rotateLeftBtn');
            const flipHorizontalBtn = document.getElementById('flipHorizontalBtn');
            const flipVerticalBtn = document.getElementById('flipVerticalBtn');
            
            console.log(`updateRotateButtonState: selectedLayerId = ${selectedLayerId}`);
            
            if (selectedLayerId !== null) {
                rotateBtn.disabled = false;
                rotateBtn.title = '選択したレイヤーを15度右回転';
                rotateLeftBtn.disabled = false;
                rotateLeftBtn.title = '選択したレイヤーを15度左回転';
                flipHorizontalBtn.disabled = false;
                flipHorizontalBtn.title = '選択したレイヤーを水平反転';
                flipVerticalBtn.disabled = false;
                flipVerticalBtn.title = '選択したレイヤーを上下反転';
                console.log('Rotate buttons ENABLED');
            } else {
                rotateBtn.disabled = true;
                rotateBtn.title = 'レイヤーを選択してから回転できます';
                rotateLeftBtn.disabled = true;
                rotateLeftBtn.title = 'レイヤーを選択してから回転できます';
                flipHorizontalBtn.disabled = true;
                flipHorizontalBtn.title = 'レイヤーを選択してから反転できます';
                flipVerticalBtn.disabled = true;
                flipVerticalBtn.title = 'レイヤーを選択してから反転できます';
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

        // テキストボタンの状態を更新
        function updateTextButtonState() {
            const textBtn = document.getElementById('textBtn');
            
            if (selectedLayerId !== null) {
                const layer = layers.find(l => l.id === selectedLayerId);
                if (layer && layer.type === 'text') {
                    // テキストレイヤーが選択されている場合は編集モード
                    textBtn.title = 'テキストを編集';
                    textBtn.style.background = '#8e44ad'; // 濃い紫で編集モードを示す
                } else {
                    // テキスト以外のレイヤーが選択されている場合は追加モード
                    textBtn.title = 'テキストを追加';
                    textBtn.style.background = '#9b59b6'; // 通常の紫
                }
            } else {
                // レイヤー未選択の場合は追加モード
                textBtn.title = 'テキストを追加';
                textBtn.style.background = '#9b59b6'; // 通常の紫
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
                    if (layer.type === 'text') {
                        // テキストレイヤーの場合はテキスト内容を表示
                        const previewText = layer.text.length > 20 ? layer.text.substring(0, 20) + '...' : layer.text;
                        titleElement.textContent = `テキスト: ${previewText}`;
                    } else {
                        titleElement.textContent = layer.title;
                    }
                    titleElement.classList.add('active');
                    console.log(`Title updated to: ${titleElement.textContent}`);
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

        // SVGデータ読み込み機能
        function loadArtworkSVG(artworkId) {
            console.log('SVGデータ読み込み開始:', artworkId);
            
            // すぐにURLパラメータを削除（リロード対策）
            removeArtworkIdFromURL();
            
            fetch(`../api/get-artwork-svg.php?id=${artworkId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.svg_data) {
                        const svgData = data.svg_data;
                        
                        // キャンバスサイズを復元
                        if (svgData.canvasWidth && svgData.canvasHeight) {
                            currentCanvasWidth = svgData.canvasWidth;
                            currentCanvasHeight = svgData.canvasHeight;
                            
                            // 入力欄とキャンバスに反映
                            document.getElementById('canvasWidth').value = currentCanvasWidth;
                            document.getElementById('canvasHeight').value = currentCanvasHeight;
                            
                            const svgCanvas = document.getElementById('mainCanvas');
                            if (svgCanvas) {
                                svgCanvas.setAttribute('width', currentCanvasWidth);
                                svgCanvas.setAttribute('height', currentCanvasHeight);
                                svgCanvas.setAttribute('viewBox', `0 0 ${currentCanvasWidth} ${currentCanvasHeight}`);
                                
                                const canvasBackground = document.getElementById('canvasBackground');
                                if (canvasBackground) {
                                    canvasBackground.setAttribute('width', currentCanvasWidth);
                                    canvasBackground.setAttribute('height', currentCanvasHeight);
                                }
                            }
                            
                            // 現在のサイズ表示を更新
                            document.getElementById('currentSizeDisplay').textContent = `${currentCanvasWidth} × ${currentCanvasHeight} px`;
                        }
                        
                        // 背景色を復元
                        if (svgData.backgroundColor) {
                            currentBackgroundColor = svgData.backgroundColor;
                            setBackgroundColor(currentBackgroundColor);
                            updateBackgroundColorSelection(currentBackgroundColor);
                        }
                        
                        // レイヤーを復元
                        if (svgData.layers && Array.isArray(svgData.layers)) {
                            console.log('レイヤー復元開始:', svgData.layers);
                            layers = [];
                            let loadedLayersCount = 0;
                            const totalLayers = svgData.layers.length;
                            
                            if (totalLayers === 0) {
                                alert('レイヤーデータがありません');
                                return;
                            }
                            
                            // 読み込むレイヤーの最大IDを事前に計算してnextLayerIdを更新
                            const layerIds = svgData.layers.map(l => l.id || 0);
                            const maxId = Math.max(...layerIds, 0);
                            nextLayerId = maxId + 1;
                            console.log(`レイヤーID範囲: ${Math.min(...layerIds)} - ${maxId}, nextLayerId: ${nextLayerId}`);
                            
                            svgData.layers.forEach((layerData, index) => {
                                console.log(`レイヤー ${index}:`, layerData);
                                
                                // typeプロパティがない場合は推測
                                if (!layerData.type) {
                                    if (layerData.text !== undefined) {
                                        layerData.type = 'text';
                                    } else if (layerData.svgContent || layerData.svgPath || layerData.materialId) {
                                        layerData.type = 'svg';
                                    }
                                    console.log('typeプロパティを推測:', layerData.type);
                                }
                                
                                if (layerData.type === 'svg') {
                                    // SVG素材レイヤー
                                    console.log('SVG素材レイヤー検出:', layerData);
                                    
                                    // svgContentが既に存在する場合はそれを使用
                                    if (layerData.svgContent) {
                                        console.log('svgContent既に存在 - 直接使用');
                                        
                                        // transformオブジェクトから位置情報を展開
                                        if (layerData.transform) {
                                            layerData.x = layerData.transform.x || layerData.x || currentCanvasWidth / 2;
                                            layerData.y = layerData.transform.y || layerData.y || currentCanvasHeight / 2;
                                            layerData.rotation = layerData.transform.rotation || layerData.rotation || 0;
                                            layerData.scale = layerData.transform.scale || layerData.scale || 1;
                                            layerData.flipX = layerData.transform.flipX !== undefined ? layerData.transform.flipX : (layerData.flipX !== undefined ? layerData.flipX : 1);
                                            layerData.flipY = layerData.transform.flipY !== undefined ? layerData.transform.flipY : (layerData.flipY !== undefined ? layerData.flipY : 1);
                                        }
                                        
                                        layers.push(layerData);
                                        renderLayer(layerData);
                                        
                                        loadedLayersCount++;
                                        if (loadedLayersCount === totalLayers) {
                                            saveToLocalStorage();
                                            console.log(`${layers.length}個のレイヤーを読み込みました`);
                                        }
                                    } else if (layerData.svgPath) {
                                        console.log('svgPathから読み込み:', layerData.svgPath);
                                        fetch(layerData.svgPath)
                                            .then(response => response.text())
                                            .then(svgText => {
                                                const parser = new DOMParser();
                                                const svgDoc = parser.parseFromString(svgText, 'image/svg+xml');
                                                const svgElement = svgDoc.querySelector('svg');
                                                
                                                if (svgElement) {
                                                    console.log('SVG要素取得成功');
                                                    // svgContentを更新
                                                    layerData.svgContent = svgElement.innerHTML;
                                                    
                                                    // transformオブジェクトから位置情報を展開
                                                    if (layerData.transform) {
                                                        layerData.x = layerData.transform.x || layerData.x || currentCanvasWidth / 2;
                                                        layerData.y = layerData.transform.y || layerData.y || currentCanvasHeight / 2;
                                                        layerData.rotation = layerData.transform.rotation || layerData.rotation || 0;
                                                        layerData.scale = layerData.transform.scale || layerData.scale || 1;
                                                        layerData.flipX = layerData.transform.flipX !== undefined ? layerData.transform.flipX : (layerData.flipX !== undefined ? layerData.flipX : 1);
                                                        layerData.flipY = layerData.transform.flipY !== undefined ? layerData.transform.flipY : (layerData.flipY !== undefined ? layerData.flipY : 1);
                                                    }
                                                    
                                                    layers.push(layerData);
                                                    renderLayer(layerData);
                                                } else {
                                                    console.error('SVG要素が見つかりません');
                                                }
                                                
                                                loadedLayersCount++;
                                                if (loadedLayersCount === totalLayers) {
                                                    saveToLocalStorage();
                                                    console.log(`${layers.length}個のレイヤーを読み込みました`);
                                                }
                                            })
                                            .catch(error => {
                                                console.error('SVG素材の読み込みエラー:', layerData.svgPath, error);
                                                loadedLayersCount++;
                                                if (loadedLayersCount === totalLayers) {
                                                    saveToLocalStorage();
                                                    console.log(`${layers.length}個のレイヤーを読み込みました`);
                                                }
                                            });
                                    } else {
                                        // svgPathもsvgContentもない場合
                                        console.warn('SVGレイヤーにsvgPathもsvgContentもありません:', layerData);
                                        loadedLayersCount++;
                                        if (loadedLayersCount === totalLayers) {
                                            saveToLocalStorage();
                                            console.log(`${layers.length}個のレイヤーを読み込みました`);
                                        }
                                    }
                                } else if (layerData.type === 'text') {
                                    // テキストレイヤー
                                    console.log('テキストレイヤー検出:', layerData);
                                    
                                    // transformオブジェクトから位置情報を展開
                                    if (layerData.transform) {
                                        layerData.x = layerData.transform.x || layerData.x || 0;
                                        layerData.y = layerData.transform.y || layerData.y || 0;
                                        layerData.rotation = layerData.transform.rotation || layerData.rotation || 0;
                                        layerData.scale = layerData.transform.scale || layerData.scale || 1;
                                        layerData.flipX = layerData.transform.flipX !== undefined ? layerData.transform.flipX : (layerData.flipX !== undefined ? layerData.flipX : 1);
                                        layerData.flipY = layerData.transform.flipY !== undefined ? layerData.transform.flipY : (layerData.flipY !== undefined ? layerData.flipY : 1);
                                    }
                                    
                                    layers.push(layerData);
                                    renderTextLayer(layerData);
                                    
                                    loadedLayersCount++;
                                    if (loadedLayersCount === totalLayers) {
                                        saveToLocalStorage();
                                        console.log(`${layers.length}個のレイヤーを読み込みました (nextLayerId: ${nextLayerId})`);
                                    }
                                } else {
                                    // 不明なタイプ
                                    console.warn('不明なレイヤータイプ:', layerData);
                                    loadedLayersCount++;
                                    if (loadedLayersCount === totalLayers) {
                                        saveToLocalStorage();
                                        console.log(`${layers.length}個のレイヤーを読み込みました (nextLayerId: ${nextLayerId})`);
                                    }
                                }
                            });
                        } else {
                            console.warn('SVGデータにレイヤー情報がありません');
                        }
                    } else {
                        console.warn('SVGデータが見つかりません');
                    }
                })
                .catch(error => {
                    console.error('SVGデータ読み込みエラー:', error);
                });
        }

        // URLからartwork_idパラメータを削除する関数
        function removeArtworkIdFromURL() {
            const url = new URL(window.location);
            url.searchParams.delete('artwork_id');
            window.history.replaceState({}, '', url);
            console.log('URLパラメータを削除しました');
        }

        // PNG出力機能
        function exportToPNG() {
            if (layers.length === 0) {
                alert('素材を追加してからPNG出力してください。');
                return;
            }

            console.log(`PNG出力開始 (${currentCanvasWidth}x${currentCanvasHeight}px)`);

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
            
            // 高解像度出力用のキャンバスを作成
            const outputCanvas = document.createElement('canvas');
            outputCanvas.width = currentCanvasWidth;
            outputCanvas.height = currentCanvasHeight;
            const ctx = outputCanvas.getContext('2d');
            
            // 高品質設定
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            
            // フォントが完全に読み込まれるのを待つ
            document.fonts.ready.then(() => {
                // まず背景を描画
                if (currentBackgroundColor === 'transparent') {
                    // 透明背景（何もしない）
                } else {
                    ctx.fillStyle = currentBackgroundColor;
                    ctx.fillRect(0, 0, outputCanvas.width, outputCanvas.height);
                }
                
                // レイヤー配列の順序通りに1つずつ描画
                function renderLayersSequentially(index) {
                    if (index >= layers.length) {
                        // 全レイヤー処理完了 - ダウンロード
                        outputCanvas.toBlob(function(blob) {
                            if (blob) {
                                const url = URL.createObjectURL(blob);
                                const link = document.createElement('a');
                                link.href = url;
                                link.download = `svg-composition-${currentCanvasWidth}x${currentCanvasHeight}-${Date.now()}.png`;
                                
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                
                                URL.revokeObjectURL(url);
                                console.log(`PNG出力完了 (${currentCanvasWidth}x${currentCanvasHeight}px)`);
                                
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
                                    updateTextButtonState();
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
                        return;
                    }
                    
                    const layer = layers[index];
                    if (!layer.visible) {
                        // 非表示レイヤーはスキップ
                        renderLayersSequentially(index + 1);
                        return;
                    }
                    
                    if (layer.type === 'text') {
                        // テキストレイヤーを直接Canvas描画
                        const transform = layer.transform || {
                            x: layer.x,
                            y: layer.y,
                            rotation: layer.rotation || 0,
                            scale: layer.scale || 1,
                            flipHorizontal: false,
                            flipVertical: false
                        };
                        
                        ctx.save();
                        
                        // 変形を適用
                        ctx.translate(transform.x, transform.y);
                        ctx.rotate((transform.rotation * Math.PI) / 180);
                        ctx.scale(
                            transform.scale * (transform.flipHorizontal ? -1 : 1),
                            transform.scale * (transform.flipVertical ? -1 : 1)
                        );
                        
                        // フォントスタイルを設定
                        const fontWeight = layer.fontWeight || '400';
                        const fontSize = layer.fontSize || 48;
                        const fontFamily = layer.fontFamily || 'Noto Sans JP';
                        ctx.font = `${fontWeight} ${fontSize}px "${fontFamily}", sans-serif`;
                        ctx.fillStyle = layer.color || '#000000';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        
                        // テキストを改行で分割して描画
                        const lines = layer.text.split('\n');
                        const lineHeight = fontSize * 1.2;
                        const totalHeight = lineHeight * (lines.length - 1);
                        const startY = -totalHeight / 2;
                        
                        lines.forEach((line, lineIndex) => {
                            const y = startY + (lineHeight * lineIndex);
                            ctx.fillText(line, 0, y);
                        });
                        
                        ctx.restore();
                        
                        // 次のレイヤーへ
                        renderLayersSequentially(index + 1);
                    } else {
                        // SVG素材レイヤー - 個別にSVGを作成して描画
                        const layerElement = document.getElementById(`layer-${layer.id}`);
                        if (!layerElement) {
                            renderLayersSequentially(index + 1);
                            return;
                        }
                        
                        // 個別レイヤーのSVGを作成
                        const tempSvg = canvas.cloneNode(false);
                        const clonedLayer = layerElement.cloneNode(true);
                        tempSvg.appendChild(clonedLayer);
                        
                        const svgData = new XMLSerializer().serializeToString(tempSvg);
                        const img = new Image();
                        
                        img.onload = function() {
                            ctx.drawImage(img, 0, 0, outputCanvas.width, outputCanvas.height);
                            // 次のレイヤーへ
                            renderLayersSequentially(index + 1);
                        };
                        
                        img.onerror = function(error) {
                            console.error(`Layer ${layer.id} 描画エラー:`, error);
                            // エラーでもスキップして次へ
                            renderLayersSequentially(index + 1);
                        };
                        
                        const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
                        const svgUrl = URL.createObjectURL(svgBlob);
                        img.src = svgUrl;
                        setTimeout(() => URL.revokeObjectURL(svgUrl), 100);
                    }
                }
                
                // 描画開始
                renderLayersSequentially(0);
                
            }).catch((error) => {
                console.error('フォント読み込みエラー:', error);
                alert('フォントの読み込みに失敗しました。PNG出力を中止します。');
                
                // エラー時も選択状態を復元
                if (currentSelectedId !== null) {
                    selectedLayerId = currentSelectedId;
                    layers.forEach(layer => {
                        renderLayer(layer);
                    });
                }
            });
        }

        // 作品投稿機能
        function uploadArtwork() {
            if (layers.length === 0) {
                alert('素材を追加してから投稿してください。');
                return;
            }

            if (!confirm('作品を投稿しますか？\n（すぐに公開されます）')) {
                return;
            }

            // ローディング表示
            const loadingDiv = document.createElement('div');
            loadingDiv.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 10000; text-align: center; min-width: 200px;';
            loadingDiv.innerHTML = '<div style="font-size: 2rem; margin-bottom: 10px;">📤</div><div style="font-size: 1.2rem;">投稿中...</div>';
            document.body.appendChild(loadingDiv);

            // exportToPNG関数を利用してPNG生成（投稿用に修正）
            exportToPNGForUpload(function(blob) {
                if (!blob) {
                    document.body.removeChild(loadingDiv);
                    alert('画像の生成に失敗しました');
                    return;
                }

                // FormData作成
                const formData = new FormData();
                formData.append('artwork', blob, `custom-artwork-${Date.now()}.png`);
                
                // SVGデータを保存（レイヤー情報をJSON形式で、テキストレイヤーは除外）
                const svgData = {
                    layers: layers.filter(layer => layer.type !== 'text'),
                    canvasWidth: currentCanvasWidth,
                    canvasHeight: currentCanvasHeight,
                    backgroundColor: currentBackgroundColor
                };
                formData.append('svg_data', JSON.stringify(svgData));
                
                // 使用素材IDを抽出して送信
                const usedMaterialIds = layers
                    .filter(layer => layer.type !== 'text' && layer.materialId)
                    .map(layer => layer.materialId)
                    .filter((id, index, self) => self.indexOf(id) === index) // 重複削除
                    .join(',');
                
                if (usedMaterialIds) {
                    formData.append('used_material_ids', usedMaterialIds);
                }

                // サーバーにアップロード
                fetch('../api/upload-custom-artwork.php', {
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
                    document.body.removeChild(loadingDiv);
                    
                    if (data.success) {
                        alert('作品を投稿しました！\nありがとうございます✨');
                    } else {
                        alert('投稿に失敗しました: ' + (data.error || '不明なエラー'));
                    }
                })
                .catch(error => {
                    document.body.removeChild(loadingDiv);
                    console.error('Upload error:', error);
                    alert('投稿中にエラーが発生しました: ' + error.message);
                });
            });
        }

        // PNG出力（投稿用・ダウンロードなし）
        function exportToPNGForUpload(callback) {
            if (layers.length === 0) {
                if (callback) callback(null);
                return;
            }

            console.log(`PNG生成開始 (投稿用: ${currentCanvasWidth}x${currentCanvasHeight}px)`);

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
            
            // 高解像度出力用のキャンバスを作成
            const outputCanvas = document.createElement('canvas');
            outputCanvas.width = currentCanvasWidth;
            outputCanvas.height = currentCanvasHeight;
            const ctx = outputCanvas.getContext('2d');
            
            // 高品質設定
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            
            // フォントが完全に読み込まれるのを待つ
            document.fonts.ready.then(() => {
                // まず背景を描画
                if (currentBackgroundColor === 'transparent') {
                    // 透明背景（何もしない）
                } else {
                    ctx.fillStyle = currentBackgroundColor;
                    ctx.fillRect(0, 0, outputCanvas.width, outputCanvas.height);
                }
                
                // レイヤー配列の順序通りに1つずつ描画（投稿用）
                function renderLayersSequentiallyForUpload(index) {
                    if (index >= layers.length) {
                        // 全レイヤー処理完了 - Blobを返す
                        outputCanvas.toBlob(function(blob) {
                            // 選択状態を復元
                            if (currentSelectedId !== null) {
                                selectedLayerId = currentSelectedId;
                                layers.forEach(layer => {
                                    renderLayer(layer);
                                });
                            }
                            
                            if (callback) callback(blob);
                        }, 'image/png');
                        return;
                    }
                    
                    const layer = layers[index];
                    if (!layer.visible || layer.type === 'text') {
                        // 非表示レイヤーまたはテキストレイヤーはスキップ（投稿時は除外）
                        renderLayersSequentiallyForUpload(index + 1);
                        return;
                    }
                    
                    // SVG素材レイヤーのみ描画
                    const layerElement = document.getElementById(`layer-${layer.id}`);
                    if (!layerElement) {
                        renderLayersSequentiallyForUpload(index + 1);
                        return;
                    }
                    
                    // 個別レイヤーのSVGを作成
                    const tempSvg = canvas.cloneNode(false);
                    const clonedLayer = layerElement.cloneNode(true);
                    tempSvg.appendChild(clonedLayer);
                    
                    const svgData = new XMLSerializer().serializeToString(tempSvg);
                    const img = new Image();
                    
                    img.onload = function() {
                        ctx.drawImage(img, 0, 0, outputCanvas.width, outputCanvas.height);
                        // 次のレイヤーへ
                        renderLayersSequentiallyForUpload(index + 1);
                    };
                    
                    img.onerror = function(error) {
                        console.error(`Layer ${layer.id} 描画エラー (投稿用):`, error);
                        // エラーでもスキップして次へ
                        renderLayersSequentiallyForUpload(index + 1);
                    };
                    
                    const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
                    const svgUrl = URL.createObjectURL(svgBlob);
                    img.src = svgUrl;
                    setTimeout(() => URL.revokeObjectURL(svgUrl), 100);
                }
                
                // 描画開始
                renderLayersSequentiallyForUpload(0);
            }).catch((error) => {
                console.error('フォント読み込みエラー:', error);
                if (currentSelectedId !== null) {
                    selectedLayerId = currentSelectedId;
                    layers.forEach(layer => {
                        renderLayer(layer);
                    });
                }
                if (callback) callback(null);
            });
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
                selectedLayerId = null;
                
                // DOM要素も削除
                const canvas = document.getElementById('mainCanvas');
                const layerElements = canvas.querySelectorAll('[id^="layer-"]');
                layerElements.forEach(element => element.remove());
                
                // 背景色もリセット
                currentBackgroundColor = 'transparent';
                setBackgroundColor('transparent');
                updateBackgroundColorSelection('transparent');
                
                // キャンバスサイズもリセット
                currentCanvasWidth = 1920;
                currentCanvasHeight = 1080;
                document.getElementById('canvasWidth').value = currentCanvasWidth;
                document.getElementById('canvasHeight').value = currentCanvasHeight;
                document.getElementById('currentSizeDisplay').textContent = `${currentCanvasWidth} × ${currentCanvasHeight} px`;
                
                const svgCanvas = document.getElementById('mainCanvas');
                if (svgCanvas) {
                    svgCanvas.setAttribute('width', currentCanvasWidth);
                    svgCanvas.setAttribute('height', currentCanvasHeight);
                    svgCanvas.setAttribute('viewBox', `0 0 ${currentCanvasWidth} ${currentCanvasHeight}`);
                    
                    const canvasBackground = document.getElementById('canvasBackground');
                    if (canvasBackground) {
                        canvasBackground.setAttribute('width', currentCanvasWidth);
                        canvasBackground.setAttribute('height', currentCanvasHeight);
                    }
                }
                
                console.log('全ての素材を削除しました');
                
                // ローカルストレージを完全に削除
                localStorage.removeItem('compose_editor_data');
                console.log('ローカルストレージを完全に削除しました');
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
            const searchButton = document.getElementById('searchButton');
            const clearButton = document.getElementById('clearSearch');
            
            if (!searchInput || !searchButton) return;
            
            // 検索実行関数
            function executeSearch() {
                const searchTerm = searchInput.value.trim();
                
                if (searchTerm) {
                    // クリアボタンを表示
                    clearButton.style.display = 'block';
                    // Ajax全件検索を実行
                    performGlobalSearch(searchTerm);
                } else {
                    alert('検索キーワードを入力してください');
                }
            }
            
            // 検索ボタンクリックイベント
            searchButton.addEventListener('click', executeSearch);
            
            // Enterキーで検索実行
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    executeSearch();
                }
            });
            
            // 入力時はクリアボタンの表示/非表示のみ
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.trim();
                // 入力があればクリアボタンを表示（検索結果がある場合）
                if (!searchTerm && clearButton.style.display === 'block') {
                    // 入力が空になった場合のみクリア動作
                    clearButton.style.display = 'none';
                    hideSearchResultsMessage();
                    window.location.reload();
                }
            });
            
            // クリアボタンイベント
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                this.style.display = 'none';
                hideSearchResultsMessage();
                // ページリロードで元の状態に戻す
                window.location.reload();
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
            
            // タイトルをエスケープして設定
            const escapedTitle = material.title.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            item.setAttribute('data-title', escapedTitle);
            
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
                         alt="${escapedTitle}" 
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
        
        // ページ読み込み（非同期）
        function loadPage(pageNumber) {
            console.log(`Loading page ${pageNumber}`);
            
            // ローディング表示
            showPaginationLoadingMessage();
            
            // Ajax APIを呼び出し
            fetch(`/admin/api/load-materials.php?page=${pageNumber}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTPエラー: ${response.status} ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // 素材グリッドを更新
                        updateMaterialsGridWithPagination(data.materials, pageNumber, data.total_pages, data.total_items);
                        console.log(`Page ${pageNumber} loaded: ${data.materials.length} materials`);
                    } else {
                        throw new Error(data.error || 'ページ読み込みエラー');
                    }
                })
                .catch(error => {
                    console.error('Page load error:', error);
                    showPaginationErrorMessage(error.message);
                });
        }
        
        // ページネーション付き素材グリッド更新
        function updateMaterialsGridWithPagination(materials, currentPage, totalPages, totalItems) {
            const materialsGrid = document.querySelector('.materials-grid');
            if (!materialsGrid) return;
            
            // グリッドを更新
            materialsGrid.innerHTML = '';
            materials.forEach(material => {
                const materialItem = createMaterialItem(material);
                materialsGrid.appendChild(materialItem);
            });
            
            // ページ情報を更新
            document.getElementById('currentPage').textContent = currentPage;
            document.getElementById('totalPages').textContent = totalPages;
            document.getElementById('totalItems').textContent = totalItems;
            
            // ページネーションボタンを更新
            updatePaginationButtons(currentPage, totalPages);
            
            // ローディングメッセージを非表示
            hidePaginationLoadingMessage();
            
            // 素材グリッドの先頭にスクロール
            materialsGrid.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // ページネーションボタンを更新
        function updatePaginationButtons(currentPage, totalPages) {
            const paginationContainer = document.querySelector('.pagination-container');
            if (!paginationContainer) return;
            
            paginationContainer.innerHTML = '';
            
            // 前へボタン
            if (currentPage > 1) {
                const prevBtn = document.createElement('button');
                prevBtn.type = 'button';
                prevBtn.className = 'pagination-btn';
                prevBtn.textContent = '前へ';
                prevBtn.onclick = () => loadPage(currentPage - 1);
                paginationContainer.appendChild(prevBtn);
            }
            
            // 次へボタン
            if (currentPage < totalPages) {
                const nextBtn = document.createElement('button');
                nextBtn.type = 'button';
                nextBtn.className = 'pagination-btn';
                nextBtn.textContent = '次へ';
                nextBtn.onclick = () => loadPage(currentPage + 1);
                paginationContainer.appendChild(nextBtn);
            }
        }
        
        // ページネーション時の検索状態管理
        function initializePaginationSearch() {
            // 検索中はページネーションを非表示にする
            const searchInput = document.getElementById('materialSearch');
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
        
        // ローディングメッセージ表示（ページネーション用）
        function showPaginationLoadingMessage() {
            const materialsGrid = document.querySelector('.materials-grid');
            if (!materialsGrid) return;
            
            materialsGrid.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #6c757d;">
                    <i class="bi bi-arrow-clockwise" style="font-size: 2rem; animation: spin 1s linear infinite;"></i>
                    <div style="margin-top: 1rem;">読み込み中...</div>
                </div>
            `;
        }
        
        // ローディングメッセージ非表示（ページネーション用）
        function hidePaginationLoadingMessage() {
            // 処理完了後は特に何もしない（グリッドが更新される）
        }
        
        // エラーメッセージ表示（ページネーション用）
        function showPaginationErrorMessage(errorMessage) {
            const materialsGrid = document.querySelector('.materials-grid');
            if (!materialsGrid) return;
            
            materialsGrid.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #dc3545;">
                    <i class="bi bi-exclamation-triangle" style="font-size: 2rem;"></i>
                    <div style="margin-top: 1rem;">エラー: ${errorMessage}</div>
                    <div style="margin-top: 0.5rem; font-size: 0.9rem;">ページを再読み込みしてください</div>
                </div>
            `;
        }

        // カラーパネル表示
        function showColorPanel(layer) {
            console.log(`showColorPanel called for layer ${layer.id}`);
            const colorPanelContent = document.getElementById('color-panel-content');
            if (!colorPanelContent) {
                console.log('color-panel-content element not found');
                return;
            }
            
            // テキストレイヤーの場合は色パネルを表示しない
            if (layer.type === 'text') {
                hideColorPanel();
                return;
            }
            
            console.log('Extracting colors from layer...');
            extractColorsFromLayer(layer);
        }

        // カラーパネル非表示
        function hideColorPanel() {
            const colorPalette = document.getElementById('colorPalette');
            if (colorPalette) {
                colorPalette.innerHTML = '';
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
            
            // URLパラメータからartwork_idを取得してSVGデータを読み込み
            const urlParams = new URLSearchParams(window.location.search);
            const artworkId = urlParams.get('artwork_id');
            
            // テキストサイズスライダーのイベントリスナー
            const textSizeSlider = document.getElementById('textSize');
            const textSizeValue = document.getElementById('textSizeValue');
            if (textSizeSlider && textSizeValue) {
                textSizeSlider.addEventListener('input', function() {
                    textSizeValue.textContent = `${this.value}px`;
                });
            }

            // モーダル外クリックで閉じる
            const textModal = document.getElementById('textModal');
            if (textModal) {
                textModal.addEventListener('click', function(e) {
                    if (e.target === textModal) {
                        closeTextModal();
                    }
                });
            }
            
            // artwork_idがない場合のみローカルストレージから復元
            if (!artworkId) {
                const restored = loadFromLocalStorage();
                if (restored) {
                    console.log('編集内容を復元しました');
                } else {
                    console.log('新規セッションを開始します');
                }
            }
            
            // artwork_idがある場合はSVGデータを読み込み
            if (artworkId) {
                loadArtworkSVG(artworkId);
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
            document.getElementById('flipHorizontalBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                flipHorizontalSelectedLayer();
            });
            document.getElementById('flipVerticalBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                flipVerticalSelectedLayer();
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
            document.getElementById('uploadBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                uploadArtwork();
            });
            document.getElementById('clearBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                clearAll();
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

    <!-- Google AdSense 広告 -->
    <div class="mt-5" style="display: flex; justify-content: center; gap: 100px; flex-wrap: wrap;">
        <?php include __DIR__ . '/../includes/ad-display.php'; ?>
        <div class="ad-desktop-only">
            <?php include __DIR__ . '/../includes/ad-display.php'; ?>
        </div>
    </div>


    <!-- みんなの作品セクション -->
    <?php if (!empty($storyArtworks)): ?>
    <section class="story-materials-section mt-5 mb-5">
        <div class="container" style="max-width: 1200px;">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center mb-2">優しい出会い</h2>
                    <p class="text-center text-muted mb-4">作者の想いが込められた作品たち</p>
                </div>
            </div>
            
            <div class="story-materials-list">
                <?php foreach ($storyArtworks as $story): ?>
                <div class="story-material-item">
                    <!-- 画像（リンク） -->
                    <a href="/everyone-work.php?id=<?= h($story['id']) ?>" class="text-decoration-none">
                        <div class="story-material-image-wrapper">
                            <?php
                            $storyImagePath = !empty($story['webp_path']) 
                                ? '/' . h($story['webp_path'])
                                : '/' . h($story['file_path']);
                            ?>
                            <div class="story-material-image">
                                <img src="<?= $storyImagePath ?>" 
                                     alt="<?= h($story['title']) ?>"
                                     loading="lazy"
                                     decoding="async">
                            </div>
                        </div>
                    </a>
                    
                    <!-- 説明（リンクなし） -->
                    <div class="story-material-content">
                        <h3 class="story-material-title">
                            <a href="/everyone-work.php?id=<?= h($story['id']) ?>" class="text-decoration-none">
                                <?= h($story['title']) ?>
                            </a>
                        </h3>
                        <div class="story-material-text">
                            <?= nl2br(h($story['description'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php include '../includes/footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- カスタムサイズ機能のJavaScript -->
    <script>
        // カスタムサイズ機能の追加
        let currentCanvasWidth = 1024;
        let currentCanvasHeight = 1024;
        
        // プリセットサイズを設定
        function setCanvasSize(width, height) {
            document.getElementById('canvasWidth').value = width;
            document.getElementById('canvasHeight').value = height;
            applyCanvasSize();
        }
        
        // キャンバスサイズを適用
        function applyCanvasSize() {
            const widthInput = document.getElementById('canvasWidth');
            const heightInput = document.getElementById('canvasHeight');
            
            let width = parseInt(widthInput.value);
            let height = parseInt(heightInput.value);
            
            // 値の検証
            if (isNaN(width) || width < 500 || width > 2000) {
                alert('幅は500px〜2000pxの範囲で入力してください');
                widthInput.value = currentCanvasWidth;
                return;
            }
            
            if (isNaN(height) || height < 500 || height > 2000) {
                alert('高さは500px〜2000pxの範囲で入力してください');
                heightInput.value = currentCanvasHeight;
                return;
            }
            
            // キャンバスサイズを更新
            currentCanvasWidth = width;
            currentCanvasHeight = height;
            
            const svgCanvas = document.getElementById('mainCanvas');
            if (svgCanvas) {
                svgCanvas.setAttribute('width', width);
                svgCanvas.setAttribute('height', height);
                svgCanvas.setAttribute('viewBox', `0 0 ${width} ${height}`);
                
                // キャンバス背景の更新
                const canvasBackground = document.getElementById('canvasBackground');
                if (canvasBackground) {
                    canvasBackground.setAttribute('width', width);
                    canvasBackground.setAttribute('height', height);
                }
            }
            
            // 現在のサイズ表示を更新
            document.getElementById('currentSizeDisplay').textContent = `${width} × ${height} px`;
            
            // キャンバスサイズ変更をローカルストレージに保存
            saveToLocalStorage();
            
            console.log(`キャンバスサイズを ${width}×${height} に変更しました`);
        }
        
        // アコーディオンのトグル機能
        function toggleHowTo() {
            const details = document.getElementById('howToDetails');
            const toggle = document.querySelector('.how-to-toggle');
            
            details.classList.toggle('active');
            toggle.classList.toggle('active');
            
            if (details.classList.contains('active')) {
                toggle.innerHTML = '<i class="bi bi-chevron-up"></i> 使い方を閉じる';
            } else {
                toggle.innerHTML = '<i class="bi bi-chevron-down"></i> 使い方を見る';
            }
        }
        
        // Enterキーでサイズ適用
        document.addEventListener('DOMContentLoaded', function() {
            const widthInput = document.getElementById('canvasWidth');
            const heightInput = document.getElementById('canvasHeight');
            
            if (widthInput) {
                widthInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        applyCanvasSize();
                    }
                });
            }
            
            if (heightInput) {
                heightInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        applyCanvasSize();
                    }
                });
            }
        });
    </script>
</body>
</html>