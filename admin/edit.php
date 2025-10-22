<?php
require_once '../config.php';
startAdminSession(); // 管理画面専用セッション開始
requireLogin();

// 管理画面はキャッシュ無効化
setNoCache();

$id = $_GET['id'] ?? '';
if (empty($id) || !is_numeric($id)) {
    header('Location: /admin/');
    exit;
}

$pdo = getDB();

// タグデータを取得
$tags = getAllTags($pdo);
$categories = getAllCategories($pdo);

// 素材の取得
$stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
$stmt->execute([$id]);
$material = $stmt->fetch();

if (!$material) {
    header('Location: /admin/');
    exit;
}

// デバッグ: SVGパス情報をログに出力
if (isset($material['svg_path'])) {
    error_log("SVG Path from DB: " . $material['svg_path']);
} else {
    error_log("SVG Path column not found in material data");
}

// 素材に関連付けられたタグを取得
$materialTags = getMaterialTags($id, $pdo);
$materialTagIds = array_column($materialTags, 'id');

$error = '';
$success = '';

if ($_POST) {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $search_keywords = trim($_POST['search_keywords'] ?? '');
    $tag_ids = $_POST['tag_ids'] ?? [];
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    
    // バリデーション
    if (empty($title) || empty($slug)) {
        $error = 'タイトルとスラッグは必須です。';
    } else {
        // スラッグの重複チェック（自分以外）
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'そのスラッグは既に使用されています。';
        } else {
            // 新しい画像がアップロードされた場合
            $imagePath = $material['image_path'];
            $webpSmallPath = $material['webp_small_path'] ?? null;
            $webpMediumPath = $material['webp_medium_path'] ?? null;
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // 古い画像ファイルを削除
                $oldImagePath = __DIR__ . '/../' . $material['image_path'];
                $oldWebpSmallPath = $material['webp_small_path'] ? __DIR__ . '/../' . $material['webp_small_path'] : null;
                $oldWebpMediumPath = $material['webp_medium_path'] ? __DIR__ . '/../' . $material['webp_medium_path'] : null;
                
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
                if ($oldWebpSmallPath && file_exists($oldWebpSmallPath)) {
                    unlink($oldWebpSmallPath);
                }
                if ($oldWebpMediumPath && file_exists($oldWebpMediumPath)) {
                    unlink($oldWebpMediumPath);
                }
                
                // 新しい画像をアップロード（初回投稿日のフォルダを使用）
                $uploadResult = uploadImage($_FILES['image'], $slug, $material['created_at']);
                if ($uploadResult) {
                    $imagePath = $uploadResult['original'];
                    $webpSmallPath = $uploadResult['webp_small'];
                    $webpMediumPath = $uploadResult['webp_medium'];
                } else {
                    $error = '画像のアップロードに失敗しました。';
                }
            }
            
            // SVGファイルの処理
            $svgPath = $material['svg_path'] ?? null;
            
            // SVGファイル削除処理
            if (isset($_POST['remove_svg']) && $_POST['remove_svg'] == '1' && $svgPath) {
                $oldSvgPath = __DIR__ . '/../' . $svgPath;
                if (file_exists($oldSvgPath)) {
                    unlink($oldSvgPath);
                }
                $svgPath = null;
            }
            
            // SVGファイルアップロード処理
            if (isset($_FILES['svg_file']) && $_FILES['svg_file']['error'] === UPLOAD_ERR_OK) {
                try {
                    // 新しいSVGファイルをアップロード（初回投稿日のフォルダを使用、古いファイルは自動削除）
                    $svgUploadResult = uploadSvgFile($_FILES['svg_file'], $slug, false, $svgPath, $material['created_at']); // 開発中は寛容なセキュリティチェック
                    if ($svgUploadResult) {
                        $svgPath = $svgUploadResult;
                    }
                } catch (Exception $e) {
                    $error = 'SVGファイルのアップロードエラー: ' . $e->getMessage();
                }
            }

            // AI製品画像のアップロード処理
            $aiProductImagePath = $material['ai_product_image_path'] ?? null;
            $aiProductImageDescription = trim($_POST['ai_product_image_description'] ?? '');
            
            if (isset($_FILES['ai_product_image']) && $_FILES['ai_product_image']['error'] === UPLOAD_ERR_OK) {
                try {
                    // 古いAI製品画像ファイルを削除
                    if ($aiProductImagePath) {
                        $oldAiProductImagePath = __DIR__ . '/../' . $aiProductImagePath;
                        if (file_exists($oldAiProductImagePath)) {
                            unlink($oldAiProductImagePath);
                        }
                    }
                    
                    // 新しいAI製品画像をアップロード（初回投稿日のフォルダを使用）
                    $aiUploadResult = uploadImage($_FILES['ai_product_image'], $slug . '_ai_product', $material['created_at']);
                    if ($aiUploadResult) {
                        $aiProductImagePath = $aiUploadResult['original'];
                    } else {
                        $error = 'AI製品画像のアップロードに失敗しました。';
                    }
                } catch (Exception $e) {
                    $error = 'AI製品画像のアップロードエラー: ' . $e->getMessage();
                }
            } elseif (isset($_FILES['ai_product_image']) && $_FILES['ai_product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                // ファイルエラーの詳細を表示
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'ファイルサイズがPHP設定値を超えています',
                    UPLOAD_ERR_FORM_SIZE => 'ファイルサイズがフォーム設定値を超えています',
                    UPLOAD_ERR_PARTIAL => 'ファイルが部分的にしかアップロードされませんでした',
                    UPLOAD_ERR_NO_TMP_DIR => '一時ディレクトリが見つかりません',
                    UPLOAD_ERR_CANT_WRITE => 'ディスクへの書き込みに失敗しました',
                    UPLOAD_ERR_EXTENSION => 'PHPの拡張機能によってアップロードが停止されました'
                ];
                $errorCode = $_FILES['ai_product_image']['error'];
                $currentLimits = 'upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size');
                $error = 'AI製品画像アップロードエラー: ' . ($uploadErrors[$errorCode] ?? "不明なエラー (Code: $errorCode)") . " (現在の制限: $currentLimits)";
            }
            
            if (empty($error)) {
                // svg_pathカラムの存在確認
                $hasSvgColumn = false;
                try {
                    $checkStmt = $pdo->query("SHOW COLUMNS FROM materials LIKE 'svg_path'");
                    $hasSvgColumn = $checkStmt->rowCount() > 0;
                } catch (Exception $e) {
                    // カラムチェックに失敗した場合は無視
                }
                
                // データベースを更新
                if ($hasSvgColumn) {
                    $stmt = $pdo->prepare("
                        UPDATE materials 
                        SET title = ?, slug = ?, description = ?, search_keywords = ?, 
                            image_path = ?, webp_small_path = ?, webp_medium_path = ?, svg_path = ?, category_id = ?,
                            ai_product_image_path = ?, ai_product_image_description = ?
                        WHERE id = ?
                    ");
                    
                    $executeParams = [
                        $title, $slug, $description, $search_keywords,
                        $imagePath, $webpSmallPath, $webpMediumPath, $svgPath, $category_id, 
                        $aiProductImagePath, $aiProductImageDescription, $id
                    ];
                } else {
                    // svg_pathカラムが存在しない場合は除外して更新
                    $stmt = $pdo->prepare("
                        UPDATE materials 
                        SET title = ?, slug = ?, description = ?, search_keywords = ?, 
                            image_path = ?, webp_small_path = ?, webp_medium_path = ?, category_id = ?,
                            ai_product_image_path = ?, ai_product_image_description = ?
                        WHERE id = ?
                    ");
                    
                    $executeParams = [
                        $title, $slug, $description, $search_keywords,
                        $imagePath, $webpSmallPath, $webpMediumPath, $category_id, 
                        $aiProductImagePath, $aiProductImageDescription, $id
                    ];
                }
                
                if ($stmt->execute($executeParams)) {
                    // タグを更新
                    addMaterialTags($id, $tag_ids, $pdo);
                    
                    $success = '素材が正常に更新されました。';
                    // 更新された情報を再取得
                    $stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
                    $stmt->execute([$id]);
                    $material = $stmt->fetch();
                    
                    // 更新されたタグ情報も再取得
                    $materialTags = getMaterialTags($id, $pdo);
                    $materialTagIds = array_column($materialTags, 'id');
                } else {
                    $error = 'データベースの更新に失敗しました。';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>素材編集 - maruttoart 管理画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #ffffff;
        }
        .sidebar {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .main-content {
            background-color: #ffffff;
        }
        .preview-container {
            max-width: 300px;
            margin-top: 10px;
        }
        .preview-image {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .svg-preview {
            margin-top: 10px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background-color: #f8f9fa;
        }
        .svg-preview-container {
            text-align: center;
            margin-bottom: 10px;
        }
        .svg-preview-info {
            text-align: center;
        }
        .current-svg-info {
            padding: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- サイドバー -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
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
                    <h1 class="h2">素材編集</h1>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= h($error) ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <?= h($success) ?>
                    <a href="/detail/<?= h($material['slug']) ?>" class="btn btn-sm btn-outline-success ms-2" target="_blank">確認する</a>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">タイトル *</label>
                                <input type="text" class="form-control" id="title" name="title" required value="<?= h($material['title']) ?>">
                            </div>

                            <div class="mb-3">
                                <label for="slug" class="form-label">スラッグ *</label>
                                <input type="text" class="form-control" id="slug" name="slug" required value="<?= h($material['slug']) ?>" placeholder="例: peach-illustration">
                                <div class="form-text">URLで使用される識別子です。英数字とハイフンのみ使用可能です。</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">説明</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?= h($material['description']) ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="category_id" class="form-label">カテゴリ</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">カテゴリを選択してください</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                                <?= $material['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                            <?= h($category['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">素材を分類するカテゴリを1つ選択してください</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">タグ選択</label>
                                <div class="row">
                                    <?php foreach ($tags as $tag): ?>
                                        <div class="col-md-4 col-sm-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="tag_ids[]" 
                                                       id="tag_<?= $tag['id'] ?>" value="<?= $tag['id'] ?>"
                                                       <?= in_array($tag['id'], $materialTagIds) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="tag_<?= $tag['id'] ?>">
                                                    <?= h($tag['name']) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">複数のタグを選択できます</div>
                            </div>

                            <div class="mb-3">
                                <label for="search_keywords" class="form-label">検索キーワード</label>
                                <input type="text" class="form-control" id="search_keywords" name="search_keywords" value="<?= h($material['search_keywords'] ?? '') ?>" placeholder="もも,桃,peach,果物,fruit,ピンク,pink">
                                <div class="form-text">日本語・英語問わず、カンマで区切って入力してください</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">現在の画像</label>
                                <div class="preview-container">
                                    <img src="/<?= h($material['webp_small_path'] ?? $material['image_path']) ?>" class="preview-image" alt="<?= h($material['title']) ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">新しい画像ファイル</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*" onchange="previewNewImage(this)">
                                <div class="form-text">PNG, JPEG, GIF対応。選択すると現在の画像が置き換わります。</div>
                                
                                <div class="preview-container">
                                    <img id="newImagePreview" class="preview-image" style="display: none;" alt="新しい画像プレビュー">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="svg_file" class="form-label">SVGファイル（オプション）</label>
                                
                                <!-- デバッグ情報 -->
                                <?php if (isset($_GET['debug'])): ?>
                                <div class="alert alert-info">
                                    <strong>SVGデバッグ情報:</strong><br>
                                    <table class="table table-sm">
                                        <tr><td>SVG Path:</td><td><?= isset($material['svg_path']) ? h($material['svg_path']) : '<em>(not set)</em>' ?></td></tr>
                                        <tr><td>Empty check:</td><td><?= empty($material['svg_path']) ? 'true' : 'false' ?></td></tr>
                                        <tr><td>File exists:</td><td><?= isset($material['svg_path']) && file_exists(__DIR__ . '/../' . $material['svg_path']) ? 'true' : 'false' ?></td></tr>
                                        <tr><td>Full file path:</td><td><?= isset($material['svg_path']) ? h(__DIR__ . '/../' . $material['svg_path']) : '<em>(not available)</em>' ?></td></tr>
                                    </table>
                                    <a href="/admin/svg_debug.php" class="btn btn-sm btn-info">詳細デバッグページを開く</a>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($material['svg_path'])): ?>
                                    <div class="current-svg-info mb-2">
                                        <div class="d-flex align-items-center justify-content-between bg-light p-2 rounded">
                                            <small class="text-muted">
                                                現在のSVG: <a href="/<?= h($material['svg_path']) ?>" target="_blank" class="text-decoration-none">
                                                    <i class="bi bi-file-earmark-image"></i> <?= basename($material['svg_path']) ?>
                                                </a>
                                            </small>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="remove_svg" id="remove_svg" value="1">
                                                <label class="form-check-label text-danger" for="remove_svg">
                                                    <small>削除</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="svg_file" name="svg_file" accept=".svg,image/svg+xml" onchange="previewSvg(this)">
                                <div class="form-text">
                                    SVGファイルをアップロードすると、詳細画面でベクター形式でダウンロードできます。<br>
                                    <small class="text-warning">※ 新しいファイルをアップロードすると、既存のSVGファイルは自動的に置き換えられます。</small>
                                </div>
                                
                                <div class="preview-container">
                                    <div id="svgPreview" class="svg-preview" style="display: none;">
                                        <div class="svg-preview-container"></div>
                                        <div class="svg-preview-info"></div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- AI生成製品画像セクション -->
                            <h5 class="mb-3">AI生成製品画像</h5>
                            
                            <?php if (!empty($material['ai_product_image_path'])): ?>
                            <div class="mb-3">
                                <label class="form-label">現在のAI生成製品画像</label>
                                <div class="preview-container">
                                    <img src="/<?= h($material['ai_product_image_path']) ?>" class="preview-image" alt="AI生成製品画像">
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="ai_product_image" class="form-label">AI生成製品画像ファイル</label>
                                <input type="file" class="form-control" id="ai_product_image" name="ai_product_image" accept="image/*" onchange="previewAiProductImage(this)">
                                <div class="form-text">このイラストを使用したAI生成製品の画像をアップロードできます。PNG, JPEG, GIF対応。</div>
                                
                                <div class="preview-container">
                                    <img id="aiProductImagePreview" class="preview-image" style="display: none;" alt="AI生成製品画像プレビュー">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="ai_product_image_description" class="form-label">AI生成製品画像の説明</label>
                                <textarea class="form-control" id="ai_product_image_description" name="ai_product_image_description" rows="3" placeholder="AI生成製品の説明や使用方法などを入力してください"><?= h($material['ai_product_image_description'] ?? '') ?></textarea>
                                <div class="form-text">AI生成製品画像の説明文を入力してください。</div>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between">
                                <a href="/admin/" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> 戻る
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> 更新
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewNewImage(input) {
            const preview = document.getElementById('newImagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewAiProductImage(input) {
            const preview = document.getElementById('aiProductImagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewSvg(input) {
            const preview = document.getElementById('svgPreview');
            const container = preview.querySelector('.svg-preview-container');
            const info = preview.querySelector('.svg-preview-info');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const svgContent = e.target.result;
                    container.innerHTML = svgContent;
                    
                    // SVGのサイズ情報を表示
                    const svgElement = container.querySelector('svg');
                    if (svgElement) {
                        const width = svgElement.getAttribute('width') || 'auto';
                        const height = svgElement.getAttribute('height') || 'auto';
                        const viewBox = svgElement.getAttribute('viewBox') || 'none';
                        
                        info.innerHTML = `
                            <small class="text-muted">
                                サイズ: ${width} × ${height} | ViewBox: ${viewBox} | ファイルサイズ: ${(file.size / 1024).toFixed(1)}KB
                            </small>
                        `;
                        
                        // プレビュー用のスタイルを適用
                        svgElement.style.maxWidth = '200px';
                        svgElement.style.maxHeight = '200px';
                        svgElement.style.border = '1px solid #dee2e6';
                        svgElement.style.borderRadius = '4px';
                        svgElement.style.padding = '8px';
                        svgElement.style.backgroundColor = '#f8f9fa';
                    }
                    
                    preview.style.display = 'block';
                }
                
                reader.readAsText(file);
            } else {
                preview.style.display = 'none';
                container.innerHTML = '';
                info.innerHTML = '';
            }
        }
    </script>
</body>
</html>
