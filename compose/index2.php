<?php
require_once '../config.php';

// 公開ページなのでキャッシュを有効化
// setPublicCache(3600, 7200);

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
                        <select id="canvasSize" onchange="handleCanvasSizeChange()">
                            <option value="800x800">正方形 (800×800)</option>
                            <option value="1200x630">横長 (1200×630)</option>
                            <option value="630x1200">縦長 (630×1200)</option>
                            <option value="1920x1080">HD (1920×1080)</option>
                            <option value="custom">カスタム</option>
                        </select>
                        <div class="custom-size-inputs" id="customSizeInputs">
                            <input type="number" id="customWidth" placeholder="幅" min="100" max="5000" value="800">
                            <span>×</span>
                            <input type="number" id="customHeight" placeholder="高さ" min="100" max="5000" value="800">
                            <button class="control-btn" onclick="applyCustomSize()" style="padding: 8px 12px; font-size: 0.85rem;">適用</button>
                        </div>
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
                <button class="primary-btn full-width" style="margin-top: 12px;" onclick="uploadArtwork()">作品を投稿</button>
            </div>
        </div>
    </div>

    <!-- 投稿確認ポップアップ -->
    <div id="confirmModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <h2 class="modal-title">作品を投稿しますか？</h2>
            <p style="margin-bottom: 20px; color: #666;">すぐに公開されます</p>
            <div class="modal-buttons">
                <button type="button" class="modal-btn secondary" onclick="hideConfirmModal()">キャンセル</button>
                <button type="button" class="modal-btn primary" onclick="confirmUpload()">投稿する</button>
            </div>
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
                const objects = canvas.getObjects().map(obj => {
                    console.log('Saving object:', {
                        left: obj.left,
                        top: obj.top,
                        scaleX: obj.scaleX,
                        scaleY: obj.scaleY,
                        width: obj.width,
                        height: obj.height
                    });
                    return {
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
                    };
                });
                const canvasData = {
                    width: originalCanvasWidth,  // canvas.widthではなく元のサイズを保存
                    height: originalCanvasHeight, // canvas.heightではなく元のサイズを保存
                    backgroundColor: canvas.backgroundColor,
                    isTransparentBg: isTransparentBg,
                    objects: objects,
                    canvasSize: document.getElementById('canvasSize').value,
                    customWidth: originalCanvasWidth,
                    customHeight: originalCanvasHeight
                };
                console.log('Saving canvas size:', { width: originalCanvasWidth, height: originalCanvasHeight });
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

                // オブジェクトを復元
                if (canvasData.objects && canvasData.objects.length > 0) {
                    console.log(`${canvasData.objects.length}個のオブジェクトを復元中...`);
                    
                    let loadedCount = 0;
                    const totalCount = canvasData.objects.length;
                    
                    canvasData.objects.forEach(objData => {
                        if (objData.materialData && objData.materialData.svg_path) {
                            console.log('Loading object:', objData);
                            fetch('/' + objData.materialData.svg_path)
                                .then(response => response.text())
                                .then(svgText => {
                                    fabric.loadSVGFromString(svgText, function(objects, options) {
                                        const obj = fabric.util.groupSVGElements(objects, options);
                                        console.log('Loaded SVG original size:', { width: obj.width, height: obj.height });
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
                                        console.log('After set:', {
                                            left: obj.left,
                                            top: obj.top,
                                            scaleX: obj.scaleX,
                                            scaleY: obj.scaleY,
                                            width: obj.width,
                                            height: obj.height
                                        });
                                        canvas.add(obj);
                                        canvas.renderAll();
                                        
                                        // 全てのオブジェクトが読み込まれたら再度フィット
                                        loadedCount++;
                                        if (loadedCount === totalCount) {
                                            setTimeout(() => {
                                                fitCanvasToContainer();
                                                canvas.renderAll();
                                            }, 100);
                                        }
                                    });
                                })
                                .catch(error => {
                                    console.error('素材復元エラー:', error);
                                    loadedCount++;
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
        function fitCanvasToContainer() {
            const wrapper = document.querySelector('.canvas-wrapper');
            const maxWidth = wrapper.clientWidth - 40; // padding考慮
            const maxHeight = wrapper.clientHeight - 40;
            
            // 元の（データ）サイズを使用
            const canvasWidth = originalCanvasWidth;
            const canvasHeight = originalCanvasHeight;
            
            console.log('fitCanvasToContainer:', { 
                maxWidth, 
                maxHeight, 
                canvasWidth, 
                canvasHeight,
                wrapperClientWidth: wrapper.clientWidth,
                wrapperClientHeight: wrapper.clientHeight
            });
            
            // スケール比率を計算
            const scaleX = maxWidth / canvasWidth;
            const scaleY = maxHeight / canvasHeight;
            const scale = Math.min(scaleX, scaleY, 1); // 1を超えない（拡大しない）
            
            console.log('Calculated scale:', scale);
            
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
                
                const isMovingSource = movingLayerIndex === actualIndex;
                const isMovingTarget = movingLayerIndex !== null && movingLayerIndex !== actualIndex;
                
                console.log(`Layer ${actualIndex}: isMovingSource=${isMovingSource}, isMovingTarget=${isMovingTarget}, movingLayerIndex=${movingLayerIndex}`);
                
                if (isMovingSource) {
                    layerItem.classList.add('moving-source');
                    console.log(`Added moving-source class to layer ${actualIndex}`);
                }
                if (isMovingTarget) {
                    layerItem.classList.add('moving-target');
                    console.log(`Added moving-target class to layer ${actualIndex}`);
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
                            <img src="/${materialData.webp_small_path || ''}" alt="">
                        </div>
                    </div>
                    <div class="layer-actions">
                        <button class="layer-action-btn">Delete</button>
                    </div>
                `;
                
                // ドラッグハンドルのクリック/タップイベント
                const dragHandle = layerItem.querySelector('.drag-handle');
                const handleDragHandleClick = (e) => {
                    console.log('Drag handle clicked/tapped, index:', actualIndex);
                    e.stopPropagation();
                    e.preventDefault();
                    toggleMoveMode(actualIndex);
                };
                dragHandle.addEventListener('click', handleDragHandleClick);
                
                // 削除ボタンのイベント
                const deleteBtn = layerItem.querySelector('.layer-action-btn');
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
            console.log('toggleMoveMode called, index:', index, 'movingLayerIndex:', movingLayerIndex);
            if (movingLayerIndex === index) {
                // 同じレイヤーをクリック → キャンセル
                movingLayerIndex = null;
                console.log('Move mode cancelled');
            } else if (movingLayerIndex !== null) {
                // 移動モード中に別のドラッグハンドルをクリック → 移動実行
                console.log('Moving layer from', movingLayerIndex, 'to', index);
                moveLayerToPosition(movingLayerIndex, index);
                movingLayerIndex = null;
            } else {
                // 移動モード開始
                movingLayerIndex = index;
                console.log('Move mode started, movingLayerIndex:', movingLayerIndex);
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
        // ダウンロード
        function downloadImage() {
            canvas.discardActiveObject();
            canvas.renderAll();
            
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
                
                // toBlobを使用（compose/index.phpと同じ方式）
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
                        console.log(`PNG出力完了 (${originalCanvasWidth}x${originalCanvasHeight}px)`);
                    } else {
                        showMessage('PNG変換に失敗しました');
                    }
                }, 'image/png');
            });
        }

        // 作品投稿
        function uploadArtwork() {
            if (canvas.getObjects().length === 0) {
                showMessage('素材を追加してから投稿してください。');
                return;
            }
            document.getElementById('confirmModal').classList.add('show');
        }

        function hideConfirmModal() {
            document.getElementById('confirmModal').classList.remove('show');
        }

        function confirmUpload() {
            hideConfirmModal();

            // ローディング表示
            const loadingDiv = document.createElement('div');
            loadingDiv.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 10000; text-align: center; min-width: 200px;';
            loadingDiv.innerHTML = '<div style="font-size: 2rem; margin-bottom: 10px;">📤</div><div style="font-size: 1.2rem;">投稿中...</div>';
            document.body.appendChild(loadingDiv);

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
                            document.body.removeChild(loadingDiv);
                            showMessage('画像の生成に失敗しました');
                            return;
                        }

                        // FormData作成
                        const formData = new FormData();
                        formData.append('artwork', blob, `custom-artwork-${Date.now()}.png`);
                    
                    // SVGデータを保存
                    const svgData = {
                        objects: canvas.getObjects().map(obj => ({
                            materialData: obj.materialData,
                            left: obj.left,
                            top: obj.top,
                            scaleX: obj.scaleX,
                            scaleY: obj.scaleY,
                            angle: obj.angle,
                            flipX: obj.flipX,
                            flipY: obj.flipY
                        })),
                        canvasWidth: originalCanvasWidth,
                        canvasHeight: originalCanvasHeight,
                        backgroundColor: canvas.backgroundColor
                    };
                    formData.append('svg_data', JSON.stringify(svgData));
                    
                    // 使用素材IDを抽出して送信
                    const usedMaterialIds = canvas.getObjects()
                        .map(obj => obj.materialData?.id)
                        .filter(id => id)
                        .filter((id, index, self) => self.indexOf(id) === index) // 重複削除
                        .join(',');
                    
                    if (usedMaterialIds) {
                        formData.append('used_material_ids', usedMaterialIds);
                    }

                    // サーバーにアップロード
                    fetch('/api/upload-custom-artwork.php', {
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
                            showMessage('作品を投稿しました！\nありがとうございます✨');
                        } else {
                            showMessage('投稿に失敗しました: ' + (data.error || '不明なエラー'));
                        }
                    })
                    .catch(error => {
                        document.body.removeChild(loadingDiv);
                        console.error('Upload error:', error);
                        showMessage('投稿中にエラーが発生しました: ' + error.message);
                    });
                })
                .catch(error => {
                    document.body.removeChild(loadingDiv);
                    canvas.setDimensions({ width: currentWidth, height: currentHeight });
                    canvas.setZoom(currentZoom);
                    canvas.renderAll();
                    console.error('Blob conversion error:', error);
                    showMessage('画像の生成に失敗しました');
                });
            }, 100);
        }

        // メッセージ表示
        function showMessage(message) {
            const messageDiv = document.createElement('div');
            messageDiv.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 10000; text-align: center; min-width: 200px; max-width: 400px;';
            messageDiv.innerHTML = `<div style="white-space: pre-line; margin-bottom: 20px; line-height: 1.6;">${message}</div><button onclick="this.parentElement.remove()" style="padding: 10px 24px; background: var(--primary-color); color: white; border: none; border-radius: 50px; cursor: pointer; font-weight: 600;">OK</button>`;
            document.body.appendChild(messageDiv);
        }

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
