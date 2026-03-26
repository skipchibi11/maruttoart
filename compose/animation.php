<?php
require_once '../config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200);

$pdo = getDB();

// GIF生成はクライアント側で実行



?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>アニメーションGIF生成 | marutto.art</title>
    <link rel="icon" href="/favicon.ico">
    
    <?php include __DIR__ . '/../includes/gtm-head.php'; ?>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/gifshot@0.4.5/dist/gifshot.min.js"></script>
    <style>
        :root {
            --primary-color: #E8A87C;
            --secondary-color: #C38E70;
            --text-dark: #5A4A42;
            --accent-purple: #9B59B6;
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
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 24px 30px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(232, 168, 124, 0.15);
        }
        
        h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 12px;
        }
        
        .breadcrumb {
            font-size: 14px;
            color: #888;
        }
        
        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .breadcrumb a:hover {
            color: var(--secondary-color);
            text-decoration: underline;
        }
        
        .preview-panel, .control-panel {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(232, 168, 124, 0.15);
        }
        
        .preview-area {
            border: 1px solid rgba(232, 168, 124, 0.2);
            border-radius: 12px;
            padding: 30px;
            min-height: 400px;
            max-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            position: relative;
            overflow: hidden;
        }
        
        #preview-canvas {
            display: block;
            max-width: 100%;
            max-height: 100%;
        }
        
        .canvas-container {
            max-width: 100% !important;
            max-height: 100% !important;
        }
        
        .canvas-container canvas {
            display: block !important;
        }
        
        #preview-svg {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
        }
        
        .section {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(232, 168, 124, 0.2);
        }
        
        .section:last-child {
            border-bottom: none;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 16px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-dark);
        }
        
        input[type="number"],
        input[type="text"],
        input[type="color"],
        select {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: all 0.2s;
        }

        input:focus, select:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(232, 168, 124, 0.2);
            border-color: var(--secondary-color);
        }
        
        input[type="color"] {
            height: 45px;
            cursor: pointer;
            padding: 4px;
        }
        
        .layer-item {
            background: rgba(255, 255, 255, 0.8);
            border: 2px solid rgba(232, 168, 124, 0.3);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.2s;
        }

        .layer-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(232, 168, 124, 0.2);
        }
        
        .layer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .layer-name {
            font-weight: 700;
            font-size: 15px;
            color: var(--text-dark);
        }
        
        .animation-settings {
            display: none;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px solid rgba(232, 168, 124, 0.2);
        }
        
        .animation-settings.active {
            display: block;
        }
        
        .btn {
            padding: 11px 24px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(232, 168, 124, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(232, 168, 124, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }
        
        .btn-secondary:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(232, 168, 124, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(232, 168, 124, 0.4);
        }
        
        .btn-small {
            padding: 6px 16px;
            font-size: 12px;
            border-radius: 50px;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .action-buttons button, .action-buttons a {
            flex: 1;
        }
        
        .format-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .format-buttons button {
            flex: 1;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 600;
        }
        
        .btn-mp4 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .btn-mp4:hover {
            background: linear-gradient(135deg, #5568d3 0%, #653a8b 100%);
        }
        
        .btn-mp4:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-info {
            background: rgba(232, 168, 124, 0.1);
            border-color: var(--primary-color);
            color: var(--text-dark);
        }
        
        .loading {
            text-align: center;
            padding: 40px 20px;
        }
        
        .spinner {
            border: 4px solid rgba(232, 168, 124, 0.2);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .result-area {
            display: none;
            margin-top: 24px;
        }
        
        .result-area.active {
            display: block;
        }
        
        .result-image-container {
            border: 1px solid rgba(232, 168, 124, 0.2);
            border-radius: 12px;
            padding: 30px;
            min-height: 300px;
            max-height: 560px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            position: relative;
            overflow: hidden;
            margin-bottom: 16px;
        }
        
        .result-image {
            max-width: 100%;
            max-height: 500px;
            height: auto;
            width: auto;
            display: block;
            margin: 0 auto;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .result-area {
            margin-top: 30px;
            display: none;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 30px;
        }
        
        .result-area.active {
            display: block;
        }

        @media (max-width: 768px) {
            .result-area {
                margin-top: 20px;
                padding: 20px;
                border-radius: 12px;
                background: rgba(255, 255, 255, 0.95);
            }
        }

        /* 低画質モード */
        .quality-mode {
            padding: 12px;
            background: rgba(232, 168, 124, 0.1);
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .quality-mode label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 14px;
        }

        .quality-mode input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* アニメーション設定ボタン */
        .animation-button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .animation-btn {
            padding: 6px 12px;
            border: 2px solid;
            background: white;
            color: var(--text-dark);
            border-radius: 16px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.2s;
            white-space: nowrap;
        }

        /* 青色: フェードイン・フェードアウト */
        .animation-btn[data-category="effect"] {
            border-color: rgba(52, 152, 219, 0.4);
        }

        .animation-btn[data-category="effect"]:hover {
            background: rgba(52, 152, 219, 0.1);
            border-color: rgba(52, 152, 219, 0.6);
        }

        .animation-btn[data-category="effect"].active {
            background: #3498db;
            color: white;
            border-color: #3498db;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }

        /* 緑色: イーズイン・イーズアウト */
        .animation-btn[data-category="easing"] {
            border-color: rgba(39, 174, 96, 0.4);
        }

        .animation-btn[data-category="easing"]:hover {
            background: rgba(39, 174, 96, 0.1);
            border-color: rgba(39, 174, 96, 0.6);
        }

        .animation-btn[data-category="easing"].active {
            background: #27ae60;
            color: white;
            border-color: #27ae60;
            box-shadow: 0 2px 8px rgba(39, 174, 96, 0.3);
        }

        /* 赤色: 1秒後・2秒後 */
        .animation-btn[data-category="delay"] {
            border-color: rgba(231, 76, 60, 0.4);
        }

        .animation-btn[data-category="delay"]:hover {
            background: rgba(231, 76, 60, 0.1);
            border-color: rgba(231, 76, 60, 0.6);
        }

        .animation-btn[data-category="delay"].active {
            background: #e74c3c;
            color: white;
            border-color: #e74c3c;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }

        /* オレンジ色: 1秒維持・2秒維持 */
        .animation-btn[data-category="duration"] {
            border-color: rgba(243, 156, 18, 0.4);
        }

        .animation-btn[data-category="duration"]:hover {
            background: rgba(243, 156, 18, 0.1);
            border-color: rgba(243, 156, 18, 0.6);
        }

        .animation-btn[data-category="duration"].active {
            background: #f39c12;
            color: white;
            border-color: #f39c12;
            box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
        }

        /* 紫色: 回転 */
        .animation-btn[data-category="rotation"] {
            border-color: rgba(155, 89, 182, 0.4);
        }

        .animation-btn[data-category="rotation"]:hover {
            background: rgba(155, 89, 182, 0.1);
            border-color: rgba(155, 89, 182, 0.6);
        }

        .animation-btn[data-category="rotation"].active {
            background: #9b59b6;
            color: white;
            border-color: #9b59b6;
            box-shadow: 0 2px 8px rgba(155, 89, 182, 0.3);
        }

        .animation-btn:active {
            transform: scale(0.95);
        }

        .layer-animation-buttons {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid rgba(232, 168, 124, 0.2);
        }

        .layer-animation-buttons .animation-button-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 0;
        }

        /* 作業エリア */
        .workspace {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
            margin-top: 30px;
        }

        @media (max-width: 1024px) {
            .workspace {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .workspace {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }
        }

        /* キャンバスエリア */
        .canvas-area {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            min-width: 0;
        }

        @media (min-width: 1025px) {
            .canvas-area {
                position: sticky;
                top: 20px;
                align-self: flex-start;
                max-height: calc(100vh - 40px);
                z-index: 10;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .canvas-area {
                position: sticky;
                top: 20px;
                z-index: 10;
                max-height: calc(100vh - 40px);
            }
        }

        @media (max-width: 768px) {
            .canvas-area {
                position: sticky;
                top: 0;
                z-index: 100;
                border-radius: 12px;
                padding: 12px;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            }
        }

        .canvas-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            max-height: 70vh;
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.05);
            padding: 20px;
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .canvas-wrapper {
                min-height: 200px;
                max-height: 300px;
                padding: 10px;
                border-radius: 8px;
            }
        }

        #preview-canvas {
            max-width: 100%;
            max-height: 100%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* コントロールパネル */
        .control-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
        }

        @media (max-width: 1024px) {
            .control-panel {
                max-height: none;
            }
        }

        @media (max-width: 768px) {
            .control-panel {
                border-radius: 12px;
                padding: 16px;
                background: rgba(255, 255, 255, 0.95);
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/gtm-body.php'; ?>

    <?php 
    $currentPage = 'compose';
    include __DIR__ . '/../includes/header.php';
    ?>

    <div class="container">
        <div class="main-content" style="padding: 40px 0 80px;">
            <h1 class="page-title" style="text-align: center; font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 600; margin-bottom: 20px; color: #A0675C;">
                Animate
            </h1>
            <div style="text-align: center; margin-bottom: 30px;">
                <a href="/everyone-works.php" class="btn btn-secondary" style="display: inline-block;">← 前に戻る</a>
            </div>
        
        <div id="main-content">
            <div class="workspace">
            <!-- キャンバスエリア -->
            <div class="canvas-area">
                <div class="canvas-wrapper" id="preview-area">
                    <canvas id="preview-canvas"></canvas>
                </div>
            </div>
            
            <!-- コントロールパネル -->
            <div class="control-panel">
                <form id="animation-form" onsubmit="return false;">
                    
                    <div class="section">
                        <div id="layers-container"></div>
                    </div>
                    
                    <div class="quality-mode" style="margin-bottom: 16px;">
                        <div style="margin-bottom: 12px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" id="transparent-bg">
                                <span>背景を透明にする（GIFのみ）</span>
                            </label>
                        </div>
                        <div class="color-picker-wrapper" style="display: flex; align-items: center; gap: 8px;">
                            <label for="bg-color" style="font-size: 14px; color: #666;">背景色:</label>
                            <input type="color" id="bg-color" class="color-picker" value="#ffffff" oninput="changeBackgroundColorPreview()" onchange="changeBackgroundColorPreview()">
                        </div>
                    </div>
                    
                    <div class="format-buttons">
                        <button type="button" class="btn btn-success" onclick="generateGif()" id="generate-gif-btn">GIF生成<br><small style="font-size:11px;opacity:0.8">320px, ~800KB</small></button>
                        <button type="button" class="btn btn-mp4" onclick="generateMp4()" id="generate-mp4-btn">MP4生成<br><small style="font-size:11px;opacity:0.8">640px, ~200KB</small></button>
                    </div>

                </form>
            </div>
            </div>
            
            <!-- 生成結果エリア -->
            <div class="result-area" id="result-area">
                <h3 class="section-title" style="margin-bottom: 20px;" id="result-title">生成結果</h3>
                <div class="result-image-container" id="result-container">
                    <!-- GIF or MP4がここに表示される -->
                </div>
                <div class="action-buttons">
                    <a id="download-link" class="btn btn-success" download>ダウンロード</a>
                    <button class="btn btn-secondary" onclick="resetResult()">新しく作成</button>
                </div>
            </div>
        </div>
        
        <script>
            // ローカルストレージのキー（作品データ用）
            const ARTWORK_STORAGE_KEY = 'marutto_animation_artwork';
            
            // 作品データの初期化
            let artworkData = null;
            let materials = [];
            let svgData, layers, canvasWidth, canvasHeight, backgroundColor;
            
            // アニメーション設定（軽量版・固定）
            const ANIMATION_DURATION = 1800; // 1.8秒（ゆっくりとした動き）
            const ANIMATION_FPS = 10; // 15fps → 10fpsに削減
            
            // ローカルストレージのキー（アニメーション設定用、作品読み込み後に設定）
            let STORAGE_KEY = null;
            
            // アニメーション設定を保持
            let animations = {};
            
            // 素材のSVGコンテンツをキャッシュ
            const materialSvgCache = {};
            
            // URLパラメータから作品IDを取得
            const urlParams = new URLSearchParams(window.location.search);
            const artworkId = urlParams.get('artwork_id');
            
            // 作品データを読み込む関数
            async function loadArtworkData() {
                try {
                    // ローカルストレージをチェック（キャッシュとして利用）
                    const savedArtwork = localStorage.getItem(ARTWORK_STORAGE_KEY);
                    const cached = savedArtwork ? JSON.parse(savedArtwork) : null;
                    
                    // キャッシュがあり、同じ作品IDの場合は使用
                    if (cached && cached.artwork && cached.artwork.id == artworkId) {
                        console.log('作品データをローカルストレージから復元しました');
                        artworkData = cached.artwork;
                        materials = cached.materials || [];
                        initializeArtwork();
                        return;
                    }
                    
                    // artwork_idがない場合はエラー
                    if (!artworkId) {
                        showError('作品IDが指定されていません。<a href="/everyone-works.php">みんなの作品一覧</a>から作品を選択してください。');
                        return;
                    }
                    
                    // APIから作品データを取得
                    const response = await fetch(`/api/get-artworks.php?artwork_id=${artworkId}`);
                    if (!response.ok) {
                        throw new Error('作品が見つかりません');
                    }
                    
                    artworkData = await response.json();
                    console.log('作品データをAPIから取得しました:', artworkData);
                    
                    // 使用素材の情報を取得
                    if (artworkData.used_material_ids) {
                        let usedMaterialIds;
                        if (Array.isArray(artworkData.used_material_ids)) {
                            usedMaterialIds = artworkData.used_material_ids;
                        } else if (typeof artworkData.used_material_ids === 'string') {
                            const trimmed = artworkData.used_material_ids.trim();
                            if (trimmed.startsWith('[')) {
                                usedMaterialIds = JSON.parse(trimmed);
                            } else {
                                usedMaterialIds = trimmed.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id));
                            }
                        } else {
                            usedMaterialIds = [];
                        }
                        
                        if (Array.isArray(usedMaterialIds) && usedMaterialIds.length > 0) {
                            const materialsResponse = await fetch('/api/get-materials.php?ids=' + usedMaterialIds.join(','));
                            materials = await materialsResponse.json();
                            console.log('素材データをAPIから取得しました:', materials.length + '件');
                        }
                    }
                    
                    // ローカルストレージに保存（キャッシュ）
                    localStorage.setItem(ARTWORK_STORAGE_KEY, JSON.stringify({
                        artwork: artworkData,
                        materials: materials
                    }));
                    
                    initializeArtwork();
                    
                } catch (error) {
                    console.error('作品データの読み込みエラー:', error);
                    showError('作品の読み込みに失敗しました。<a href="/everyone-works.php">みんなの作品一覧</a>から作品を選択してください。');
                }
            }
            
            // エラー表示関数
            function showError(message) {
                document.body.innerHTML = `
                    <div class="container">
                        <div class="main-content" style="padding: 40px 0;">
                            <h1 class="page-title" style="text-align: center; font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 600; margin-bottom: 40px; color: #A0675C;">
                                Animate Works
                            </h1>
                            <div class="alert alert-error" style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 16px; border-radius: 8px; margin: 0 auto; max-width: 600px;">
                                ${message}
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // 作品データを初期化する関数
            function initializeArtwork() {
                if (!artworkData) {
                    showError('作品データが読み込めませんでした。');
                    return;
                }
            
                // SVGデータを解析
                svgData = JSON.parse(artworkData.svg_data || '{}');
                layers = svgData.layers || [];
                
                console.log('読み込まれたレイヤーデータ:', layers);
                console.log('各レイヤーの色情報:');
                layers.forEach((layer, idx) => {
                    console.log(`  レイヤー${idx} (materialId: ${layer.materialId}):`, {
                        hasColors: !!layer.colors,
                        colorsLength: layer.colors ? layer.colors.length : 0,
                        colors: layer.colors
                    });
                });
                
                // 作品の元のサイズと背景色を取得
                canvasWidth = svgData.canvasWidth || svgData.width || 800;
                canvasHeight = svgData.canvasHeight || svgData.height || 800;
                backgroundColor = svgData.backgroundColor || '#ffffff';
                
                console.log('作品サイズ:', { width: canvasWidth, height: canvasHeight, aspectRatio: (canvasWidth / canvasHeight).toFixed(2) });
                
                // ローカルストレージのキーを設定
                STORAGE_KEY = 'marutto_animation_' + artworkData.id;
                
                // ローカルストレージから設定を復元
                loadFromLocalStorage();
                
                // レイヤーをレンダリング
                renderLayers();
                
                // 背景色入力を初期化
                const bgColorInput = document.getElementById('bg-color');
                if (bgColorInput) {
                    bgColorInput.value = backgroundColor;
                }
                
                // 透明背景チェックボックスのイベント
                const transparentBgCheckbox = document.getElementById('transparent-bg');
                if (transparentBgCheckbox && bgColorInput) {
                    transparentBgCheckbox.addEventListener('change', function() {
                        bgColorInput.disabled = this.checked;
                        bgColorInput.style.opacity = this.checked ? '0.5' : '1';
                        bgColorInput.style.cursor = this.checked ? 'not-allowed' : 'pointer';
                    });
                }
                
                // レイヤーレンダリング後にUIに反映（少し遅延させる）
                setTimeout(() => {
                    renderPreview();
                }, 100);
            }
            
            // レイヤー一覧を表示
            function renderLayers() {
                const container = document.getElementById('layers-container');
                container.innerHTML = '';
                
                layers.forEach((layer, index) => {
                    const layerId = `layer_${index}`;
                    
                    // 素材情報を取得
                    const material = materials.find(m => m.id == layer.materialId);
                    
                    // R2 URL対応
                    let thumbnailUrl = '';
                    if (material && material.webp_small_path) {
                        if (material.webp_small_path.startsWith('http://') || material.webp_small_path.startsWith('https://')) {
                            thumbnailUrl = material.webp_small_path;
                        } else {
                            thumbnailUrl = '/' + material.webp_small_path;
                        }
                    }
                    const materialTitle = material ? material.title : '';
                    
                    const layerDiv = document.createElement('div');
                    layerDiv.className = 'layer-item';
                    
                    layerDiv.innerHTML = `
                        <div class="layer-header">
                            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                                ${thumbnailUrl ? `<img src="${thumbnailUrl}" alt="" style="width: 50px; height: 50px; object-fit: contain; border-radius: 4px; background: #f5f5f5; padding: 4px;">` : ''}
                            </div>
                        </div>
                        <div class="layer-animation-buttons">
                            <div class="animation-button-group">
                                <button type="button" class="animation-btn" data-type="fadeIn" data-category="effect" data-layer-index="${index}">Fade In</button>
                                <button type="button" class="animation-btn" data-type="fadeOut" data-category="effect" data-layer-index="${index}">Fade Out</button>
                                <button type="button" class="animation-btn" data-type="easeIn" data-category="easing" data-layer-index="${index}">Ease In</button>
                                <button type="button" class="animation-btn" data-type="easeOut" data-category="easing" data-layer-index="${index}">Ease Out</button>
                                <button type="button" class="animation-btn" data-type="rotate" data-category="rotation" data-layer-index="${index}">Rotate</button>
                                <button type="button" class="animation-btn" data-type="delay1s" data-category="delay" data-layer-index="${index}">+1s</button>
                                <button type="button" class="animation-btn" data-type="delay2s" data-category="delay" data-layer-index="${index}">+2s</button>
                                <button type="button" class="animation-btn" data-type="duration1s" data-category="duration" data-layer-index="${index}">1s</button>
                                <button type="button" class="animation-btn" data-type="duration2s" data-category="duration" data-layer-index="${index}">2s</button>
                            </div>
                        </div>
                    `;
                    
                    container.appendChild(layerDiv);
                });
                
                // ボタンのイベントリスナーを再設定
                initializeAnimationButtons();
                
                // ボタンの状態を更新
                updateAllButtonStates();
            }
            
            // 全ボタンの状態を更新
            function updateAllButtonStates() {
                layers.forEach((layer, index) => {
                    const layerId = `layer_${index}`;
                    const animation = animations[layerId];
                    
                    // このレイヤーのボタンを取得
                    const buttons = document.querySelectorAll(`[data-layer-index="${index}"]`);
                    
                    buttons.forEach(button => {
                        const type = button.getAttribute('data-type');
                        if (animation && animation[type]) {
                            button.classList.add('active');
                        } else {
                            button.classList.remove('active');
                        }
                    });
                });
            }
            
            // アニメーションボタンの初期化
            function initializeAnimationButtons() {
                const buttons = document.querySelectorAll('.animation-btn');
                buttons.forEach(button => {
                    // 既存のイベントリスナーを削除するため、新しいボタンとして扱う
                    button.replaceWith(button.cloneNode(true));
                });
                
                // 再度ボタンを取得してイベントリスナーを追加
                const newButtons = document.querySelectorAll('.animation-btn');
                newButtons.forEach(button => {
                    button.addEventListener('click', function(e) {
                        e.stopPropagation();
                        
                        const layerIndex = parseInt(this.getAttribute('data-layer-index'));
                        const layerId = `layer_${layerIndex}`;
                        const type = this.getAttribute('data-type');
                        const category = this.getAttribute('data-category');
                        
                        // アニメーション設定を初期化（存在しない場合）
                        if (!animations[layerId]) {
                            animations[layerId] = {};
                        }
                        
                        // 同じカテゴリの他のボタンを無効化（排他制御）
                        if (category) {
                            const categoryButtons = document.querySelectorAll(`[data-layer-index="${layerIndex}"][data-category="${category}"]`);
                            categoryButtons.forEach(btn => {
                                const btnType = btn.getAttribute('data-type');
                                if (btnType !== type) {
                                    animations[layerId][btnType] = false;
                                    btn.classList.remove('active');
                                }
                            });
                        }
                        
                        // トグル処理
                        animations[layerId][type] = !animations[layerId][type];
                        
                        // ボタンのアクティブ状態を更新
                        this.classList.toggle('active', animations[layerId][type]);
                        
                        // ローカルストレージに保存
                        saveToLocalStorage();
                        
                        // プレビューを更新
                        renderPreview();
                    });
                });
            }
            
            // アニメーション設定の表示/非表示
            // ローカルストレージに保存
            function saveToLocalStorage() {
                try {
                    if (STORAGE_KEY) {
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(animations));
                    }
                } catch (error) {
                    console.error('ローカルストレージへの保存に失敗:', error);
                }
            }
            
            // ローカルストレージから復元
            function loadFromLocalStorage() {
                try {
                    if (!STORAGE_KEY) return;
                    
                    const saved = localStorage.getItem(STORAGE_KEY);
                    if (saved) {
                        animations = JSON.parse(saved);
                        console.log('アニメーション設定を復元しました:', animations);
                    }
                } catch (error) {
                    console.error('ローカルストレージからの読み込みに失敗:', error);
                }
            }
            
            // 背景色変更プレビュー（リアルタイム反映）
            function changeBackgroundColorPreview() {
                const bgColorInput = document.getElementById('bg-color');
                if (bgColorInput) {
                    backgroundColor = bgColorInput.value;
                    renderPreview();
                }
            }
            
            // 素材のSVGコンテンツを取得（色情報を適用）
            async function fetchMaterialSvg(materialId, layerColors) {
                const cacheKey = materialId + '_' + JSON.stringify(layerColors || {});
                if (materialSvgCache[cacheKey]) {
                    return materialSvgCache[cacheKey];
                }
                
                const material = materials.find(m => m.id == materialId);
                if (!material || !material.svg_path) {
                    return { content: '', viewBox: { width: 100, height: 100 } };
                }
                
                // SVGパスをR2 URL対応
                let svgUrl = material.svg_path;
                if (svgUrl && !svgUrl.startsWith('http://') && !svgUrl.startsWith('https://')) {
                    svgUrl = '/' + svgUrl;
                }
                
                try {
                    const response = await fetch(svgUrl);
                    const text = await response.text();
                    
                    // SVGタグを除去してコンテンツのみを取得
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(text, 'image/svg+xml');
                    const svgElement = doc.querySelector('svg');
                    
                    if (svgElement) {
                        // viewBoxまたはwidth/heightを取得
                        let viewBox = { width: 100, height: 100 };
                        
                        if (svgElement.hasAttribute('viewBox')) {
                            const vb = svgElement.getAttribute('viewBox').split(' ');
                            viewBox = {
                                x: parseFloat(vb[0]) || 0,
                                y: parseFloat(vb[1]) || 0,
                                width: parseFloat(vb[2]) || 100,
                                height: parseFloat(vb[3]) || 100
                            };
                        } else {
                            viewBox = {
                                x: 0,
                                y: 0,
                                width: parseFloat(svgElement.getAttribute('width')) || 100,
                                height: parseFloat(svgElement.getAttribute('height')) || 100
                            };
                        }
                        
                        // SVGルート要素のstroke属性を取得（継承用）
                        let defaultStrokeAttrs = {};
                        const svgStyle = svgElement.getAttribute('style');
                        if (svgStyle) {
                            const linecapMatch = svgStyle.match(/stroke-linecap\s*:\s*([^;]+)/i);
                            const linejoinMatch = svgStyle.match(/stroke-linejoin\s*:\s*([^;]+)/i);
                            const miterlimitMatch = svgStyle.match(/stroke-miterlimit\s*:\s*([^;]+)/i);
                            
                            if (linecapMatch) defaultStrokeAttrs.strokeLinecap = linecapMatch[1].trim();
                            if (linejoinMatch) defaultStrokeAttrs.strokeLinejoin = linejoinMatch[1].trim();
                            if (miterlimitMatch) defaultStrokeAttrs.strokeMiterlimit = miterlimitMatch[1].trim();
                        }
                        
                        console.log('SVGルート要素のstroke設定:', defaultStrokeAttrs);
                        
                        // 色情報を適用
                        console.log('=== SVG読み込み開始 ===');
                        console.log('materialId:', materialId);
                        console.log('layerColors:', JSON.stringify(layerColors, null, 2));
                        
                        // SVG要素から直接描画要素を取得
                        const allElements = svgElement.querySelectorAll('path, rect, circle, ellipse, line, polyline, polygon');
                        
                        console.log('取得した要素数:', allElements.length);
                        console.log('元の要素の状態:');
                        Array.from(allElements).forEach((el, idx) => {
                            console.log(`  [${idx}] ${el.tagName}: fill="${el.getAttribute('fill')}" stroke="${el.getAttribute('stroke')}"`);
                        });
                        
                        if (layerColors && Array.isArray(layerColors) && layerColors.length > 0) {
                            console.log('=== 色情報適用開始 ===');
                            if (allElements.length > 0) {
                                layerColors.forEach(colorInfo => {
                                    const element = allElements[colorInfo.index];
                                    if (element) {
                                        const beforeFill = element.getAttribute('fill');
                                        const beforeStroke = element.getAttribute('stroke');
                                        const beforeStyle = element.getAttribute('style');
                                        
                                        console.log(`要素[${colorInfo.index}] 適用前:`, {
                                            tag: element.tagName,
                                            fill: beforeFill,
                                            stroke: beforeStroke,
                                            style: beforeStyle
                                        });
                                        console.log(`要素[${colorInfo.index}] 適用する色:`, {
                                            fill: colorInfo.fill,
                                            stroke: colorInfo.stroke
                                        });
                                        
                                        // style属性内のfill/stroke色のみを削除（他のstroke属性は保持）
                                        if (beforeStyle) {
                                            let newStyle = beforeStyle
                                                .replace(/fill\s*:\s*[^;]+;?/gi, '')
                                                .replace(/stroke\s*:\s*(rgb|rgba|#|[a-z]+)\([^)]*\)[^;]*;?/gi, '')
                                                .replace(/stroke\s*:\s*#[0-9a-f]{3,8}[^;]*;?/gi, '')
                                                .replace(/stroke\s*:\s*[a-z]+[^;]*;?/gi, '')
                                                .trim();
                                            // stroke-width, stroke-linejoin, stroke-linecapなどは保持
                                            if (newStyle && newStyle !== ';') {
                                                element.setAttribute('style', newStyle);
                                            } else if (!newStyle || newStyle === ';') {
                                                element.removeAttribute('style');
                                            }
                                            console.log(`  -> style属性からfill/stroke色を削除（他は保持）: "${newStyle}"`);
                                        }
                                        
                                        // fillの処理
                                        if (colorInfo.fill !== undefined && colorInfo.fill !== null) {
                                            if (colorInfo.fill === '') {
                                                // 空文字列の場合はfillを削除（透明に）
                                                element.setAttribute('fill', 'none');
                                                console.log(`  -> fill を none に設定`);
                                            } else {
                                                // 色が指定されている場合は適用
                                                element.setAttribute('fill', colorInfo.fill);
                                                console.log(`  -> fill を ${colorInfo.fill} に設定`);
                                            }
                                        }
                                        
                                        // 不透明度の処理
                                        if (colorInfo.fillOpacity !== undefined) {
                                            element.setAttribute('fill-opacity', colorInfo.fillOpacity);
                                            console.log(`  -> fill-opacity を ${colorInfo.fillOpacity} に設定`);
                                        }
                                        if (colorInfo.strokeOpacity !== undefined) {
                                            element.setAttribute('stroke-opacity', colorInfo.strokeOpacity);
                                            console.log(`  -> stroke-opacity を ${colorInfo.strokeOpacity} に設定`);
                                        }
                                        if (colorInfo.opacity !== undefined) {
                                            element.setAttribute('opacity', colorInfo.opacity);
                                            console.log(`  -> opacity を ${colorInfo.opacity} に設定`);
                                        }
                                        
                                        // strokeの処理
                                        if (colorInfo.stroke !== undefined && colorInfo.stroke !== null) {
                                            if (colorInfo.stroke === '') {
                                                // 空文字列の場合はstrokeを削除
                                                element.setAttribute('stroke', 'none');
                                                console.log(`  -> stroke を none に設定`);
                                            } else {
                                                // 色が指定されている場合は適用
                                                element.setAttribute('stroke', colorInfo.stroke);
                                                console.log(`  -> stroke を ${colorInfo.stroke} に設定`);
                                                
                                                // SVGルート要素のstroke属性を継承
                                                if (defaultStrokeAttrs.strokeLinecap) {
                                                    element.setAttribute('stroke-linecap', defaultStrokeAttrs.strokeLinecap);
                                                    console.log(`  -> stroke-linecap を ${defaultStrokeAttrs.strokeLinecap} に設定`);
                                                }
                                                if (defaultStrokeAttrs.strokeLinejoin) {
                                                    element.setAttribute('stroke-linejoin', defaultStrokeAttrs.strokeLinejoin);
                                                    console.log(`  -> stroke-linejoin を ${defaultStrokeAttrs.strokeLinejoin} に設定`);
                                                }
                                                if (defaultStrokeAttrs.strokeMiterlimit) {
                                                    element.setAttribute('stroke-miterlimit', defaultStrokeAttrs.strokeMiterlimit);
                                                    console.log(`  -> stroke-miterlimit を ${defaultStrokeAttrs.strokeMiterlimit} に設定`);
                                                }
                                            }
                                        }
                                        
                                        console.log(`要素[${colorInfo.index}] 適用後:`, {
                                            fill: element.getAttribute('fill'),
                                            stroke: element.getAttribute('stroke'),
                                            style: element.getAttribute('style')
                                        });
                                    } else {
                                        console.warn(`  要素${colorInfo.index}が見つかりません (全体: ${allElements.length}要素)`);
                                    }
                                });
                                
                                console.log('=== 色情報適用完了 ===');
                            } else {
                                console.warn('描画要素が見つかりませんでした');
                            }
                        } else {
                            console.log('色情報なし - 元のSVGを使用');
                            
                            // 色情報がない場合でも、style属性をパースして属性に変換
                            // （Fabric.jsがstyle属性を正しく読み込めないため）
                            if (allElements.length > 0) {
                                allElements.forEach((element, idx) => {
                                    const style = element.getAttribute('style');
                                    if (style) {
                                        console.log(`  要素[${idx}] style属性を属性に変換中: "${style}"`);
                                        
                                        // fillを抽出
                                        const fillMatch = style.match(/fill\s*:\s*([^;]+)/);
                                        if (fillMatch && fillMatch[1] !== 'none') {
                                            element.setAttribute('fill', fillMatch[1].trim());
                                            console.log(`  要素[${idx}] fill=${fillMatch[1].trim()} を属性に設定`);
                                        }
                                        
                                        // fill-opacityを抽出
                                        const fillOpacityMatch = style.match(/fill-opacity\s*:\s*([\d.]+)/);
                                        if (fillOpacityMatch) {
                                            element.setAttribute('fill-opacity', fillOpacityMatch[1]);
                                            console.log(`  要素[${idx}] fill-opacity=${fillOpacityMatch[1]} を属性に設定`);
                                        }
                                        
                                        // strokeを抽出
                                        const strokeMatch = style.match(/stroke\s*:\s*([^;]+)/);
                                        if (strokeMatch && strokeMatch[1] !== 'none') {
                                            element.setAttribute('stroke', strokeMatch[1].trim());
                                            console.log(`  要素[${idx}] stroke=${strokeMatch[1].trim()} を属性に設定`);
                                        }
                                        
                                        // stroke-opacityを抽出
                                        const strokeOpacityMatch = style.match(/stroke-opacity\s*:\s*([\d.]+)/);
                                        if (strokeOpacityMatch) {
                                            element.setAttribute('stroke-opacity', strokeOpacityMatch[1]);
                                            console.log(`  要素[${idx}] stroke-opacity=${strokeOpacityMatch[1]} を属性に設定`);
                                        }
                                        
                                        // opacityを抽出
                                        const opacityMatch = style.match(/(?:^|;)\s*opacity\s*:\s*([\d.]+)/);
                                        if (opacityMatch) {
                                            element.setAttribute('opacity', opacityMatch[1]);
                                            console.log(`  要素[${idx}] opacity=${opacityMatch[1]} を属性に設定`);
                                        }
                                        
                                        // stroke-width, stroke-linejoin, stroke-linecapなどのstroke関連のみを保持
                                        let keepStyle = '';
                                        const strokeWidthMatch = style.match(/stroke-width\s*:\s*([^;]+)/);
                                        const strokeLinecapMatch = style.match(/stroke-linecap\s*:\s*([^;]+)/);
                                        const strokeLinejoinMatch = style.match(/stroke-linejoin\s*:\s*([^;]+)/);
                                        const strokeMiterlimitMatch = style.match(/stroke-miterlimit\s*:\s*([^;]+)/);
                                        
                                        if (strokeWidthMatch) keepStyle += `stroke-width:${strokeWidthMatch[1]};`;
                                        if (strokeLinecapMatch) keepStyle += `stroke-linecap:${strokeLinecapMatch[1]};`;
                                        if (strokeLinejoinMatch) keepStyle += `stroke-linejoin:${strokeLinejoinMatch[1]};`;
                                        if (strokeMiterlimitMatch) keepStyle += `stroke-miterlimit:${strokeMiterlimitMatch[1]};`;
                                        
                                        if (keepStyle) {
                                            element.setAttribute('style', keepStyle);
                                            console.log(`  要素[${idx}] style属性を更新: "${keepStyle}"`);
                                        } else {
                                            element.removeAttribute('style');
                                            console.log(`  要素[${idx}] style属性を削除`);
                                        }
                                    }
                                });
                            }
                        }
                        
                        const content = svgElement.innerHTML;
                        
                        console.log('=== 最終的なSVGコンテンツ（最初の500文字）===');
                        console.log(content.substring(0, 500));
                        console.log('=== SVGコンテンツに#a9d693が含まれているか ===');
                        console.log('含まれている:', content.includes('#a9d693'));
                        console.log('#a9d693の出現回数:', (content.match(/#a9d693/g) || []).length);
                        
                        const result = {
                            content: content,
                            viewBox: viewBox
                        };
                        
                        materialSvgCache[cacheKey] = result;
                        return result;
                    }
                } catch (error) {
                    console.error('SVG fetch error:', error);
                }
                
                return { content: '', viewBox: { width: 100, height: 100 } };
            }
            
            // Fabric.jsキャンバスインスタンス
            let fabricCanvas = null;
            
            // Fabric.jsを使ってプレビューを表示
            async function renderPreview() {
                const width = canvasWidth;
                const height = canvasHeight;
                const bgColor = backgroundColor;
                
                // プレビューエリアのサイズを取得
                const previewArea = document.getElementById('preview-area');
                const maxWidth = previewArea.clientWidth - 60; // padding分を引く
                const maxHeight = previewArea.clientHeight - 60;
                
                // アスペクト比を維持してスケール計算（常に枠内に収める）
                const scale = Math.min(maxWidth / width, maxHeight / height);
                
                // Fabric.jsキャンバスを初期化（初回のみ）
                if (!fabricCanvas) {
                    fabricCanvas = new fabric.Canvas('preview-canvas', {
                        width: width,
                        height: height,
                        backgroundColor: bgColor,
                        selection: false,
                        renderOnAddRemove: false
                    });
                    
                    // CSSでスケーリング
                    fabricCanvas.setDimensions(
                        { width: width * scale, height: height * scale },
                        { cssOnly: true }
                    );
                    
                    // canvas-containerのサイズも調整
                    const container = fabricCanvas.wrapperEl;
                    container.style.width = (width * scale) + 'px';
                    container.style.height = (height * scale) + 'px';
                } else {
                    // 既存のオブジェクトをクリア
                    fabricCanvas.clear();
                    
                    // サイズと背景色を更新
                    fabricCanvas.setDimensions({ width: width, height: height });
                    fabricCanvas.setBackgroundColor(bgColor, fabricCanvas.renderAll.bind(fabricCanvas));
                    
                    // CSSでスケーリング
                    fabricCanvas.setDimensions(
                        { width: width * scale, height: height * scale },
                        { cssOnly: true }
                    );
                    
                    // canvas-containerのサイズも調整
                    const container = fabricCanvas.wrapperEl;
                    container.style.width = (width * scale) + 'px';
                    container.style.height = (height * scale) + 'px';
                }
                
                // レイヤーを描画
                for (let index = 0; index < layers.length; index++) {
                    const layer = layers[index];
                    const layerId = `layer_${index}`;
                    const animation = animations[layerId] || {};
                    
                    // 素材IDからSVGコンテンツを取得
                    if (layer.materialId) {
                        const svgData = await fetchMaterialSvg(layer.materialId, layer.colors);
                        
                        if (svgData && svgData.content) {
                            // SVG文字列を作成
                            const svgString = `<svg viewBox="${svgData.viewBox.x || 0} ${svgData.viewBox.y || 0} ${svgData.viewBox.width} ${svgData.viewBox.height}" xmlns="http://www.w3.org/2000/svg">${svgData.content}</svg>`;
                            
                            // Fabric.jsでSVGをロード
                            await new Promise((resolve) => {
                                fabric.loadSVGFromString(svgString, (objects, options) => {
                                    if (!objects || objects.length === 0) {
                                        resolve();
                                        return;
                                    }
                                    
                                    // SVGグループを作成
                                    const svgGroup = fabric.util.groupSVGElements(objects, options);
                                    
                                    // transform情報を取得
                                    const transform = layer.transform || {};
                                    const scaleX = Math.abs(transform.scaleX !== undefined ? transform.scaleX : 1);
                                    const scaleY = Math.abs(transform.scaleY !== undefined ? transform.scaleY : 1);
                                    const rotation = transform.rotation !== undefined ? transform.rotation : 0;
                                    const flipH = transform.flipHorizontal !== undefined ? transform.flipHorizontal : false;
                                    const flipV = transform.flipVertical !== undefined ? transform.flipVertical : false;
                                    
                                    // 位置を取得
                                    let x = transform.x !== undefined ? transform.x : 0;
                                    let y = transform.y !== undefined ? transform.y : 0;
                                    
                                    // originalCenterを取得
                                    const originalCenter = layer.originalCenter || { 
                                        x: svgData.viewBox.width / 2, 
                                        y: svgData.viewBox.height / 2 
                                    };
                                    
                                    // Fabric.jsの中心座標を計算
                                    const centerX = x + originalCenter.x * scaleX;
                                    const centerY = y + originalCenter.y * scaleY;
                                    
                                    // アニメーション初期状態の不透明度
                                    let initialOpacity = 1;
                                    if (animation.fadeIn) {
                                        initialOpacity = 0.3; // 完全に消すのではなく薄く表示
                                    }
                                    
                                    // オブジェクトを設定
                                    svgGroup.set({
                                        left: centerX,
                                        top: centerY,
                                        originX: 'center',
                                        originY: 'center',
                                        scaleX: scaleX * (flipH ? -1 : 1),
                                        scaleY: scaleY * (flipV ? -1 : 1),
                                        angle: rotation,
                                        opacity: initialOpacity,
                                        selectable: false,
                                        evented: false
                                    });
                                    
                                    fabricCanvas.add(svgGroup);
                                    resolve();
                                });
                            });
                        }
                    }
                }
                
                fabricCanvas.renderAll();
                
                // プレビューでアニメーションを再生
                playPreviewAnimation();
            }
            
            // プレビューでアニメーションを再生
            let animationTimeouts = [];
            
            function playPreviewAnimation() {
                // 既存のタイムアウトをクリア
                animationTimeouts.forEach(timeout => clearTimeout(timeout));
                animationTimeouts = [];
                
                if (!fabricCanvas) return;
                
                const objects = fabricCanvas.getObjects();
                
                // 各レイヤーにアニメーションを適用
                layers.forEach((layer, index) => {
                    const layerId = `layer_${index}`;
                    const animation = animations[layerId];
                    const obj = objects[index];
                    
                    if (!obj || !animation) return;
                    
                    // delay（開始タイミング）を計算
                    let delaySeconds = 0;
                    if (animation.delay1s) delaySeconds = 1;
                    else if (animation.delay2s) delaySeconds = 2;
                    
                    // duration（継続時間）を計算
                    let durationMs = ANIMATION_DURATION;
                    if (animation.duration1s) durationMs = 1000;
                    else if (animation.duration2s) durationMs = 2000;
                    
                    // イージングを決定（よりゆっくりとした動き）
                    let fabricEasing = fabric.util.ease.linear;
                    if (animation.easeIn) fabricEasing = fabric.util.ease.easeInCubic;
                    else if (animation.easeOut) fabricEasing = fabric.util.ease.easeOutCubic;
                    
                    // 初期状態を保存
                    const initialOpacity = obj.opacity;
                    
                    // delayミリ秒後にアニメーション開始
                    const timeout = setTimeout(() => {
                        const animateProps = {};
                        
                        // 初期角度を保存
                        const initialAngle = obj.angle || 0;
                        
                        // フェードイン
                        if (animation.fadeIn) {
                            obj.set({ opacity: 0.3 });
                            animateProps.opacity = 1;
                        }
                        
                        // フェードアウト
                        if (animation.fadeOut) {
                            obj.set({ opacity: 1 });
                            animateProps.opacity = 0;
                        }
                        
                        // 回転
                        if (animation.rotate) {
                            obj.set({ angle: initialAngle });
                            animateProps.angle = initialAngle + 360;
                        }
                        
                        // アニメーションが設定されている場合のみ実行
                        if (Object.keys(animateProps).length > 0) {
                            obj.animate(animateProps, {
                                duration: durationMs,
                                easing: fabricEasing,
                                onChange: fabricCanvas.renderAll.bind(fabricCanvas),
                                onComplete: () => {
                                    // アニメーション完了後、少し待ってループ
                                    const loopTimeout = setTimeout(() => {
                                        playPreviewAnimation();
                                    }, 500);
                                    animationTimeouts.push(loopTimeout);
                                }
                            });
                        }
                    }, delaySeconds * 1000);
                    
                    animationTimeouts.push(timeout);
                });
            }
            
            // アニメーションフレームを生成（Fabric.jsを使用）
            async function generateAnimatedFrame(progress, width, height, transparentBg = false) {
                const bgColor = transparentBg ? null : backgroundColor;
                const animationDuration = ANIMATION_DURATION;
                
                // 一時的なFabric.jsキャンバスを作成
                const tempCanvas = new fabric.Canvas(document.createElement('canvas'), {
                    width: width,
                    height: height,
                    backgroundColor: bgColor,
                    selection: false,
                    renderOnAddRemove: false
                });
                
                // レイヤーを描画（アニメーション適用）
                for (let index = 0; index < layers.length; index++) {
                    const layer = layers[index];
                    const layerId = `layer_${index}`;
                    const animation = animations[layerId] || {};
                    
                    // delay（開始タイミング）を計算
                    let delaySeconds = 0;
                    if (animation.delay1s) delaySeconds = 1;
                    else if (animation.delay2s) delaySeconds = 2;
                    
                    // duration（継続時間）を計算
                    let durationMs = animationDuration;
                    if (animation.duration1s) durationMs = 1000;
                    else if (animation.duration2s) durationMs = 2000;
                    
                    const totalDuration = animationDuration + delaySeconds * 1000;
                    const currentTime = progress * totalDuration;
                    
                    let layerProgress = 0;
                    if (currentTime < delaySeconds * 1000) {
                        layerProgress = 0;
                    } else if (currentTime < delaySeconds * 1000 + durationMs) {
                        layerProgress = (currentTime - delaySeconds * 1000) / durationMs;
                    } else {
                        layerProgress = 1;
                    }
                    
                    if (layer.materialId) {
                        const svgData = await fetchMaterialSvg(layer.materialId, layer.colors);
                        
                        if (svgData && svgData.content) {
                            // SVG文字列を作成
                            const svgString = `<svg viewBox="${svgData.viewBox.x || 0} ${svgData.viewBox.y || 0} ${svgData.viewBox.width} ${svgData.viewBox.height}" xmlns="http://www.w3.org/2000/svg">${svgData.content}</svg>`;
                            
                            // Fabric.jsでSVGをロード
                            await new Promise((resolve) => {
                                fabric.loadSVGFromString(svgString, (objects, options) => {
                                    if (!objects || objects.length === 0) {
                                        resolve();
                                        return;
                                    }
                                    
                                    const svgGroup = fabric.util.groupSVGElements(objects, options);
                                    
                                    const transform = layer.transform || {};
                                    
                                    // スケール調整（低画質モード対応）
                                    const scaleRatio = width / canvasWidth;
                                    const scaleX = Math.abs(transform.scaleX !== undefined ? transform.scaleX : 1) * scaleRatio;
                                    const scaleY = Math.abs(transform.scaleY !== undefined ? transform.scaleY : 1) * scaleRatio;
                                    const rotation = transform.rotation !== undefined ? transform.rotation : 0;
                                    const flipH = transform.flipHorizontal !== undefined ? transform.flipHorizontal : false;
                                    const flipV = transform.flipVertical !== undefined ? transform.flipVertical : false;
                                    
                                    let x = (transform.x !== undefined ? transform.x : 0) * scaleRatio;
                                    let y = (transform.y !== undefined ? transform.y : 0) * scaleRatio;
                                    
                                    // アニメーション適用
                                    let animX = x;
                                    let animY = y;
                                    let animScaleX = scaleX;
                                    let animScaleY = scaleY;
                                    let animRotation = rotation;
                                    let opacity = 1;
                                    
// イージングを決定（よりゆっくりとした動き）
                                    let easing = 'linear';
                                    if (animation.easeIn) easing = 'easeIn';
                                    else if (animation.easeOut) easing = 'easeOut';
                                    
                                    const easedProgress = applyEasing(layerProgress, easing);
                                    
                                    // フェードイン
                                    if (animation.fadeIn) {
                                        opacity = easedProgress;
                                    }
                                    // フェードアウト
                                    if (animation.fadeOut) {
                                        opacity = 1 - easedProgress;
                                    }
                                    // 回転
                                    if (animation.rotate) {
                                        animRotation = rotation + (360 * easedProgress);
                                    }
                                    
                                    // originalCenterを取得
                                    const originalCenter = layer.originalCenter || { 
                                        x: svgData.viewBox.width / 2, 
                                        y: svgData.viewBox.height / 2 
                                    };
                                    
                                    // Fabric.jsの中心座標を計算
                                    const centerX = animX + originalCenter.x * animScaleX;
                                    const centerY = animY + originalCenter.y * animScaleY;
                                    
                                    // オブジェクトを設定
                                    svgGroup.set({
                                        left: centerX,
                                        top: centerY,
                                        originX: 'center',
                                        originY: 'center',
                                        scaleX: animScaleX * (flipH ? -1 : 1),
                                        scaleY: animScaleY * (flipV ? -1 : 1),
                                        angle: animRotation,
                                        opacity: opacity,
                                        selectable: false,
                                        evented: false
                                    });
                                    
                                    tempCanvas.add(svgGroup);
                                    resolve();
                                });
                            });
                        }
                    }
                }
                
                tempCanvas.renderAll();
                
                // CanvasをData URLとして返す
                return tempCanvas.toDataURL({ format: 'png' });
            }
            
            // イージング関数（よりゆっくりとした動き）
            function applyEasing(t, easing) {
                switch (easing) {
                    case 'easeInOut':
                        return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
                    case 'easeIn':
                        // Cubic easing in (t^3)
                        return t * t * t;
                    case 'easeOut':
                        // Cubic easing out (よりゆっくり)
                        return 1 - Math.pow(1 - t, 3);
                    case 'linear':
                    default:
                        return t;
                }
            }
            
            // GIF生成（gifshotを使用）
            async function generateGif() {
                const generateBtn = document.getElementById('generate-gif-btn');
                const mp4Btn = document.getElementById('generate-mp4-btn');
                const previewArea = document.getElementById('preview-area');
                const resultArea = document.getElementById('result-area');
                const transparentBg = document.getElementById('transparent-bg').checked;
                
                try {
                    // ボタンを無効化
                    generateBtn.disabled = true;
                    mp4Btn.disabled = true;
                    generateBtn.innerHTML = '生成中...';
                    
                    // ローディング表示
                    previewArea.innerHTML = '<div class="loading"><div class="spinner"></div><p>フレームを生成中...</p></div>';
                    
                    // 常に低画質モード（長辺320px、10fps）
                    const scale = 320 / Math.max(canvasWidth, canvasHeight);
                    const width = Math.round(canvasWidth * scale);
                    const height = Math.round(canvasHeight * scale);
                    const fps = 10;
                    console.log('低画質モード:', { width, height, fps, transparentBg });
                    
                    const duration = ANIMATION_DURATION;
                    
                    console.log('GIF生成開始:', { width, height, duration, fps });
                    
                    // 最も長いレイヤーの総時間を計算（delay + animation）
                    let maxTotalDuration = duration;
                    layers.forEach((layer, index) => {
                        const layerId = `layer_${index}`;
                        const animation = animations[layerId];
                        if (animation) {
                            let delaySeconds = 0;
                            if (animation.delay1s) delaySeconds = 1;
                            else if (animation.delay2s) delaySeconds = 2;
                            
                            const layerTotalDuration = duration + (delaySeconds * 1000);
                            if (layerTotalDuration > maxTotalDuration) {
                                maxTotalDuration = layerTotalDuration;
                            }
                        }
                    });
                    
                    console.log('最大総時間:', maxTotalDuration, 'ms');
                    
                    // フレーム数を計算（最大総時間を基準）
                    const totalFrames = Math.ceil((maxTotalDuration / 1000) * fps);
                    const interval = 1 / fps;
                    
                    console.log('フレーム数:', totalFrames, 'インターバル:', interval);
                    
                    // 各フレームのCanvas要素を生成
                    const images = [];
                    for (let frame = 0; frame < totalFrames; frame++) {
                        const progress = frame / Math.max(totalFrames - 1, 1);
                        
                        // アニメーションフレームのData URLを生成（Fabric.jsで直接生成）
                        const dataUrl = await generateAnimatedFrame(progress, width, height, transparentBg);
                        images.push(dataUrl);
                        
                        // 進行状況を表示
                        const percent = Math.round((frame / totalFrames) * 100);
                        previewArea.innerHTML = `<div class="loading"><div class="spinner"></div><p>フレームを生成中... ${frame + 1}/${totalFrames} (${percent}%)</p></div>`;
                        
                        console.log(`フレーム ${frame + 1}/${totalFrames} 追加完了`);
                    }
                    
                    console.log('全フレーム生成完了、GIFエンコード開始');
                    previewArea.innerHTML = '<div class="loading"><div class="spinner"></div><p>GIFをエンコード中...</p></div>';
                    
                    // gifshotでGIF生成
                    gifshot.createGIF({
                        images: images,
                        gifWidth: width,
                        gifHeight: height,
                        interval: interval,
                        numFrames: totalFrames,
                        sampleInterval: 10,
                        numWorkers: 2
                    }, function(obj) {
                        if (!obj.error) {
                            console.log('GIF生成完了: サイズ', width, 'x', height);
                            const url = obj.image;
                            
                            // 結果を表示
                            document.getElementById('result-title').textContent = '生成されたGIF';
                            document.getElementById('result-container').innerHTML = `<img src="${url}" class="result-image" alt="Generated GIF">`;
                            document.getElementById('download-link').href = url;
                            document.getElementById('download-link').download = `animation_${artworkData.id}_${Date.now()}.gif`;
                            
                            resultArea.classList.add('active');
                            
                            // プレビューをリセットして再描画
                            fabricCanvas = null;
                            previewArea.innerHTML = '<canvas id="preview-canvas"></canvas>';
                            renderPreview();
                            
                            // ボタンを有効化
                            generateBtn.disabled = false;
                            mp4Btn.disabled = false;
                            generateBtn.innerHTML = 'GIF生成<br><small style="font-size:11px;opacity:0.8">320px, ~800KB</small>';
                        } else {
                            throw new Error(obj.error);
                        }
                    });
                    
                } catch (error) {
                    console.error('GIF生成エラー:', error);
                    alert('GIF生成に失敗しました: ' + error.message);
                    
                    // エラー時の処理
                    previewArea.innerHTML = '<canvas id="preview-canvas"></canvas>';
                    renderPreview();
                    generateBtn.disabled = false;
                    mp4Btn.disabled = false;
                    generateBtn.innerHTML = 'GIF生成<br><small style="font-size:11px;opacity:0.8">320px, ~800KB</small>';
                }
            }
            
            // MP4生成（MediaRecorder APIを使用）
            async function generateMp4() {
                const mp4Btn = document.getElementById('generate-mp4-btn');
                const gifBtn = document.getElementById('generate-gif-btn');
                const previewArea = document.getElementById('preview-area');
                const resultArea = document.getElementById('result-area');
                
                try {
                    // MediaRecorder APIのサポート確認
                    if (!window.MediaRecorder) {
                        alert('お使いのブラウザはMP4生成に対応していません。GIF生成をお試しください。');
                        return;
                    }
                    
                    // ボタンを無効化
                    mp4Btn.disabled = true;
                    gifBtn.disabled = true;
                    mp4Btn.innerHTML = '生成中...';
                    
                    // ローディング表示
                    previewArea.innerHTML = '<div class="loading"><div class="spinner"></div><p>MP4を生成中...</p></div>';
                    
                    // 640pxで生成（推奨サイズ）
                    const scale = 640 / Math.max(canvasWidth, canvasHeight);
                    const width = Math.round(canvasWidth * scale);
                    const height = Math.round(canvasHeight * scale);
                    const fps = 30;
                    
                    console.log('=== MP4生成設定 ===');
                    console.log('元のサイズ:', canvasWidth, 'x', canvasHeight);
                    console.log('スケール:', scale);
                    console.log('出力サイズ:', width, 'x', height);
                    console.log('FPS:', fps);
                    
                    const duration = ANIMATION_DURATION;
                    
                    // 最も長いレイヤーの総時間を計算
                    let maxTotalDuration = duration;
                    layers.forEach((layer, index) => {
                        const layerId = `layer_${index}`;
                        const animation = animations[layerId];
                        if (animation) {
                            let delaySeconds = 0;
                            if (animation.delay1s) delaySeconds = 1;
                            else if (animation.delay2s) delaySeconds = 2;
                            
                            const layerTotalDuration = duration + (delaySeconds * 1000);
                            if (layerTotalDuration > maxTotalDuration) {
                                maxTotalDuration = layerTotalDuration;
                            }
                        }
                    });
                    
                    console.log('最大総時間:', maxTotalDuration, 'ms');
                    
                    // フレーム数を計算
                    const totalFrames = Math.ceil((maxTotalDuration / 1000) * fps);
                    
                    // 一時的なcanvasを作成
                    const tempCanvas = document.createElement('canvas');
                    tempCanvas.width = width;
                    tempCanvas.height = height;
                    const ctx = tempCanvas.getContext('2d');
                    
                    // MediaRecorderの設定
                    const stream = tempCanvas.captureStream(fps);
                    // MP4を優先（Chrome/Edge/Safariで対応、より汎用性が高い）
                    // Chrome/EdgeではH.264コーデック指定が必要
                    let mimeType;
                    if (MediaRecorder.isTypeSupported('video/mp4;codecs=h264')) {
                        mimeType = 'video/mp4;codecs=h264';
                    } else if (MediaRecorder.isTypeSupported('video/mp4;codecs=avc1')) {
                        mimeType = 'video/mp4;codecs=avc1';
                    } else if (MediaRecorder.isTypeSupported('video/webm;codecs=vp9')) {
                        mimeType = 'video/webm;codecs=vp9';
                    } else if (MediaRecorder.isTypeSupported('video/webm;codecs=vp8')) {
                        mimeType = 'video/webm;codecs=vp8';
                    } else {
                        mimeType = 'video/webm'; // フォールバック
                    }
                    
                    console.log('使用するMIMEタイプ:', mimeType);
                    
                    const recorder = new MediaRecorder(stream, {
                        mimeType: mimeType,
                        videoBitsPerSecond: 2500000 // 2.5Mbps
                    });
                    
                    const chunks = [];
                    recorder.ondataavailable = (e) => {
                        if (e.data.size > 0) {
                            chunks.push(e.data);
                            console.log('データ受信:', e.data.size, 'bytes');
                        }
                    };
                    
                    recorder.onerror = (e) => {
                        console.error('MediaRecorder エラー:', e);
                        alert('動画の録画中にエラーが発生しました: ' + (e.error ? e.error.message : 'Unknown error'));
                        
                        // エラー時の処理
                        previewArea.innerHTML = '<canvas id="preview-canvas"></canvas>';
                        renderPreview();
                        mp4Btn.disabled = false;
                        gifBtn.disabled = false;
                        mp4Btn.innerHTML = 'MP4生成<br><small style="font-size:11px;opacity:0.8">640px, ~200KB</small>';
                    };
                    
                    recorder.onstop = () => {
                        console.log('録画完了、Blob作成中...');
                        console.log('受信したチャンク数:', chunks.length);
                        
                        if (chunks.length === 0) {
                            console.error('録画データがありません');
                            alert('動画の録画に失敗しました。お使いのデバイスではMP4生成に対応していない可能性があります。GIF生成をお試しください。');
                            
                            // エラー時の処理
                            previewArea.innerHTML = '<canvas id="preview-canvas"></canvas>';
                            renderPreview();
                            mp4Btn.disabled = false;
                            gifBtn.disabled = false;
                            mp4Btn.innerHTML = 'MP4生成<br><small style="font-size:11px;opacity:0.8">640px, ~200KB</small>';
                            return;
                        }
                        
                        const blob = new Blob(chunks, { type: mimeType });
                        const url = URL.createObjectURL(blob);
                        
                        console.log('MP4生成完了: サイズ', width, 'x', height, 'ファイルサイズ:', Math.round(blob.size / 1024), 'KB');
                        console.log('Blob URL:', url);
                        console.log('MIMEタイプ:', mimeType);
                        
                        // 結果を表示
                        document.getElementById('result-title').textContent = 'MP4生成完了';
                        const videoHtml = `
                            <video class="result-image" controls autoplay loop muted playsinline style="max-width: 100%; max-height: 500px;">
                                <source src="${url}" type="${mimeType}">
                                お使いのブラウザはこの動画形式に対応していません。
                            </video>
                        `;
                        document.getElementById('result-container').innerHTML = videoHtml;
                        
                        // ダウンロードリンクを設定
                        const downloadLink = document.getElementById('download-link');
                        downloadLink.href = url;
                        const extension = mimeType.includes('mp4') ? 'mp4' : 'webm';
                        downloadLink.download = `animation_${artworkData.id}_${Date.now()}.${extension}`;
                        
                        console.log('ダウンロードリンク設定完了:', downloadLink.download);
                        
                        resultArea.classList.add('active');
                        
                        // プレビューをリセットして再描画
                        fabricCanvas = null;
                        previewArea.innerHTML = '<canvas id="preview-canvas"></canvas>';
                        renderPreview();
                        
                        // ボタンを有効化
                        mp4Btn.disabled = false;
                        gifBtn.disabled = false;
                        mp4Btn.innerHTML = 'MP4生成<br><small style="font-size:11px;opacity:0.8">640px, ~200KB</small>';
                    };
                    
                    // 録画開始（100msごとにデータを送信）
                    try {
                        recorder.start(100); // タイムスライスを指定
                        console.log('録画開始 - state:', recorder.state);
                    } catch (error) {
                        console.error('録画開始エラー:', error);
                        alert('動画の録画を開始できませんでした: ' + error.message);
                        
                        // エラー時の処理
                        previewArea.innerHTML = '<canvas id="preview-canvas"></canvas>';
                        renderPreview();
                        mp4Btn.disabled = false;
                        gifBtn.disabled = false;
                        mp4Btn.innerHTML = 'MP4生成<br><small style="font-size:11px;opacity:0.8">640px, ~200KB</small>';
                        return;
                    }
                    
                    // フレームごとに描画
                    const frameInterval = 1000 / fps;
                    let currentFrame = 0;
                    
                    const drawFrame = async () => {
                        try {
                            if (currentFrame >= totalFrames) {
                                // 録画終了
                                console.log('全フレーム描画完了 - 録画停止');
                                recorder.stop();
                                return;
                            }
                            
                            const progress = currentFrame / Math.max(totalFrames - 1, 1);
                        
                        // Fabric.jsで一時的なキャンバスを作成してフレームを生成
                        // devicePixelRatioを1に固定してサイズを制御
                        const fabricTemp = new fabric.Canvas(document.createElement('canvas'), {
                            width: width,
                            height: height,
                            backgroundColor: backgroundColor,
                            selection: false,
                            renderOnAddRemove: false,
                            enableRetinaScaling: false // Retinaスケーリングを無効化
                        });
                        
                        // デバッグ: canvasサイズを確認（最初のフレームのみ）
                        if (currentFrame === 0) {
                            const fabricCanvas = fabricTemp.getElement();
                            console.log('=== fabricTemp canvas 情報 ===');
                            console.log('設定値 width:', width, 'height:', height);
                            console.log('fabricTemp.width:', fabricTemp.width);
                            console.log('fabricTemp.height:', fabricTemp.height);
                            console.log('fabricCanvas.width:', fabricCanvas.width);
                            console.log('fabricCanvas.height:', fabricCanvas.height);
                            console.log('devicePixelRatio:', window.devicePixelRatio);
                        }
                        
                        // レイヤーを描画
                        for (let index = 0; index < layers.length; index++) {
                            const layer = layers[index];
                            const layerId = `layer_${index}`;
                            const animation = animations[layerId] || {};
                            
                            // delay計算
                            let delaySeconds = 0;
                            if (animation.delay1s) delaySeconds = 1;
                            else if (animation.delay2s) delaySeconds = 2;
                            
                            // duration計算
                            let durationMs = ANIMATION_DURATION;
                            if (animation.duration1s) durationMs = 1000;
                            else if (animation.duration2s) durationMs = 2000;
                            
                            const totalDuration = maxTotalDuration;
                            const currentTime = progress * totalDuration;
                            
                            let layerProgress = 0;
                            if (currentTime < delaySeconds * 1000) {
                                layerProgress = 0;
                            } else if (currentTime < delaySeconds * 1000 + durationMs) {
                                layerProgress = (currentTime - delaySeconds * 1000) / durationMs;
                            } else {
                                layerProgress = 1;
                            }
                            
                            if (layer.materialId) {
                                const svgData = await fetchMaterialSvg(layer.materialId, layer.colors);
                                
                                if (svgData && svgData.content) {
                                    const svgString = `<svg viewBox="${svgData.viewBox.x || 0} ${svgData.viewBox.y || 0} ${svgData.viewBox.width} ${svgData.viewBox.height}" xmlns="http://www.w3.org/2000/svg">${svgData.content}</svg>`;
                                    
                                    await new Promise((resolve) => {
                                        fabric.loadSVGFromString(svgString, (objects, options) => {
                                            if (!objects || objects.length === 0) {
                                                resolve();
                                                return;
                                            }
                                            
                                            const svgGroup = fabric.util.groupSVGElements(objects, options);
                                            const transform = layer.transform || {};
                                            
                                            const scaleRatio = width / canvasWidth;
                                            const scaleX = Math.abs(transform.scaleX !== undefined ? transform.scaleX : 1) * scaleRatio;
                                            const scaleY = Math.abs(transform.scaleY !== undefined ? transform.scaleY : 1) * scaleRatio;
                                            const rotation = transform.rotation !== undefined ? transform.rotation : 0;
                                            const flipH = transform.flipHorizontal !== undefined ? transform.flipHorizontal : false;
                                            const flipV = transform.flipVertical !== undefined ? transform.flipVertical : false;
                                            
                                            let x = (transform.x !== undefined ? transform.x : 0) * scaleRatio;
                                            let y = (transform.y !== undefined ? transform.y : 0) * scaleRatio;
                                            
                                            const originalCenter = layer.originalCenter || { 
                                                x: svgData.viewBox.width / 2, 
                                                y: svgData.viewBox.height / 2 
                                            };
                                            
                                            const centerX = x + originalCenter.x * scaleX;
                                            const centerY = y + originalCenter.y * scaleY;
                                            
                                            // デバッグ: 座標計算を確認（最初のフレーム、最初のレイヤーのみ）
                                            if (currentFrame === 0 && index === 0) {
                                                console.log('=== レイヤー座標計算 (layer 0, frame 0) ===');
                                                console.log('scaleRatio:', scaleRatio);
                                                console.log('transform.x:', transform.x, '-> x:', x);
                                                console.log('transform.y:', transform.y, '-> y:', y);
                                                console.log('transform.scaleX:', transform.scaleX, '-> scaleX:', scaleX);
                                                console.log('transform.scaleY:', transform.scaleY, '-> scaleY:', scaleY);
                                                console.log('viewBox:', svgData.viewBox);
                                                console.log('originalCenter:', originalCenter);
                                                console.log('計算結果 - centerX:', centerX, 'centerY:', centerY);
                                            }
                                            
                                            // アニメーション適用
                                            let animRotation = rotation;
                                            let opacity = 1;
                                            
                                            // イージング関数
                                            const easeInCubic = (t) => t * t * t;
                                            const easeOutCubic = (t) => 1 - Math.pow(1 - t, 3);
                                            
                                            let t = layerProgress;
                                            if (animation.easeIn) t = easeInCubic(t);
                                            else if (animation.easeOut) t = easeOutCubic(t);
                                            
                                            if (animation.fadeIn) {
                                                opacity = t;
                                            }
                                            if (animation.fadeOut) {
                                                opacity = 1 - t;
                                            }
                                            if (animation.rotate) {
                                                animRotation = rotation + (360 * t);
                                            }
                                            
                                            svgGroup.set({
                                                left: centerX,
                                                top: centerY,
                                                originX: 'center',
                                                originY: 'center',
                                                scaleX: scaleX * (flipH ? -1 : 1),
                                                scaleY: scaleY * (flipV ? -1 : 1),
                                                angle: animRotation,
                                                opacity: opacity,
                                                selectable: false,
                                                evented: false
                                            });
                                            
                                            fabricTemp.add(svgGroup);
                                            resolve();
                                        });
                                    });
                                }
                            }
                        }
                        
                        fabricTemp.renderAll();
                        
                        // fabricのcanvasをtempCanvasに描画
                        const fabricCanvas2 = fabricTemp.getElement();
                        ctx.clearRect(0, 0, width, height);
                        // ソースと出力先のサイズを明示的に指定
                        ctx.drawImage(fabricCanvas2, 0, 0, fabricCanvas2.width, fabricCanvas2.height, 0, 0, width, height);
                        
                        // デバッグ: tempCanvasの内容を確認（最初のフレームのみ）
                        if (currentFrame === 0) {
                            console.log('=== tempCanvas 描画確認 ===');
                            console.log('tempCanvas.width:', tempCanvas.width, 'tempCanvas.height:', tempCanvas.height);
                            console.log('描画元 fabricCanvas.width:', fabricCanvas2.width, 'fabricCanvas.height:', fabricCanvas2.height);
                            console.log('drawImage: src(0,0,', fabricCanvas2.width, ',', fabricCanvas2.height, ') -> dest(0,0,', width, ',', height, ')');
                        }
                        
                        // 進行状況を表示
                        const percent = Math.round((currentFrame / totalFrames) * 100);
                        previewArea.innerHTML = `<div class="loading"><div class="spinner"></div><p>MP4を生成中... ${currentFrame + 1}/${totalFrames} (${percent}%)</p></div>`;
                        
                        currentFrame++;
                        
                        // 次のフレームをスケジュール
                        setTimeout(drawFrame, frameInterval);
                        
                        } catch (error) {
                            console.error('フレーム描画エラー:', error);
                            // エラーが発生しても続行を試みる
                            currentFrame++;
                            if (currentFrame < totalFrames) {
                                setTimeout(drawFrame, frameInterval);
                            } else {
                                // 最終フレームでエラーの場合は録画を停止
                                recorder.stop();
                            }
                        }
                    };
                    
                    // 最初のフレームを描画
                    drawFrame();
                    
                } catch (error) {
                    console.error('MP4生成エラー:', error);
                    alert('MP4生成に失敗しました: ' + error.message);
                    
                    // エラー時の処理
                    previewArea.innerHTML = '<canvas id="preview-canvas"></canvas>';
                    renderPreview();
                    mp4Btn.disabled = false;
                    gifBtn.disabled = false;
                    mp4Btn.innerHTML = 'MP4生成<br><small style="font-size:11px;opacity:0.8">640px, ~200KB</small>';
                }
            }
            
            // 結果をリセット
            function resetResult() {
                document.getElementById('result-area').classList.remove('active');
            }
            
            // ページ読み込み時に作品データを取得
            loadArtworkData();
        </script>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
