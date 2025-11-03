<?php
require_once '../config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// 新着10個のSVG素材を取得
$stmt = $pdo->prepare("
    SELECT DISTINCT id, title, slug, image_path, svg_path, webp_medium_path, category_id, structured_bg_color, created_at
    FROM materials 
    WHERE svg_path IS NOT NULL 
    AND svg_path != '' 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute();
$materials = $stmt->fetchAll();

// カテゴリ情報も取得
$categoryIds = array_column($materials, 'category_id');
if (!empty($categoryIds)) {
    $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
    $categoryStmt = $pdo->prepare("SELECT id, slug FROM categories WHERE id IN ($placeholders)");
    $categoryStmt->execute($categoryIds);
    $categoriesById = [];
    while ($cat = $categoryStmt->fetch()) {
        $categoriesById[$cat['id']] = $cat;
    }
    
    // 素材データにカテゴリ情報を追加
    foreach ($materials as &$material) {
        $material['category_slug'] = $categoriesById[$material['category_id']]['slug'] ?? '';
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>あなたのアトリエー - maruttoart</title>
    <meta name="description" content="複数のSVG素材を組み合わせて、オリジナルの作品を作成できます。">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/assets/icons/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/assets/icons/apple-touch-icon.png">
    
    <style>
        /* Bootstrap 5ベースの基本スタイル */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Noto Sans JP', sans-serif;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #222;
            background-color: #fff;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            padding-right: 15px;
            padding-left: 15px;
            margin-right: auto;
            margin-left: auto;
        }

        @media (min-width: 576px) {
            .container { max-width: 540px; }
        }

        @media (min-width: 768px) {
            .container { max-width: 720px; }
        }

        @media (min-width: 992px) {
            .container { max-width: 960px; }
        }

        @media (min-width: 1200px) {
            .container { max-width: 1140px; }
        }

        @media (min-width: 1400px) {
            .container { max-width: 1320px; }
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin-left: -15px;
            margin-right: -15px;
        }

        .col-lg-6 {
            position: relative;
            width: 100%;
            padding-left: 15px;
            padding-right: 15px;
            flex: 0 0 100%;
            max-width: 100%;
        }

        @media (min-width: 992px) {
            .col-lg-6 {
                flex: 0 0 50%;
                max-width: 50%;
            }
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
            margin-bottom: 1.5rem;
        }

        .card-body {
            flex: 1 1 auto;
            padding: 1.25rem;
        }

        /* ユーティリティクラス */
        .text-center { text-align: center !important; }
        .text-muted { color: #6c757d !important; }
        .mb-0 { margin-bottom: 0 !important; }
        .mb-2 { margin-bottom: 0.5rem !important; }
        .mb-3 { margin-bottom: 1rem !important; }
        .mb-4 { margin-bottom: 1.5rem !important; }
        .mt-3 { margin-top: 1rem !important; }
        .mt-4 { margin-top: 1.5rem !important; }
        .mt-5 { margin-top: 3rem !important; }
        .py-4 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
        .bg-light { background-color: #f8f9fa !important; }
        .d-inline-flex { display: inline-flex !important; }
        .align-items-center { align-items: center !important; }
        .me-1 { margin-right: 0.25rem !important; }
        .me-2 { margin-right: 0.5rem !important; }
        .position-relative { position: relative !important; }

        /* SVG表示セクション */
        .svg-display-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .svg-container {
            max-width: 100%;
            margin: 0 auto;
        }

        .svg-image-wrapper {
            position: relative;
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        #mainCanvas {
            width: 100%;
            height: auto;
            max-width: 100%;
            display: block;
        }

        /* タブアイコン */
        .tab-icons {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .tab-icon {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #495057;
            width: 60px;
            height: 60px;
        }

        .tab-icon:hover {
            background: #e9ecef;
            border-color: #adb5bd;
            transform: translateY(-1px);
        }

        .tab-icon.active {
            background: #007bff;
            border-color: #007bff;
            color: white;
            box-shadow: 0 2px 4px rgba(0,123,255,0.3);
        }

        .tab-icon:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            background: #f8f9fa;
            border-color: #e9ecef;
            color: #6c757d;
        }

        .tab-icon:disabled:hover {
            background: #f8f9fa;
            border-color: #e9ecef;
            transform: none;
        }

        .tab-icon-img {
            width: 24px;
            height: 24px;
        }

        /* SVGコントロール */
        .svg-controls {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .svg-controls .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
            display: block;
        }

        .svg-controls .btn {
            margin: 2px;
            font-size: 0.875rem;
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            background: white;
            color: #495057;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .svg-controls .btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        .svg-controls .btn.active {
            background: #007bff;
            border-color: #007bff;
            color: white;
        }

        /* 素材セクション */
        .materials-section {
            margin-bottom: 2rem;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 20px;
            background: #f8f9fa;
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

        /* フォームコントロールのスタイル */
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
            border: 2px solid #dee2e6;
            border-radius: 6px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            color: #212529;
            background-color: #fff;
            border-color: #4285f4;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(66, 133, 244, 0.25);
        }

        .form-control:hover {
            border-color: #4285f4;
        }

        .form-control-sm {
            min-height: calc(1.5em + 0.5rem + 4px);
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 4px;
        }

        .input-group {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            width: 100%;
        }

        .input-group > .form-control {
            position: relative;
            flex: 1 1 auto;
            width: 1%;
            min-width: 0;
        }

        .input-group > .form-control:not(:last-child) {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }

        .input-group > .btn:not(:first-child) {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
            margin-left: -2px;
        }

        .input-group .btn {
            position: relative;
            z-index: 2;
        }

        /* ボタンのスタイル */
        .btn {
            display: inline-block;
            font-weight: 400;
            color: #212529;
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            background-color: transparent;
            border: 2px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn:hover {
            color: #212529;
            text-decoration: none;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 4px;
        }

        .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
        }

        .btn-outline-secondary:hover {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
            transform: translateY(-1px);
        }

        .btn-outline-primary {
            color: #4285f4;
            border-color: #4285f4;
        }

        .btn-outline-primary:hover {
            color: #fff;
            background-color: #4285f4;
            border-color: #4285f4;
            transform: translateY(-1px);
        }

        /* プライマリ・サクセスボタンのスタイル */
        .btn-primary, .btn-success {
            padding: 0.5rem 1.5rem;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            color: #fff;
            background-color: #4285f4;
            border-color: #4285f4;
        }

        .btn-primary:hover {
            background-color: #3367d6;
            border-color: #3367d6;
            transform: translateY(-1px);
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
        .search-form .btn-outline-secondary {
            background-color: #ffffff;
            color: #444;
            border: 2px solid #ccc;
            border-radius: 12px;
            padding: 0.75em 1.25em;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
        }

        .search-form .btn-outline-secondary:hover {
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
            .search-form .btn-outline-secondary {
                width: 100%;
            }
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

        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 12px;
            margin-top: 1rem;
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
            background: white;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .materials-grid::-webkit-scrollbar {
            width: 6px;
        }

        .materials-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .materials-grid::-webkit-scrollbar-thumb {
            background: #007bff;
            border-radius: 3px;
        }

        .material-item {
            aspect-ratio: 1;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .material-item:hover {
            border-color: #007bff;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .material-item.selected {
            border-color: #007bff;
            background-color: #e7f3ff;
        }

        .material-item.selected-for-placement {
            border-color: #28a745;
            background-color: #d4edda;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .material-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* 水平素材グリッドのスタイル */
        .search-container {
            max-width: 400px;
        }

        .materials-grid-horizontal {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 10px 0;
            scroll-behavior: smooth;
        }

        .materials-grid-horizontal::-webkit-scrollbar {
            height: 6px;
        }

        .materials-grid-horizontal::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .materials-grid-horizontal::-webkit-scrollbar-thumb {
            background: #007bff;
            border-radius: 3px;
        }

        .material-item-horizontal {
            flex-shrink: 0;
            width: 100px;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .material-item-horizontal:hover {
            border-color: #007bff;
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,123,255,0.2);
        }

        .material-item-horizontal img {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .material-title {
            font-size: 0.75rem;
            color: #495057;
            font-weight: 500;
            line-height: 1.2;
            max-height: 2.4em;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .material-item-horizontal.selected-for-placement {
            border-color: #28a745;
            background-color: #d4edda;
            transform: translateY(-4px) scale(1.05);
        }

        /* 検索結果なしの表示 */
        #noSearchResults {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            background: white;
        }

        /* ドラッグ&ドロップ関連のスタイル */
        .svg-image-wrapper {
            transition: all 0.3s ease;
        }

        .svg-image-wrapper.drag-over {
            background-color: rgba(0, 123, 255, 0.05) !important;
            border: 2px dashed #007bff !important;
        }

        /* レイヤードラッグ関連のスタイル */
        body.dragging {
            cursor: grabbing !important;
            user-select: none;
        }

        body.dragging * {
            cursor: grabbing !important;
        }

        [id^="layer-"] {
            cursor: grab;
            transition: filter 0.2s ease;
        }

        [id^="layer-"]:hover {
            filter: drop-shadow(0 0 3px rgba(0, 123, 255, 0.5));
        }

        [id^="layer-"].dragging {
            cursor: grabbing;
            opacity: 0.8;
            transition: none; /* ドラッグ中はトランジションを無効化 */
        }

        /* スムーズな移動のための最適化 */
        #mainCanvas [id^="layer-"] {
            will-change: transform;
            transform-origin: center;
        }

        /* レイヤーリスト */
        .layers-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: white;
        }

        .layer-item {
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: background-color 0.2s ease;
        }

        .layer-item:hover {
            background-color: #f8f9fa;
        }

        .layer-item.active {
            background-color: #e7f3ff;
            border-left: 3px solid #007bff;
        }

        .layer-info {
            flex: 1;
            font-size: 0.875rem;
        }

        .layer-controls {
            display: flex;
            gap: 5px;
        }

        .layer-btn {
            padding: 4px 8px;
            font-size: 0.75rem;
            border: 1px solid #dee2e6;
            background: white;
            color: #495057;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .layer-btn:hover {
            background: #e9ecef;
        }

        .layer-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #f8f9fa;
            color: #6c757d;
        }

        .layer-btn:disabled:hover {
            background: #f8f9fa;
        }

        /* 背景色パレット用スタイル（詳細画面と同一） */
        .bg-color-section {
            background: linear-gradient(135deg, #fff8f0 0%, #f8f9fa 100%);
            border-radius: 16px;
            padding: 1.5rem;
            border: 2px solid #f0f0f0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }

        .bg-color-palette {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .bg-color-btn {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 60px;
        }

        .bg-color-btn:hover {
            border-color: #4285f4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.2);
        }

        .bg-color-btn.active {
            border-color: #4285f4;
            border-width: 3px;
            background-color: rgba(66, 133, 244, 0.1);
        }

        .bg-swatch {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-bottom: 0.25rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .transparent-bg {
            background: linear-gradient(45deg, #ddd 25%, transparent 25%), 
                        linear-gradient(-45deg, #ddd 25%, transparent 25%), 
                        linear-gradient(45deg, transparent 75%, #ddd 75%), 
                        linear-gradient(-45deg, transparent 75%, #ddd 75%);
            background-size: 10px 10px;
            background-position: 0 0, 0 5px, 5px -5px, -5px 0px;
        }

        /* カラーピッカー入力 */
        .form-control-color {
            width: 35px !important;
            height: 35px !important;
            border: 2px solid #dee2e6 !important;
            border-radius: 50% !important;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            display: block;
            flex-shrink: 0;
            min-width: 35px;
            padding: 0 !important;
            overflow: hidden;
        }

        .form-control-color:hover {
            border-color: #4285f4 !important;
            transform: scale(1.05);
            box-shadow: 0 3px 8px rgba(66, 133, 244, 0.2);
        }

        .form-control-color:focus {
            border-color: #4285f4 !important;
            box-shadow: 0 0 0 0.2rem rgba(66, 133, 244, 0.25), 
                        0 3px 8px rgba(66, 133, 244, 0.2) !important;
            transform: scale(1.02);
            outline: none;
        }

        .form-control-color::-webkit-color-swatch-wrapper {
            padding: 0 !important;
            border: none !important;
            border-radius: 50% !important;
            width: 100% !important;
            height: 100% !important;
        }

        .form-control-color::-webkit-color-swatch {
            border: none !important;
            border-radius: 50% !important;
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* Firefox対応 */
        .form-control-color::-moz-color-swatch {
            border: none !important;
            border-radius: 50% !important;
            width: 100% !important;
            height: 100% !important;
        }

        /* 操作ボタン */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }

        .action-btn {
            padding: 8px 16px;
            border: 1px solid #007bff;
            background: white;
            color: #007bff;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .action-btn:hover {
            background: #007bff;
            color: white;
        }

        .action-btn.danger {
            border-color: #dc3545;
            color: #dc3545;
        }

        .action-btn.danger:hover {
            background: #dc3545;
            color: white;
        }

        /* 移動コントロール */
        .move-controls {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
        }

        .move-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }

        .move-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            background: white;
            color: #6c757d;
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            gap: 0.25rem;
            padding: 0.5rem;
        }

        .move-btn:hover {
            border-color: #4285f4;
            color: #4285f4;
            background-color: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(66, 133, 244, 0.2);
        }

        .move-btn:active {
            transform: translateY(0);
            box-shadow: 0 1px 4px rgba(66, 133, 244, 0.3);
        }

        .move-btn svg {
            margin-bottom: 0.125rem;
        }

        /* ローディングスピナー */
        .spinner-border {
            display: inline-block;
            width: 2rem;
            height: 2rem;
            vertical-align: -0.125em;
            border: 0.25em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border 0.75s linear infinite;
        }

        @keyframes spinner-border {
            to {
                transform: rotate(360deg);
            }
        }

        .visually-hidden {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }

            .materials-grid {
                grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
                gap: 8px;
            }

            .tab-icons {
                gap: 8px;
            }

            .tab-icon {
                width: 50px;
                height: 50px;
                padding: 10px;
            }

            .svg-controls {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="/">maruttoart</a>
        </div>
    </nav>
    
    <div class="container mt-3">
        <nav aria-label="breadcrumb">
            <ol style="list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap;">
                <li style="margin-right: 0.5rem;">
                    <a href="/" style="color: #222; text-decoration: none;">ホーム</a>
                    <span style="margin-left: 0.5rem; color: #222;"> &gt; </span>
                </li>
                <li style="color: #222;">
                    あなたのアトリエ
                </li>
            </ol>
        </nav>
    </div>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-6" style="margin: 0 auto;">
                
                <!-- 素材選択セクション -->
                <div class="materials-section mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0">素材を選択</h5>
                        <div class="search-form">
                            <form class="d-flex align-items-center">
                                <input type="text" 
                                       id="materialSearch"
                                       placeholder="素材を検索（例：猫、花、食べ物など）" 
                                       class="search-input form-control me-2"
                                       onkeydown="handleSearchKeydown(event)">
                                <button type="button" class="search-button btn btn-primary" onclick="searchMaterials()">検索</button>
                                <button type="button" class="btn btn-outline-secondary ms-2" onclick="clearSearch()">クリア</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="materials-grid" id="materialsGrid">
                        <?php 
                        // デバッグ用: 素材IDの重複チェック
                        $materialIds = array_column($materials, 'id');
                        $duplicateIds = array_diff_assoc($materialIds, array_unique($materialIds));
                        if (!empty($duplicateIds)) {
                            echo "<!-- DEBUG: 重複ID検出: " . implode(', ', $duplicateIds) . " -->";
                        }
                        
                        foreach ($materials as $material): ?>
                        <div class="material-item" 
                             draggable="true"
                             data-material-id="<?= h($material['id']) ?>"
                             data-svg-path="<?= h($material['svg_path']) ?>"
                             data-title="<?= h($material['title']) ?>"
                             onclick="addMaterialToCanvas(this)"
                             ondragstart="startMaterialDrag(event, this)"
                             ondragend="endMaterialDrag(event, this)">
                            <!-- DEBUG: ID <?= h($material['id']) ?> - <?= h($material['title']) ?> -->
                            <img src="/<?= h($material['webp_medium_path'] ?: $material['image_path']) ?>" 
                                 alt="<?= h($material['title']) ?>"
                                 loading="lazy"
                                 draggable="false">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div id="noSearchResults" class="text-center text-muted py-4" style="display: none;">
                        検索結果がありません
                    </div>
                </div>

                <!-- SVG表示セクション -->
                <div class="svg-display-section">
                    <div class="svg-container">
                        <div class="svg-image-wrapper" 
                             id="canvasDropArea"
                             ondragover="allowDrop(event)" 
                             ondragleave="clearDragHighlight(event)"
                             ondrop="dropMaterialOnCanvas(event)">
                            <svg id="mainCanvas" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg">
                                <!-- 背景 -->
                                <rect id="canvasBackground" width="100%" height="100%" fill="white"/>
                                <!-- レイヤーがここに動的に追加される -->
                            </svg>
                        </div>
                        
                        <!-- タブアイコン -->
                        <div class="tab-icons">

                            

                            
                            <!-- 変形操作ボタン -->
                            <button type="button" class="tab-icon" id="zoomOutBtn" onclick="scaleSelectedLayer(0.9)" title="縮小" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                    <path d="M8 11h6"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" id="zoomInBtn" onclick="scaleSelectedLayer(1.1)" title="拡大" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                    <path d="M11 8v6"/>
                                    <path d="M8 11h6"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" id="rotateLeftBtn" onclick="rotateSelectedLayer(-15)" title="左回転" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <path d="M2.5 2v6h6M2.66 15.57a10 10 0 1 0 .57-8.38"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" id="rotateRightBtn" onclick="rotateSelectedLayer(15)" title="右回転" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38"/>
                                </svg>
                            </button>
                            
                            <!-- テーマボタン -->
                            <button type="button" class="tab-icon" id="springThemeBtn" onclick="applyColorTheme('spring')" title="春テーマ" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <circle cx="12" cy="12" r="3"/>
                                    <path d="M12 16.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 1 1 4.5 4.5 4.5 4.5 0 1 1-4.5 4.5"/>
                                    <path d="M12 7.5V9"/>
                                    <path d="M7.5 12H9"/>
                                    <path d="M16.5 12H15"/>
                                    <path d="M12 16.5V15"/>
                                    <path d="m8 8 1.88 1.88"/>
                                    <path d="M14.12 9.88 16 8"/>
                                    <path d="m8 16 1.88-1.88"/>
                                    <path d="M14.12 14.12 16 16"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" id="summerThemeBtn" onclick="applyColorTheme('summer')" title="夏テーマ" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <circle cx="12" cy="12" r="4"/>
                                    <path d="M12 2v2"/>
                                    <path d="M12 20v2"/>
                                    <path d="m4.93 4.93 1.41 1.41"/>
                                    <path d="m17.66 17.66 1.41 1.41"/>
                                    <path d="M2 12h2"/>
                                    <path d="M20 12h2"/>
                                    <path d="m6.34 17.66-1.41 1.41"/>
                                    <path d="m19.07 4.93-1.41 1.41"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" id="autumnThemeBtn" onclick="applyColorTheme('autumn')" title="秋テーマ" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
                                    <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" id="winterThemeBtn" onclick="applyColorTheme('winter')" title="冬テーマ" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <path d="m10 20-1.25-2.5L6 18"/>
                                    <path d="M10 4 8.75 6.5 6 6"/>
                                    <path d="m14 20 1.25-2.5L18 18"/>
                                    <path d="m14 4 1.25 2.5L18 6"/>
                                    <path d="m17 21-3-6h-4"/>
                                    <path d="m17 3-3 6 1.5 3"/>
                                    <path d="M2 12h6.5L10 9"/>
                                    <path d="m20 10-1.5 2 1.5 2"/>
                                    <path d="M22 12h-6.5L14 15"/>
                                    <path d="m4 10 1.5 2L4 14"/>
                                    <path d="m7 21 3-6-1.5-3"/>
                                    <path d="m7 3 3 6h4"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" id="monochromeThemeBtn" onclick="applyColorTheme('monochrome')" title="白黒テーマ" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <path d="M11.25 17.25h1.5L12 18z"/>
                                    <path d="m15 12 2 2"/>
                                    <path d="M18 6.5a.5.5 0 0 0-.5-.5"/>
                                    <path d="M20.69 9.67a4.5 4.5 0 1 0-7.04-5.5 8.35 8.35 0 0 0-3.3 0 4.5 4.5 0 1 0-7.04 5.5C2.49 11.2 2 12.88 2 14.5 2 19.47 6.48 22 12 22s10-2.53 10-7.5c0-1.62-.48-3.3-1.3-4.83"/>
                                    <path d="M6 6.5a.495.495 0 0 1 .5-.5"/>
                                    <path d="m9 12-2 2"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" id="sepiaThemeBtn" onclick="applyColorTheme('sepia')" title="セピアテーマ" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <path d="M10 2v2"/>
                                    <path d="M14 2v2"/>
                                    <path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1"/>
                                    <path d="M6 2v2"/>
                                </svg>
                            </button>
                            
                            <!-- レイヤー操作ボタン -->
                            <button type="button" class="tab-icon" id="bringToFrontBtn" onclick="bringLayerToFront()" title="最前面に移動" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <rect x="8" y="8" width="8" height="8" rx="2"/>
                                    <path d="M4 10a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2"/>
                                    <path d="M14 20a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" id="sendToBackBtn" onclick="sendLayerToBack()" title="最背面に移動" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <rect x="14" y="14" width="8" height="8" rx="2"/>
                                    <rect x="2" y="2" width="8" height="8" rx="2"/>
                                    <path d="M7 14v1a2 2 0 0 0 2 2h1"/>
                                    <path d="M14 7h1a2 2 0 0 1 2 2v1"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" id="deleteLayerBtn" onclick="deleteSelectedLayer()" title="レイヤー削除" disabled>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <path d="M10 11v6"/>
                                    <path d="M14 11v6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
                                    <path d="M3 6h18"/>
                                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- コントロールパネル -->
                <div class="card">
                    <div class="card-body">


                        <!-- 背景色選択（常時表示） -->
                        <div class="bg-color-section mb-4">
                            <div class="bg-color-palette d-flex justify-content-center align-items-center gap-3">
                                <button type="button" class="bg-color-btn active" data-color="transparent" title="透明（背景なし）" onclick="changeBackground('transparent'); updateBackgroundUI('transparent');">
                                    <div class="bg-swatch transparent-bg"></div>
                                </button>
                                
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" id="customBgColor" class="form-control form-control-color" 
                                           title="カスタم背景色を選択" value="#ffffff" 
                                           oninput="changeBackground(this.value)" 
                                           onchange="changeBackground(this.value)">
                                </div>
                            </div>
                        </div>

                        <!-- 操作ボタン -->
                        <div class="action-buttons mt-4 d-flex gap-2">
                            <button class="btn btn-outline-secondary" onclick="exportToPNG()">PNGダウンロード</button>
                            <button class="btn btn-outline-secondary" onclick="clearAll()">全て削除</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // グローバル変数
        let layers = [];
        let activeLayerId = null;
        let layerIdCounter = 0;
        let currentBackground = 'white';
        let selectedPngSize = 2500; // 固定2500px
        let centerOffsetCounter = 0; // 中央配置時のオフセット用

        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            loadFromStorage();
            
            // 初期背景色の設定
            changeBackground(currentBackground);
            
            // ドラッグ&ドロップの初期化
            initializeDragAndDrop();
        });

        // ドラッグ&ドロップの初期化
        function initializeDragAndDrop() {
            console.log('ドラッグ&ドロップ初期化中...');
            
            // キャンバスドロップエリア
            const canvasDropArea = document.getElementById('canvasDropArea');
            if (canvasDropArea) {
                console.log('キャンバスドロップエリア見つかりました');
                
                // 既存のイベントリスナーを削除して再追加
                canvasDropArea.removeEventListener('dragover', allowDrop);
                canvasDropArea.removeEventListener('drop', dropMaterialOnCanvas);
                canvasDropArea.removeEventListener('dragleave', clearDragHighlight);
                
                canvasDropArea.addEventListener('dragover', allowDrop);
                canvasDropArea.addEventListener('drop', dropMaterialOnCanvas);
                canvasDropArea.addEventListener('dragleave', clearDragHighlight);
                
                console.log('キャンバスイベントリスナー設定完了');
            } else {
                console.log('キャンバスドロップエリアが見つかりません');
            }
            
            // 素材アイテム
            const materialItems = document.querySelectorAll('.material-item');
            console.log('素材アイテム数:', materialItems.length);
            
            materialItems.forEach((item, index) => {
                console.log(`素材${index + 1}にイベント設定:`, item.dataset.title);
                
                // 既存のイベントリスナーを削除
                item.removeEventListener('dragstart', item._dragStartHandler);
                item.removeEventListener('dragend', item._dragEndHandler);
                
                // 新しいイベントリスナーを追加（ドラッグのみ）
                item._dragStartHandler = (e) => startMaterialDrag(e, item);
                item._dragEndHandler = (e) => endMaterialDrag(e, item);
                
                item.addEventListener('dragstart', item._dragStartHandler);
                item.addEventListener('dragend', item._dragEndHandler);
            });
        }



        // 素材をキャンバスに追加
        function addMaterialToCanvas(element) {
            const materialId = element.dataset.materialId;
            const svgPath = element.dataset.svgPath;
            const title = element.dataset.title;

            // SVGファイルを読み込み
            fetch('/' + svgPath)
                .then(response => response.text())
                .then(svgText => {
                    const parser = new DOMParser();
                    const svgDoc = parser.parseFromString(svgText, 'image/svg+xml');
                    const svgElement = svgDoc.querySelector('svg');
                    
                    if (svgElement) {
                        // レイヤーオブジェクトを作成
                        const layer = {
                            id: ++layerIdCounter,
                            materialId: materialId,
                            svgId: materialId,
                            title: title,
                            svgContent: svgElement.innerHTML,
                            originalSvgContent: svgElement.innerHTML, // 元の色情報を保持
                            svgPath: svgPath, // 元のSVGパスを保存
                            transform: {
                                x: 512 + (centerOffsetCounter * 20), // キャンバス中央 + 少しずつオフセット
                                y: 512 + (centerOffsetCounter * 20), // キャンバス中央 + 少しずつオフセット
                                scale: 1,
                                rotation: 0
                            },
                            visible: true
                        };

                        layers.push(layer);
                        renderLayer(layer);
                        
                        saveToStorage();

                        // デバッグ用ログ
                        console.log(`素材追加: ${title} at (${layer.transform.x}, ${layer.transform.y})`);

                        // オフセットカウンターを更新（10回で初期化）
                        centerOffsetCounter = (centerOffsetCounter + 1) % 10;

                        // 素材タブから自動的にレイヤータブに切り替え
                        
                    }
                })
                .catch(error => {
                    console.error('SVG読み込みエラー:', error);
                    alert('素材の読み込みに失敗しました');
                });
        }

        // レイヤーをレンダリング
        function renderLayer(layer) {
            const canvas = document.getElementById('mainCanvas');
            
            // 既存のレイヤー要素の位置を記録
            const existingLayer = document.getElementById(`layer-${layer.id}`);
            let nextSibling = null;
            if (existingLayer) {
                nextSibling = existingLayer.nextSibling;
                existingLayer.remove();
            }

            if (!layer.visible) return;

            // 新しいレイヤー要素を作成
            const layerGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            layerGroup.id = `layer-${layer.id}`;
            layerGroup.innerHTML = layer.svgContent;

            // 正しい位置に挿入（順序を保持）
            if (nextSibling) {
                canvas.insertBefore(layerGroup, nextSibling);
            } else {
                // レイヤーIDの順序で正しい位置を見つける
                const layerIndex = layers.findIndex(l => l.id === layer.id);
                const allLayerElements = Array.from(canvas.querySelectorAll('[id^="layer-"]'));
                
                let insertPosition = null;
                for (let i = layerIndex + 1; i < layers.length; i++) {
                    const nextLayerElement = document.getElementById(`layer-${layers[i].id}`);
                    if (nextLayerElement) {
                        insertPosition = nextLayerElement;
                        break;
                    }
                }
                
                if (insertPosition) {
                    canvas.insertBefore(layerGroup, insertPosition);
                } else {
                    canvas.appendChild(layerGroup);
                }
            }

            // オブジェクトの境界ボックスを取得
            const bbox = layerGroup.getBBox();
            const centerX = bbox.x + bbox.width / 2;
            const centerY = bbox.y + bbox.height / 2;

            // オブジェクトをキャンバス上の指定位置に配置（元の位置をオフセット）
            const translateX = layer.transform.x - centerX;
            const translateY = layer.transform.y - centerY;

            // 変形を適用
            const transform = `translate(${translateX}, ${translateY}) scale(${layer.transform.scale}) rotate(${layer.transform.rotation}, ${layer.transform.x}, ${layer.transform.y})`;
            layerGroup.setAttribute('transform', transform);

            // クリックとドラッグイベントを追加
            layerGroup.style.cursor = 'move';
            
            // クリックイベント（ドラッグと干渉しないように）
            let clickTimeout;
            layerGroup.addEventListener('mousedown', (e) => {
                clickTimeout = setTimeout(() => {
                    selectLayer(layer.id);
                }, 150); // ドラッグとクリックを区別するための遅延
            });
            
            layerGroup.addEventListener('mouseup', () => {
                clearTimeout(clickTimeout);
            });
            
            layerGroup.addEventListener('mousemove', () => {
                clearTimeout(clickTimeout);
            });
            
            // ドラッグ機能を追加
            addLayerDragFunctionality(layerGroup, layer.id);
        }

        // 全レイヤーを正しい順序で再レンダリング
        function renderAllLayers() {
            const canvas = document.getElementById('mainCanvas');
            
            // 既存のレイヤー要素をすべて削除
            canvas.querySelectorAll('[id^="layer-"]').forEach(element => {
                element.remove();
            });
            
            // レイヤーを配列順で再レンダリング（配列の後の要素が前面に表示）
            layers
                .filter(layer => layer.visible)
                .forEach(layer => {
                    const layerGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                    layerGroup.id = `layer-${layer.id}`;
                    layerGroup.innerHTML = layer.svgContent;

                    // オブジェクトの境界ボックスを取得
                    canvas.appendChild(layerGroup);
                    const bbox = layerGroup.getBBox();
                    const centerX = bbox.x + bbox.width / 2;
                    const centerY = bbox.y + bbox.height / 2;

                    // オブジェクトをキャンバス上の指定位置に配置（元の位置をオフセット）
                    const translateX = layer.transform.x - centerX;
                    const translateY = layer.transform.y - centerY;

                    // 変形を適用
                    const transform = `translate(${translateX}, ${translateY}) scale(${layer.transform.scale}) rotate(${layer.transform.rotation}, ${layer.transform.x}, ${layer.transform.y})`;
                    layerGroup.setAttribute('transform', transform);

                    // クリックとドラッグイベントを追加
                    layerGroup.style.cursor = 'move';
                    
                    // クリックイベント（ドラッグと干渉しないように）
                    let clickTimeout;
                    layerGroup.addEventListener('mousedown', (e) => {
                        clickTimeout = setTimeout(() => {
                            selectLayer(layer.id);
                        }, 150); // ドラッグとクリックを区別するための遅延
                    });
                    
                    layerGroup.addEventListener('mouseup', () => {
                        clearTimeout(clickTimeout);
                    });
                    
                    layerGroup.addEventListener('mousemove', () => {
                        clearTimeout(clickTimeout);
                    });
                    
                    // ドラッグ機能を追加
                    addLayerDragFunctionality(layerGroup, layer.id);
                });
        }

        // レイヤー選択
        function selectLayer(layerId) {
            console.log('selectLayer called with layerId:', layerId);
            activeLayerId = layerId;
            console.log('activeLayerId set to:', activeLayerId);
            
            // 全レイヤーのハイライトを削除
            document.querySelectorAll('[id^="layer-"]').forEach(layerElement => {
                layerElement.style.filter = '';
            });
            
            // 選択されたレイヤーをハイライト
            const selectedLayer = document.getElementById(`layer-${layerId}`);
            console.log('selectedLayer element:', selectedLayer);
            if (selectedLayer) {
                selectedLayer.style.filter = 'drop-shadow(0 0 5px #007bff)';
            }

            // 変形コントロールを表示
            updateTransformControls();
        }

        // 選択中のレイヤーを最前面に移動
        function bringLayerToFront() {
            if (!activeLayerId) return;
            
            const layerIndex = layers.findIndex(l => l.id === activeLayerId);
            if (layerIndex !== -1 && layerIndex < layers.length - 1) {
                // 配列の最後に移動（表示上は最前面に移動）
                const layer = layers[layerIndex];
                layers.splice(layerIndex, 1);
                layers.push(layer);
                
                renderAllLayers();
                saveToStorage();
            }
        }

        // 選択中のレイヤーを最背面に移動
        function sendLayerToBack() {
            if (!activeLayerId) return;
            
            const layerIndex = layers.findIndex(l => l.id === activeLayerId);
            if (layerIndex !== -1 && layerIndex > 0) {
                // 配列の最初に移動（表示上は最背面に移動）
                const layer = layers[layerIndex];
                layers.splice(layerIndex, 1);
                layers.unshift(layer);
                
                renderAllLayers();
                saveToStorage();
            }
        }

        // 選択中のレイヤーを削除
        function deleteSelectedLayer() {
            if (!activeLayerId) return;
            
            if (confirm('選択中のレイヤーを削除しますか？')) {
                const layerIndex = layers.findIndex(l => l.id === activeLayerId);
                if (layerIndex !== -1) {
                    // レイヤーをキャンバスから削除
                    const layerElement = document.getElementById(`layer-${activeLayerId}`);
                    if (layerElement) {
                        layerElement.remove();
                    }
                    
                    // 配列から削除
                    layers.splice(layerIndex, 1);
                    
                    // アクティブレイヤーをリセット
                    activeLayerId = null;
                    
                    updateTransformControls();
                    saveToStorage();
                }
            }
        }

        // 変形コントロールを更新
        function updateTransformControls() {
            // タブアイコンスタイルの変形ボタン
            const zoomOutBtn = document.getElementById('zoomOutBtn');
            const zoomInBtn = document.getElementById('zoomInBtn');
            const rotateLeftBtn = document.getElementById('rotateLeftBtn');
            const rotateRightBtn = document.getElementById('rotateRightBtn');

            // テーマボタン
            const springThemeBtn = document.getElementById('springThemeBtn');
            const summerThemeBtn = document.getElementById('summerThemeBtn');
            const autumnThemeBtn = document.getElementById('autumnThemeBtn');
            const winterThemeBtn = document.getElementById('winterThemeBtn');
            const monochromeThemeBtn = document.getElementById('monochromeThemeBtn');
            const sepiaThemeBtn = document.getElementById('sepiaThemeBtn');

            // レイヤー操作ボタン
            const bringToFrontBtn = document.getElementById('bringToFrontBtn');
            const sendToBackBtn = document.getElementById('sendToBackBtn');
            const deleteLayerBtn = document.getElementById('deleteLayerBtn');

            if (activeLayerId) {
                // タブアイコンスタイルの変形ボタンを有効化
                zoomOutBtn.disabled = false;
                zoomInBtn.disabled = false;
                rotateLeftBtn.disabled = false;
                rotateRightBtn.disabled = false;

                // テーマボタンを有効化
                springThemeBtn.disabled = false;
                summerThemeBtn.disabled = false;
                autumnThemeBtn.disabled = false;
                winterThemeBtn.disabled = false;
                monochromeThemeBtn.disabled = false;
                sepiaThemeBtn.disabled = false;

                // レイヤー操作ボタンを有効化
                bringToFrontBtn.disabled = false;
                sendToBackBtn.disabled = false;
                deleteLayerBtn.disabled = false;
            } else {
                // タブアイコンスタイルの変形ボタンを無効化
                zoomOutBtn.disabled = true;
                zoomInBtn.disabled = true;
                rotateLeftBtn.disabled = true;
                rotateRightBtn.disabled = true;

                // テーマボタンを無効化
                springThemeBtn.disabled = true;
                summerThemeBtn.disabled = true;
                autumnThemeBtn.disabled = true;
                winterThemeBtn.disabled = true;
                monochromeThemeBtn.disabled = true;
                sepiaThemeBtn.disabled = true;

                // レイヤー操作ボタンを無効化
                bringToFrontBtn.disabled = true;
                sendToBackBtn.disabled = true;
                deleteLayerBtn.disabled = true;
            }
        }

        // レイヤー移動
        function moveLayer(direction) {
            if (!activeLayerId) return;
            
            const layer = layers.find(l => l.id === activeLayerId);
            if (!layer) return;

            // より大きな移動幅に設定
            const moveDistance = 50;
            
            // キャンバス外にも移動可能にする
            const maxMove = 2000; // 十分に大きな範囲
            
            switch(direction) {
                case 'up':
                    layer.transform.y = Math.max(-maxMove, layer.transform.y - moveDistance);
                    break;
                case 'down':
                    layer.transform.y = Math.min(maxMove, layer.transform.y + moveDistance);
                    break;
                case 'left':
                    layer.transform.x = Math.max(-maxMove, layer.transform.x - moveDistance);
                    break;
                case 'right':
                    layer.transform.x = Math.min(maxMove, layer.transform.x + moveDistance);
                    break;
            }

            renderLayer(layer);
            
            saveToStorage();
        }

        // レイヤー位置リセット
        function resetLayerPosition() {
            if (!activeLayerId) return;
            
            const layer = layers.find(l => l.id === activeLayerId);
            if (!layer) return;

            layer.transform.x = 512;
            layer.transform.y = 512;

            renderLayer(layer);
            
            saveToStorage();
        }

        // レイヤースケール変更
        function scaleLayer(scale) {
            if (!activeLayerId) return;
            
            const layer = layers.find(l => l.id === activeLayerId);
            if (!layer) return;

            // scaleが1未満の場合は相対的な変更、1以上の場合は絶対値
            if (scale < 1) {
                // 相対的な変更（例：0.9 = 現在の90%に）
                layer.transform.scale = Math.max(0.1, Math.min(3, layer.transform.scale * scale));
            } else {
                // 絶対値指定（例：1.1 = 現在の110%に、ただし1より大きい場合）
                if (scale > 1) {
                    layer.transform.scale = Math.max(0.1, Math.min(3, layer.transform.scale * scale));
                } else {
                    // scale === 1の場合はリセット
                    layer.transform.scale = 1;
                }
            }

            renderLayer(layer);
            
            updateTransformControls();
            saveToStorage();
        }

        // レイヤー回転
        function rotateLayer(degrees, reset = false) {
            if (!activeLayerId) return;
            
            const layer = layers.find(l => l.id === activeLayerId);
            if (!layer) return;

            if (reset) {
                layer.transform.rotation = 0;
            } else {
                layer.transform.rotation = (layer.transform.rotation + degrees) % 360;
            }

            renderLayer(layer);
            
            updateTransformControls();
            saveToStorage();
        }

        // タブアイコンボタン用のスケール関数
        function scaleSelectedLayer(scale) {
            if (!activeLayerId) return;
            
            const layer = layers.find(l => l.id === activeLayerId);
            if (!layer) return;

            layer.transform.scale *= scale;
            
            // スケールの最小値・最大値を制限
            if (layer.transform.scale < 0.1) layer.transform.scale = 0.1;
            if (layer.transform.scale > 5) layer.transform.scale = 5;

            renderLayer(layer);
            
            updateTransformControls();
            saveToStorage();
        }

        // タブアイコンボタン用の回転関数
        function rotateSelectedLayer(degrees) {
            if (!activeLayerId) return;
            
            const layer = layers.find(l => l.id === activeLayerId);
            if (!layer) return;

            layer.transform.rotation = (layer.transform.rotation + degrees) % 360;

            renderLayer(layer);
            
            updateTransformControls();
            saveToStorage();
        }

        // 色テーマ適用
        function applyColorTheme(theme) {
            console.log('=== applyColorTheme START ===');
            console.log('theme:', theme);
            console.log('activeLayerId:', activeLayerId);
            
            if (!activeLayerId) {
                alert('レイヤーを選択してください');
                return;
            }

            const layer = layers.find(l => l.id === activeLayerId);
            console.log('layer found:', layer);
            if (!layer) return;

            const layerElement = document.getElementById(`layer-${layer.id}`);
            console.log('layerElement:', layerElement);
            if (!layerElement) return;

            const svgElements = layerElement.querySelectorAll('path, circle, rect, polygon, ellipse');
            console.log('svgElements count:', svgElements.length);
            const excludeGrayBlack = true; // デフォルトで黒・グレー系を除外
            console.log('excludeGrayBlack:', excludeGrayBlack);

            const themeColors = {
                spring: ['#90EE90', '#FFB6C1', '#98FB98', '#F0E68C'],
                summer: ['#87CEEB', '#FFD700', '#FF6347', '#00CED1'],
                autumn: ['#DEB887', '#FF6347', '#D2691E', '#CD853F'],
                winter: ['#E6F3FF', '#4682B4', '#B0C4DE', '#87CEFA'],
                monochrome: ['#000000', '#404040', '#808080', '#C0C0C0'],
                sepia: ['#DEB887', '#8B4513', '#A0522D', '#CD853F']
            };

            const colors = themeColors[theme] || themeColors.spring;
            console.log('theme colors:', colors);

            // 元の色とテーマ色のマッピングを作成（毎回ランダムに配置）
            const colorMapping = new Map();
            const shuffledColors = [...colors]; // 配列をコピー
            
            // Fisher-Yates アルゴリズムで配列をシャッフル
            for (let i = shuffledColors.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [shuffledColors[i], shuffledColors[j]] = [shuffledColors[j], shuffledColors[i]];
            }
            
            let colorIndex = 0;

            svgElements.forEach((element, index) => {
                console.log(`Processing element ${index}:`, element);
                
                // 現在の色を取得（fillまたはstyleから）
                let currentColor = element.getAttribute('fill');
                const styleAttr = element.getAttribute('style');
                
                // style属性から色情報を抽出
                if (styleAttr && styleAttr.includes('fill:')) {
                    const fillMatch = styleAttr.match(/fill:\s*([^;]+)/);
                    if (fillMatch) {
                        // rgb(r,g,b) を16進数に変換
                        const colorValue = fillMatch[1].trim();
                        if (colorValue.startsWith('rgb(')) {
                            const rgbMatch = colorValue.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/);
                            if (rgbMatch) {
                                const r = parseInt(rgbMatch[1]);
                                const g = parseInt(rgbMatch[2]);
                                const b = parseInt(rgbMatch[3]);
                                currentColor = '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
                            }
                        } else {
                            currentColor = colorValue;
                        }
                    }
                }
                
                // fallback to black if no color found
                if (!currentColor) {
                    currentColor = '#000000';
                }
                
                console.log('Current color:', currentColor);
                
                // style属性を削除してfill属性を優先
                if (styleAttr) {
                    console.log('Removing style:', styleAttr);
                    element.removeAttribute('style');
                }
                
                // 黒・グレー除外チェック
                if (excludeGrayBlack && (currentColor.includes('#000') || 
                    currentColor.includes('gray') || currentColor.includes('grey'))) {
                    console.log('Skipping gray/black color:', currentColor);
                    return; // 変更しない
                }

                // 同じ色のオブジェクトには同じランダムテーマ色を適用
                if (!colorMapping.has(currentColor)) {
                    const newThemeColor = shuffledColors[colorIndex % shuffledColors.length];
                    colorMapping.set(currentColor, newThemeColor);
                    console.log(`Mapping: ${currentColor} -> ${newThemeColor}`);
                    colorIndex++;
                }

                const newColor = colorMapping.get(currentColor);
                console.log('Setting new color:', newColor);
                element.setAttribute('fill', newColor);
                
                if (element.getAttribute('stroke') && element.getAttribute('stroke') !== 'none') {
                    element.setAttribute('stroke', newColor);
                    console.log('Also set stroke:', newColor);
                }
            });

            console.log('Color mapping created:', colorMapping);

            // 変更されたSVGコンテンツをレイヤーデータに保存
            layer.svgContent = layerElement.innerHTML;
            console.log('Updated layer svgContent');
            
            renderLayer(layer);
            saveToStorage();
            console.log('=== applyColorTheme END ===');
        }

        // ランダム配色
        function randomizeColors() {
            if (!activeLayerId) {
                alert('レイヤーを選択してください');
                return;
            }
            
            const layer = layers.find(l => l.id === activeLayerId);
            if (!layer) return;
            
            const layerElement = document.getElementById(`layer-${layer.id}`);
            if (!layerElement) return;
            
            const svgElements = layerElement.querySelectorAll('path, circle, rect, polygon, ellipse');
            const excludeGrayBlack = true; // デフォルトで黒・グレー系を除外
            
            // カラーパレット
            let colors = [
                '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
                '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
                '#F8C471', '#82E0AA', '#F1948A', '#85C1E9', '#D2B4DE'
            ];
            
            if (excludeGrayBlack) {
                // グレー・黒系の色を除外
                colors = colors.filter(color => {
                    const hex = color.toLowerCase();
                    return !hex.includes('gray') && !hex.includes('grey') && 
                           hex !== '#000000' && hex !== '#333333' && hex !== '#666666' && hex !== '#999999';
                });
            }
            
            // 元の色とランダム色のマッピングを作成
            const colorMapping = new Map();
            
            svgElements.forEach(element => {
                // style属性を削除してfill属性を優先
                element.removeAttribute('style');
                
                // 現在の色を取得
                let currentColor = element.getAttribute('fill') || '#000000';
                
                // 黒・グレー除外チェック
                if (excludeGrayBlack && (currentColor.includes('#000') || 
                    currentColor.includes('gray') || currentColor.includes('grey'))) {
                    return; // 変更しない
                }

                // 同じ色のオブジェクトには同じランダム色を適用
                if (!colorMapping.has(currentColor)) {
                    const randomColor = colors[Math.floor(Math.random() * colors.length)];
                    colorMapping.set(currentColor, randomColor);
                }

                const newColor = colorMapping.get(currentColor);
                element.setAttribute('fill', newColor);
                
                if (element.getAttribute('stroke') && element.getAttribute('stroke') !== 'none') {
                    element.setAttribute('stroke', newColor);
                }
            });
            
            // 変更されたSVGコンテンツをレイヤーデータに保存
            layer.svgContent = layerElement.innerHTML;
            
            renderLayer(layer);
            saveToStorage();
        }

        // 色をリセット
        function resetColors() {
            if (!activeLayerId) {
                alert('レイヤーを選択してください');
                return;
            }
            
            const layer = layers.find(l => l.id === activeLayerId);
            if (!layer) return;
            
            console.log('resetColors - layer object:', layer);
            
            // 元のSVGコンテンツがある場合はそれを使用
            if (layer.originalSvgContent) {
                console.log('Resetting colors using originalSvgContent');
                layer.svgContent = layer.originalSvgContent;
                renderLayer(layer);
                saveToStorage();
                return;
            }
            
            // フォールバック: SVGファイルを再読み込み
            let svgPath = layer.svgPath;
            
            // svgPathがない場合、materialIdから推測
            if (!svgPath && layer.materialId) {
                svgPath = `uploads/structured/${layer.materialId}.svg`;
                console.log('SVGパスを推測:', svgPath);
            }
            
            // それでもsvgPathがない場合、svgIdから推測
            if (!svgPath && layer.svgId) {
                svgPath = `uploads/structured/${layer.svgId}.svg`;
                console.log('SVGパスをsvgIdから推測:', svgPath);
            }
            
            if (!svgPath) {
                console.error('SVGパスが見つかりません。layer:', layer);
                alert('色をリセットできませんでした。SVGパスが見つかりません。');
                return;
            }
            
            console.log('Fetching original SVG from:', svgPath);
            
            // 複数のパスパターンを試行
            const pathsToTry = [
                svgPath,
                `uploads/structured/${layer.materialId}.svg`,
                `uploads/${layer.materialId}.svg`,
                layer.svgId ? `uploads/structured/${layer.svgId}.svg` : null,
                layer.svgId ? `uploads/${layer.svgId}.svg` : null
            ].filter(path => path !== null);
            
            console.log('Trying paths:', pathsToTry);
            
            let tryIndex = 0;
            
            function tryNextPath() {
                if (tryIndex >= pathsToTry.length) {
                    console.error('すべてのパスで失敗しました');
                    alert('色をリセットできませんでした。SVGファイルが見つかりませんでした。');
                    return;
                }
                
                const currentPath = pathsToTry[tryIndex];
                console.log(`Trying path ${tryIndex + 1}/${pathsToTry.length}:`, currentPath);
                
                fetch('/' + currentPath)
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
                            console.log('Successfully loaded SVG from:', currentPath);
                            layer.svgContent = svgElement.innerHTML;
                            layer.originalSvgContent = svgElement.innerHTML; // 今後のために保存
                            layer.svgPath = currentPath; // 正しいパスを保存
                            renderLayer(layer);
                            saveToStorage();
                        } else {
                            throw new Error('SVG要素が見つかりません');
                        }
                    })
                    .catch(error => {
                        console.log(`Path ${currentPath} failed:`, error.message);
                        tryIndex++;
                        tryNextPath(); // 次のパスを試行
                    });
            }
            
            tryNextPath();
        }

        // 背景変更
        function changeBackground(color, skipUIUpdate = false) {
            currentBackground = color;
            const background = document.getElementById('canvasBackground');
            
            if (color === 'transparent') {
                background.setAttribute('fill-opacity', '0');
            } else {
                background.setAttribute('fill', color);
                background.setAttribute('fill-opacity', '1');
            }

            // UI更新をスキップしない場合のみ実行（リアルタイム更新時のパフォーマンス向上）
            if (!skipUIUpdate) {
                updateBackgroundUI(color);
            }

            // ストレージ保存（リアルタイム更新時は頻度を制限）
            if (!changeBackground._saveTimer) {
                saveToStorage();
                changeBackground._saveTimer = setTimeout(() => {
                    changeBackground._saveTimer = null;
                }, 100); // 100ms間隔で保存を制限
            }
        }

        // 背景色UI更新を分離（パフォーマンス向上）
        function updateBackgroundUI(color) {
            const bgColorSection = document.querySelector('.bg-color-section');
            if (bgColorSection) {
                // 全てのボタンからactiveクラスを削除
                bgColorSection.querySelectorAll('.bg-color-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // transparentボタンの状態更新
                if (color === 'transparent') {
                    const transparentBtn = bgColorSection.querySelector('[data-color="transparent"]');
                    if (transparentBtn) {
                        transparentBtn.classList.add('active');
                    }
                } else {
                    // カスタム色の場合はtransparentボタンのactiveを解除
                    const transparentBtn = bgColorSection.querySelector('[data-color="transparent"]');
                    if (transparentBtn) {
                        transparentBtn.classList.remove('active');
                    }
                }
                
                // カスタムカラーピッカーの値を更新（値が異なる場合のみ）
                const customColorPicker = document.getElementById('customBgColor');
                if (customColorPicker && color !== 'transparent' && customColorPicker.value !== color) {
                    customColorPicker.value = color;
                }
            }
        }

        // PNG出力
        function exportToPNG() {
            if (layers.length === 0) {
                alert('レイヤーがありません。素材を追加してください。');
                return;
            }

            console.log('PNG出力開始 - 現在の背景色:', currentBackground);
            console.log('PNG出力サイズ: 2500px (固定)');

            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', { alpha: true });
            
            // 選択されたサイズでキャンバスを設定
            canvas.width = selectedPngSize;
            canvas.height = selectedPngSize;
            
            // 透明背景の場合はキャンバスをクリア、そうでなければ背景色を設定
            if (currentBackground === 'transparent') {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            } else {
                ctx.fillStyle = currentBackground;
                ctx.fillRect(0, 0, canvas.width, canvas.height);
            }
            
            // SVGデータを取得（透明背景の場合は背景要素を除外）
            const mainCanvas = document.getElementById('mainCanvas');
            const svgClone = mainCanvas.cloneNode(true);
            
            // 透明背景の場合は背景要素を削除
            if (currentBackground === 'transparent') {
                const backgroundElement = svgClone.getElementById('canvasBackground');
                if (backgroundElement) {
                    backgroundElement.remove();
                }
            }
            
            const svgData = new XMLSerializer().serializeToString(svgClone);
            const svgBlob = new Blob([svgData], {type: 'image/svg+xml'});
            const url = URL.createObjectURL(svgBlob);
            
            const img = new Image();
            img.onload = function() {
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                
                // PNGとしてダウンロード
                canvas.toBlob(function(blob) {
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = `svg-composition-${Date.now()}.png`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                }, 'image/png');
            };
            
            img.onerror = function() {
                alert('PNG出力に失敗しました。');
                URL.revokeObjectURL(url);
            };
            
            img.src = url;
        }

        // 全削除
        function clearAll() {
            if (confirm('全てのレイヤーを削除しますか？')) {
                layers = [];
                activeLayerId = null;
                
                // DOM要素も削除
                document.querySelectorAll('[id^="layer-"]').forEach(element => {
                    element.remove();
                });
                
                
                updateTransformControls();
                saveToStorage();
            }
        }



        // レイヤードラッグ機能を追加
        function addLayerDragFunctionality(layerElement, layerId) {
            let isDragging = false;
            let startX, startY;
            let initialTransform = { x: 0, y: 0 };
            let currentDelta = { x: 0, y: 0 };
            let animationId = null;

            // マウスイベント
            layerElement.addEventListener('mousedown', startDrag);
            
            // タッチイベント
            layerElement.addEventListener('touchstart', startTouchDrag, { passive: false });

            function startDrag(e) {
                if (e.button !== 0) return; // 左クリックのみ
                
                e.preventDefault();
                e.stopPropagation();
                
                isDragging = true;
                startX = e.clientX;
                startY = e.clientY;
                
                // 現在のレイヤーを選択
                selectLayer(layerId);
                
                // レイヤーの現在のtransformを取得
                const layer = layers.find(l => l.id === layerId);
                if (layer) {
                    initialTransform = { ...layer.transform };
                }
                
                // console.log('レイヤードラッグ開始:', layerId);
                
                // グローバルイベントリスナー
                document.addEventListener('mousemove', drag);
                document.addEventListener('mouseup', endDrag);
                
                // ドラッグ中のスタイル
                document.body.classList.add('dragging');
                layerElement.classList.add('dragging');
            }

            function startTouchDrag(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const touch = e.touches[0];
                isDragging = true;
                startX = touch.clientX;
                startY = touch.clientY;
                
                // 現在のレイヤーを選択
                selectLayer(layerId);
                
                // レイヤーの現在のtransformを取得
                const layer = layers.find(l => l.id === layerId);
                if (layer) {
                    initialTransform = { ...layer.transform };
                }
                
                // console.log('レイヤータッチドラッグ開始:', layerId);
                
                // グローバルタッチイベントリスナー
                document.addEventListener('touchmove', touchDrag, { passive: false });
                document.addEventListener('touchend', endTouchDrag);
                
                // ドラッグ中のスタイル
                document.body.classList.add('dragging');
                layerElement.classList.add('dragging');
            }

            function drag(e) {
                if (!isDragging) return;
                
                e.preventDefault();
                
                currentDelta.x = e.clientX - startX;
                currentDelta.y = e.clientY - startY;
                
                // フレーム更新をリクエスト
                if (!animationId) {
                    animationId = requestAnimationFrame(updateFrame);
                }
            }

            function touchDrag(e) {
                if (!isDragging) return;
                
                e.preventDefault();
                
                const touch = e.touches[0];
                currentDelta.x = touch.clientX - startX;
                currentDelta.y = touch.clientY - startY;
                
                // フレーム更新をリクエスト
                if (!animationId) {
                    animationId = requestAnimationFrame(updateFrame);
                }
            }

            function updateFrame() {
                if (isDragging) {
                    updateLayerPosition(layerId, currentDelta.x, currentDelta.y);
                }
                animationId = null;
            }

            function endDrag() {
                if (!isDragging) return;
                
                isDragging = false;
                // console.log('レイヤードラッグ終了:', layerId);
                
                // イベントリスナーを削除
                document.removeEventListener('mousemove', drag);
                document.removeEventListener('mouseup', endDrag);
                
                // アニメーションフレームをキャンセル
                if (animationId) {
                    cancelAnimationFrame(animationId);
                    animationId = null;
                }
                
                // スタイルを戻す
                document.body.classList.remove('dragging');
                layerElement.classList.remove('dragging');
                
                // 最終的な位置で完全レンダリング
                const layer = layers.find(l => l.id === layerId);
                if (layer) {
                    renderLayer(layer);
                }
                
                // 最終的な変更を保存
                saveToStorage();
            }

            function endTouchDrag() {
                if (!isDragging) return;
                
                isDragging = false;
                // console.log('レイヤータッチドラッグ終了:', layerId);
                
                // イベントリスナーを削除
                document.removeEventListener('touchmove', touchDrag);
                document.removeEventListener('touchend', endTouchDrag);
                
                // アニメーションフレームをキャンセル
                if (animationId) {
                    cancelAnimationFrame(animationId);
                    animationId = null;
                }
                
                // スタイルを戻す
                document.body.classList.remove('dragging');
                layerElement.classList.remove('dragging');
                
                // 最終的な位置で完全レンダリング
                const layer = layers.find(l => l.id === layerId);
                if (layer) {
                    renderLayer(layer);
                }
                
                // 最終的な変更を保存
                saveToStorage();
            }

            function updateLayerPosition(layerId, deltaX, deltaY) {
                const layer = layers.find(l => l.id === layerId);
                if (!layer) return;
                
                // SVGキャンバスのスケールを考慮した移動量を計算
                const canvas = document.getElementById('mainCanvas');
                const canvasRect = canvas.getBoundingClientRect();
                const scaleX = 1024 / canvasRect.width;
                const scaleY = 1024 / canvasRect.height;
                
                // 新しい位置を計算
                const newX = initialTransform.x + (deltaX * scaleX);
                const newY = initialTransform.y + (deltaY * scaleY);
                
                // 境界チェック（キャンバス内に制限）
                layer.transform.x = Math.max(-400, Math.min(1424, newX));
                layer.transform.y = Math.max(-400, Math.min(1424, newY));
                
                // 高速更新：transform属性のみを直接更新
                updateLayerTransformFast(layerId, layer);
                
                // 変形コントロールも更新（リアルタイム）
                if (activeLayerId === layerId) {
                    updateTransformControls();
                }
            }

            // 高速transform更新（再レンダリングなし）
            function updateLayerTransformFast(layerId, layer) {
                const layerElement = document.getElementById(`layer-${layerId}`);
                if (!layerElement) return;
                
                // オブジェクトの境界ボックスを取得（変形前の状態）
                const bbox = layerElement.getBBox();
                const centerX = bbox.x + bbox.width / 2;
                const centerY = bbox.y + bbox.height / 2;
                
                // オブジェクトをキャンバス上の指定位置に配置（元の位置をオフセット）
                const translateX = layer.transform.x - centerX;
                const translateY = layer.transform.y - centerY;
                
                // 変形を適用
                const transform = `translate(${translateX}, ${translateY}) scale(${layer.transform.scale}) rotate(${layer.transform.rotation}, ${layer.transform.x}, ${layer.transform.y})`;
                layerElement.setAttribute('transform', transform);
            }
        }

        // ローカルストレージに保存
        function saveToStorage() {
            const data = {
                layers: layers,
                activeLayerId: activeLayerId,
                currentBackground: currentBackground,
                timestamp: Date.now()
            };
            localStorage.setItem('svgComposer', JSON.stringify(data));
        }

        // ローカルストレージから読み込み
        function loadFromStorage() {
            try {
                const data = JSON.parse(localStorage.getItem('svgComposer'));
                if (data && data.layers) {
                    layers = data.layers;
                    activeLayerId = data.activeLayerId;
                    currentBackground = data.currentBackground || 'white';
                    
                    // レイヤーIDカウンターを復元
                    layerIdCounter = Math.max(...layers.map(l => l.id), 0);
                    
                    // レイヤーを再レンダリング
                    layers.forEach(layer => renderLayer(layer));
                    
                    // 背景を復元
                    changeBackground(currentBackground);
                    
                    
                    updateTransformControls();
                }
            } catch (error) {
                console.error('データ読み込みエラー:', error);
            }
        }

        // ドラッグ&ドロップ関連の関数
        let draggedMaterialData = null;

        // 素材のドラッグ開始
        function startMaterialDrag(event, element) {
            console.log('ドラッグ開始:', element.dataset.title);
            
            draggedMaterialData = {
                materialId: element.dataset.materialId,
                svgPath: element.dataset.svgPath,
                title: element.dataset.title
            };
            
            // DataTransferにも同じデータを設定
            try {
                event.dataTransfer.setData('text/plain', JSON.stringify(draggedMaterialData));
                event.dataTransfer.effectAllowed = 'copy';
            } catch (e) {
                console.log('DataTransfer設定エラー:', e);
            }
            
            // ドラッグ中の視覚効果
            element.style.opacity = '0.5';
            
            // ドラッグイメージをより小さく設定
            try {
                const dragImage = element.cloneNode(true);
                dragImage.style.transform = 'scale(0.8)';
                document.body.appendChild(dragImage);
                event.dataTransfer.setDragImage(dragImage, 40, 40);
                setTimeout(() => {
                    if (document.body.contains(dragImage)) {
                        document.body.removeChild(dragImage);
                    }
                }, 1);
            } catch (e) {
                console.log('ドラッグイメージ設定エラー:', e);
            }
        }

        // 素材のドラッグ終了
        function endMaterialDrag(event, element) {
            element.style.opacity = '1';
        }

        // ドロップを許可
        function allowDrop(event) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
            console.log('ドラッグオーバー検出');
            
            // ドロップエリアのハイライト効果
            const dropArea = document.getElementById('canvasDropArea');
            dropArea.classList.add('drag-over');
        }

        // ドラッグハイライトをクリア
        function clearDragHighlight(event) {
            // 子要素にドラッグが移動した場合はハイライトを維持
            if (event.relatedTarget && event.currentTarget.contains(event.relatedTarget)) {
                return;
            }
            
            const dropArea = document.getElementById('canvasDropArea');
            dropArea.classList.remove('drag-over');
        }

        // キャンバスに素材をドロップ
        function dropMaterialOnCanvas(event) {
            event.preventDefault();
            console.log('ドロップ検出:', draggedMaterialData);
            
            // ドロップエリアのハイライトを解除
            const dropArea = document.getElementById('canvasDropArea');
            dropArea.classList.remove('drag-over');
            
            let materialData = draggedMaterialData;
            
            // DataTransferからもデータを取得を試行
            if (!materialData) {
                try {
                    const transferData = event.dataTransfer.getData('text/plain');
                    if (transferData) {
                        materialData = JSON.parse(transferData);
                        console.log('DataTransferからデータを取得:', materialData);
                    }
                } catch (e) {
                    console.log('DataTransfer読み込みエラー:', e);
                }
            }
            
            if (!materialData) {
                console.log('ドラッグデータなし');
                return;
            }
            
            // ドロップ位置を計算
            const rect = event.currentTarget.getBoundingClientRect();
            const x = ((event.clientX - rect.left) / rect.width) * 1024;
            const y = ((event.clientY - rect.top) / rect.height) * 1024;
            
            console.log('ドロップ位置:', { x, y });
            
            // 指定位置に素材を追加
            addMaterialToCanvasAtPosition(materialData, x, y);
            
            draggedMaterialData = null;
        }

        // 指定位置に素材を追加
        function addMaterialToCanvasAtPosition(materialData, x, y) {
            const { materialId, svgPath, title } = materialData;

            // SVGファイルを読み込み
            fetch('/' + svgPath)
                .then(response => response.text())
                .then(svgText => {
                    const parser = new DOMParser();
                    const svgDoc = parser.parseFromString(svgText, 'image/svg+xml');
                    const svgElement = svgDoc.querySelector('svg');
                    
                    if (svgElement) {
                        // レイヤーオブジェクトを作成（指定位置で）
                        const layer = {
                            id: ++layerIdCounter,
                            materialId: materialId,
                            svgId: materialId,
                            title: title,
                            svgContent: svgElement.innerHTML,
                            originalSvgContent: svgElement.innerHTML,
                            svgPath: svgPath,
                            transform: {
                                x: Math.max(50, Math.min(974, x)), // キャンバス境界内に制限
                                y: Math.max(50, Math.min(974, y)),
                                scale: 1,
                                rotation: 0
                            },
                            visible: true
                        };

                        layers.push(layer);
                        renderLayer(layer);
                        
                        saveToStorage();

                        // 素材タブから自動的にレイヤータブに切り替え
                        
                    }
                })
                .catch(error => {
                    console.error('SVG読み込みエラー:', error);
                    alert('素材の読み込みに失敗しました');
                });
        }

        // タッチデバイス対応（現在は無効化 - タッチでも中央配置）
        let selectedMaterialForPlacement = null;
        let touchPlacementActive = false;

        // 素材のタッチ選択（スマートフォン/タブレット用）
        function handleMaterialTouch(event, element) {
            // タッチでも通常のクリック動作をする（中央配置）
            if (event.type === 'touchstart') {
                event.preventDefault();
                
                // 通常のaddMaterialToCanvas関数を呼び出し
                addMaterialToCanvas(element);
            }
        }

        // キャンバスでのタッチ配置（現在は無効化）
        function handleCanvasTouch(event) {
            // タッチ配置機能は無効化されています
            // タッチでも通常のクリック動作（中央配置）を利用してください
        }

        // タッチ配置モードをリセット（現在は無効化）
        function resetTouchPlacementMode() {
            touchPlacementActive = false;
            selectedMaterialForPlacement = null;
            document.querySelectorAll('.material-item.selected-for-placement').forEach(item => {
                item.classList.remove('selected-for-placement');
            });
            hideTouchPlacementGuide();
        }

        // 配置モードの案内を表示（現在は無効化）
        function showTouchPlacementGuide() {
            // タッチ配置機能は無効化されているため、案内を表示しません
            return;
        }

        // 配置モードの案内を非表示
        function hideTouchPlacementGuide() {
            const guide = document.getElementById('touchPlacementGuide');
            if (guide) {
                guide.style.display = 'none';
            }
        }

        // ページ初期化時にタッチイベントを設定（現在は無効化）
        document.addEventListener('DOMContentLoaded', function() {
            // タッチ配置機能は無効化されています
            // タッチでも通常のクリック動作（中央配置）を使用します
        });



        // 素材検索機能
        function searchMaterials() {
            const searchTerm = document.getElementById('materialSearch').value.toLowerCase().trim();
            const materials = document.querySelectorAll('.material-item');
            const noResults = document.getElementById('noSearchResults');
            let visibleCount = 0;

            materials.forEach(material => {
                const title = material.dataset.title.toLowerCase();
                const shouldShow = !searchTerm || title.includes(searchTerm);
                
                material.style.display = shouldShow ? 'flex' : 'none';
                if (shouldShow) visibleCount++;
            });

            // 検索結果がない場合のメッセージ表示
            if (searchTerm && visibleCount === 0) {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }

        // 検索クリア
        function clearSearch() {
            document.getElementById('materialSearch').value = '';
            searchMaterials();
        }

        // キーボード入力処理
        function handleSearchKeydown(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchMaterials();
            }
        }


    </script>
</body>
</html>
