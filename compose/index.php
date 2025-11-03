<?php
require_once '../config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// ランダムに10個のSVG素材を取得
$stmt = $pdo->prepare("
    SELECT id, title, slug, image_path, svg_path, webp_medium_path, category_id, structured_bg_color
    FROM materials 
    WHERE svg_path IS NOT NULL 
    AND svg_path != '' 
    ORDER BY RAND() 
    LIMIT 5
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
        }

        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
            margin-top: 1rem;
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

        .material-item img {
            width: 100%;
            height: 100%;
            object-fit: contain;
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

        /* 背景色パレット用スタイル */
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

        .bg-color-btn small {
            font-size: 0.7rem;
            color: #666;
            font-weight: 500;
        }

        .bg-color-btn.active small {
            color: #4285f4;
            font-weight: 600;
        }

        .transparent-bg {
            background: linear-gradient(45deg, #ddd 25%, transparent 25%), 
                        linear-gradient(-45deg, #ddd 25%, transparent 25%), 
                        linear-gradient(45deg, transparent 75%, #ddd 75%), 
                        linear-gradient(-45deg, transparent 75%, #ddd 75%);
            background-size: 10px 10px;
            background-position: 0 0, 0 5px, 5px -5px, -5px 0px;
        }

        /* PNG出力サイズ選択 */
        .export-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        .size-selector {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .size-btn {
            padding: 8px 16px;
            border: 2px solid #dee2e6;
            background: white;
            color: #495057;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .size-btn:hover {
            border-color: #4285f4;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.2);
        }

        .size-btn.active {
            border-color: #4285f4;
            background-color: rgba(66, 133, 244, 0.1);
            color: #4285f4;
            font-weight: 600;
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
                
                <!-- SVG表示セクション -->
                <div class="svg-display-section">
                    <div class="svg-container">
                        <div class="svg-image-wrapper">
                            <svg id="mainCanvas" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg">
                                <!-- 背景 -->
                                <rect id="canvasBackground" width="100%" height="100%" fill="white"/>
                                <!-- レイヤーがここに動的に追加される -->
                            </svg>
                        </div>
                        
                        <!-- タブアイコン -->
                        <div class="tab-icons">
                            <button type="button" class="tab-icon active" data-tab="materials" onclick="switchTab('materials')" title="素材選択">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <rect width="7" height="7" x="3" y="3" rx="1"/>
                                    <rect width="7" height="7" x="14" y="3" rx="1"/>
                                    <rect width="7" height="7" x="14" y="14" rx="1"/>
                                    <rect width="7" height="7" x="3" y="14" rx="1"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" data-tab="layers" onclick="switchTab('layers')" title="レイヤー管理">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/>
                                    <path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/>
                                    <path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" data-tab="color" onclick="switchTab('color')" title="色変更">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <path d="M12 22a1 1 0 0 1 0-20 10 9 0 0 1 10 9 5 5 0 0 1-5 5h-2.25a1.75 1.75 0 0 0-1.4 2.8l.3.4a1.75 1.75 0 0 1-1.4 2.8z"/>
                                    <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/>
                                    <circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/>
                                    <circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/>
                                    <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" data-tab="background" onclick="switchTab('background')" title="背景変更">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <path d="m19 11-8-8-8.6 8.6a2 2 0 0 0 0 2.8l5.2 5.2c.8.8 2 .8 2.8 0L19 11Z"/>
                                    <path d="m5 2 5 5"/>
                                    <path d="M2 13h15"/>
                                    <path d="M22 20a2 2 0 1 1-4 0c0-1.6 1.7-2.4 2-4 .3 1.6 2 2.4 2 4Z"/>
                                </svg>
                            </button>
                            
                            <button type="button" class="tab-icon" data-tab="transform" onclick="switchTab('transform')" title="変形">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="tab-icon-img">
                                    <polyline points="5,9 2,12 5,15"></polyline>
                                    <polyline points="9,5 12,2 15,5"></polyline>
                                    <polyline points="15,19 12,22 9,19"></polyline>
                                    <polyline points="19,9 22,12 19,15"></polyline>
                                    <line x1="2" y1="12" x2="22" y2="12"></line>
                                    <line x1="12" y1="2" x2="12" y2="22"></line>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- コントロールパネル -->
                <div class="card">
                    <div class="card-body">
                        <!-- 素材選択パネル -->
                        <div class="svg-controls" id="materialsTab">
                            <label class="form-label">
                                <svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                                    <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1h-5zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm0 2a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1h-2a.5.5 0 0 1-.5-.5z"/>
                                    <path d="M9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.5L9.5 0zm0 1v2A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5z"/>
                                </svg>
                                素材を選択してキャンバスに追加
                            </label>
                            <div class="materials-grid" id="materialsGrid">
                                <?php foreach ($materials as $material): ?>
                                <div class="material-item" 
                                     data-material-id="<?= h($material['id']) ?>"
                                     data-svg-path="<?= h($material['svg_path']) ?>"
                                     data-title="<?= h($material['title']) ?>"
                                     onclick="addMaterialToCanvas(this)">
                                    <img src="/<?= h($material['webp_medium_path'] ?: $material['image_path']) ?>" 
                                         alt="<?= h($material['title']) ?>"
                                         loading="lazy">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- レイヤー管理パネル -->
                        <div class="svg-controls" id="layersTab" style="display: none;">
                            <label class="form-label">
                                <svg width="16" height="16" fill="currentColor" class="me-1" viewBox="0 0 16 16">
                                    <path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/>
                                    <path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/>
                                    <path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>
                                </svg>
                                レイヤー一覧
                            </label>
                            <div class="layers-list" id="layersList">
                                <div class="text-center text-muted py-4">
                                    まだレイヤーがありません<br>
                                    素材を追加してください
                                </div>
                            </div>
                        </div>

                        <!-- 色変更パネル -->
                        <div class="svg-controls" id="colorTab" style="display: none;">
                            <div id="colorControls" style="display: none;">
                                <label class="form-label">選択中のレイヤー: <span id="selectedColorLayerName"></span></label>
                                
                                <!-- 季節テーマ -->
                            <div class="mb-3">
                                <label class="form-label">季節テーマ</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn" onclick="applyColorTheme('spring')" title="春のパステルカラー">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                                    <button class="btn" onclick="applyColorTheme('summer')" title="夏のパステルカラー">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                                    <button class="btn" onclick="applyColorTheme('autumn')" title="秋のパステルカラー">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
                                            <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                                        </svg>
                                    </button>
                                    <button class="btn" onclick="applyColorTheme('winter')" title="冬のパステルカラー">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                                    <button class="btn" onclick="applyColorTheme('monochrome')" title="白黒の濃淡">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11.25 17.25h1.5L12 18z"/>
                                            <path d="m15 12 2 2"/>
                                            <path d="M18 6.5a.5.5 0 0 0-.5-.5"/>
                                            <path d="M20.69 9.67a4.5 4.5 0 1 0-7.04-5.5 8.35 8.35 0 0 0-3.3 0 4.5 4.5 0 1 0-7.04 5.5C2.49 11.2 2 12.88 2 14.5 2 19.47 6.48 22 12 22s10-2.53 10-7.5c0-1.62-.48-3.3-1.3-4.83"/>
                                            <path d="M6 6.5a.495.495 0 0 1 .5-.5"/>
                                            <path d="m9 12-2 2"/>
                                        </svg>
                                    </button>
                                    <button class="btn" onclick="applyColorTheme('sepia')" title="セピアの温もり">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M10 2v2"/>
                                            <path d="M14 2v2"/>
                                            <path d="M16 8a1 1 0 0 1 1 1v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V9a1 1 0 0 1 1-1h14a4 4 0 1 1 0 8h-1"/>
                                            <path d="M6 2v2"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- ランダム配色・リセット -->
                            <div class="d-flex gap-2 mb-3">
                                <button class="btn flex-fill" onclick="randomizeColors()">ランダム配色</button>
                                <button class="btn flex-fill" onclick="resetColors()">色をリセット</button>
                            </div>

                            <!-- 黒・グレー除外設定 -->
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="excludeGrayBlack" checked>
                                <label class="form-check-label" for="excludeGrayBlack">
                                    黒・グレー系の色を除外
                                </label>
                            </div>
                            </div>
                            
                            <div id="noColorSelected" class="text-center text-muted">
                                レイヤーを選択してください
                            </div>
                        </div>

                        <!-- 背景変更パネル -->
                        <div class="svg-controls" id="backgroundTab" style="display: none;">
                            <!-- 背景色選択 -->
                            <div class="bg-color-section mb-4">
                                <div class="bg-color-palette d-flex justify-content-center align-items-center gap-3">
                                    <button type="button" class="bg-color-btn" data-color="transparent" title="透明（背景なし）" onclick="changeBackground('transparent')">
                                        <div class="bg-swatch transparent-bg"></div>
                                        <small>透明</small>
                                    </button>
                                    
                                    <button type="button" class="bg-color-btn" data-color="white" title="白色背景" onclick="changeBackground('white')">
                                        <div class="bg-swatch" style="background-color: white;"></div>
                                        <small>白</small>
                                    </button>
                                    
                                    <button type="button" class="bg-color-btn" data-color="#f8f9fa" title="グレー背景" onclick="changeBackground('#f8f9fa')">
                                        <div class="bg-swatch" style="background-color: #f8f9fa;"></div>
                                        <small>グレー</small>
                                    </button>
                                    
                                    <button type="button" class="bg-color-btn" data-color="#000000" title="黒色背景" onclick="changeBackground('#000000')">
                                        <div class="bg-swatch" style="background-color: #000000;"></div>
                                        <small>黒</small>
                                    </button>
                                    
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="color" id="customBgColor" class="form-control form-control-color" 
                                               style="width: 50px; height: 38px;" title="カスタム背景色を選択" value="#ffffff" onchange="changeBackground(this.value)">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 変形パネル -->
                        <div class="svg-controls" id="transformTab" style="display: none;">
                            <div id="transformControls" style="display: none;">
                                <label class="form-label">選択中のレイヤー: <span id="selectedLayerName"></span></label>
                                
                                <!-- 移動ボタン -->
                                <div class="mb-3">
                                    <label class="form-label">位置調整</label>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button class="btn" onclick="moveLayer('left')" title="左に移動">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M13 9a1 1 0 0 1-1-1V5.061a1 1 0 0 0-1.811-.75l-6.835 6.836a1.207 1.207 0 0 0 0 1.707l6.835 6.835a1 1 0 0 0 1.811-.75V16a1 1 0 0 1 1-1h6a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1z"/>
                                            </svg>
                                        </button>
                                        <button class="btn" onclick="moveLayer('up')" title="上に移動">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M9 13a1 1 0 0 0-1-1H5.061a1 1 0 0 1-.75-1.811l6.836-6.835a1.207 1.207 0 0 1 1.707 0l6.835 6.835a1 1 0 0 1-.75 1.811H16a1 1 0 0 0-1 1v6a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1z"/>
                                            </svg>
                                        </button>
                                        <button class="btn" onclick="moveLayer('down')" title="下に移動">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M15 11a1 1 0 0 0 1 1h2.939a1 1 0 0 1 .75 1.811l-6.835 6.836a1.207 1.207 0 0 1-1.707 0L4.31 13.81a1 1 0 0 1 .75-1.811H8a1 1 0 0 0 1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1z"/>
                                            </svg>
                                        </button>
                                        <button class="btn" onclick="moveLayer('right')" title="右に移動">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 9a1 1 0 0 0 1-1V5.061a1 1 0 0 1 1.811-.75l6.836 6.836a1.207 1.207 0 0 1 0 1.707l-6.836 6.835a1 1 0 0 1-1.811-.75V16a1 1 0 0 0-1-1H5a1 1 0 0 1-1-1v-4a1 1 0 0 1 1-1z"/>
                                            </svg>
                                        </button>
                                        <button class="btn" onclick="resetLayerPosition()" title="位置をリセット">リセット</button>
                                    </div>
                                </div>

                                <!-- スケール調整 -->
                                <div class="mb-3">
                                    <label class="form-label">サイズ調整</label>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button class="btn" onclick="scaleLayerStep(-0.1)" title="縮小">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="11" cy="11" r="8"/>
                                                <line x1="21" x2="16.65" y1="21" y2="16.65"/>
                                                <line x1="8" x2="14" y1="11" y2="11"/>
                                            </svg>
                                        </button>
                                        <button class="btn" onclick="scaleLayerStep(0.1)" title="拡大">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="11" cy="11" r="8"/>
                                                <line x1="21" x2="16.65" y1="21" y2="16.65"/>
                                                <line x1="11" x2="11" y1="8" y2="14"/>
                                                <line x1="8" x2="14" y1="11" y2="11"/>
                                            </svg>
                                        </button>
                                        <button class="btn" onclick="scaleLayer(1.0)" title="リセット">リセット</button>
                                    </div>
                                </div>

                                <!-- 回転調整 -->
                                <div class="mb-3">
                                    <label class="form-label">回転調整</label>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button class="btn" onclick="rotateLayer(-15)" title="左回転">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                                <path d="M3 3v5h5"/>
                                            </svg>
                                        </button>
                                        <button class="btn" onclick="rotateLayer(15)" title="右回転">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/>
                                                <path d="M21 3v5h-5"/>
                                            </svg>
                                        </button>
                                        <button class="btn" onclick="rotateLayer(0, true)" title="回転をリセット">リセット</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="noTransformSelected" class="text-center text-muted">
                                レイヤーを選択してください
                            </div>
                        </div>

                        <!-- PNG出力サイズ選択 -->
                        <div class="export-section mt-4">
                            <label class="form-label">PNG出力サイズ</label>
                            <div class="size-selector mb-3">
                                <div class="d-flex flex-wrap gap-2 justify-content-center">
                                    <button class="size-btn active" data-size="1000" onclick="selectSize(this, 1000)">1000px</button>
                                    <button class="size-btn" data-size="1500" onclick="selectSize(this, 1500)">1500px</button>
                                    <button class="size-btn" data-size="2000" onclick="selectSize(this, 2000)">2000px</button>
                                    <button class="size-btn" data-size="2500" onclick="selectSize(this, 2500)">2500px</button>
                                    <button class="size-btn" data-size="3000" onclick="selectSize(this, 3000)">3000px</button>
                                </div>
                            </div>
                            <div class="text-center mb-3">
                                <small class="text-muted">選択されたサイズ: <span id="selectedSize">1000</span>px × <span id="selectedSizeY">1000</span>px</small>
                            </div>
                        </div>

                        <!-- 操作ボタン -->
                        <div class="action-buttons mt-4">
                            <button class="action-btn" onclick="exportToPNG()">PNGで保存</button>
                            <button class="action-btn" onclick="clearAll()">全て削除</button>
                            <button class="action-btn" onclick="reloadMaterials()">素材を再読み込み</button>
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
        let selectedPngSize = 1000; // デフォルト1000px

        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            loadFromStorage();
            updateLayersList();
            // 初期背景色の設定
            changeBackground(currentBackground);
        });

        // タブ切り替え
        function switchTab(tabName) {
            // タブアイコンの状態更新
            document.querySelectorAll('.tab-icon').forEach(icon => {
                icon.classList.remove('active');
            });
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

            // パネルの表示切り替え
            document.querySelectorAll('.svg-controls').forEach(panel => {
                panel.style.display = 'none';
            });
            document.getElementById(`${tabName}Tab`).style.display = 'block';
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
                                x: Math.random() * 200 + 100, // ランダム位置
                                y: Math.random() * 200 + 100,
                                scale: 1,
                                rotation: 0
                            },
                            visible: true
                        };

                        layers.push(layer);
                        renderLayer(layer);
                        updateLayersList();
                        saveToStorage();

                        // 素材タブから自動的にレイヤータブに切り替え
                        setTimeout(() => switchTab('layers'), 100);
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

            // オブジェクトの境界ボックスを取得してから変形を適用
            const bbox = layerGroup.getBBox();
            const centerX = bbox.x + bbox.width / 2;
            const centerY = bbox.y + bbox.height / 2;

            // 変形を適用（回転の中心をオブジェクトの中心にする）
            const transform = `translate(${layer.transform.x}, ${layer.transform.y}) scale(${layer.transform.scale}) rotate(${layer.transform.rotation}, ${centerX}, ${centerY})`;
            layerGroup.setAttribute('transform', transform);

            // クリックイベントを追加
            layerGroup.style.cursor = 'pointer';
            layerGroup.addEventListener('click', () => selectLayer(layer.id));
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

                    // オブジェクトの境界ボックスを取得してから変形を適用
                    canvas.appendChild(layerGroup);
                    const bbox = layerGroup.getBBox();
                    const centerX = bbox.x + bbox.width / 2;
                    const centerY = bbox.y + bbox.height / 2;

                    // 変形を適用（回転の中心をオブジェクトの中心にする）
                    const transform = `translate(${layer.transform.x}, ${layer.transform.y}) scale(${layer.transform.scale}) rotate(${layer.transform.rotation}, ${centerX}, ${centerY})`;
                    layerGroup.setAttribute('transform', transform);

                    // クリックイベントを追加
                    layerGroup.style.cursor = 'pointer';
                    layerGroup.addEventListener('click', () => selectLayer(layer.id));
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

            // レイヤーリストの選択状態を更新
            updateLayersList();

            // 変形コントロールを表示
            updateTransformControls();
        }

        // レイヤーリストを更新
        function updateLayersList() {
            const layersList = document.getElementById('layersList');
            
            if (layers.length === 0) {
                layersList.innerHTML = `
                    <div class="text-center text-muted py-4">
                        まだレイヤーがありません<br>
                        素材を追加してください
                    </div>
                `;
                return;
            }

            // レイヤーを逆順で表示（一番上が前面）
            const reversedLayers = [...layers].reverse();
            layersList.innerHTML = reversedLayers.map((layer, index) => {
                const layerIndex = layers.length - 1 - index; // 元配列でのインデックス
                const canMoveUp = layerIndex < layers.length - 1;
                const canMoveDown = layerIndex > 0;
                
                return `
                <div class="layer-item ${activeLayerId === layer.id ? 'active' : ''}" 
                     onclick="selectLayer(${layer.id})">
                    <div class="layer-info">
                        <strong>${layer.title}</strong>
                        <div class="text-muted" style="font-size: 0.75rem;">
                            位置: (${Math.round(layer.transform.x)}, ${Math.round(layer.transform.y)}) | 
                            サイズ: ${Math.round(layer.transform.scale * 100)}% | 
                            回転: ${layer.transform.rotation}°
                        </div>
                    </div>
                    <div class="layer-controls">
                        <button class="layer-btn" onclick="event.stopPropagation(); moveLayerUp(${layer.id})" 
                                title="前面に移動" ${!canMoveUp ? 'disabled' : ''}>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-up"><path d="M9 13a1 1 0 0 0-1-1H5.061a1 1 0 0 1-.75-1.811l6.836-6.835a1.207 1.207 0 0 1 1.707 0l6.835 6.835a1 1 0 0 1-.75 1.811H16a1 1 0 0 0-1 1v6a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1z"/></svg>
                        </button>
                        <button class="layer-btn" onclick="event.stopPropagation(); moveLayerDown(${layer.id})" 
                                title="背面に移動" ${!canMoveDown ? 'disabled' : ''}>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-big-down"><path d="M15 11a1 1 0 0 0 1 1h2.939a1 1 0 0 1 .75 1.811l-6.835 6.836a1.207 1.207 0 0 1-1.707 0L4.31 13.81a1 1 0 0 1 .75-1.811H8a1 1 0 0 0 1-1V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1z"/></svg>
                        </button>
                        <button class="layer-btn" onclick="event.stopPropagation(); toggleLayerVisibility(${layer.id})" 
                                title="${layer.visible ? '非表示' : '表示'}">
                            ${layer.visible ? 
                                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>' : 
                                '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye-closed"><path d="m15 18-.722-3.25"/><path d="M2 8a10.645 10.645 0 0 0 20 0"/><path d="m20 15-1.726-2.05"/><path d="m4 15 1.726-2.05"/><path d="m9 18 .722-3.25"/></svg>'
                            }
                        </button>
                        <button class="layer-btn" onclick="event.stopPropagation(); removeLayer(${layer.id})" 
                                title="削除">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c0-1 1-2 2-2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
                        </button>
                    </div>
                </div>
                `;
            }).join('');
        }

        // レイヤーの表示/非表示切り替え
        function toggleLayerVisibility(layerId) {
            const layer = layers.find(l => l.id === layerId);
            if (layer) {
                layer.visible = !layer.visible;
                renderLayer(layer);
                updateLayersList();
                saveToStorage();
            }
        }

        // レイヤー削除
        function removeLayer(layerId) {
            const layerIndex = layers.findIndex(l => l.id === layerId);
            if (layerIndex !== -1) {
                layers.splice(layerIndex, 1);
                
                // DOM要素も削除
                const layerElement = document.getElementById(`layer-${layerId}`);
                if (layerElement) {
                    layerElement.remove();
                }

                // アクティブレイヤーが削除された場合
                if (activeLayerId === layerId) {
                    activeLayerId = null;
                    updateTransformControls();
                }

                updateLayersList();
                saveToStorage();
            }
        }

        // レイヤーを前面に移動
        function moveLayerUp(layerId) {
            const layerIndex = layers.findIndex(l => l.id === layerId);
            if (layerIndex < layers.length - 1) {
                // 配列内で1つ後ろに移動（表示上は前面に移動）
                const layer = layers[layerIndex];
                layers.splice(layerIndex, 1);
                layers.splice(layerIndex + 1, 0, layer);
                
                renderAllLayers();
                updateLayersList();
                saveToStorage();
            }
        }

        // レイヤーを背面に移動
        function moveLayerDown(layerId) {
            const layerIndex = layers.findIndex(l => l.id === layerId);
            if (layerIndex > 0) {
                // 配列内で1つ前に移動（表示上は背面に移動）
                const layer = layers[layerIndex];
                layers.splice(layerIndex, 1);
                layers.splice(layerIndex - 1, 0, layer);
                
                renderAllLayers();
                updateLayersList();
                saveToStorage();
            }
        }

        // 変形コントロールを更新
        function updateTransformControls() {
            const transformControls = document.getElementById('transformControls');
            const noTransformSelected = document.getElementById('noTransformSelected');
            const selectedLayerName = document.getElementById('selectedLayerName');
            
            // 色変更パネルの要素も取得
            const colorControls = document.getElementById('colorControls');
            const noColorSelected = document.getElementById('noColorSelected');
            const selectedColorLayerName = document.getElementById('selectedColorLayerName');

            if (activeLayerId) {
                const layer = layers.find(l => l.id === activeLayerId);
                if (layer) {
                    // 変形パネル
                    transformControls.style.display = 'block';
                    noTransformSelected.style.display = 'none';
                    selectedLayerName.textContent = layer.title;
                    
                    // 色変更パネル
                    colorControls.style.display = 'block';
                    noColorSelected.style.display = 'none';
                    selectedColorLayerName.textContent = layer.title;
                }
            } else {
                // 変形パネル
                transformControls.style.display = 'none';
                noTransformSelected.style.display = 'block';
                
                // 色変更パネル
                colorControls.style.display = 'none';
                noColorSelected.style.display = 'block';
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
            updateLayersList();
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
            updateLayersList();
            saveToStorage();
        }

        // レイヤースケール変更
        function scaleLayer(scale) {
            if (!activeLayerId) return;
            
            const layer = layers.find(l => l.id === activeLayerId);
            if (!layer) return;

            layer.transform.scale = Math.max(0.1, Math.min(3, scale));

            renderLayer(layer);
            updateLayersList();
            saveToStorage();
        }

        // レイヤースケール段階調整
        function scaleLayerStep(step) {
            if (!activeLayerId) return;
            
            const layer = layers.find(l => l.id === activeLayerId);
            if (!layer) return;

            // 現在のスケールに段階的な調整を加算
            const newScale = layer.transform.scale + step;
            
            // スケールの範囲制限（0.1〜3.0）
            layer.transform.scale = Math.max(0.1, Math.min(3.0, newScale));

            renderLayer(layer);
            updateLayersList();
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
            updateLayersList();
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
            const excludeGrayBlack = document.getElementById('excludeGrayBlack').checked;
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
            const excludeGrayBlack = document.getElementById('excludeGrayBlack').checked;
            
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
        function changeBackground(color) {
            currentBackground = color;
            const background = document.getElementById('canvasBackground');
            
            if (color === 'transparent') {
                background.setAttribute('fill-opacity', '0');
            } else {
                background.setAttribute('fill', color);
                background.setAttribute('fill-opacity', '1');
            }

            // ボタンの状態更新
            const backgroundTab = document.getElementById('backgroundTab');
            if (backgroundTab) {
                // 全てのボタンからactiveクラスを削除
                backgroundTab.querySelectorAll('.bg-color-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // 対応する色のボタンをアクティブに
                const activeBtn = backgroundTab.querySelector(`[data-color="${color}"]`);
                if (activeBtn) {
                    activeBtn.classList.add('active');
                }
                
                // カスタムカラーピッカーの値も更新
                const customColorPicker = document.getElementById('customBgColor');
                if (customColorPicker && color !== 'transparent') {
                    customColorPicker.value = color;
                }
            }

            saveToStorage();
        }

        // PNG出力サイズ選択
        function selectSize(buttonElement, size) {
            selectedPngSize = size;
            
            // 全てのサイズボタンからactiveクラスを削除
            document.querySelectorAll('.size-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // クリックされたボタンにactiveクラスを追加
            buttonElement.classList.add('active');
            
            // 選択されたサイズを表示更新
            document.getElementById('selectedSize').textContent = size;
            document.getElementById('selectedSizeY').textContent = size;
            
            console.log('PNG出力サイズを変更:', size + 'px');
        }

        // PNG出力
        function exportToPNG() {
            if (layers.length === 0) {
                alert('レイヤーがありません。素材を追加してください。');
                return;
            }

            console.log('PNG出力開始 - 現在の背景色:', currentBackground);
            console.log('PNG出力サイズ:', selectedPngSize + 'px');

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
                
                updateLayersList();
                updateTransformControls();
                saveToStorage();
            }
        }

        // 素材を再読み込み
        function reloadMaterials() {
            if (confirm('新しい素材を読み込みますか？（現在の作業内容は保持されます）')) {
                // ローディング表示
                const materialsGrid = document.getElementById('materialsGrid');
                materialsGrid.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">読み込み中...</span></div><div class="mt-2">新しい素材を読み込み中...</div></div>';
                
                // AJAXで新しい素材を取得
                fetch('/compose/api/reload-materials.php', {
                    method: 'GET',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateMaterialsGrid(data.materials);
                    } else {
                        console.error('素材の読み込みに失敗しました:', data.error);
                        materialsGrid.innerHTML = '<div class="text-center text-danger py-4">素材の読み込みに失敗しました。ページを再読み込みしてください。</div>';
                    }
                })
                .catch(error => {
                    console.error('エラー:', error);
                    materialsGrid.innerHTML = '<div class="text-center text-danger py-4">通信エラーが発生しました。ページを再読み込みしてください。</div>';
                });
            }
        }

        // 素材グリッドを更新
        function updateMaterialsGrid(materials) {
            const materialsGrid = document.getElementById('materialsGrid');
            let html = '';
            
            materials.forEach(material => {
                const imagePath = material.webp_medium_path || material.image_path;
                html += `
                    <div class="material-item" 
                         data-material-id="${escapeHtml(material.id)}"
                         data-svg-path="${escapeHtml(material.svg_path)}"
                         data-title="${escapeHtml(material.title)}"
                         onclick="addMaterialToCanvas(this)">
                        <img src="/${escapeHtml(imagePath)}" 
                             alt="${escapeHtml(material.title)}"
                             loading="lazy">
                    </div>
                `;
            });
            
            materialsGrid.innerHTML = html;
        }

        // HTMLエスケープユーティリティ関数
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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
                    
                    updateLayersList();
                    updateTransformControls();
                }
            } catch (error) {
                console.error('データ読み込みエラー:', error);
            }
        }
    </script>
</body>
</html>
