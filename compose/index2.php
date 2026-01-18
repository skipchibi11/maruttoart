<?php
require_once '../config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200);

$pdo = getDB();

// ベクター素材をカテゴリ別に取得
$materialsSql = "
    SELECT m.id, m.title, m.slug, m.svg_path, m.webp_small_path, m.structured_bg_color, c.title as category_name, c.slug as category_slug
    FROM materials m
    LEFT JOIN categories c ON m.category_id = c.id
    WHERE m.svg_path IS NOT NULL AND m.svg_path != ''
    ORDER BY c.title, m.title
";
$stmt = $pdo->prepare($materialsSql);
$stmt->execute();
$allMaterials = $stmt->fetchAll();

// 浮遊素材用にランダムに8件取得
$floatingMaterialsSql = "SELECT m.webp_small_path as image_path, m.structured_bg_color FROM materials m ORDER BY RAND() LIMIT 8";
$floatingMaterialsStmt = $pdo->prepare($floatingMaterialsSql);
$floatingMaterialsStmt->execute();
$floatingMaterials = $floatingMaterialsStmt->fetchAll();

// カテゴリごとにグループ化
$materialsByCategory = [];
foreach ($allMaterials as $material) {
    $categoryName = $material['category_name'] ?? '未分類';
    if (!isset($materialsByCategory[$categoryName])) {
        $materialsByCategory[$categoryName] = [];
    }
    $materialsByCategory[$categoryName][] = $material;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>無料イラスト作成ツール｜marutto.art</title>
    <meta name="description" content="パーツを組み合わせて、オリジナルイラストを無料で作成。ブログ・資料・SNSに使えます。">
    <link rel="icon" href="/favicon.ico">
    
    <?php include __DIR__ . '/../includes/gtm-head.php'; ?>
    
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
        }

        /* メインコンテンツ */
        .main-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
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
        }

        .category-tab:hover,
        .category-tab.active {
            background: var(--primary-color);
            color: white;
        }

        .material-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 12px;
            max-height: 300px;
            overflow-y: auto;
            padding: 8px;
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

        /* キャンバスエリア */
        .canvas-area {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            min-width: 0; /* グリッドのオーバーフロー防止 */
        }

        .canvas-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .canvas-size-selector {
            display: flex;
            gap: 8px;
            align-items: center;
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
    </style>
</head>
<body>
    <?php 
    include __DIR__ . '/../includes/gtm-body.php';
    ?>

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
    $currentPage = 'compose';
    include __DIR__ . '/../includes/header.php';
    ?>
    
    <!-- メインコンテンツ -->
    <div class="main-wrapper">
        <!-- 素材選択セクション -->
        <div class="material-selection">
            <div class="material-header">
                <h2 class="material-title">素材を選ぶ</h2>
                <div class="search-box">
                    <input type="text" id="materialSearch" class="search-input" placeholder="素材を検索...">
                </div>
            </div>
            
            <div class="category-tabs" id="categoryTabs">
                <button class="category-tab active" onclick="filterByCategory('all')">すべて</button>
                <?php foreach ($materialsByCategory as $categoryName => $materials): ?>
                <button class="category-tab" onclick="filterByCategory('<?= h($categoryName) ?>')">
                    <?= h($categoryName) ?>
                </button>
                <?php endforeach; ?>
            </div>
            
            <div class="material-grid" id="materialGrid">
                <?php foreach ($allMaterials as $material): ?>
                <div class="material-item" 
                     data-category="<?= h($material['category_name'] ?? '未分類') ?>"
                     data-title="<?= h(strtolower($material['title'])) ?>"
                     style="background-color: <?= h($material['structured_bg_color'] ?? '#ffffff') ?>"
                     onclick='addMaterial(<?= json_encode($material) ?>)'>
                    <img src="/<?= h($material['webp_small_path']) ?>" 
                         alt="<?= h($material['title']) ?>"
                         loading="lazy">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 作業エリア -->
        <div class="workspace">
            <!-- キャンバスエリア -->
            <div class="canvas-area">
                <div class="canvas-controls">
                    <div class="canvas-size-selector">
                        <label>サイズ:</label>
                        <select id="canvasSize">
                            <option value="800x800">正方形 (800×800)</option>
                            <option value="1200x630">横長 (1200×630)</option>
                            <option value="630x1200">縦長 (630×1200)</option>
                            <option value="1920x1080">HD (1920×1080)</option>
                        </select>
                        <button class="control-btn" onclick="applyCanvasSize()">適用</button>
                    </div>
                    <button class="control-btn" onclick="clearAll()">全削除</button>
                </div>
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

            <!-- レイヤー -->
            <div class="control-section">
                <h3 class="control-title">レイヤー</h3>
                <div class="layer-list" id="layerList">
                    <!-- レイヤーがここに表示されます -->
                </div>
            </div>

            <!-- アクション -->
            <div class="control-section">
                <button class="primary-btn full-width" onclick="downloadImage()">ダウンロード</button>
                <button class="primary-btn full-width" style="margin-top: 12px;" onclick="showPostModal()">投稿する</button>
            </div>
        </div>
    </div>

    <!-- 投稿モーダル -->
    <div id="postModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-title">作品を投稿</h2>
            <form id="postForm">
                <div class="form-group">
                    <label>作品タイトル *</label>
                    <input type="text" id="postTitle" required maxlength="100">
                </div>
                <div class="form-group">
                    <label>ペンネーム *</label>
                    <input type="text" id="postPenName" required maxlength="50">
                </div>
                <div class="form-group">
                    <label>説明</label>
                    <textarea id="postDescription" maxlength="500"></textarea>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="modal-btn secondary" onclick="hidePostModal()">キャンセル</button>
                    <button type="submit" class="modal-btn primary">投稿</button>
                </div>
            </form>
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

        // LocalStorageに保存
        function saveToLocalStorage() {
            try {
                const canvasData = {
                    width: canvas.width,
                    height: canvas.height,
                    backgroundColor: canvas.backgroundColor,
                    isTransparentBg: isTransparentBg,
                    objects: canvas.getObjects().map(obj => ({
                        materialData: obj.materialData,
                        left: obj.left,
                        top: obj.top,
                        scaleX: obj.scaleX,
                        scaleY: obj.scaleY,
                        angle: obj.angle,
                        flipX: obj.flipX,
                        flipY: obj.flipY,
                        originX: obj.originX,
                        originY: obj.originY
                    })),
                    canvasSize: document.getElementById('canvasSize').value
                };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(canvasData));
                console.log('キャンバス状態を保存しました');
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
                }
                
                const width = canvasData.width || 800;
                const height = canvasData.height || 800;
                
                // 原寸サイズを更新
                originalCanvasWidth = width;
                originalCanvasHeight = height;
                
                canvas.setDimensions({ width, height });
                
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

                // オブジェクトを復元
                if (canvasData.objects && canvasData.objects.length > 0) {
                    console.log(`${canvasData.objects.length}個のオブジェクトを復元中...`);
                    
                    canvasData.objects.forEach(objData => {
                        if (objData.materialData && objData.materialData.svg_path) {
                            fetch('/' + objData.materialData.svg_path)
                                .then(response => response.text())
                                .then(svgText => {
                                    fabric.loadSVGFromString(svgText, function(objects, options) {
                                        const obj = fabric.util.groupSVGElements(objects, options);
                                        obj.set({
                                            left: objData.left,
                                            top: objData.top,
                                            scaleX: objData.scaleX,
                                            scaleY: objData.scaleY,
                                            angle: objData.angle,
                                            flipX: objData.flipX,
                                            flipY: objData.flipY,
                                            originX: objData.originX,
                                            originY: objData.originY,
                                            materialData: objData.materialData
                                        });
                                        canvas.add(obj);
                                        canvas.renderAll();
                                    });
                                })
                                .catch(error => {
                                    console.error('素材復元エラー:', error);
                                });
                        }
                    });
                }

                console.log('キャンバス状態を復元しました');
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
            
            // 保存データを復元
            loadFromLocalStorage();
            
            // 表示エリアに収まるように調整
            fitCanvasToContainer();
            
            // ウィンドウリサイズ時にも調整
            window.addEventListener('resize', fitCanvasToContainer);
        }

        // カテゴリの開閉
        function toggleCategory(element) {
            const grid = element.nextElementSibling;
            const isVisible = grid.style.display !== 'none';
            grid.style.display = isVisible ? 'none' : 'grid';
            element.querySelector('span').textContent = isVisible ? '▼' : '▲';
        }

        // 素材を追加
        function addMaterial(material) {
            console.log('素材追加:', material.title);
            
            // SVGファイルを直接読み込み
            fetch('/' + material.svg_path)
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
                            materialData: material
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

        // キャンバスサイズ変更（適用ボタン用）
        function applyCanvasSize() {
            if (!confirm('キャンバスサイズを変更しますか？\n配置した素材の位置は維持されます。')) {
                return;
            }
            
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
        function fitCanvasToContainer() {
            const wrapper = document.querySelector('.canvas-wrapper');
            const maxWidth = wrapper.clientWidth - 40; // padding考慮
            const maxHeight = wrapper.clientHeight - 40;
            
            const canvasWidth = canvas.getWidth();
            const canvasHeight = canvas.getHeight();
            
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
            
            if (!selectedObject) {
                container.innerHTML = '<p style="color: #999; font-size: 0.85rem; text-align: center; padding: 20px 0;">素材を選択してください</p>';
                return;
            }
            
            // 素材から色を抽出
            const colors = extractColors(selectedObject);
            
            if (colors.length === 0) {
                container.innerHTML = '<p style="color: #999; font-size: 0.85rem; text-align: center; padding: 20px 0;">色変更できません</p>';
                return;
            }
            
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
                
                picker.addEventListener('input', function() {
                    changeObjectColor(colorInfo.color, this.value);
                    preview.style.backgroundColor = this.value;
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
                    if (child.fill && child.fill !== 'transparent' && typeof child.fill === 'string') {
                        const normalizedColor = normalizeColor(child.fill);
                        if (normalizedColor) {
                            colors.set(normalizedColor, { color: normalizedColor, objects: (colors.get(normalizedColor)?.objects || []).concat([child]) });
                        }
                    }
                });
            } else if (obj.fill && obj.fill !== 'transparent' && typeof obj.fill === 'string') {
                // 単一オブジェクトの場合
                const normalizedColor = normalizeColor(obj.fill);
                if (normalizedColor) {
                    colors.set(normalizedColor, { color: normalizedColor, objects: [obj] });
                }
            }
            
            return Array.from(colors.values());
        }
        
        function normalizeColor(color) {
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
                    if (obj.fill && normalizeColor(obj.fill) === normalizedOld) {
                        obj.set('fill', normalizedNew);
                    }
                });
            } else if (selectedObject.fill && normalizeColor(selectedObject.fill) === normalizedOld) {
                selectedObject.set('fill', normalizedNew);
            }
            
            canvas.renderAll();
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
                
                layerItem.onclick = () => selectLayer(actualIndex);
                
                const materialData = obj.materialData || {};
                const bgColor = materialData.structured_bg_color || '#f0f0f0';
                
                layerItem.innerHTML = `
                    <div class="drag-handle">
                        <div class="drag-dot"></div>
                        <div class="drag-dot"></div>
                        <div class="drag-dot"></div>
                        <div class="drag-dot"></div>
                    </div>
                    <div class="layer-info">
                        <div class="layer-preview" style="background-color: ${bgColor}">
                            <img src="/${materialData.webp_small_path || ''}" alt="">
                        </div>
                    </div>
                    <div class="layer-actions">
                        <button class="layer-action-btn" onclick="event.stopPropagation(); deleteLayer(${actualIndex})">Delete</button>
                    </div>
                `;
                
                layerList.appendChild(layerItem);
            });
        }

        let draggedIndex = null;

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

        // ダウンロード
        function downloadImage() {
            canvas.discardActiveObject();
            canvas.renderAll();
            
            const dataURL = canvas.toDataURL({
                format: 'png',
                quality: 1
            });
            
            const link = document.createElement('a');
            link.download = 'marutto-art-' + Date.now() + '.png';
            link.href = dataURL;
            link.click();
        }

        // 投稿モーダル
        function showPostModal() {
            if (canvas.getObjects().length === 0) {
                alert('素材を追加してから投稿してください');
                return;
            }
            document.getElementById('postModal').classList.add('show');
        }

        function hidePostModal() {
            document.getElementById('postModal').classList.remove('show');
        }

        // 投稿処理
        document.getElementById('postForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            canvas.discardActiveObject();
            canvas.renderAll();
            
            const title = document.getElementById('postTitle').value;
            const penName = document.getElementById('postPenName').value;
            const description = document.getElementById('postDescription').value;
            
            const imageData = canvas.toDataURL('image/png');
            const svgData = canvas.toSVG();
            
            // 使用素材ID
            const usedMaterials = canvas.getObjects()
                .map(obj => obj.materialData?.id)
                .filter(id => id);
            
            const formData = new FormData();
            formData.append('title', title);
            formData.append('pen_name', penName);
            formData.append('description', description);
            formData.append('image_data', imageData);
            formData.append('svg_data', svgData);
            formData.append('used_material_ids', usedMaterials.join(','));
            
            try {
                const response = await fetch('/api/upload-custom-artwork.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('投稿が完了しました！承認後に公開されます。');
                    hidePostModal();
                    document.getElementById('postForm').reset();
                } else {
                    alert('エラー: ' + (result.message || '投稿に失敗しました'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('投稿に失敗しました');
            }
        });

        // カテゴリフィルター
        function filterByCategory(categoryName) {
            const tabs = document.querySelectorAll('.category-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            const items = document.querySelectorAll('.material-item');
            items.forEach(item => {
                if (categoryName === 'all' || item.dataset.category === categoryName) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // 素材検索
        document.getElementById('materialSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.material-item');
            
            items.forEach(item => {
                const title = item.dataset.title;
                if (title.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // ページ読み込み時に初期化
        window.addEventListener('load', initCanvas);
    </script>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
