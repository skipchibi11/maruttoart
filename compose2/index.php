<?php
require_once '../config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// 最大20個のSVG素材を取得
$stmt = $pdo->prepare("
    SELECT DISTINCT id, title, slug, image_path, svg_path, webp_medium_path, category_id, created_at
    FROM materials 
    WHERE svg_path IS NOT NULL 
    AND svg_path != '' 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute();
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e3f2fd 100%);
            min-height: 100vh;
            /* スマホでのスクロールを改善 */
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: auto;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
        }

        .header h1 {
            color: #2c5aa0;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
            min-height: 600px;
        }

        /* 素材パネル */
        .materials-panel {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            order: 1;
        }

        .materials-panel h3 {
            color: #2c5aa0;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
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
            order: 2;
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
            /* タッチ操作の最適化 */
            touch-action: manipulation;
            padding: 10px; /* 内側の余白を最小限に */
        }

        #mainCanvas {
            width: 100%;
            height: 100%;
            max-width: 500px;
            max-height: 500px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            aspect-ratio: 1; /* 正方形を保持 */
        }

        /* 操作ボタンエリア */
        .manipulation-controls {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            order: 3;
        }

        .manipulation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
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
            order: 4;
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
    </style>
</head>
<body>
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
                    <button id="rotateLeftBtn" class="btn btn-rotate-left" title="選択したレイヤーを30度左回転">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-rotate-ccw-icon lucide-rotate-ccw"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                    </button>
                    <button id="rotateBtn" class="btn btn-rotate" title="選択したレイヤーを30度右回転">
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
                </div>
            </div>

            <!-- 出力・削除ボタンエリア -->
            <div class="action-controls">
                <h3><i class="bi bi-tools"></i> 出力・削除</h3>
                <div class="action-buttons">
                    <button id="exportBtn" class="btn btn-export">
                        <i class="bi bi-download"></i> PNG出力
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
        let layerIdCounter = 0;
        let selectedLayerId = null;
        let isDragging = false;
        let dragStartPos = { x: 0, y: 0 };
        let dragStartTransform = { x: 0, y: 0 };
        
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
                            id: ++layerIdCounter,
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
            }
        }

        // 選択されたレイヤーを30度右回転
        function rotateSelectedLayer() {
            if (selectedLayerId === null) {
                alert('回転させるレイヤーを選択してください。');
                return;
            }

            const layer = layers.find(l => l.id === selectedLayerId);
            if (layer) {
                layer.transform.rotation += 30;
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
            }
        }

        // 選択されたレイヤーを30度左回転
        function rotateLeftSelectedLayer() {
            if (selectedLayerId === null) {
                alert('回転させるレイヤーを選択してください。');
                return;
            }

            const layer = layers.find(l => l.id === selectedLayerId);
            if (layer) {
                layer.transform.rotation -= 30;
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
        }

        // 回転ボタンの状態を更新
        function updateRotateButtonState() {
            const rotateBtn = document.getElementById('rotateBtn');
            const rotateLeftBtn = document.getElementById('rotateLeftBtn');
            
            console.log(`updateRotateButtonState: selectedLayerId = ${selectedLayerId}`);
            
            if (selectedLayerId !== null) {
                rotateBtn.disabled = false;
                rotateBtn.title = '選択したレイヤーを30度右回転';
                rotateLeftBtn.disabled = false;
                rotateLeftBtn.title = '選択したレイヤーを30度左回転';
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
                    } else {
                        alert('PNG変換に失敗しました。');
                    }
                }, 'image/png');
            };
            
            img.onerror = function(error) {
                console.error('PNG出力エラー:', error);
                alert('PNG出力に失敗しました。');
            };
            
            // SVGデータをBlobとして作成
            const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
            const svgUrl = URL.createObjectURL(svgBlob);
            img.src = svgUrl;
            
            setTimeout(() => URL.revokeObjectURL(svgUrl), 1000);
        }

        // 全削除機能
        function clearAll() {
            if (layers.length === 0) {
                alert('削除する素材がありません。');
                return;
            }

            if (confirm('全ての素材を削除しますか？')) {
                layers = [];
                layerIdCounter = 0;
                
                // DOM要素も削除
                const canvas = document.getElementById('mainCanvas');
                const layerElements = canvas.querySelectorAll('[id^="layer-"]');
                layerElements.forEach(element => element.remove());
                
                console.log('全ての素材を削除しました');
            }
        }

        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            console.log('ページ読み込み完了');
            
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
            document.getElementById('exportBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                exportToPNG();
            });
            document.getElementById('clearBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                clearAll();
            });
            
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
                
                // キャンバス、レイヤー要素、操作ボタンエリアのクリックは除外
                if (!canvas.contains(e.target) && 
                    !e.target.closest('.layer-element') && 
                    !manipulationControls.contains(e.target) &&
                    !actionControls.contains(e.target)) {
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
            updateSelectedLayerTitle();

            console.log(`${materialItems.length}個の素材を読み込み完了`);
            console.log('素材をクリックしてキャンバスに配置してください');
            console.log('レイヤーをクリック/タップして選択し、ドラッグで移動できます（デベロッパーツール対応）');
            console.log('レイヤーを選択して各種操作ボタンで回転・拡大縮小・前面背面移動ができます');
        });
    </script>
</body>
</html>