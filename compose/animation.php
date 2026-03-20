<?php
require_once '../config.php';

// ログインチェック（管理者のみアクセス可能）
// 必要に応じてコメントアウトを解除
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: /admin/login.php');
//     exit;
// }

$pdo = getDB();

// URLパラメータまたはローカルストレージから作品IDを取得
$artworkId = isset($_GET['artwork_id']) ? intval($_GET['artwork_id']) : null;
$artwork = null;
$artworkMaterials = [];
$fromLocalStorage = false;

// 作品IDが指定された場合、作品データを取得
if ($artworkId) {
    $artworkStmt = $pdo->prepare("
        SELECT id, title, svg_data, used_material_ids, file_path, webp_path
        FROM community_artworks 
        WHERE id = ? AND status = 'approved' AND svg_data IS NOT NULL
    ");
    $artworkStmt->execute([$artworkId]);
    $artwork = $artworkStmt->fetch();
    
    // 使用素材の情報を取得
    if ($artwork && !empty($artwork['used_material_ids'])) {
        $materialIds = explode(',', $artwork['used_material_ids']);
        $materialIds = array_map('intval', $materialIds);
        $materialIds = array_filter($materialIds);
        
        if (!empty($materialIds)) {
            $placeholders = str_repeat('?,', count($materialIds) - 1) . '?';
            $materialsStmt = $pdo->prepare("
                SELECT id, title, svg_path, webp_small_path
                FROM materials
                WHERE id IN ($placeholders)
            ");
            $materialsStmt->execute($materialIds);
            $artworkMaterials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} else {
    // URLに作品IDがない場合は、ローカルストレージから読み込む指示
    $fromLocalStorage = true;
}

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
            margin-top: 20px;
            display: none;
        }
        
        .result-area.active {
            display: block;
        }

        .animation-grid {
            display: grid;
            gap: 24px;
            grid-template-columns: 1fr 420px;
        }

        .preview-panel {
            min-width: 0; /* グリッドのオーバーフロー防止 */
        }

        .preview-area {
            width: 100%;
            overflow: hidden; /* はみ出し防止 */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #preview-svg {
            max-width: 100%;
            max-height: 500px;
            width: auto;
            height: auto;
            display: block;
        }
        
        #preview-svg > svg {
            max-width: 100%;
            max-height: 500px;
            width: auto !important;
            height: auto !important;
        }

        @media (max-width: 1024px) {
            .animation-grid {
                grid-template-columns: 1fr !important;
            }
            
            #preview-svg {
                max-height: 600px;
            }
            
            #preview-svg > svg {
                max-height: 600px;
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
            <h1 class="page-title" style="text-align: center; font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 600; margin-bottom: 10px; color: #A0675C;">
                Animate Works
            </h1>
            <p class="page-subtitle" style="text-align: center; font-size: clamp(0.9rem, 2vw, 1.1rem); color: #8B7355; margin-bottom: 40px; font-weight: 500;">
                みんなの作品にアニメーション効果を追加してGIFを生成
            </p>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>
        
        <?php if (!$artwork && !$fromLocalStorage): ?>
        <div class="alert alert-error">
            作品が見つかりません。<a href="/everyone-works.php">みんなの作品一覧</a>から作品を選択してください。
        </div>
        <?php else: ?>
        
        <div id="main-content" <?= $fromLocalStorage ? 'style="display:none;"' : '' ?>>
            <div class="animation-grid">
            <div class="preview-panel">
                <h2 class="section-title">プレビュー</h2>
                <div class="preview-area" id="preview-area">
                    <canvas id="preview-canvas"></canvas>
                </div>
                
                <div class="result-area" id="result-area">
                    <h3 class="section-title">生成されたGIF</h3>
                    <div class="result-image-container">
                        <img id="result-gif" class="result-image" alt="Generated GIF">
                    </div>
                    <div class="action-buttons">
                        <a id="download-link" class="btn btn-success" download>ダウンロード</a>
                        <button class="btn btn-secondary" onclick="resetResult()">新しく作成</button>
                    </div>
                </div>
            </div>
            
            <div class="control-panel">
                <form id="animation-form" onsubmit="return false;">
                    
                    <?php if ($artwork): ?>
                    <div class="section">
                        <h2 class="section-title">作品情報</h2>
                        <p><strong><?= h($artwork['title']) ?></strong></p>
                        <p style="font-size: 12px; color: #666;">作品ID: <?= $artwork['id'] ?></p>
                    </div>
                    <?php else: ?>
                    <div class="section">
                        <h2 class="section-title">作品情報</h2>
                        <p id="artwork-info-title"><strong>読み込み中...</strong></p>
                        <p id="artwork-info-id" style="font-size: 12px; color: #666;"></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="section">
                        <h2 class="section-title">レイヤーアニメーション</h2>
                        <div id="layers-container"></div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="button" class="btn btn-success" onclick="generateGif()" id="generate-btn">GIF生成</button>
                    </div>

                </form>
            </div>
            </div>
        </div>
        
        <script>
            // ローカルストレージのキー（作品データ用）
            const ARTWORK_STORAGE_KEY = 'marutto_animation_artwork';
            
            // 作品データの初期化
            let artworkData = <?= $fromLocalStorage ? 'null' : json_encode($artwork) ?>;
            let materials = <?= $fromLocalStorage ? '[]' : json_encode($artworkMaterials) ?>;
            let svgData, layers, canvasWidth, canvasHeight, backgroundColor;
            
            // ローカルストレージから作品データを復元
            if (!artworkData) {
                const savedArtwork = localStorage.getItem(ARTWORK_STORAGE_KEY);
                if (savedArtwork) {
                    const parsed = JSON.parse(savedArtwork);
                    artworkData = parsed.artwork;
                    materials = parsed.materials;
                    console.log('作品データをローカルストレージから復元しました');
                    document.getElementById('main-content').style.display = '';
                } else {
                    // 作品データがない場合はエラー表示
                    document.body.innerHTML = `
                        <div class="container">
                            <header>
                                <h1>🎬 アニメーションGIF生成</h1>
                            </header>
                            <div class="alert alert-error">
                                作品が見つかりません。<a href="/everyone-works.php">みんなの作品一覧</a>から作品を選択してください。
                            </div>
                        </div>
                    `;
                    throw new Error('作品データがありません');
                }
            } else {
                // 新規読み込み時は作品データをローカルストレージに保存
                localStorage.setItem(ARTWORK_STORAGE_KEY, JSON.stringify({
                    artwork: artworkData,
                    materials: materials
                }));
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
            
            // 作品情報をUIに表示（ローカルストレージから復元した場合）
            if (!<?= $artwork ? 'true' : 'false' ?>) {
                document.getElementById('artwork-info-title').innerHTML = '<strong>' + (artworkData.title || '無題') + '</strong>';
                document.getElementById('artwork-info-id').textContent = '作品ID: ' + artworkData.id;
            }
            
            // 作品の元のサイズと背景色を取得
            canvasWidth = svgData.canvasWidth || svgData.width || 800;
            canvasHeight = svgData.canvasHeight || svgData.height || 800;
            backgroundColor = svgData.backgroundColor || '#ffffff';
            
            console.log('作品サイズ:', { width: canvasWidth, height: canvasHeight, aspectRatio: (canvasWidth / canvasHeight).toFixed(2) });
            
            // アニメーション設定（軽量版・固定）
            const ANIMATION_DURATION = 1200; // 1.5秒 → 1.2秒に短縮
            const ANIMATION_FPS = 10; // 15fps → 10fpsに削減
            
            // ローカルストレージのキー
            const STORAGE_KEY = 'marutto_animation_' + artworkData.id;
            
            // アニメーション設定を保持
            let animations = {};
            
            // 素材のSVGコンテンツをキャッシュ
            const materialSvgCache = {};
            
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
                                ${thumbnailUrl ? `<img src="${thumbnailUrl}" alt="${materialTitle}" style="width: 50px; height: 50px; object-fit: contain; border-radius: 4px; background: #f5f5f5; padding: 4px;">` : ''}
                                <div style="flex: 1;">
                                    <span class="layer-name">レイヤー ${index + 1}</span>
                                    ${materialTitle ? `<div style="font-size: 11px; color: #666; margin-top: 2px;">${materialTitle}</div>` : ''}
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-small" onclick="toggleAnimation('${layerId}')">
                                アニメーション設定
                            </button>
                        </div>
                        <div class="animation-settings" id="settings-${layerId}">
                            <div class="form-group">
                                <label>アニメーションタイプ</label>
                                <select id="type-${layerId}" onchange="updateAnimation('${layerId}', 'type', this.value)">
                                    <option value="">なし</option>
                                    <option value="fadeIn">フェードイン</option>
                                    <option value="fadeOut">フェードアウト</option>
                                    <option value="slideFromBottom">下から上にスライド</option>
                                    <option value="slideFromTop">上から下にスライド</option>
                                    <option value="slideFromLeft">左からスライド</option>
                                    <option value="slideFromRight">右からスライド</option>
                                    <option value="scale">スケール</option>
                                    <option value="rotate">回転</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>イージング</label>
                                <select id="easing-${layerId}" onchange="updateAnimation('${layerId}', 'easing', this.value)">
                                    <option value="linear">リニア</option>
                                    <option value="easeIn">イーズイン</option>
                                    <option value="easeOut">イーズアウト</option>
                                    <option value="easeInOut">イーズインアウト</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>開始タイミング</label>
                                <select id="delay-${layerId}" onchange="updateAnimation('${layerId}', 'delay', parseFloat(this.value) || 0)">
                                    <option value="0">すぐに開始</option>
                                    <option value="0.5">0.5秒後</option>
                                    <option value="1">1秒後</option>
                                    <option value="1.5">1.5秒後</option>
                                    <option value="2">2秒後</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>維持時間</label>
                                <select id="hold-${layerId}" onchange="updateAnimation('${layerId}', 'hold', parseFloat(this.value) || 0)">
                                    <option value="0">なし（すぐループ）</option>
                                    <option value="0.5">0.5秒維持</option>
                                    <option value="1">1秒維持</option>
                                    <option value="1.5">1.5秒維持</option>
                                    <option value="2">2秒維持</option>
                                </select>
                            </div>
                        </div>
                    `;
                    container.appendChild(layerDiv);
                });
            }
            
            // アニメーション設定の表示/非表示
            function toggleAnimation(layerId) {
                const settings = document.getElementById(`settings-${layerId}`);
                settings.classList.toggle('active');
            }
            
            // アニメーション設定を更新
            function updateAnimation(layerId, property, value) {
                if (!animations[layerId]) {
                    animations[layerId] = { layerId: layerId };
                }
                animations[layerId][property] = value;
                
                console.log('アニメーション設定更新:', layerId, property, value);
                
                // ローカルストレージに保存
                saveToLocalStorage();
                
                // プレビューを更新（デバウンス処理）
                if (window.previewUpdateTimer) {
                    clearTimeout(window.previewUpdateTimer);
                }
                window.previewUpdateTimer = setTimeout(() => {
                    console.log('プレビュー更新を実行');
                    renderPreview().catch(error => {
                        console.error('プレビュー更新エラー:', error);
                    });
                }, 100); // 100ms後に更新
            }
            
            // ローカルストレージに保存
            function saveToLocalStorage() {
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(animations));
                } catch (error) {
                    console.error('ローカルストレージへの保存に失敗:', error);
                }
            }
            
            // ローカルストレージから復元
            function loadFromLocalStorage() {
                try {
                    const saved = localStorage.getItem(STORAGE_KEY);
                    if (saved) {
                        animations = JSON.parse(saved);
                        console.log('アニメーション設定を復元しました:', animations);
                    } else {
                        console.log('保存されたアニメーション設定はありません');
                    }
                } catch (error) {
                    console.error('ローカルストレージからの読み込みに失敗:', error);
                }
            }
            
            // 保存されたアニメーション設定をUIに反映
            function applyAnimationsToUI() {
                console.log('アニメーション設定をUIに反映開始', animations);
                
                Object.keys(animations).forEach(layerId => {
                    const animation = animations[layerId];
                    
                    // ID属性で直接要素を取得
                    const typeSelect = document.getElementById(`type-${layerId}`);
                    const easingSelect = document.getElementById(`easing-${layerId}`);
                    const delayInput = document.getElementById(`delay-${layerId}`);
                    const holdInput = document.getElementById(`hold-${layerId}`);
                    
                    console.log(`レイヤー ${layerId}:`, animation);
                    
                    if (typeSelect && animation.type !== undefined) {
                        typeSelect.value = animation.type;
                        console.log(`  → タイプを "${animation.type}" に設定しました`);
                    }
                    
                    if (easingSelect && animation.easing !== undefined) {
                        easingSelect.value = animation.easing;
                        console.log(`  → イージングを "${animation.easing}" に設定しました`);
                    }
                    
                    if (delayInput && animation.delay !== undefined) {
                        delayInput.value = animation.delay;
                        console.log(`  → 開始タイミングを "${animation.delay}" に設定しました`);
                    }
                    
                    if (holdInput && animation.hold !== undefined) {
                        holdInput.value = animation.hold;
                        console.log(`  → 維持時間を "${animation.hold}" に設定しました`);
                    }
                });
                
                console.log('アニメーション設定をUIに反映完了');
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
                    const animation = animations[layerId];
                    
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
                                    
                                    // アニメーション設定を適用（progress = 0の状態）
                                    const finalX = x;
                                    const finalY = y;
                                    
                                    if (animation && animation.type && animation.type !== 'none') {
                                        const type = animation.type;
                                        
                                        // progress=0の状態での初期位置を計算
                                        if (type === 'slideFromLeft') {
                                            x -= width;
                                        } else if (type === 'slideFromRight') {
                                            x += width;
                                        } else if (type === 'slideFromTop') {
                                            y -= height;
                                        } else if (type === 'slideFromBottom') {
                                            y += height;
                                        }
                                    }
                                    
                                    // originalCenterを取得
                                    const originalCenter = layer.originalCenter || { 
                                        x: svgData.viewBox.width / 2, 
                                        y: svgData.viewBox.height / 2 
                                    };
                                    
                                    // Fabric.jsの中心座標を計算（アニメーション初期位置）
                                    const centerX = x + originalCenter.x * scaleX;
                                    const centerY = y + originalCenter.y * scaleY;
                                    
                                    // 最終位置も計算
                                    const finalCenterX = finalX + originalCenter.x * scaleX;
                                    const finalCenterY = finalY + originalCenter.y * scaleY;
                                    
                                    // 初期不透明度の設定
                                    let initialOpacity = 1;
                                    if (animation && animation.type === 'fadeIn') {
                                        initialOpacity = 0;
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
                                    
                                    // アニメーション情報を保存
                                    svgGroup.animationInfo = {
                                        layerId: layerId,
                                        animation: animation,
                                        finalLeft: finalCenterX,
                                        finalTop: finalCenterY,
                                        initialLeft: centerX,
                                        initialTop: centerY,
                                        initialOpacity: initialOpacity
                                    };
                                    
                                    fabricCanvas.add(svgGroup);
                                    resolve();
                                });
                            });
                        }
                    }
                }
                
                fabricCanvas.renderAll();
                
                // アニメーションを自動再生
                setTimeout(() => {
                    playPreviewAnimation();
                }, 100);
            }
            
            // プレビューキャンバスでアニメーションを再生
            let animationLoopTimer = null;
            
            function playPreviewAnimation() {
                if (!fabricCanvas) return;
                
                // 既存のループタイマーをクリア
                if (animationLoopTimer) {
                    clearTimeout(animationLoopTimer);
                    animationLoopTimer = null;
                }
                
                const objects = fabricCanvas.getObjects();
                const animationDuration = ANIMATION_DURATION;
                
                // アニメーションを持つオブジェクトの数をカウント
                let totalAnimations = 0;
                objects.forEach(obj => {
                    if (obj.animationInfo && obj.animationInfo.animation && 
                        obj.animationInfo.animation.type && obj.animationInfo.animation.type !== 'none') {
                        totalAnimations++;
                    }
                });
                
                if (totalAnimations === 0) return;
                
                // まず全オブジェクトを初期位置に配置
                objects.forEach(obj => {
                    if (obj.animationInfo) {
                        const info = obj.animationInfo;
                        obj.set({
                            left: info.initialLeft,
                            top: info.initialTop,
                            opacity: info.initialOpacity
                        });
                    }
                });
                fabricCanvas.renderAll();
                
                // 最長の完了時間を計算（ループタイミング用）
                let maxCompletionTime = 0;
                
                objects.forEach(obj => {
                    if (!obj.animationInfo) return;
                    
                    const info = obj.animationInfo;
                    const animation = info.animation;
                    
                    if (!animation || !animation.type || animation.type === 'none') return;
                    
                    const delay = animation.delay ? parseFloat(animation.delay) * 1000 : 0;
                    const hold = animation.hold ? parseFloat(animation.hold) * 1000 : 0;
                    const easing = animation.easing || 'linear';
                    
                    const completionTime = delay + animationDuration + hold;
                    if (completionTime > maxCompletionTime) {
                        maxCompletionTime = completionTime;
                    }
                    
                    // Fabric.jsのイージング関数に変換
                    let fabricEasing = fabric.util.ease['linear'];
                    if (easing === 'easeIn') fabricEasing = fabric.util.ease['easeInQuad'];
                    else if (easing === 'easeOut') fabricEasing = fabric.util.ease['easeOutQuad'];
                    else if (easing === 'easeInOut') fabricEasing = fabric.util.ease['easeInOutQuad'];
                    
                    setTimeout(() => {
                        const animateProps = {};
                        
                        if (animation.type === 'fadeIn') {
                            animateProps.opacity = 1;
                        } else if (animation.type === 'fadeOut') {
                            animateProps.opacity = 0;
                        } else if (animation.type === 'slideFromLeft' || animation.type === 'slideFromRight') {
                            animateProps.left = info.finalLeft;
                        } else if (animation.type === 'slideFromTop' || animation.type === 'slideFromBottom') {
                            animateProps.top = info.finalTop;
                        } else if (animation.type === 'scale') {
                            const startScale = animation.startScale || 0;
                            const endScale = animation.endScale || 1;
                            const currentScaleX = obj.scaleX;
                            const currentScaleY = obj.scaleY;
                            const flipX = currentScaleX < 0 ? -1 : 1;
                            const flipY = currentScaleY < 0 ? -1 : 1;
                            
                            // 初期スケールを設定（アニメーション開始前）
                            obj.set({
                                scaleX: Math.abs(currentScaleX) * startScale * flipX,
                                scaleY: Math.abs(currentScaleY) * startScale * flipY
                            });
                            
                            animateProps.scaleX = Math.abs(currentScaleX) * endScale * flipX;
                            animateProps.scaleY = Math.abs(currentScaleY) * endScale * flipY;
                        } else if (animation.type === 'rotate') {
                            const startRotate = animation.startRotate || 0;
                            const endRotate = animation.endRotate || 360;
                            const currentAngle = obj.angle || 0;
                            
                            // 初期角度を設定（アニメーション開始前）
                            obj.set({
                                angle: currentAngle + startRotate
                            });
                            
                            animateProps.angle = currentAngle + endRotate;
                        }
                        
                        obj.animate(animateProps, {
                            duration: animationDuration,
                            easing: fabricEasing,
                            onChange: fabricCanvas.renderAll.bind(fabricCanvas)
                        });
                    }, delay);
                });
                
                // 全アニメーション完了後にループ
                animationLoopTimer = setTimeout(() => {
                    playPreviewAnimation();
                }, maxCompletionTime + 100); // 100ms余裕を持たせる
            }
            
            // アニメーションフレームを生成（Fabric.jsを使用）
            async function generateAnimatedFrame(progress) {
                const width = canvasWidth;
                const height = canvasHeight;
                const bgColor = backgroundColor;
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
                    const animation = animations[layerId];
                    
                    // delay（開始タイミング）とhold（維持時間）を考慮した進捗を計算
                    const delay = animation && animation.delay ? parseFloat(animation.delay) : 0;
                    const hold = animation && animation.hold ? parseFloat(animation.hold) : 0;
                    const totalDuration = animationDuration + delay * 1000 + hold * 1000;
                    const currentTime = progress * totalDuration;
                    
                    let layerProgress = 0;
                    if (currentTime < delay * 1000) {
                        layerProgress = 0;
                    } else if (currentTime < delay * 1000 + animationDuration) {
                        layerProgress = (currentTime - delay * 1000) / animationDuration;
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
                                    const scaleX = Math.abs(transform.scaleX !== undefined ? transform.scaleX : 1);
                                    const scaleY = Math.abs(transform.scaleY !== undefined ? transform.scaleY : 1);
                                    const rotation = transform.rotation !== undefined ? transform.rotation : 0;
                                    const flipH = transform.flipHorizontal !== undefined ? transform.flipHorizontal : false;
                                    const flipV = transform.flipVertical !== undefined ? transform.flipVertical : false;
                                    
                                    let x = transform.x !== undefined ? transform.x : 0;
                                    let y = transform.y !== undefined ? transform.y : 0;
                                    
                                    // アニメーション適用
                                    let animX = x;
                                    let animY = y;
                                    let animScaleX = scaleX;
                                    let animScaleY = scaleY;
                                    let animRotation = rotation;
                                    let opacity = 1;
                                    
                                    if (animation && animation.type) {
                                        const easing = animation.easing || 'linear';
                                        const easedProgress = applyEasing(layerProgress, easing);
                                        
                                        switch (animation.type) {
                                            case 'fadeIn':
                                                opacity = easedProgress;
                                                break;
                                            case 'fadeOut':
                                                opacity = 1 - easedProgress;
                                                break;
                                            case 'slideFromBottom':
                                                const startYBottom = height;
                                                animY = startYBottom + (y - startYBottom) * easedProgress;
                                                break;
                                            case 'slideFromTop':
                                                const startYTop = -svgData.viewBox.height;
                                                animY = startYTop + (y - startYTop) * easedProgress;
                                                break;
                                            case 'slideFromLeft':
                                                const startXLeft = -svgData.viewBox.width;
                                                animX = startXLeft + (x - startXLeft) * easedProgress;
                                                break;
                                            case 'slideFromRight':
                                                const startXRight = width;
                                                animX = startXRight + (x - startXRight) * easedProgress;
                                                break;
                                            case 'scale':
                                                const startScale = animation.startScale || 0;
                                                const endScale = animation.endScale || 1;
                                                const scale = startScale + (endScale - startScale) * easedProgress;
                                                animScaleX = scaleX * scale;
                                                animScaleY = scaleY * scale;
                                                break;
                                            case 'rotate':
                                                const startRotate = animation.startRotate || 0;
                                                const endRotate = animation.endRotate || 360;
                                                const rotateAmount = startRotate + (endRotate - startRotate) * easedProgress;
                                                animRotation = rotation + rotateAmount;
                                                break;
                                        }
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
            
            // イージング関数
            function applyEasing(t, easing) {
                switch (easing) {
                    case 'easeInOut':
                        return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                    case 'easeIn':
                        return t * t;
                    case 'easeOut':
                        return 1 - (1 - t) * (1 - t);
                    case 'linear':
                    default:
                        return t;
                }
            }
            
            // GIF生成（gifshotを使用）
            async function generateGif() {
                const generateBtn = document.getElementById('generate-btn');
                const previewArea = document.getElementById('preview-area');
                const resultArea = document.getElementById('result-area');
                
                try {
                    // ボタンを無効化
                    generateBtn.disabled = true;
                    generateBtn.textContent = '生成中...';
                    
                    // ローディング表示
                    previewArea.innerHTML = '<div class="loading"><div class="spinner"></div><p>フレームを生成中...</p></div>';
                    
                    const width = canvasWidth;
                    const height = canvasHeight;
                    const duration = ANIMATION_DURATION;
                    const fps = ANIMATION_FPS;
                    
                    console.log('GIF生成開始:', { width, height, duration, fps });
                    
                    // 最も長いレイヤーの総時間を計算（delay + animation + hold）
                    let maxTotalDuration = duration;
                    layers.forEach((layer, index) => {
                        const layerId = `layer_${index}`;
                        const animation = animations[layerId];
                        if (animation) {
                            const delay = animation.delay ? parseFloat(animation.delay) : 0;
                            const hold = animation.hold ? parseFloat(animation.hold) : 0;
                            const layerTotalDuration = duration + (delay * 1000) + (hold * 1000);
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
                        const dataUrl = await generateAnimatedFrame(progress);
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
                            document.getElementById('result-gif').src = url;
                            document.getElementById('download-link').href = url;
                            document.getElementById('download-link').download = `animation_${artworkData.id}_${Date.now()}.gif`;
                            
                            resultArea.classList.add('active');
                            
                            // プレビューをリセットして再描画
                            fabricCanvas = null;
                            previewArea.innerHTML = '<canvas id="preview-canvas"></canvas>';
                            renderPreview();
                            
                            // ボタンを有効化
                            generateBtn.disabled = false;
                            generateBtn.textContent = 'GIF生成';
                        } else {
                            throw new Error(obj.error);
                        }
                    });
                    
                } catch (error) {
                    console.error('GIF生成エラー:', error);
                    alert('GIF生成に失敗しました: ' + error.message);
                    
                    // エラー時の処理
                    previewArea.innerHTML = '<div id="preview-svg"></div>';
                    renderPreview();
                    generateBtn.disabled = false;
                    generateBtn.textContent = 'GIF生成';
                }
            }
            
            // 結果をリセット
            function resetResult() {
                document.getElementById('result-area').classList.remove('active');
            }
            
            // 初期化
            renderLayers();
            
            // ローカルストレージから設定を復元
            loadFromLocalStorage();
            
            // レイヤーレンダリング後にUIに反映（少し遅延させる）
            setTimeout(() => {
                applyAnimationsToUI();
            }, 100);
            
            // 初期プレビューを表示
            renderPreview();
            
            // URLからクエリストリングを削除（履歴は保持）
            if (window.location.search) {
                const url = window.location.protocol + '//' + window.location.host + window.location.pathname;
                history.replaceState({}, document.title, url);
            }
        </script>
        
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
