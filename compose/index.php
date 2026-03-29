<?php
require_once '../config.php';

// 公開ページなのでキャッシュを有効化
// setPublicCache(3600, 7200);

$pdo = getDB();

// カテゴリ一覧のみ取得
$categoriesSql = "
    SELECT DISTINCT c.id, c.title, c.slug
    FROM categories c
    INNER JOIN materials m ON m.category_id = c.id
    WHERE m.svg_path IS NOT NULL AND m.svg_path != ''
    ORDER BY c.title
";
$stmt = $pdo->prepare($categoriesSql);
$stmt->execute();
$categories = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>無料イラスト作成ツール｜marutto.art</title>
    <meta name="description" content="パーツを組み合わせて、オリジナルイラストを無料で作成。ブログ・資料・SNSに使えます。">
    <link rel="icon" href="/favicon.ico">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://marutto.art/compose/">
    
    <!-- hreflang tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/compose/" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/compose/" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/compose/" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/compose/" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/compose/" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/compose/" />
    
    <?php include __DIR__ . '/../includes/gtm-head.php'; ?>
    <?php include __DIR__ . '/../includes/adsense-head.php'; ?>
    
    <style>
        :root {
            --primary-color: #E8A87C;
            --secondary-color: #C38E70;
            --text-dark: #5A4A42;
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
            overflow-x: clip;
        }

        /* メインコンテンツ */
        .main-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
            min-height: 150vh;
        }

        /* 素材選択セクション */
        .material-selection {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .material-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .material-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .search-box {
            flex: 1;
            max-width: 400px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 10px 16px;
            border-radius: 50px;
            border: 2px solid var(--primary-color);
            font-size: 0.9rem;
            background: white;
        }

        .search-input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(232, 168, 124, 0.2);
        }

        .category-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .category-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
                scrollbar-color: var(--primary-color) transparent;
                padding-bottom: 8px;
            }

            .category-tabs::-webkit-scrollbar {
                height: 4px;
            }

            .category-tabs::-webkit-scrollbar-track {
                background: transparent;
            }

            .category-tabs::-webkit-scrollbar-thumb {
                background: var(--primary-color);
                border-radius: 4px;
            }
        }

        .category-tab {
            padding: 8px 20px;
            border-radius: 50px;
            border: 2px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .category-tab {
                flex-shrink: 0;
                font-size: 0.85rem;
                padding: 6px 16px;
            }
        }

        .category-tab:hover,
        .category-tab.active {
            background: var(--primary-color);
            color: white;
        }

        .artwork-notice {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 10px;
            margin: 8px 8px 12px 8px;
            font-size: 0.85rem;
            color: #856404;
            line-height: 1.5;
            display: none;
        }

        .artwork-notice.show {
            display: block;
        }

        .material-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 12px;
            max-height: 150px;
            overflow-y: auto;
            padding: 8px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            padding: 8px;
        }

        .pagination-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .pagination-btn:hover:not(:disabled) {
            background: var(--primary-color);
            color: white;
        }

        .pagination-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .pagination-info {
            font-size: 0.85rem;
            color: var(--text-dark);
        }

        .control-divider {
            border: none;
            border-top: 1px solid rgba(90, 74, 66, 0.2);
            margin: 14px 0;
        }

        .suggested-item-section {
            background: #fffbf5;
            border: 2px solid var(--primary-color);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .suggested-item-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0 0 12px 0;
            text-align: center;
        }

        .suggested-item-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
        }

        .suggested-item {
            cursor: pointer;
            border-radius: 12px;
            padding: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
            background: white;
            border: 2px solid #ddd;
            max-width: 150px;
        }

        .suggested-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-color: var(--primary-color);
        }

        .suggested-item img {
            width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .suggested-item-label {
            margin-top: 8px;
            font-size: 0.85rem;
            color: var(--text-dark);
            text-align: center;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .material-grid {
                grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
                gap: 8px;
            }
        }

        .material-item {
            aspect-ratio: 1;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            border: 2px solid transparent;
        }

        .material-item:hover {
            transform: scale(1.05);
            border-color: var(--primary-color);
        }

        .material-item img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* 作業エリア */
        .workspace {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
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
            min-width: 0; /* グリッドのオーバーフロー防止 */
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



        .canvas-size-selector {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .canvas-size-selector label {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .canvas-size-selector select {
            padding: 8px 12px;
            border-radius: 8px;
            border: 2px solid var(--primary-color);
            background: white;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .custom-size-inputs {
            display: none;
            gap: 4px;
            align-items: center;
        }

        .custom-size-inputs.active {
            display: flex;
        }

        .custom-size-inputs input {
            width: 80px;
            padding: 8px;
            border-radius: 8px;
            border: 2px solid var(--primary-color);
            font-size: 0.9rem;
            text-align: center;
        }

        .custom-size-inputs span {
            font-size: 0.9rem;
            color: var(--text-dark);
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

        #canvas {
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

        .control-section {
            margin-bottom: 24px;
        }

        .control-section:last-child {
            margin-bottom: 0;
        }

        .control-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--primary-color);
        }

        .control-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .control-btn {
            padding: 10px;
            border-radius: 8px;
            border: 2px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .control-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        .control-btn.full-width {
            grid-column: 1 / -1;
        }

        .color-picker {
            width: 100%;
            height: 44px;
            border-radius: 8px;
            border: 2px solid var(--primary-color);
            cursor: pointer;
        }

        .layer-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .layer-item {
            background: white;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: grab;
            border: 2px solid transparent;
            transition: all 0.2s;
            gap: 12px;
            user-select: none;
            -webkit-user-select: none;
            touch-action: manipulation;
        }

        .layer-item:active {
            cursor: grabbing;
        }

        .layer-item.dragging {
            opacity: 0.5;
            cursor: grabbing;
        }

        .layer-item.drag-over {
            border-top: 3px solid var(--primary-color);
        }

        .drag-handle {
            display: grid;
            grid-template-columns: repeat(2, 4px);
            grid-template-rows: repeat(3, 4px);
            gap: 3px;
            cursor: grab;
            flex-shrink: 0;
            user-select: none;
            -webkit-user-select: none;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            padding: 8px;
            margin: -8px;
        }

        .drag-handle::before,
        .drag-handle::after {
            content: '';
            width: 4px;
            height: 4px;
            background-color: #999;
            border-radius: 50%;
        }

        .drag-handle::before {
            grid-column: 1;
            grid-row: 1;
        }

        .drag-handle::after {
            grid-column: 2;
            grid-row: 1;
        }

        .drag-dot {
            width: 4px;
            height: 4px;
            background-color: #999;
            border-radius: 50%;
        }

        .layer-item:hover .drag-handle .drag-dot,
        .layer-item:hover .drag-handle::before,
        .layer-item:hover .drag-handle::after {
            background-color: var(--primary-color);
        }

        .layer-item.moving-source {
            border: 3px solid var(--primary-color) !important;
            background: rgba(232, 168, 124, 0.3) !important;
            box-shadow: 0 0 10px rgba(232, 168, 124, 0.5);
        }

        .layer-item.moving-target {
            background: rgba(232, 168, 124, 0.15) !important;
            border: 2px dashed var(--primary-color) !important;
        }

        .layer-item.selected {
            border-color: var(--primary-color);
            background: rgba(232, 168, 124, 0.1);
        }

        .layer-item:hover {
            background: rgba(232, 168, 124, 0.05);
        }

        .layer-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
        }

        .layer-preview {
            width: 40px;
            height: 40px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .layer-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .layer-name {
            font-size: 0.85rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .layer-actions {
            display: flex;
            gap: 4px;
            flex-shrink: 0;
        }

        .layer-action-btn {
            padding: 4px 8px;
            border-radius: 4px;
            border: none;
            background: var(--primary-color);
            color: white;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .layer-action-btn:hover {
            background: var(--secondary-color);
        }

        .layer-action-btn.duplicate {
            background: #6c757d;
        }

        .layer-action-btn.duplicate:hover {
            background: #5a6268;
        }

        .primary-btn {
            width: 100%;
            padding: 14px 28px;
            border-radius: 50px;
            border: none;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-bottom: 12px;
        }

        .primary-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .secondary-btn {
            width: 100%;
            padding: 10px 20px;
            border-radius: 25px;
            border: 2px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .secondary-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        .secondary-btn {
            width: 100%;
            padding: 10px 20px;
            border-radius: 25px;
            border: 2px solid var(--primary-color);
            background: white;
            color: var(--primary-color);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .secondary-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        /* スクロールバー */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        /* モーダル */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 2px solid #ddd;
            font-size: 0.9rem;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .modal-btn {
            flex: 1;
            padding: 12px;
            border-radius: 50px;
            border: 2px solid var(--primary-color);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .modal-btn.primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .modal-btn.secondary {
            background: white;
            color: var(--primary-color);
        }

        .modal-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php 
    include __DIR__ . '/../includes/gtm-body.php';
    ?>

    <?php 
    $currentPage = 'compose';
    include __DIR__ . '/../includes/header.php';
    ?>
    
    <!-- メインコンテンツ -->
    <div class="main-wrapper">
        <!-- 推奨アイテムセクション -->
        <div id="suggestedItemSection" class="suggested-item-section" style="display: none;">
            <div class="suggested-item-content">
                <h3 class="suggested-item-title">この素材/作品から始める</h3>
                <div id="suggestedItemContainer" class="suggested-item-container"></div>
            </div>
        </div>

        <!-- 素材選択セクション -->
        <div class="material-selection">
            <div class="material-header">
                <h2 class="material-title">素材を選ぶ</h2>
                <div class="search-box">
                    <input type="text" id="materialSearch" class="search-input" placeholder="素材を検索...">
                </div>
            </div>
            
            <div class="category-tabs" id="categoryTabs">
                <button class="category-tab active" onclick="filterByCategory('おすすめ')">おすすめ</button>
                <button class="category-tab" onclick="filterByCategory('all')">すべて</button>
                <?php foreach ($categories as $category): ?>
                <button class="category-tab" onclick="filterByCategory('<?= h($category['title']) ?>')">
                    <?= h($category['title']) ?>
                </button>
                <?php endforeach; ?>
                <button class="category-tab" onclick="filterByCategory('作品')">作品</button>
            </div>
            
            <div class="artwork-notice" id="artworkNotice">
                ⚠️ 作品を読み込むと、現在の編集内容が消去されます。
            </div>
            
            <div class="material-grid" id="materialGrid">
                <!-- 素材がJavaScriptで動的に表示されます -->
            </div>
            
            <div class="pagination" id="pagination">
                <button class="pagination-btn" id="prevBtn" onclick="changePage(-1)">前へ</button>
                <span class="pagination-info" id="pageInfo">1 / 1</span>
                <button class="pagination-btn" id="nextBtn" onclick="changePage(1)">次へ</button>
            </div>
            
            <!-- おすすめ素材のIDのみ -->
            <script>
                const RECOMMENDED_MATERIAL_IDS = [828,661,1002,994,968,778,1018,1016,1015,1011];
            </script>
        </div>

        <!-- 作業エリア -->
        <div class="workspace">
            <!-- キャンバスエリア -->
            <div class="canvas-area">
                <div class="canvas-wrapper">
                    <canvas id="canvas"></canvas>
                </div>
            </div>

            <!-- コントロールパネル -->
        <div class="control-panel">
            <!-- 色変更 -->
            <div class="control-section">
                <h3 class="control-title">色変更</h3>
                <div id="colorPickerContainer">
                    <p style="color: #999; font-size: 0.85rem; text-align: center; padding: 20px 0;">素材を選択してください</p>
                </div>
                <button id="randomColorBtn" class="secondary-btn full-width" onclick="applyRandomColors()" style="margin-top: 12px; display: none;">Change Random Color</button>
            </div>

            <!-- レイヤー -->
            <div class="control-section">
                <h3 class="control-title">レイヤー</h3>
                <div class="layer-list" id="layerList">
                    <!-- レイヤーがここに表示されます -->
                </div>
            </div>

            <!-- 背景設定 -->
            <div class="control-section">
                <h3 class="control-title">背景</h3>
                <div style="margin-bottom: 12px;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                        <input type="radio" name="bgType" value="transparent" checked onchange="toggleBackgroundType()">
                        <span>透明</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="bgType" value="color" onchange="toggleBackgroundType()">
                        <span>背景色</span>
                    </label>
                </div>
                <div class="color-picker-wrapper">
                    <input type="color" id="bgColorPicker" class="color-picker" value="#ffffff" oninput="changeBackgroundColor()" onchange="changeBackgroundColor()" disabled>
                </div>
            </div>

            <!-- キャンバス設定 -->
            <div class="control-section">
                <h3 class="control-title">キャンバス</h3>
                <div class="canvas-size-selector" style="margin-bottom: 12px;">
                    <label>サイズ:</label>
                    <select id="canvasSize" onchange="handleCanvasSizeChange()">
                        <option value="800x800">正方形 (800×800)</option>
                        <option value="1200x630">横長 (1200×630)</option>
                        <option value="630x1200">縦長 (630×1200)</option>
                        <option value="1920x1080">HD (1920×1080)</option>
                        <option value="custom">カスタム</option>
                    </select>
                </div>
                <div class="custom-size-inputs" id="customSizeInputs" style="margin-bottom: 12px;">
                    <input type="number" id="customWidth" placeholder="幅" min="100" max="5000" value="800">
                    <span>×</span>
                    <input type="number" id="customHeight" placeholder="高さ" min="100" max="5000" value="800">
                    <button class="control-btn" onclick="applyCustomSize()" style="padding: 8px 12px; font-size: 0.85rem;">適用</button>
                </div>
                <button class="secondary-btn full-width" onclick="clearAll()">全削除</button>
            </div>

            <!-- アクション -->
            <div class="control-section">
                <button class="primary-btn full-width" onclick="downloadImage()">PNG ダウンロード</button>
                <button class="secondary-btn full-width" onclick="downloadSVG()" style="margin-top: 12px;">SVG ダウンロード</button>
                <hr class="control-divider">
                <button class="primary-btn full-width" style="margin-top: 12px;" onclick="uploadArtwork()">作品を投稿</button>
                <p style="margin-top: 8px; font-size: 0.8rem; color: #999; text-align: center; line-height: 1.4;">多くの人が投稿できるよう、作品の投稿は 1日1回まで となっています</p>
                <hr class="control-divider">
                <button class="secondary-btn full-width" style="margin-top: 12px;" onclick="window.history.back()">前に戻る</button>
            </div>
        </div>
    </div>
    </div>

    <!-- 投稿確認ポップアップ -->
    <div id="confirmModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <h2 class="modal-title">作品を投稿しますか？</h2>
            <p style="margin-bottom: 8px; color: #666;">すぐに公開されます</p>
            <p style="margin-bottom: 20px; font-size: 0.85rem; color: #999; line-height: 1.5;">多くの人が投稿できるよう、作品の投稿は 1日1回まで となっています</p>
            <div class="modal-buttons">
                <button type="button" class="modal-btn secondary" onclick="hideConfirmModal()">キャンセル</button>
                <button type="button" class="modal-btn primary" onclick="confirmUpload()">投稿する</button>
            </div>
        </div>
    </div>

    <!-- 投稿成功ポップアップ -->
    <div id="successModal" class="modal">
        <div class="modal-content" style="max-width: 450px; text-align: center; position: relative;">
            <button onclick="hideSuccessModal()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #999; padding: 0; width: 30px; height: 30px; line-height: 1; transition: color 0.2s;" onmouseover="this.style.color='#333'" onmouseout="this.style.color='#999'" aria-label="閉じる">×</button>
            <div style="font-size: 3rem; margin-bottom: 16px;">✨</div>
            <h2 class="modal-title">作品を投稿しました！</h2>
            <p style="margin-bottom: 12px; color: #666; line-height: 1.6;">ありがとうございます</p>
            <p style="margin-bottom: 24px; color: #999; font-size: 0.9rem; line-height: 1.5;">トップページと作品一覧への反映には、最大2日程度かかる場合があります。</p>
            <div style="display: flex; gap: 12px;">
                <a id="artworkDetailLink" href="#" class="modal-btn primary" style="flex: 1; text-decoration: none; display: flex; align-items: center; justify-content: center; line-height: normal;">詳細を見る</a>
                <button type="button" id="tweetButton" class="modal-btn secondary" onclick="tweetArtwork()" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                    ツイート
                </button>
            </div>
        </div>
    </div>

    <!-- エラーメッセージポップアップ -->
    <div id="errorModal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <div style="font-size: 3rem; margin-bottom: 16px;">⚠️</div>
            <h2 class="modal-title" id="errorModalTitle">エラー</h2>
            <p id="errorModalMessage" style="margin-bottom: 24px; color: #666; line-height: 1.6;"></p>
            <button type="button" class="modal-btn primary" onclick="hideErrorModal()" style="width: 100%;">OK</button>
        </div>
    </div>

    <!-- ローディング中ポップアップ -->
    <div id="loadingModal" class="modal">
        <div class="modal-content" style="max-width: 300px; text-align: center;">
            <h2 class="modal-title">投稿中</h2>
            <p style="color: #999; font-size: 0.9rem;">しばらくお待ちください</p>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
    <script>
        let canvas;
        let selectedObject = null;
        const STORAGE_KEY = 'maruttoart_canvas_state';
        let originalCanvasWidth = 800;  // 原寸の幅
        let originalCanvasHeight = 800; // 原寸の高さ
        let isTransparentBg = true;     // 背景が透明かどうか

        // LocalStorageに保存（compose/index.php互換形式）
        function saveToLocalStorage() {
            try {
                const objects = canvas.getObjects().map((obj, zIndex) => {
                    // Fabric.jsのcenter originから左上基準に変換
                    const centerX = obj.width ? (obj.width / 2) : 0;
                    const centerY = obj.height ? (obj.height / 2) : 0;
                    const absScaleX = Math.abs(obj.scaleX);
                    const absScaleY = Math.abs(obj.scaleY);
                    const scale = (absScaleX + absScaleY) / 2; // 平均スケール（後方互換性のため）
                    
                    return {
                        id: obj.materialData?.id || Math.floor(Math.random() * 10000),
                        type: 'svg',
                        materialId: obj.materialData?.id,
                        title: obj.materialData?.title || '',
                        svgPath: obj.materialData?.svg_path,
                        originalCenter: {
                            x: centerX,
                            y: centerY
                        },
                        transform: {
                            x: obj.left - (centerX * absScaleX),
                            y: obj.top - (centerY * absScaleY),
                            scale: scale,
                            scaleX: absScaleX,
                            scaleY: absScaleY,
                            rotation: obj.angle,
                            flipHorizontal: obj.flipX || obj.scaleX < 0,
                            flipVertical: obj.flipY || obj.scaleY < 0
                        },
                        visible: true,
                        zIndex: zIndex,
                        // 互換性のため古い形式も保持
                        left: obj.left,
                        top: obj.top,
                        scaleX: obj.scaleX,
                        scaleY: obj.scaleY,
                        angle: obj.angle,
                        flipX: obj.flipX || obj.scaleX < 0,
                        flipY: obj.flipY || obj.scaleY < 0,
                        originX: obj.originX,
                        originY: obj.originY,
                        materialData: {
                            ...obj.materialData,
                            zIndex: zIndex
                        },
                        // 色情報を保存
                        colors: (() => {
                            const colors = [];
                            if (obj.type === 'group' && obj._objects) {
                                obj._objects.forEach((child, index) => {
                                    colors.push({
                                        index: index,
                                        fill: child.fill,
                                        stroke: child.stroke
                                    });
                                });
                            }
                            return colors;
                        })()
                    };
                });
                
                const canvasData = {
                    layers: objects, // compose/index.php互換
                    canvasWidth: originalCanvasWidth,
                    canvasHeight: originalCanvasHeight,
                    backgroundColor: canvas.backgroundColor,
                    // 互換性のため古い形式も保持
                    width: originalCanvasWidth,
                    height: originalCanvasHeight,
                    isTransparentBg: isTransparentBg,
                    objects: objects,
                    canvasSize: document.getElementById('canvasSize').value,
                    customWidth: originalCanvasWidth,
                    customHeight: originalCanvasHeight
                };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(canvasData));
            } catch (error) {
                console.error('保存エラー:', error);
            }
        }

        // LocalStorageから復元
        function loadFromLocalStorage() {
            try {
                const saved = localStorage.getItem(STORAGE_KEY);
                if (!saved) {
                    console.log('保存データがありません');
                    return;
                }

                const canvasData = JSON.parse(saved);
                
                // キャンバスサイズを復元
                if (canvasData.canvasSize) {
                    document.getElementById('canvasSize').value = canvasData.canvasSize;
                    
                    // カスタムサイズの場合、入力欄を表示して値を復元
                    if (canvasData.canvasSize === 'custom' && canvasData.customWidth && canvasData.customHeight) {
                        document.getElementById('customSizeInputs').classList.add('active');
                        document.getElementById('customWidth').value = canvasData.customWidth;
                        document.getElementById('customHeight').value = canvasData.customHeight;
                    }
                }
                
                const width = canvasData.width || 800;
                const height = canvasData.height || 800;
                
                // 原寸サイズを更新
                originalCanvasWidth = width;
                originalCanvasHeight = height;
                
                canvas.setDimensions({ width, height });
                
                // 表示エリアに収まるようにスケーリング
                fitCanvasToContainer();
                
                if (canvasData.backgroundColor) {
                    canvas.backgroundColor = canvasData.backgroundColor;
                }
                
                // 背景タイプを復元
                if (typeof canvasData.isTransparentBg !== 'undefined') {
                    isTransparentBg = canvasData.isTransparentBg;
                    if (isTransparentBg) {
                        document.querySelector('input[name="bgType"][value="transparent"]').checked = true;
                        canvas.backgroundColor = 'transparent';
                        document.getElementById('bgColorPicker').disabled = true;
                    } else {
                        document.querySelector('input[name="bgType"][value="color"]').checked = true;
                        document.getElementById('bgColorPicker').disabled = false;
                        if (canvasData.backgroundColor) {
                            document.getElementById('bgColorPicker').value = canvasData.backgroundColor;
                        }
                    }
                }

                // オブジェクトを復元（layers/objects両対応）
                const objectsToRestore = canvasData.layers || canvasData.objects || [];
                if (objectsToRestore && objectsToRestore.length > 0) {
                    // zIndexでソートしてから復元（表示順を保証）
                    const sortedObjects = objectsToRestore.slice().sort((a, b) => {
                        const zIndexA = a.zIndex ?? a.materialData?.zIndex ?? 999;
                        const zIndexB = b.zIndex ?? b.materialData?.zIndex ?? 999;
                        return zIndexA - zIndexB;
                    });
                    
                    let loadedCount = 0;
                    const totalCount = sortedObjects.length;
                    const loadedObjects = []; // 読み込んだオブジェクトを順序通りに格納
                    
                    sortedObjects.forEach((objData, index) => {
                        // svgPathの取得（複数の形式に対応）
                        let svgPath = null;
                        if (objData.materialData && objData.materialData.svg_path) {
                            svgPath = objData.materialData.svg_path;
                        } else if (objData.svgPath) {
                            svgPath = objData.svgPath;
                        }
                        
                        if (svgPath) {
                            // SVGパスをR2 URL対応
                            let svgUrl = svgPath;
                            if (svgUrl && !svgUrl.startsWith('http://') && !svgUrl.startsWith('https://')) {
                                svgUrl = '/' + svgUrl;
                            }
                            
                            fetch(svgUrl)
                                .then(response => response.text())
                                .then(svgText => {
                                    // 色情報がある場合は、SVGテキストを直接編集してから読み込む
                                    let modifiedSvgText = svgText;
                                    if (objData.colors && objData.colors.length > 0) {
                                        console.log('SVG読み込み前に色を置換:', objData.colors);
                                        
                                        // DOMParserでSVGを解析
                                        const parser = new DOMParser();
                                        const svgDoc = parser.parseFromString(svgText, 'image/svg+xml');
                                        const svgElement = svgDoc.querySelector('svg');
                                        
                                        if (svgElement) {
                                            // 描画要素を取得
                                            const drawingElements = svgElement.querySelectorAll('path, rect, circle, ellipse, line, polyline, polygon');
                                            console.log('描画要素数:', drawingElements.length);
                                            
                                            // 色を適用
                                            objData.colors.forEach(colorData => {
                                                const element = drawingElements[colorData.index];
                                                if (element) {
                                                    console.log(`要素[${colorData.index}] 処理前:`, {
                                                        fill: element.getAttribute('fill'),
                                                        style: element.getAttribute('style')
                                                    });
                                                    
                                                    if (colorData.fill) {
                                                        // style属性を完全に削除してfill属性のみにする
                                                        const style = element.getAttribute('style');
                                                        if (style) {
                                                            // fillとstroke以外のスタイルを保持
                                                            let newStyleParts = [];
                                                            style.split(';').forEach(part => {
                                                                const trimmed = part.trim();
                                                                if (trimmed && 
                                                                    !trimmed.match(/^fill\s*:/i) && 
                                                                    !trimmed.match(/^stroke\s*:/i)) {
                                                                    newStyleParts.push(trimmed);
                                                                }
                                                            });
                                                            
                                                            if (newStyleParts.length > 0) {
                                                                element.setAttribute('style', newStyleParts.join(';') + ';');
                                                            } else {
                                                                element.removeAttribute('style');
                                                            }
                                                        }
                                                        
                                                        // fill属性を設定
                                                        element.setAttribute('fill', colorData.fill);
                                                    }
                                                    if (colorData.stroke !== undefined) {
                                                        if (colorData.stroke === null || colorData.stroke === 'none') {
                                                            element.removeAttribute('stroke');
                                                        } else {
                                                            element.setAttribute('stroke', colorData.stroke);
                                                        }
                                                    }
                                                    console.log(`要素[${colorData.index}]に色を適用: fill=${colorData.fill}`);
                                                }
                                            });
                                            
                                            // 修正したSVGをシリアライズ
                                            const serializer = new XMLSerializer();
                                            modifiedSvgText = serializer.serializeToString(svgDoc);
                                        }
                                    }
                                    
                                    fabric.loadSVGFromString(modifiedSvgText, function(objects, options) {
                                        const obj = fabric.util.groupSVGElements(objects, options);
                                        
                                        // 保存時のデータに応じて復元方法を選択
                                        if (objData.left !== undefined && objData.originX) {
                                            // 新しい形式（直接的な値で復元）
                                            obj.set({
                                                left: objData.left,
                                                top: objData.top,
                                                scaleX: objData.scaleX,
                                                scaleY: objData.scaleY,
                                                angle: objData.angle,
                                                flipX: objData.flipX,
                                                flipY: objData.flipY,
                                                originX: objData.originX || 'center',
                                                originY: objData.originY || 'center',
                                                materialData: {
                                                    ...objData.materialData,
                                                    zIndex: objData.zIndex
                                                }
                                            });
                                        } else if (objData.transform) {
                                            // layers形式（変換計算が必要な場合）
                                            const transform = objData.transform;
                                            const originalCenter = objData.originalCenter || { x: obj.width / 2, y: obj.height / 2 };
                                            const scaleX = transform.scaleX || transform.scale;
                                            const scaleY = transform.scaleY || transform.scale;
                                            const centerOffsetX = originalCenter.x * scaleX;
                                            const centerOffsetY = originalCenter.y * scaleY;
                                            
                                            obj.set({
                                                left: transform.x + centerOffsetX,
                                                top: transform.y + centerOffsetY,
                                                scaleX: transform.flipHorizontal ? -scaleX : scaleX,
                                                scaleY: transform.flipVertical ? -scaleY : scaleY,
                                                angle: transform.rotation,
                                                originX: 'center',
                                                originY: 'center',
                                                materialData: {
                                                    ...(objData.materialData || {
                                                        id: objData.materialId,
                                                        title: objData.title,
                                                        svg_path: svgPath
                                                    }),
                                                    zIndex: objData.zIndex
                                                }
                                            });
                                        }
                                        
                                        // 読み込んだオブジェクトを配列に格納（順序を保持）
                                        loadedObjects[index] = obj;
                                        loadedCount++;
                                        
                                        // 全てのオブジェクトが読み込まれたら、順序通りにキャンバスに追加
                                        if (loadedCount === totalCount) {
                                            setTimeout(() => {
                                                // 順序通りにキャンバスに追加
                                                loadedObjects.forEach(loadedObj => {
                                                    if (loadedObj) {
                                                        canvas.add(loadedObj);
                                                    }
                                                });
                                                canvas.renderAll();
                                                fitCanvasToContainer();
                                                updateLayerList();
                                                console.log('全ての素材を復元しました');
                                            }, 100);
                                        }
                                    });
                                })
                                .catch(error => {
                                    console.error('素材復元エラー:', error);
                                    loadedObjects[index] = null; // エラーの場合はnullを格納
                                    loadedCount++;
                                    if (loadedCount === totalCount) {
                                        setTimeout(() => {
                                            // 順序通りにキャンバスに追加（null以外）
                                            loadedObjects.forEach(loadedObj => {
                                                if (loadedObj) {
                                                    canvas.add(loadedObj);
                                                }
                                            });
                                            canvas.renderAll();
                                            fitCanvasToContainer();
                                            updateLayerList();
                                        }, 100);
                                    }
                                });
                        } else {
                            console.warn('svgPathが見つかりません:', objData);
                            loadedCount++;
                        }
                    });
                }

            } catch (error) {
                console.error('復元エラー:', error);
            }
        }

        // 初期化
        function initCanvas() {
            canvas = new fabric.Canvas('canvas', {
                width: 800,
                height: 800,
                backgroundColor: 'transparent'
            });
            
            // 原寸サイズを設定
            originalCanvasWidth = 800;
            originalCanvasHeight = 800;

            canvas.on('selection:created', function(e) {
                selectedObject = e.selected[0];
                updateLayerList();
                updateColorPickers();
            });

            canvas.on('selection:updated', function(e) {
                selectedObject = e.selected[0];
                updateLayerList();
                updateColorPickers();
            });

            canvas.on('selection:cleared', function() {
                selectedObject = null;
                updateLayerList();
                updateColorPickers();
            });

            canvas.on('object:added', function() {
                updateLayerList();
                saveToLocalStorage();
            });
            
            canvas.on('object:removed', function() {
                updateLayerList();
                saveToLocalStorage();
            });
            
            canvas.on('object:modified', function() {
                updateLayerList();
                saveToLocalStorage();
            });

            updateLayerList();
            
            // 通常モードの場合は保存データを復元
            loadFromLocalStorage();
            
            // 表示エリアに収まるように調整
            fitCanvasToContainer();
            
            // ウィンドウリサイズ時にも調整（デバウンス）
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(fitCanvasToContainer, 150);
            });
        }

        // カテゴリの開閉
        function toggleCategory(element) {
            const grid = element.nextElementSibling;
            const isVisible = grid.style.display !== 'none';
            grid.style.display = isVisible ? 'none' : 'grid';
            element.querySelector('span').textContent = isVisible ? '▼' : '▲';
        }

        // 推奨アイテム（素材または作品）を表示（非同期対応）
        async function displaySuggestedItem() {
            // URLパラメーターから取得
            const urlParams = new URLSearchParams(window.location.search);
            const suggestedMaterialId = parseInt(urlParams.get('material_id')) || 0;
            const suggestedArtworkId = parseInt(urlParams.get('artwork_id')) || 0;

            if (!suggestedMaterialId && !suggestedArtworkId) {
                return;
            }

            const section = document.getElementById('suggestedItemSection');
            const container = document.getElementById('suggestedItemContainer');

            if (suggestedMaterialId) {
                // おすすめ素材から探す（なければAPI取得）
                let material = recommendedMaterialsArray.find(m => m.id === suggestedMaterialId);
                
                if (!material) {
                    // APIから単体取得
                    try {
                        const response = await fetch('/api/get-materials.php?ids=' + suggestedMaterialId);
                        const materials = await response.json();
                        material = materials[0];
                    } catch (error) {
                        console.error('素材取得エラー:', error);
                        return;
                    }
                }
                
                if (material) {
                    const thumbPath = material.webp_small_path;
                    const isRemoteThumb = thumbPath.startsWith('http://') || thumbPath.startsWith('https://');
                    const finalThumbUrl = isRemoteThumb ? thumbPath : '/' + thumbPath;

                    container.innerHTML = `
                        <div class="suggested-item" onclick="addSuggestedMaterialById(${material.id})">
                            <div style="background-color: ${material.structured_bg_color || '#f0f0f0'}; padding: 12px; border-radius: 8px;">
                                <img src="${finalThumbUrl}" alt="${material.title || ''}">
                            </div>
                            <div class="suggested-item-label">${material.title || '素材'}</div>
                        </div>
                    `;
                    section.style.display = 'block';
                }
            } else if (suggestedArtworkId) {
                // 作品をAPI取得（ID指定で直接取得）
                let artwork = null;
                try {
                    const response = await fetch('/api/get-artworks.php?artwork_id=' + suggestedArtworkId);
                    if (response.ok) {
                        artwork = await response.json();
                    }
                } catch (error) {
                    console.error('作品取得エラー:', error);
                    return;
                }
                
                if (artwork && artwork.webp_path) {
                    const thumbPath = artwork.webp_path;
                    const isRemoteThumb = thumbPath.startsWith('http://') || thumbPath.startsWith('https://');
                    const finalThumbUrl = isRemoteThumb ? thumbPath : '/' + thumbPath;

                    container.innerHTML = `
                        <div class="suggested-item" onclick="addSuggestedArtworkById(${artwork.id})">
                            <img src="${finalThumbUrl}" alt="${artwork.title || '作品'}">
                            <div class="suggested-item-label">${artwork.title || '作品'}</div>
                        </div>
                    `;
                    section.style.display = 'block';
                }
            }
        }

        // 推奨素材をキャンバスに追加（ID指定）
        async function addSuggestedMaterialById(materialId) {
            let material = recommendedMaterialsArray.find(m => m.id === materialId);
            
            if (!material) {
                // APIから取得
                try {
                    const response = await fetch('/api/get-materials.php?ids=' + materialId);
                    const materials = await response.json();
                    material = materials[0];
                } catch (error) {
                    console.error('素材取得エラー:', error);
                    return;
                }
            }
            
            if (material) {
                addMaterial(material);
                // セクションを非表示にしてURLパラメーターを削除
                document.getElementById('suggestedItemSection').style.display = 'none';
                const url = new URL(window.location);
                url.search = '';
                window.history.replaceState({}, '', url);
            }
        }

        // 推奨作品をキャンバスに展開（ID指定）
        async function addSuggestedArtworkById(artworkId) {
            let artwork = null;
            try {
                const response = await fetch('/api/get-artworks.php?artwork_id=' + artworkId);
                if (response.ok) {
                    artwork = await response.json();
                }
            } catch (error) {
                console.error('作品取得エラー:', error);
                return;
            }
            
            if (artwork) {
                await loadArtworkToCanvas(artwork);
                // セクションを非表示にしてURLパラメーターを削除
                document.getElementById('suggestedItemSection').style.display = 'none';
                const url = new URL(window.location);
                url.search = '';
                window.history.replaceState({}, '', url);
            }
        }

        // 素材を追加
        function addMaterial(material) {
            // SVGパスをR2 URL対応
            let svgUrl = material.svg_path;
            if (svgUrl && !svgUrl.startsWith('http://') && !svgUrl.startsWith('https://')) {
                svgUrl = '/' + svgUrl;
            }
            
            // SVGファイルを直接読み込み
            fetch(svgUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(svgText => {
                    fabric.loadSVGFromString(svgText, function(objects, options) {
                        const obj = fabric.util.groupSVGElements(objects, options);
                        
                        // 原寸サイズを基準に中心位置を計算（ズームの影響を受けない）
                        obj.set({
                            left: originalCanvasWidth / 2,
                            top: originalCanvasHeight / 2,
                            originX: 'center',
                            originY: 'center',
                            materialData: {
                                id: material.id,
                                title: material.title,
                                svg_path: material.svg_path,
                                webp_small_path: material.webp_small_path,
                                structured_bg_color: material.structured_bg_color
                            }
                        });
                        
                        // キャンバスサイズに応じて自動スケール（最大200px）
                        const scale = Math.min(200 / obj.width, 200 / obj.height, 1);
                        obj.scale(scale);
                        
                        canvas.add(obj);
                        canvas.setActiveObject(obj);
                        canvas.renderAll();
                        
                        console.log('素材追加完了:', material.title);
                    });
                })
                .catch(error => {
                    console.error('SVG読み込みエラー:', error);
                    alert(`素材の読み込みに失敗しました: ${error.message}`);
                });
        }

        // キャンバスサイズ変更
        function handleCanvasSizeChange() {
            const size = document.getElementById('canvasSize').value;
            const customInputs = document.getElementById('customSizeInputs');
            
            if (size === 'custom') {
                customInputs.classList.add('active');
            } else {
                customInputs.classList.remove('active');
                applyCanvasSize();
            }
        }

        function applyCustomSize() {
            const width = parseInt(document.getElementById('customWidth').value);
            const height = parseInt(document.getElementById('customHeight').value);
            
            if (!width || !height || width < 100 || height < 100 || width > 5000 || height > 5000) {
                alert('有効なサイズを入力してください（100-5000px）');
                return;
            }
            
            // 原寸サイズを更新
            originalCanvasWidth = width;
            originalCanvasHeight = height;
            
            // 実際のキャンバスサイズを設定（データサイズ）
            canvas.setDimensions({ width, height });
            
            // 表示エリアに収まるようにスケーリング
            fitCanvasToContainer();
            
            canvas.renderAll();
            
            // サイズ変更を保存
            saveToLocalStorage();
        }

        function applyCanvasSize() {
            const size = document.getElementById('canvasSize').value;
            const [width, height] = size.split('x').map(Number);
            
            // 原寸サイズを更新
            originalCanvasWidth = width;
            originalCanvasHeight = height;
            
            // 実際のキャンバスサイズを設定（データサイズ）
            canvas.setDimensions({ width, height });
            
            // 表示エリアに収まるようにスケーリング
            fitCanvasToContainer();
            
            canvas.renderAll();
            
            // サイズ変更を保存
            saveToLocalStorage();
        }
        
        // キャンバスを表示エリアに収める
        let resizeTimeout;
        let lastWrapperHeight = null;

        function fitCanvasToContainer(force = false) {
            const wrapper = document.querySelector('.canvas-wrapper');
            const maxWidth = wrapper.clientWidth - 40; // padding考慮
            
            // スマホの場合、高さの微小な変更（アドレスバー）を無視（ただしforceがtrueの場合は実行）
            const currentHeight = wrapper.clientHeight;
            if (!force && window.innerWidth <= 768) {
                if (lastWrapperHeight !== null && Math.abs(currentHeight - lastWrapperHeight) < 50) {
                    // 高さの変化が50px未満の場合は再計算しない
                    return;
                }
            }
            lastWrapperHeight = currentHeight;
            
            const maxHeight = currentHeight - 40;
            
            // 元の（データ）サイズを使用
            const canvasWidth = originalCanvasWidth;
            const canvasHeight = originalCanvasHeight;
            
            // スケール比率を計算
            const scaleX = maxWidth / canvasWidth;
            const scaleY = maxHeight / canvasHeight;
            const scale = Math.min(scaleX, scaleY, 1); // 1を超えない（拡大しない）
            
            // ズームレベルを設定
            canvas.setZoom(scale);
            
            // 表示サイズを設定
            canvas.setWidth(canvasWidth * scale);
            canvas.setHeight(canvasHeight * scale);
        }

        // 色変更
        function updateColorPickers() {
            const container = document.getElementById('colorPickerContainer');
            const randomColorBtn = document.getElementById('randomColorBtn');
            
            if (!selectedObject) {
                container.innerHTML = '<p style="color: #999; font-size: 0.85rem; text-align: center; padding: 20px 0;">素材を選択してください</p>';
                if (randomColorBtn) randomColorBtn.style.display = 'none';
                return;
            }
            
            // 素材から色を抽出
            const colors = extractColors(selectedObject);
            
            if (colors.length === 0) {
                container.innerHTML = '<p style="color: #999; font-size: 0.85rem; text-align: center; padding: 20px 0;">色変更できません</p>';
                if (randomColorBtn) randomColorBtn.style.display = 'none';
                return;
            }
            
            // ランダムカラーボタンを表示
            if (randomColorBtn) randomColorBtn.style.display = 'block';
            
            container.innerHTML = '';
            colors.forEach((colorInfo, index) => {
                const colorItem = document.createElement('div');
                colorItem.style.cssText = 'display: flex; align-items: center; gap: 12px; margin-bottom: 12px;';
                
                const preview = document.createElement('div');
                preview.style.cssText = `width: 40px; height: 40px; border-radius: 8px; background-color: ${colorInfo.color}; border: 2px solid #ddd;`;
                
                const picker = document.createElement('input');
                picker.type = 'color';
                picker.className = 'color-picker';
                picker.value = colorInfo.color;
                picker.style.flex = '1';
                picker.dataset.colorIndex = index;
                
                let currentColor = colorInfo.color; // 現在の色を追跡
                
                picker.addEventListener('input', function() {
                    changeObjectColor(currentColor, this.value);
                    preview.style.backgroundColor = this.value;
                    currentColor = this.value; // 色を更新後、現在の色を更新
                    saveToLocalStorage();
                });
                
                colorItem.appendChild(preview);
                colorItem.appendChild(picker);
                container.appendChild(colorItem);
            });
        }
        
        function extractColors(obj) {
            const colors = new Map();
            
            if (obj._objects) {
                // グループオブジェクトの場合
                obj._objects.forEach(child => {
                    // fillを抽出
                    if (child.fill && child.fill !== 'transparent' && typeof child.fill === 'string') {
                        const normalizedColor = normalizeColor(child.fill);
                        if (normalizedColor) {
                            const existing = colors.get(normalizedColor);
                            if (existing) {
                                existing.objects.push(child);
                                if (!existing.properties.includes('fill')) existing.properties.push('fill');
                            } else {
                                colors.set(normalizedColor, { color: normalizedColor, objects: [child], properties: ['fill'] });
                            }
                        }
                    }
                    // strokeを抽出
                    if (child.stroke && child.stroke !== 'transparent' && typeof child.stroke === 'string') {
                        const normalizedColor = normalizeColor(child.stroke);
                        if (normalizedColor) {
                            const existing = colors.get(normalizedColor);
                            if (existing) {
                                if (!existing.objects.includes(child)) existing.objects.push(child);
                                if (!existing.properties.includes('stroke')) existing.properties.push('stroke');
                            } else {
                                colors.set(normalizedColor, { color: normalizedColor, objects: [child], properties: ['stroke'] });
                            }
                        }
                    }
                });
            } else {
                // 単一オブジェクトの場合
                const properties = [];
                if (obj.fill && obj.fill !== 'transparent' && typeof obj.fill === 'string') {
                    properties.push('fill');
                }
                if (obj.stroke && obj.stroke !== 'transparent' && typeof obj.stroke === 'string') {
                    properties.push('stroke');
                }
                if (properties.length > 0) {
                    const normalizedColor = normalizeColor(obj.fill || obj.stroke);
                    if (normalizedColor) {
                        colors.set(normalizedColor, { color: normalizedColor, objects: [obj], properties: properties });
                    }
                }
            }
            
            return Array.from(colors.values());
        }
        
        function normalizeColor(color) {
            // hsl形式を16進数に変換
            if (color.startsWith('hsl')) {
                const match = color.match(/hsl\((\d+),\s*([\d.]+)%,\s*([\d.]+)%\)/);
                if (match) {
                    const h = parseInt(match[1]);
                    const s = parseFloat(match[2]) / 100;
                    const l = parseFloat(match[3]) / 100;
                    
                    // HSLをRGBに変換
                    const c = (1 - Math.abs(2 * l - 1)) * s;
                    const x = c * (1 - Math.abs((h / 60) % 2 - 1));
                    const m = l - c / 2;
                    let r = 0, g = 0, b = 0;
                    
                    if (h >= 0 && h < 60) {
                        r = c; g = x; b = 0;
                    } else if (h >= 60 && h < 120) {
                        r = x; g = c; b = 0;
                    } else if (h >= 120 && h < 180) {
                        r = 0; g = c; b = x;
                    } else if (h >= 180 && h < 240) {
                        r = 0; g = x; b = c;
                    } else if (h >= 240 && h < 300) {
                        r = x; g = 0; b = c;
                    } else if (h >= 300 && h < 360) {
                        r = c; g = 0; b = x;
                    }
                    
                    const rHex = Math.round((r + m) * 255).toString(16).padStart(2, '0');
                    const gHex = Math.round((g + m) * 255).toString(16).padStart(2, '0');
                    const bHex = Math.round((b + m) * 255).toString(16).padStart(2, '0');
                    return `#${rHex}${gHex}${bHex}`;
                }
            }
            // rgb形式を16進数に変換
            if (color.startsWith('rgb')) {
                const match = color.match(/\d+/g);
                if (match && match.length >= 3) {
                    const r = parseInt(match[0]).toString(16).padStart(2, '0');
                    const g = parseInt(match[1]).toString(16).padStart(2, '0');
                    const b = parseInt(match[2]).toString(16).padStart(2, '0');
                    return `#${r}${g}${b}`;
                }
            }
            // 既に16進数の場合はそのまま
            if (color.startsWith('#')) {
                return color.toLowerCase();
            }
            return color;
        }
        
        function changeObjectColor(oldColor, newColor) {
            if (!selectedObject) return;
            
            const normalizedOld = normalizeColor(oldColor);
            const normalizedNew = normalizeColor(newColor);
            
            if (selectedObject._objects) {
                selectedObject._objects.forEach(obj => {
                    // fillの変更
                    if (obj.fill && normalizeColor(obj.fill) === normalizedOld) {
                        obj.set('fill', normalizedNew);
                        obj.dirty = true;
                    }
                    // strokeの変更
                    if (obj.stroke && normalizeColor(obj.stroke) === normalizedOld) {
                        obj.set('stroke', normalizedNew);
                        obj.dirty = true;
                    }
                });
                selectedObject.dirty = true;
            } else {
                // fillの変更
                if (selectedObject.fill && normalizeColor(selectedObject.fill) === normalizedOld) {
                    selectedObject.set('fill', normalizedNew);
                    selectedObject.dirty = true;
                }
                // strokeの変更
                if (selectedObject.stroke && normalizeColor(selectedObject.stroke) === normalizedOld) {
                    selectedObject.set('stroke', normalizedNew);
                    selectedObject.dirty = true;
                }
            }
            
            canvas.renderAll();
            saveToLocalStorage();
        }

        // ランダムなソフトカラーを生成
        function randomSoftColor() {
            const h = Math.floor(Math.random() * 360);
            const s = 40 + Math.random() * 20; // 40–60%
            const l = 65 + Math.random() * 15; // 65–80%
            return `hsl(${h}, ${s}%, ${l}%)`;
        }

        // ランダムカラーを適用
        function applyRandomColors() {
            if (!selectedObject) return;
            
            const colors = extractColors(selectedObject);
            
            colors.forEach(colorInfo => {
                const newColor = randomSoftColor();
                changeObjectColor(colorInfo.color, newColor);
            });
            
            // カラーピッカーを更新
            updateColorPickers();
            saveToLocalStorage();
        }

        // 背景タイプの切り替え
        function toggleBackgroundType() {
            const bgType = document.querySelector('input[name="bgType"]:checked').value;
            const bgColorPicker = document.getElementById('bgColorPicker');
            
            if (bgType === 'transparent') {
                isTransparentBg = true;
                canvas.backgroundColor = 'transparent';
                bgColorPicker.disabled = true;
            } else {
                isTransparentBg = false;
                bgColorPicker.disabled = false;
                canvas.backgroundColor = bgColorPicker.value;
            }
            
            canvas.renderAll();
            saveToLocalStorage();
        }

        // 背景色の変更
        function changeBackgroundColor() {
            if (!isTransparentBg) {
                const color = document.getElementById('bgColorPicker').value;
                canvas.backgroundColor = color;
                canvas.renderAll();
                saveToLocalStorage();
            }
        }

        // レイヤーリスト更新
        function updateLayerList() {
            const layerList = document.getElementById('layerList');
            layerList.innerHTML = '';
            
            const objects = canvas.getObjects();
            // 逆順で表示（リストの一番上が最前面）
            const reversedObjects = [...objects].reverse();
            
            reversedObjects.forEach((obj, displayIndex) => {
                const actualIndex = objects.length - 1 - displayIndex; // 実際のインデックス
                const isSelected = obj === selectedObject;
                
                const layerItem = document.createElement('div');
                layerItem.className = 'layer-item' + (isSelected ? ' selected' : '');
                layerItem.draggable = true;
                layerItem.dataset.index = actualIndex;
                
                // ドラッグイベント
                layerItem.addEventListener('dragstart', handleDragStart);
                layerItem.addEventListener('dragover', handleDragOver);
                layerItem.addEventListener('drop', handleDrop);
                layerItem.addEventListener('dragend', handleDragEnd);
                layerItem.addEventListener('dragleave', handleDragLeave);
                
                // レイヤーアイテムのクリック/タップイベント（移動モード時の処理）
                const handleLayerClick = (e) => {
                    if (movingLayerIndex !== null && movingLayerIndex !== actualIndex) {
                        // 移動モード中で、別のレイヤーをクリック
                        moveLayerToPosition(movingLayerIndex, actualIndex);
                        movingLayerIndex = null;
                        updateLayerList();
                    } else if (movingLayerIndex === null) {
                        // 通常のレイヤー選択
                        selectLayer(actualIndex);
                    }
                };
                layerItem.addEventListener('click', handleLayerClick);
                
                const materialData = obj.materialData || {};
                const bgColor = materialData.structured_bg_color || '#f0f0f0';
                
                // サムネイル画像のパスを取得（R2 URL対応）
                let thumbnailPath = materialData.webp_small_path || materialData.image_path || '';
                let thumbnailUrl = '';
                
                // R2 URLかローカルパスか判定
                if (thumbnailPath) {
                    if (thumbnailPath.startsWith('http://') || thumbnailPath.startsWith('https://')) {
                        // R2 URLの場合はそのまま使用
                        thumbnailUrl = thumbnailPath;
                    } else {
                        // ローカルパスの場合
                        thumbnailUrl = thumbnailPath.startsWith('/') ? thumbnailPath : '/' + thumbnailPath;
                    }
                }
                
                const isMovingSource = movingLayerIndex === actualIndex;
                const isMovingTarget = movingLayerIndex !== null && movingLayerIndex !== actualIndex;
                
                if (isMovingSource) {
                    layerItem.classList.add('moving-source');
                }
                if (isMovingTarget) {
                    layerItem.classList.add('moving-target');
                }
                
                layerItem.innerHTML = `
                    <div class="drag-handle">
                        <div class="drag-dot"></div>
                        <div class="drag-dot"></div>
                        <div class="drag-dot"></div>
                        <div class="drag-dot"></div>
                    </div>
                    <div class="layer-info">
                        <div class="layer-preview" style="background-color: ${bgColor}">
                            ${thumbnailUrl ? `<img src="${thumbnailUrl}" alt="${materialData.title || ''}" onerror="console.error('Image load failed:', this.src)">` : `<div style="color: #999; font-size: 10px;">No Image</div>`}
                        </div>
                    </div>
                    <div class="layer-actions">
                        <button class="layer-action-btn duplicate">Duplicate</button>
                        <button class="layer-action-btn delete">Delete</button>
                    </div>
                `;
                
                // ドラッグハンドルのクリック/タップイベント
                const dragHandle = layerItem.querySelector('.drag-handle');
                const handleDragHandleClick = (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    toggleMoveMode(actualIndex);
                };
                dragHandle.addEventListener('click', handleDragHandleClick);
                
                // 複製ボタンのイベント
                const duplicateBtn = layerItem.querySelector('.layer-action-btn.duplicate');
                const handleDuplicateClick = (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    duplicateLayer(actualIndex);
                };
                duplicateBtn.addEventListener('click', handleDuplicateClick);
                
                // 削除ボタンのイベント
                const deleteBtn = layerItem.querySelector('.layer-action-btn.delete');
                const handleDeleteClick = (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    deleteLayer(actualIndex);
                };
                deleteBtn.addEventListener('click', handleDeleteClick);
                
                layerList.appendChild(layerItem);
            });
        }

        let draggedIndex = null;
        let movingLayerIndex = null; // スマホ用移動モード

        // スマホ用：移動モードの切り替え
        function toggleMoveMode(index) {
            if (movingLayerIndex === index) {
                // 同じレイヤーをクリック → キャンセル
                movingLayerIndex = null;
            } else if (movingLayerIndex !== null) {
                // 移動モード中に別のドラッグハンドルをクリック → 移動実行
                moveLayerToPosition(movingLayerIndex, index);
                movingLayerIndex = null;
            } else {
                // 移動モード開始
                movingLayerIndex = index;
            }
            updateLayerList();
        }

        function handleDragStart(e) {
            draggedIndex = parseInt(e.currentTarget.dataset.index);
            e.currentTarget.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            const target = e.currentTarget;
            if (!target.classList.contains('dragging')) {
                target.classList.add('drag-over');
            }
        }

        function handleDragLeave(e) {
            e.currentTarget.classList.remove('drag-over');
        }

        function handleDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropIndex = parseInt(e.currentTarget.dataset.index);
            e.currentTarget.classList.remove('drag-over');
            
            if (draggedIndex !== null && draggedIndex !== dropIndex) {
                moveLayerToPosition(draggedIndex, dropIndex);
            }
        }

        function handleDragEnd(e) {
            e.currentTarget.classList.remove('dragging');
            document.querySelectorAll('.layer-item').forEach(item => {
                item.classList.remove('drag-over');
            });
            draggedIndex = null;
        }

        function moveLayerToPosition(fromIndex, toIndex) {
            const objects = canvas.getObjects();
            const movedObject = objects[fromIndex];
            
            // オブジェクトを削除して新しい位置に挿入
            canvas.remove(movedObject);
            canvas.insertAt(movedObject, toIndex);
            
            canvas.renderAll();
            updateLayerList();
            saveToLocalStorage();
        }

        // レイヤー選択
        function selectLayer(index) {
            const obj = canvas.item(index);
            canvas.setActiveObject(obj);
            canvas.renderAll();
        }

        // レイヤー複製
        function duplicateLayer(index) {
            const obj = canvas.item(index);
            if (!obj) return;
            
            // オブジェクトを複製
            obj.clone((clonedObj) => {
                // 少しずらして配置
                clonedObj.set({
                    left: clonedObj.left + 20,
                    top: clonedObj.top + 20
                });
                
                // materialDataも複製
                if (obj.materialData) {
                    clonedObj.materialData = JSON.parse(JSON.stringify(obj.materialData));
                }
                
                // 元のレイヤーの1つ上の階層に挿入
                canvas.insertAt(clonedObj, index + 1);
                canvas.setActiveObject(clonedObj);
                canvas.renderAll();
                
                // レイヤーリストを更新
                updateLayerList();
                saveToLocalStorage();
            });
        }

        // レイヤー削除
        function deleteLayer(index) {
            const obj = canvas.item(index);
            canvas.remove(obj);
            canvas.renderAll();
        }

        // 全削除
        function clearAll() {
            if (confirm('すべての素材を削除しますか？')) {
                canvas.clear();
                
                // 背景を再設定
                if (isTransparentBg) {
                    canvas.backgroundColor = 'transparent';
                } else {
                    canvas.backgroundColor = document.getElementById('bgColorPicker').value;
                }
                
                canvas.renderAll();
                saveToLocalStorage();
            }
        }

        // PNGダウンロード
        function downloadImage() {
            canvas.discardActiveObject();
            canvas.renderAll();
            
            // 直接ダウンロードを実行
            executeDownload();
        }

        // SVGダウンロード
        function downloadSVG() {
            canvas.discardActiveObject();
            canvas.renderAll();
            
            // SVGとしてエクスポート
            const svgData = canvas.toSVG({
                width: originalCanvasWidth,
                height: originalCanvasHeight,
                viewBox: {
                    x: 0,
                    y: 0,
                    width: originalCanvasWidth,
                    height: originalCanvasHeight
                }
            });
            
            // SVGデータをBlobに変換
            const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            
            // ダウンロードリンクを作成
            const link = document.createElement('a');
            link.href = url;
            link.download = `marutto-art-${originalCanvasWidth}x${originalCanvasHeight}-${Date.now()}.svg`;
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            URL.revokeObjectURL(url);
        }
        
        // ダウンロード実行
        function executeDownload() {
            // 現在の表示状態を保存
            const currentZoom = canvas.getZoom();
            const currentWidth = canvas.width;
            const currentHeight = canvas.height;
            
            // 元のサイズに戻して画像生成
            canvas.setZoom(1);
            canvas.setDimensions({ 
                width: originalCanvasWidth, 
                height: originalCanvasHeight 
            });
            canvas.renderAll();
            
            // レンダリング完了を待つ
            requestAnimationFrame(() => {
                // Fabric.jsの内部Canvas要素を取得（ネイティブのHTMLCanvasElement）
                const nativeCanvas = canvas.lowerCanvasEl;
                
                // toBlobを使用
                nativeCanvas.toBlob(function(blob) {
                    // 表示を戻す
                    canvas.setDimensions({ width: currentWidth, height: currentHeight });
                    canvas.setZoom(currentZoom);
                    canvas.renderAll();
                    
                    if (blob) {
                        const url = URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = `marutto-art-${originalCanvasWidth}x${originalCanvasHeight}-${Date.now()}.png`;
                        
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        URL.revokeObjectURL(url);
                    } else {
                        showErrorModal('PNG変換に失敗しました');
                    }
                }, 'image/png');
            });
        }
        
        // ブラウザで表示
        function displayInBrowser() {
            // 現在の表示状態を保存
            const currentZoom = canvas.getZoom();
            const currentWidth = canvas.width;
            const currentHeight = canvas.height;
            
            // 元のサイズに戻して画像生成
            canvas.setZoom(1);
            canvas.setDimensions({ 
                width: originalCanvasWidth, 
                height: originalCanvasHeight 
            });
            canvas.renderAll();
            
            // レンダリング完了を待つ
            requestAnimationFrame(() => {
                // Fabric.jsの内部Canvas要素を取得
                const nativeCanvas = canvas.lowerCanvasEl;
                
                nativeCanvas.toBlob(function(blob) {
                    // 表示を戻す
                    canvas.setDimensions({ width: currentWidth, height: currentHeight });
                    canvas.setZoom(currentZoom);
                    canvas.renderAll();
                    
                    if (blob) {
                        const url = URL.createObjectURL(blob);
                        window.open(url, '_blank');
                        
                        // 少し後にURLを解放
                        setTimeout(() => URL.revokeObjectURL(url), 60000);
                    } else {
                        showErrorModal('PNG変換に失敗しました');
                    }
                }, 'image/png');
            });
        }

        // 作品投稿
        function uploadArtwork() {
            if (canvas.getObjects().length === 0) {
                showErrorModal('素材を追加してから投稿してください。');
                return;
            }
            document.getElementById('confirmModal').classList.add('show');
        }

        function hideConfirmModal() {
            document.getElementById('confirmModal').classList.remove('show');
        }

        let currentArtworkId = null; // ツイート用に作品IDを保持

        function showSuccessModal(artworkId) {
            currentArtworkId = artworkId; // 作品IDを保存
            if (artworkId) {
                // 詳細ページへのリンクを設定
                const link = document.getElementById('artworkDetailLink');
                link.href = '/everyone-work.php?id=' + artworkId;
            }
            document.getElementById('successModal').classList.add('show');
        }

        function hideSuccessModal() {
            document.getElementById('successModal').classList.remove('show');
        }

        function tweetArtwork() {
            let tweetText = 'I made this with marutto.art✨\n\n';
            let url = 'https://marutto.art/';
            
            if (currentArtworkId) {
                url = `https://marutto.art/everyone-work.php?id=${currentArtworkId}`;
            }
            
            const tweetUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(tweetText)}&url=${encodeURIComponent(url)}`;
            window.open(tweetUrl, '_blank', 'width=550,height=420');
        }

        function showErrorModal(message) {
            document.getElementById('errorModalMessage').textContent = message;
            document.getElementById('errorModal').classList.add('show');
        }

        function hideErrorModal() {
            document.getElementById('errorModal').classList.remove('show');
        }

        function showLoadingModal() {
            document.getElementById('loadingModal').classList.add('show');
        }

        function hideLoadingModal() {
            document.getElementById('loadingModal').classList.remove('show');
        }

        function confirmUpload() {
            hideConfirmModal();

            // ローディング表示
            showLoadingModal();

            // 選択を解除
            canvas.discardActiveObject();
            canvas.renderAll();

            // PNG生成（元のサイズで）
            const currentZoom = canvas.getZoom();
            const currentWidth = canvas.width;
            const currentHeight = canvas.height;
            
            canvas.setZoom(1);
            canvas.setDimensions({ 
                width: originalCanvasWidth, 
                height: originalCanvasHeight 
            });
            canvas.renderAll();

            setTimeout(() => {
                // DataURLを生成してBlobに変換
                const dataURL = canvas.toDataURL('image/png');
                
                // DataURLをBlobに変換
                fetch(dataURL)
                    .then(res => res.blob())
                    .then(blob => {
                        // 表示を戻す
                        canvas.setDimensions({ width: currentWidth, height: currentHeight });
                        canvas.setZoom(currentZoom);
                        canvas.renderAll();
                        
                        if (!blob) {
                            hideLoadingModal();
                            showErrorModal('画像の生成に失敗しました');
                            return;
                        }
                    
                    // SVGデータを保存（compose/index.php互換形式）
                    const layers = canvas.getObjects().map(obj => {
                        const centerX = obj.width ? (obj.width / 2) : 0;
                        const centerY = obj.height ? (obj.height / 2) : 0;
                        const absScaleX = Math.abs(obj.scaleX);
                        const absScaleY = Math.abs(obj.scaleY);
                        const scale = (absScaleX + absScaleY) / 2; // 後方互換性のため
                        
                        // 色情報を抽出
                        const colors = [];
                        if (obj.type === 'group' && obj._objects) {
                            obj._objects.forEach((child, index) => {
                                colors.push({
                                    index: index,
                                    fill: child.fill,
                                    stroke: child.stroke
                                });
                            });
                        } else if (obj.fill || obj.stroke) {
                            // 単一オブジェクト（ellipse, path, rectなど）の色情報
                            colors.push({
                                index: 0,
                                fill: obj.fill,
                                stroke: obj.stroke
                            });
                        }
                        
                        return {
                            id: obj.materialData?.id || Math.floor(Math.random() * 10000),
                            type: 'svg',
                            materialId: obj.materialData?.id,
                            title: obj.materialData?.title || '',
                            svgPath: obj.materialData?.svg_path,
                            originalCenter: {
                                x: centerX,
                                y: centerY
                            },
                            transform: {
                                x: obj.left - (centerX * absScaleX),
                                y: obj.top - (centerY * absScaleY),
                                scale: scale,
                                scaleX: absScaleX,
                                scaleY: absScaleY,
                                rotation: obj.angle,
                                flipHorizontal: obj.flipX || obj.scaleX < 0,
                                flipVertical: obj.flipY || obj.scaleY < 0
                            },
                            visible: true,
                            colors: colors
                        };
                    });
                    
                    const svgData = {
                        layers: layers,
                        canvasWidth: originalCanvasWidth,
                        canvasHeight: originalCanvasHeight,
                        backgroundColor: canvas.backgroundColor
                    };
                    
                    // 使用素材IDを抽出して送信
                    const usedMaterialIds = canvas.getObjects()
                        .map(obj => obj.materialData?.id)
                        .filter(id => id)
                        .filter((id, index, self) => self.indexOf(id) === index) // 重複削除
                        .join(',');
                    
                    // WebP版も生成（サムネイル用・軽量化 - 最大幅300px）
                    const maxWidth = 300;
                    const scale = Math.min(1, maxWidth / canvas.width);
                    const tempCanvas = document.createElement('canvas');
                    tempCanvas.width = canvas.width * scale;
                    tempCanvas.height = canvas.height * scale;
                    const tempCtx = tempCanvas.getContext('2d');
                    tempCtx.drawImage(canvas.lowerCanvasEl, 0, 0, tempCanvas.width, tempCanvas.height);
                    
                    tempCanvas.toBlob(function(webpBlob) {
                        if (!webpBlob) {
                            hideLoadingModal();
                            showErrorModal('WebP画像の生成に失敗しました');
                            return;
                        }
                        
                        // R2 presigned URL 方式でアップロード（PNG + WebP）
                        const pngFileName = `custom-artwork-${Date.now()}.png`;
                        const webpFileName = `custom-artwork-${Date.now()}_thumb.webp`;

                        // Step 1: 2つのpresigned URLを取得
                        Promise.all([
                            fetch('/api/get-r2-presigned-url.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    fileName: pngFileName,
                                    fileType: blob.type,
                                    fileSize: blob.size
                                })
                            }),
                            fetch('/api/get-r2-presigned-url.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    fileName: webpFileName,
                                    fileType: 'image/webp',
                                    fileSize: webpBlob.size
                                })
                            })
                        ])
                        .then(responses => Promise.all(responses.map(r => {
                            if (!r.ok) throw new Error(`HTTP ${r.status}`);
                            return r.json();
                        })))
                        .then(([pngPresigned, webpPresigned]) => {
                            if (!pngPresigned.success || !webpPresigned.success) {
                                throw new Error('Presigned URL の取得に失敗しました');
                            }

                            // Step 2: 両方のファイルをR2にアップロード
                            return Promise.all([
                                fetch(pngPresigned.data.presignedUrl, {
                                    method: 'PUT',
                                    headers: { 'Content-Type': blob.type },
                                    body: blob
                                }),
                                fetch(webpPresigned.data.presignedUrl, {
                                    method: 'PUT',
                                    headers: { 'Content-Type': 'image/webp' },
                                    body: webpBlob
                                })
                            ]).then(([pngRes, webpRes]) => {
                                if (!pngRes.ok || !webpRes.ok) {
                                    throw new Error('R2 アップロードに失敗しました');
                                }
                                return {
                                    pngKey: pngPresigned.data.key,
                                    webpKey: webpPresigned.data.key,
                                    uniqueId: pngPresigned.data.uniqueId
                                };
                            });
                        })
                        .then(uploadData => {
                            // Step 3: アップロード完了をサーバーに通知してDB登録
                            return fetch('/api/confirm-r2-upload.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    pngKey: uploadData.pngKey,
                                    webpKey: uploadData.webpKey,
                                    uniqueId: uploadData.uniqueId,
                                    svgData: svgData,
                                    usedMaterialIds: usedMaterialIds
                                })
                            });
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            hideLoadingModal();
                            
                            if (data.success) {
                                showSuccessModal(data.data?.artworkId);
                            } else {
                                showErrorModal('投稿に失敗しました: ' + (data.error || '不明なエラー'));
                            }
                        })
                        .catch(error => {
                            hideLoadingModal();
                            console.error('Upload error:', error);
                            showErrorModal('投稿中にエラーが発生しました: ' + error.message);
                        });
                    }, 'image/webp', 0.85); // WebP品質85%
                })
                .catch(error => {
                    hideLoadingModal();
                    canvas.setDimensions({ width: currentWidth, height: currentHeight });
                    canvas.setZoom(currentZoom);
                    canvas.renderAll();
                    console.error('Blob conversion error:', error);
                    showErrorModal('画像の生成に失敗しました');
                });
            }, 100);
        }



        // ページング関連の変数
        let currentPage = 1;
        let itemsPerPage = 10;
        let currentCategory = 'おすすめ';
        let currentSearchTerm = '';
        let allMaterialsArray = [];
        let recommendedMaterialsArray = [];
        let recentArtworksArray = [];
        let filteredMaterials = [];

        // データの読み込み
        // 素材データをAPIから読み込み
        let materialsCache = {}; // カテゴリごとにキャッシュ
        let artworksCache = null;

        async function loadMaterialsData() {
            // 初回はおすすめ素材のみ読み込み
            try {
                const response = await fetch('/api/get-materials.php?ids=' + RECOMMENDED_MATERIAL_IDS.join(','));
                recommendedMaterialsArray = await response.json();
                materialsCache['おすすめ'] = recommendedMaterialsArray;
            } catch (error) {
                console.error('おすすめ素材の読み込みエラー:', error);
                recommendedMaterialsArray = [];
            }
        }

        // カテゴリ別に素材をAPI取得
        async function loadMaterialsByCategory(category) {
            // キャッシュがあればそれを返す
            if (materialsCache[category]) {
                return materialsCache[category];
            }

            try {
                const response = await fetch('/api/get-materials.php?category=' + encodeURIComponent(category));
                const materials = await response.json();
                materialsCache[category] = materials;
                return materials;
            } catch (error) {
                console.error(`カテゴリ "${category}" の素材読み込みエラー:`, error);
                return [];
            }
        }

        // 作品をAPI取得
        async function loadArtworks() {
            if (artworksCache) {
                return artworksCache;
            }

            try {
                const response = await fetch('/api/get-artworks.php?limit=5');
                artworksCache = await response.json();
                recentArtworksArray = artworksCache;
                return artworksCache;
            } catch (error) {
                console.error('作品の読み込みエラー:', error);
                return [];
            }
        }

        // 素材を表示（非同期対応）
        async function renderMaterials() {
            // 作品カテゴリの場合は専用処理
            if (currentCategory === '作品') {
                await renderArtworks();
                return;
            }

            // ローディング表示
            const grid = document.getElementById('materialGrid');
            grid.innerHTML = '<div style="text-align: center; padding: 40px; color: #999;">読み込み中...</div>';

            // カテゴリに応じてデータ取得
            if (currentSearchTerm) {
                // 検索の場合は全素材から検索
                if (!allMaterialsArray || allMaterialsArray.length === 0) {
                    // 全素材を取得
                    allMaterialsArray = await loadMaterialsByCategory('all');
                }
                filteredMaterials = filterMaterialsBySearch(currentSearchTerm);
            } else if (currentCategory === 'おすすめ') {
                filteredMaterials = recommendedMaterialsArray;
            } else if (currentCategory === 'all') {
                // 全素材を取得
                filteredMaterials = await loadMaterialsByCategory('all');
                allMaterialsArray = filteredMaterials; // キャッシュ
            } else {
                // カテゴリ別に取得
                filteredMaterials = await loadMaterialsByCategory(currentCategory);
            }

            const totalPages = Math.ceil(filteredMaterials.length / itemsPerPage);
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const materialsToShow = filteredMaterials.slice(startIndex, endIndex);

            // グリッドに表示（DocumentFragment使用で高速化）
            const fragment = document.createDocumentFragment();

            materialsToShow.forEach(material => {
                const thumbPath = material.webp_small_path;
                const isRemoteThumb = thumbPath.startsWith('http://') || thumbPath.startsWith('https://');
                const finalThumbUrl = isRemoteThumb ? thumbPath : '/' + thumbPath;

                const div = document.createElement('div');
                div.className = 'material-item';
                div.style.backgroundColor = material.structured_bg_color || '#ffffff';
                div.onclick = () => addMaterial(material);

                const img = document.createElement('img');
                img.src = finalThumbUrl;
                img.alt = material.title;
                img.loading = 'lazy';

                div.appendChild(img);
                fragment.appendChild(div);
            });

            // 一度に追加してリフローを最小化
            grid.innerHTML = '';
            grid.appendChild(fragment);

            // ページネーション情報を更新
            updatePagination(totalPages);
        }

        // 作品専用のレンダリング関数（非同期対応）
        async function renderArtworks() {
            const grid = document.getElementById('materialGrid');
            grid.innerHTML = '<div style="text-align: center; padding: 40px; color: #999;">読み込み中...</div>';

            // 作品データを取得
            const artworks = await loadArtworks();

            const fragment = document.createDocumentFragment();

            artworks.forEach(artwork => {
                const thumbPath = artwork.webp_path;
                const isRemoteThumb = thumbPath.startsWith('http://') || thumbPath.startsWith('https://');
                const finalThumbUrl = isRemoteThumb ? thumbPath : '/' + thumbPath;

                const div = document.createElement('div');
                div.className = 'material-item artwork-item';
                div.onclick = async () => await loadArtworkToCanvas(artwork);

                const img = document.createElement('img');
                img.src = finalThumbUrl;
                img.alt = artwork.title || '作品';
                img.loading = 'lazy';

                div.appendChild(img);
                fragment.appendChild(div);
            });

            grid.innerHTML = '';
            grid.appendChild(fragment);

            // 作品は固定5件なのでページネーションは非表示
            updatePagination(1);
        }

        // 作品をキャンバスに展開する関数
        // 作品をキャンバスに展開する関数（非同期対応）
        async function loadArtworkToCanvas(artwork) {
            if (!artwork || !artwork.svg_data) {
                console.error('作品データまたはSVGデータがありません');
                return;
            }

            // LocalStorageをクリアして新規作成モードに
            localStorage.removeItem(STORAGE_KEY);
            canvas.clear();

            try {
                const svgData = JSON.parse(artwork.svg_data);

                // used_material_idsから素材情報を事前に取得
                let materialInfoMap = {};
                if (artwork.used_material_ids) {
                    try {
                        // 既に配列の場合はそのまま、文字列の場合はパースまたは分割
                        let usedMaterialIds;
                        if (Array.isArray(artwork.used_material_ids)) {
                            usedMaterialIds = artwork.used_material_ids;
                        } else if (typeof artwork.used_material_ids === 'string') {
                            // JSON配列形式（"[41,43]"）かカンマ区切り（"41,43"）かを判定
                            const trimmed = artwork.used_material_ids.trim();
                            if (trimmed.startsWith('[')) {
                                // JSON形式
                                usedMaterialIds = JSON.parse(trimmed);
                            } else {
                                // カンマ区切り形式
                                usedMaterialIds = trimmed.split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id));
                            }
                        } else {
                            usedMaterialIds = [];
                        }
                        
                        // APIから素材情報を取得
                        if (Array.isArray(usedMaterialIds) && usedMaterialIds.length > 0) {
                            try {
                                const response = await fetch('/api/get-materials.php?ids=' + usedMaterialIds.join(','));
                                const materials = await response.json();
                                materials.forEach(material => {
                                    materialInfoMap[material.id] = material;
                                });
                            } catch (error) {
                                console.error('素材情報の取得エラー:', error);
                            }
                        }
                    } catch (e) {
                        console.error('used_material_ids の処理エラー:', e, 'データ:', artwork.used_material_ids);
                    }
                }

                // キャンバスサイズを設定
                if (svgData.canvasWidth && svgData.canvasHeight) {
                    originalCanvasWidth = parseInt(svgData.canvasWidth);
                    originalCanvasHeight = parseInt(svgData.canvasHeight);
                    canvas.setDimensions({ 
                        width: originalCanvasWidth, 
                        height: originalCanvasHeight 
                    });
                    
                    // カスタムサイズとして設定
                    document.getElementById('canvasSize').value = 'custom';
                    document.getElementById('customSizeInputs').classList.add('active');
                    document.getElementById('customWidth').value = originalCanvasWidth;
                    document.getElementById('customHeight').value = originalCanvasHeight;
                }

                // 背景色を設定
                if (svgData.backgroundColor) {
                    if (svgData.backgroundColor === 'transparent') {
                        isTransparentBg = true;
                        document.querySelector('input[name="bgType"][value="transparent"]').checked = true;
                        canvas.backgroundColor = 'transparent';
                    } else {
                        isTransparentBg = false;
                        document.querySelector('input[name="bgType"][value="color"]').checked = true;
                        document.getElementById('bgColorPicker').value = svgData.backgroundColor;
                        document.getElementById('bgColorPicker').disabled = false;
                        canvas.backgroundColor = svgData.backgroundColor;
                    }
                }
                
                canvas.renderAll();
                
                // 初期表示を整える（強制実行）
                fitCanvasToContainer(true);

                // 素材を読み込む
                const materialsData = svgData.objects || svgData.materials || svgData.layers || [];
                if (materialsData && Array.isArray(materialsData) && materialsData.length > 0) {
                    let loadedCount = 0;
                    const totalCount = materialsData.length;
                    
                    materialsData.forEach((materialData, index) => {
                        // 素材パスを取得
                        let svgPath = null;
                        if (materialData.materialData && materialData.materialData.svg_path) {
                            svgPath = materialData.materialData.svg_path;
                        } else if (materialData.svgPath) {
                            svgPath = materialData.svgPath;
                        } else if (materialData.svg_path) {
                            svgPath = materialData.svg_path;
                        }
                        
                        if (!svgPath) {
                            loadedCount++;
                            return;
                        }
                        
                        // SVGパスをURL対応
                        let svgUrl = svgPath;
                        if (svgUrl && !svgUrl.startsWith('http://') && !svgUrl.startsWith('https://')) {
                            svgUrl = '/' + svgUrl;
                        }
                        
                        // SVGファイルを読み込み
                        fetch(svgUrl)
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }
                                return response.text();
                            })
                            .then(svgText => {
                                // 色情報がある場合はSVGテキストを編集
                                let modifiedSvgText = svgText;
                                if (materialData.colors && materialData.colors.length > 0) {
                                    const parser = new DOMParser();
                                    const svgDoc = parser.parseFromString(svgText, 'image/svg+xml');
                                    const svgElement = svgDoc.querySelector('svg');
                                    
                                    if (svgElement) {
                                        const drawingElements = svgElement.querySelectorAll('path, rect, circle, ellipse, line, polyline, polygon');
                                        
                                        materialData.colors.forEach(colorData => {
                                            const element = drawingElements[colorData.index];
                                            if (element) {
                                                if (colorData.fill) {
                                                    element.setAttribute('fill', colorData.fill);
                                                }
                                                if (colorData.stroke !== undefined) {
                                                    if (colorData.stroke === null || colorData.stroke === 'none') {
                                                        element.removeAttribute('stroke');
                                                    } else {
                                                        element.setAttribute('stroke', colorData.stroke);
                                                    }
                                                }
                                            }
                                        });
                                        
                                        const serializer = new XMLSerializer();
                                        modifiedSvgText = serializer.serializeToString(svgDoc);
                                    }
                                }
                                
                                fabric.loadSVGFromString(modifiedSvgText, function(objects, options) {
                                    const obj = fabric.util.groupSVGElements(objects, options);
                                    
                                    // 素材IDを取得
                                    const materialId = parseInt(materialData.materialId) || parseInt(materialData.id);
                                    
                                    // 素材情報を取得（materialInfoMapから）
                                    const dbMaterialInfo = materialInfoMap[materialId] || {};
                                    
                                    const fullMaterialData = {
                                        id: materialId || dbMaterialInfo.id,
                                        title: dbMaterialInfo.title || materialData.title || '',
                                        svg_path: dbMaterialInfo.svg_path || svgPath,
                                        webp_small_path: dbMaterialInfo.webp_small_path || '',
                                        structured_bg_color: dbMaterialInfo.structured_bg_color || '#f0f0f0'
                                    };
                                    
                                    // 座標とトランスフォームを復元
                                    const transform = materialData.transform || {};
                                    const posX = transform.x !== undefined ? transform.x : (materialData.x || materialData.left || (originalCanvasWidth / 2));
                                    const posY = transform.y !== undefined ? transform.y : (materialData.y || materialData.top || (originalCanvasHeight / 2));
                    
                                    const scaleXValue = transform.scaleX !== undefined ? Math.abs(transform.scaleX) : 
                                                        (transform.scale !== undefined ? Math.abs(transform.scale) : Math.abs(materialData.scale || materialData.scaleX || 1));
                                    const scaleYValue = transform.scaleY !== undefined ? Math.abs(transform.scaleY) : 
                                                        (transform.scale !== undefined ? Math.abs(transform.scale) : Math.abs(materialData.scale || materialData.scaleY || 1));
                                    const rotation = transform.rotation !== undefined ? transform.rotation : (materialData.rotation || materialData.angle || 0);
                                    
                                    let flipX = transform.flipHorizontal !== undefined ? transform.flipHorizontal : (materialData.flipX || materialData.scaleX < 0 || false);
                                    let flipY = transform.flipVertical !== undefined ? transform.flipVertical : (materialData.flipY || materialData.scaleY < 0 || false);
                                    
                                    const originalCenter = materialData.originalCenter || { x: obj.width / 2, y: obj.height / 2 };
                                    const centerOffsetX = originalCenter.x * scaleXValue;
                                    const centerOffsetY = originalCenter.y * scaleYValue;
                                    
                                    obj.set({
                                        left: posX + centerOffsetX,
                                        top: posY + centerOffsetY,
                                        scaleX: flipX ? -scaleXValue : scaleXValue,
                                        scaleY: flipY ? -scaleYValue : scaleYValue,
                                        angle: rotation,
                                        originX: 'center',
                                        originY: 'center',
                                        materialData: {
                                            ...fullMaterialData,
                                            zIndex: index
                                        }
                                    });
                                    
                                    canvas.add(obj);
                                    
                                    // 色情報を復元
                                    if (materialData.colors && materialData.colors.length > 0 && obj._objects) {
                                        materialData.colors.forEach(colorData => {
                                            const child = obj._objects[colorData.index];
                                            if (child) {
                                                child.fill = colorData.fill;
                                                child.stroke = colorData.stroke;
                                                child.dirty = true;
                                            }
                                        });
                                        obj.dirty = true;
                                    }
                                    
                                    canvas.renderAll();
                                    
                                    loadedCount++;
                                    
                                    if (loadedCount === totalCount) {
                                        setTimeout(() => {
                                            // zIndexでソート
                                            const allObjects = canvas.getObjects();
                                            allObjects.sort((a, b) => {
                                                const zIndexA = a.materialData?.zIndex ?? 999;
                                                const zIndexB = b.materialData?.zIndex ?? 999;
                                                return zIndexA - zIndexB;
                                            });
                                            canvas.remove(...canvas.getObjects());
                                            allObjects.forEach(obj => canvas.add(obj));
                                            
                                            canvas.renderAll();
                                            fitCanvasToContainer(true);
                                            saveToLocalStorage();
                                        }, 200);
                                    }
                                });
                            })
                            .catch(error => {
                                console.error('SVG読み込みエラー:', error);
                                loadedCount++;
                                if (loadedCount === totalCount) {
                                    setTimeout(() => {
                                        canvas.renderAll();
                                        fitCanvasToContainer(true);
                                        saveToLocalStorage();
                                    }, 200);
                                }
                            });
                    });
                } else {
                    // 素材が0個の場合
                    setTimeout(() => {
                        canvas.renderAll();
                        fitCanvasToContainer(true);
                        saveToLocalStorage();
                    }, 100);
                }

            } catch (error) {
                console.error('作品展開エラー:', error);
            }
        }

        // ページネーション更新
        function updatePagination(totalPages) {
            document.getElementById('pageInfo').textContent = `${currentPage} / ${totalPages}`;
            document.getElementById('prevBtn').disabled = currentPage === 1;
            document.getElementById('nextBtn').disabled = currentPage >= totalPages || totalPages === 0;
        }

        // ページ変更（非同期対応）
        async function changePage(delta) {
            const totalPages = Math.ceil(filteredMaterials.length / itemsPerPage);
            const newPage = currentPage + delta;
            
            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                await renderMaterials();
            }
        }

        // 検索フィルター
        function filterMaterialsBySearch(searchInput) {
            const searchTerms = searchInput
                .toLowerCase()
                .replace(/　/g, ' ')
                .split(' ')
                .filter(term => term.trim() !== '');

            if (searchTerms.length === 0) {
                return currentCategory === 'おすすめ' ? recommendedMaterialsArray :
                       currentCategory === 'all' ? allMaterialsArray :
                       materialsCache[currentCategory] || [];
            }

            // 検索時は常に全素材から検索
            const baseMaterials = allMaterialsArray || [];

            return baseMaterials.filter(material => {
                const title = (material.title || '').toLowerCase();
                const searchKeywords = (material.search_keywords || '').toLowerCase();
                return searchTerms.some(term => title.includes(term) || searchKeywords.includes(term));
            });
        }

        // カテゴリフィルター
        async function filterByCategory(categoryName) {
            currentCategory = categoryName;
            currentPage = 1;
            
            const tabs = document.querySelectorAll('.category-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // 作品タブの場合は注意書きを表示、それ以外は非表示
            const artworkNotice = document.getElementById('artworkNotice');
            if (categoryName === '作品') {
                artworkNotice.classList.add('show');
            } else {
                artworkNotice.classList.remove('show');
            }
            
            await renderMaterials();
        }

        // デバウンス関数
        let searchTimeout;
        function debounce(func, wait) {
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(searchTimeout);
                    func(...args);
                };
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(later, wait);
            };
        }

        // 素材検索（デバウンス付き、非同期対応）
        const performSearch = debounce(async function(value) {
            currentSearchTerm = value;
            currentPage = 1;
            await renderMaterials();
        }, 300);

        document.getElementById('materialSearch').addEventListener('input', function(e) {
            performSearch(e.target.value);
        });

        // ページ読み込み時に初期化
        window.addEventListener('load', async function() {
            await loadMaterialsData(); // おすすめ素材のみ読み込み
            await renderMaterials(); // おすすめ素材を表示
            initCanvas();
            await displaySuggestedItem(); // 推奨アイテムを表示
        });
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
