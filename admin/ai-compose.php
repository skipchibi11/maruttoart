<?php
require_once '../config.php';
startAdminSession();
requireLogin();
setNoCache();

$pdo = getDB();

// カテゴリ一覧を取得
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
    <title>AI自動作成 - 管理画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
    <style>
        :root {
            --primary-color: #E8A87C;
            --secondary-color: #C38E70;
            --text-dark: #5A4A42;
        }

        body {
            background-color: #f8f9fa;
        }

        .sidebar {
            background-color: #ffffff;
            min-height: 100vh;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .main-content {
            padding: 20px;
        }

        .canvas-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        #canvas-wrapper {
            display: inline-block;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin: 20px auto;
        }

        .control-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .btn-primary-custom {
            background-color: var(--primary-color);
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary-custom:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-secondary-custom {
            background-color: #6c757d;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-secondary-custom:hover {
            background-color: #5a6268;
        }

        .material-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 15px;
        }

        .material-item {
            width: 80px;
            height: 80px;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 8px;
            background: white;
            position: relative;
        }

        .material-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .material-item-title {
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            font-size: 10px;
            text-align: center;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.show {
            display: flex;
        }

        .loading-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
        }

        .spinner {
            border: 4px solid rgba(0,0,0,0.1);
            border-radius: 50%;
            border-top: 4px solid var(--primary-color);
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .ai-result-info {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
        }

        .ai-result-info h6 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .ai-result-info pre {
            background: white;
            padding: 10px;
            border-radius: 4px;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- サイドバー -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4>maruttoart</h4>
                        <small class="text-muted">管理画面</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/">
                                <i class="bi bi-house-door"></i> ダッシュボード
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/upload.php">
                                <i class="bi bi-plus-circle"></i> 素材アップロード
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="/admin/ai-compose.php">
                                <i class="bi bi-stars"></i> AI自動作成
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/categories.php">
                                <i class="bi bi-folder"></i> カテゴリ管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/tags.php">
                                <i class="bi bi-tags"></i> タグ管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/artworks.php">
                                <i class="bi bi-palette"></i> みんなのアトリエ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/calendar.php">
                                <i class="bi bi-calendar3"></i> カレンダー管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/analytics.php">
                                <i class="bi bi-graph-up"></i> アクセス分析
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/">
                                <i class="bi bi-globe"></i> 公式サイト
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/logout.php">
                                <i class="bi bi-box-arrow-right"></i> ログアウト
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- メインコンテンツ -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="bi bi-stars"></i> AI自動作成</h1>
                </div>

                <!-- コントロールパネル -->
                <div class="control-panel">
                    <h5 class="mb-3">生成設定</h5>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">素材の数</label>
                            <select class="form-select" id="materialCount">
                                <option value="3">3個</option>
                                <option value="4" selected>4個</option>
                                <option value="5">5個</option>
                                <option value="6">6個</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">キャンバスサイズ</label>
                            <select class="form-select" id="canvasSize">
                                <option value="800x800" selected>正方形 (800x800)</option>
                                <option value="1080x1080">Instagram (1080x1080)</option>
                                <option value="1200x630">OGP (1200x630)</option>
                                <option value="1920x1080">Full HD (1920x1080)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">カテゴリフィルター</label>
                            <select class="form-select" id="categoryFilter">
                                <option value="">全カテゴリ</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category['id']) ?>">
                                        <?= htmlspecialchars($category['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">AIプロンプト（オプション）</label>
                            <textarea class="form-control" id="aiPrompt" rows="3" placeholder="例: 春らしい明るい雰囲気で、中央に大きく配置してください。背景は淡いピンク色にしてください。"></textarea>
                            <small class="text-muted">空欄の場合、AIが自動的に良い組み合わせを提案します。</small>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-primary-custom" onclick="generateComposition()">
                            <i class="bi bi-stars"></i> AI生成
                        </button>
                        <button class="btn btn-secondary-custom" onclick="clearCanvas()">
                            <i class="bi bi-trash"></i> クリア
                        </button>
                        <button class="btn btn-secondary-custom" onclick="downloadSVG()" id="downloadSvgBtn" disabled>
                            <i class="bi bi-download"></i> SVGダウンロード
                        </button>
                        <button class="btn btn-secondary-custom" onclick="downloadPNG()" id="downloadPngBtn" disabled>
                            <i class="bi bi-image"></i> PNGダウンロード
                        </button>
                    </div>

                    <!-- 選択された素材のプレビュー -->
                    <div id="selectedMaterialsPreview" style="display: none;">
                        <h6 class="mt-4 mb-2">選択された素材:</h6>
                        <div class="material-preview" id="materialPreviewContainer"></div>
                    </div>

                    <!-- AI結果情報 -->
                    <div id="aiResultInfo" class="ai-result-info" style="display: none;">
                        <h6><i class="bi bi-info-circle"></i> AI生成結果</h6>
                        <pre id="aiResultContent"></pre>
                    </div>
                </div>

                <!-- キャンバスエリア -->
                <div class="canvas-container">
                    <h5 class="mb-3">プレビュー</h5>
                    <div id="canvas-wrapper">
                        <canvas id="canvas"></canvas>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- ローディングオーバーレイ -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <p id="loadingMessage">AI生成中...</p>
        </div>
    </div>

    <script>
        let canvas;
        let originalCanvasWidth = 800;
        let originalCanvasHeight = 800;
        let selectedMaterials = [];
        let aiComposition = null;

        // キャンバス初期化
        function initCanvas() {
            canvas = new fabric.Canvas('canvas', {
                backgroundColor: '#ffffff',
                preserveObjectStacking: true
            });

            canvas.setDimensions({
                width: originalCanvasWidth,
                height: originalCanvasHeight
            });

            // 表示サイズを調整
            fitCanvasToContainer();
        }

        function fitCanvasToContainer() {
            const containerWidth = document.getElementById('canvas-wrapper').offsetWidth || 600;
            const zoom = Math.min(containerWidth / originalCanvasWidth, 600 / originalCanvasHeight, 1);
            canvas.setZoom(zoom);
            canvas.setDimensions({
                width: originalCanvasWidth * zoom,
                height: originalCanvasHeight * zoom
            });
        }

        // キャンバスクリア
        function clearCanvas() {
            canvas.clear();
            canvas.backgroundColor = '#ffffff';
            canvas.renderAll();
            selectedMaterials = [];
            aiComposition = null;
            document.getElementById('selectedMaterialsPreview').style.display = 'none';
            document.getElementById('aiResultInfo').style.display = 'none';
            document.getElementById('downloadSvgBtn').disabled = true;
            document.getElementById('downloadPngBtn').disabled = true;
        }

        // ローディング表示
        function showLoading(message = 'AI生成中...') {
            document.getElementById('loadingMessage').textContent = message;
            document.getElementById('loadingOverlay').classList.add('show');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('show');
        }

        // AI生成メイン関数
        async function generateComposition() {
            const materialCount = parseInt(document.getElementById('materialCount').value);
            const canvasSize = document.getElementById('canvasSize').value;
            const categoryFilter = document.getElementById('categoryFilter').value;
            const aiPrompt = document.getElementById('aiPrompt').value.trim();

            // キャンバスサイズを更新
            const [width, height] = canvasSize.split('x').map(Number);
            originalCanvasWidth = width;
            originalCanvasHeight = height;
            canvas.setDimensions({ width, height });
            fitCanvasToContainer();

            showLoading('ランダム素材を選択中...');

            try {
                // ステップ1: ランダム素材を取得
                const materials = await fetchRandomMaterials(materialCount, categoryFilter);
                if (!materials || materials.length === 0) {
                    alert('素材が見つかりませんでした。');
                    hideLoading();
                    return;
                }

                selectedMaterials = materials;
                displayMaterialPreview(materials);

                // ステップ2: AIに組み合わせ方を提案させる
                showLoading('AIが組み合わせを考えています...');
                const composition = await requestAIComposition(materials, width, height, aiPrompt);
                
                if (!composition) {
                    alert('AI生成に失敗しました。');
                    hideLoading();
                    return;
                }

                aiComposition = composition;
                displayAIResult(composition);

                // ステップ3: キャンバスに配置
                showLoading('キャンバスに配置中...');
                await applyCompositionToCanvas(composition);

                hideLoading();
                
                // ダウンロードボタンを有効化
                document.getElementById('downloadSvgBtn').disabled = false;
                document.getElementById('downloadPngBtn').disabled = false;

            } catch (error) {
                console.error('生成エラー:', error);
                alert('エラーが発生しました: ' + error.message);
                hideLoading();
            }
        }

        // ランダム素材取得
        async function fetchRandomMaterials(count, categoryId) {
            let url = `/api/get-materials.php?random=${count}&has_svg=1`;
            if (categoryId) {
                url += `&category_id=${categoryId}`;
            }

            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`素材の取得に失敗しました (HTTP ${response.status})`);
            }
            
            const data = await response.json();
            
            // エラーレスポンスのチェック
            if (data.error) {
                throw new Error(`素材の取得エラー: ${data.error}`);
            }
            
            // 配列でない、または空の場合
            if (!Array.isArray(data) || data.length === 0) {
                throw new Error('SVGファイルを持つ素材が見つかりませんでした。素材をアップロードしてください。');
            }
            
            return data;
        }

        // 素材プレビュー表示
        function displayMaterialPreview(materials) {
            const container = document.getElementById('materialPreviewContainer');
            container.innerHTML = '';

            materials.forEach(material => {
                const thumbPath = material.webp_small_path;
                const isRemote = thumbPath && (thumbPath.startsWith('http://') || thumbPath.startsWith('https://'));
                const finalThumb = isRemote ? thumbPath : '/' + thumbPath;

                const div = document.createElement('div');
                div.className = 'material-item';
                div.innerHTML = `
                    <img src="${finalThumb}" alt="${material.title || ''}">
                    <div class="material-item-title">${material.title || ''}</div>
                `;
                container.appendChild(div);
            });

            document.getElementById('selectedMaterialsPreview').style.display = 'block';
        }

        // AI結果表示
        function displayAIResult(composition) {
            document.getElementById('aiResultContent').textContent = JSON.stringify(composition, null, 2);
            document.getElementById('aiResultInfo').style.display = 'block';
        }

        // AIに組み合わせ提案をリクエスト
        async function requestAIComposition(materials, width, height, userPrompt) {
            const response = await fetch('/admin/api/ai-composition.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    materials: materials,
                    canvasWidth: width,
                    canvasHeight: height,
                    userPrompt: userPrompt
                })
            });

            if (!response.ok) {
                throw new Error('AI APIリクエストに失敗しました');
            }

            const result = await response.json();
            if (!result.success) {
                throw new Error(result.error || 'AI生成に失敗しました');
            }

            return result.composition;
        }

        // 組み合わせをキャンバスに適用
        async function applyCompositionToCanvas(composition) {
            canvas.clear();
            canvas.backgroundColor = composition.backgroundColor || '#ffffff';
            canvas.renderAll();

            // 各レイヤーを順番に配置
            for (const layer of composition.layers) {
                await addLayerToCanvas(layer);
            }
        }

        // レイヤーをキャンバスに追加
        async function addLayerToCanvas(layer) {
            return new Promise((resolve, reject) => {
                const material = selectedMaterials.find(m => m.id === layer.materialId);
                if (!material || !material.svg_path) {
                    resolve();
                    return;
                }

                let svgUrl = material.svg_path;
                if (!svgUrl.startsWith('http://') && !svgUrl.startsWith('https://')) {
                    svgUrl = '/' + svgUrl;
                }

                fetch(svgUrl)
                    .then(response => response.text())
                    .then(svgText => {
                        fabric.loadSVGFromString(svgText, function(objects, options) {
                            const obj = fabric.util.groupSVGElements(objects, options);
                            
                            // AIが指定した変換を適用
                            obj.set({
                                left: layer.x || 0,
                                top: layer.y || 0,
                                scaleX: layer.scale || 1,
                                scaleY: layer.scale || 1,
                                angle: layer.rotation || 0,
                                originX: 'center',
                                originY: 'center',
                                materialData: {
                                    id: material.id,
                                    title: material.title,
                                    svg_path: material.svg_path
                                }
                            });

                            // 色変更が指定されている場合
                            if (layer.colors && layer.colors.length > 0) {
                                applyColors(obj, layer.colors);
                            }

                            canvas.add(obj);
                            canvas.renderAll();
                            resolve();
                        });
                    })
                    .catch(error => {
                        console.error('SVG読み込みエラー:', error);
                        resolve();
                    });
            });
        }

        // 色を適用
        function applyColors(obj, colors) {
            if (obj.type === 'group' && obj._objects) {
                colors.forEach(colorInfo => {
                    if (obj._objects[colorInfo.index]) {
                        if (colorInfo.fill) {
                            obj._objects[colorInfo.index].set('fill', colorInfo.fill);
                        }
                        if (colorInfo.stroke) {
                            obj._objects[colorInfo.index].set('stroke', colorInfo.stroke);
                        }
                    }
                });
            } else {
                // 単一オブジェクトの場合
                if (colors[0]) {
                    if (colors[0].fill) obj.set('fill', colors[0].fill);
                    if (colors[0].stroke) obj.set('stroke', colors[0].stroke);
                }
            }
        }

        // SVGダウンロード
        function downloadSVG() {
            canvas.discardActiveObject();
            canvas.renderAll();
            
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
            
            const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `ai-composition-${originalCanvasWidth}x${originalCanvasHeight}-${Date.now()}.svg`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        // PNGダウンロード
        function downloadPNG() {
            canvas.discardActiveObject();
            canvas.renderAll();

            const currentZoom = canvas.getZoom();
            const currentWidth = canvas.width;
            const currentHeight = canvas.height;
            
            canvas.setZoom(1);
            canvas.setDimensions({ 
                width: originalCanvasWidth, 
                height: originalCanvasHeight 
            });
            canvas.renderAll();

            requestAnimationFrame(() => {
                const nativeCanvas = canvas.lowerCanvasEl;
                nativeCanvas.toBlob(function(blob) {
                    canvas.setDimensions({ width: currentWidth, height: currentHeight });
                    canvas.setZoom(currentZoom);
                    canvas.renderAll();
                    
                    if (blob) {
                        const url = URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = `ai-composition-${originalCanvasWidth}x${originalCanvasHeight}-${Date.now()}.png`;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(url);
                    }
                }, 'image/png');
            });
        }

        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            initCanvas();
            
            // ウィンドウリサイズ時の調整
            window.addEventListener('resize', () => {
                fitCanvasToContainer();
            });
        });
    </script>
</body>
</html>
