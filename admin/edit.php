<?php
require_once '../config.php';
requireLogin();

$id = $_GET['id'] ?? '';
if (empty($id) || !is_numeric($id)) {
    header('Location: /admin/');
    exit;
}

$pdo = getDB();

// 素材の取得
$stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
$stmt->execute([$id]);
$material = $stmt->fetch();

if (!$material) {
    header('Location: /admin/');
    exit;
}

$error = '';
$success = '';

if ($_POST) {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $youtube_url = trim($_POST['youtube_url'] ?? '');
    $search_keywords_en = trim($_POST['search_keywords_en'] ?? '');
    $search_keywords_jp = trim($_POST['search_keywords_jp'] ?? '');
    
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
            $webpPath = $material['webp_path'];
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // 古い画像ファイルを削除
                $oldImagePath = __DIR__ . '/../' . $material['image_path'];
                $oldWebpPath = __DIR__ . '/../' . $material['webp_path'];
                
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
                if (file_exists($oldWebpPath)) {
                    unlink($oldWebpPath);
                }
                
                // 新しい画像をアップロード
                $uploadResult = uploadImage($_FILES['image'], $slug);
                if ($uploadResult) {
                    $imagePath = $uploadResult['original'];
                    $webpPath = $uploadResult['webp'];
                } else {
                    $error = '画像のアップロードに失敗しました。';
                }
            }
            
            if (empty($error)) {
                // データベースを更新
                $stmt = $pdo->prepare("
                    UPDATE materials 
                    SET title = ?, slug = ?, description = ?, youtube_url = ?, 
                        search_keywords_en = ?, search_keywords_jp = ?, 
                        image_path = ?, webp_path = ?
                    WHERE id = ?
                ");
                
                if ($stmt->execute([
                    $title, $slug, $description, $youtube_url,
                    $search_keywords_en, $search_keywords_jp,
                    $imagePath, $webpPath, $id
                ])) {
                    $success = '素材が正常に更新されました。';
                    // 更新された情報を再取得
                    $stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
                    $stmt->execute([$id]);
                    $material = $stmt->fetch();
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
                            <div class="row">
                                <div class="col-md-8">
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
                                        <label for="youtube_url" class="form-label">YouTube URL</label>
                                        <input type="url" class="form-control" id="youtube_url" name="youtube_url" value="<?= h($material['youtube_url']) ?>" placeholder="https://www.youtube.com/watch?v=...">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="search_keywords_en" class="form-label">検索キーワード（英語）</label>
                                                <input type="text" class="form-control" id="search_keywords_en" name="search_keywords_en" value="<?= h($material['search_keywords_en']) ?>" placeholder="peach,fruit,pink">
                                                <div class="form-text">カンマで区切って入力</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="search_keywords_jp" class="form-label">検索キーワード（日本語）</label>
                                                <input type="text" class="form-control" id="search_keywords_jp" name="search_keywords_jp" value="<?= h($material['search_keywords_jp']) ?>" placeholder="もも,果物,ピンク">
                                                <div class="form-text">カンマで区切って入力</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">現在の画像</label>
                                        <div class="preview-container">
                                            <img src="/<?= h($material['webp_path']) ?>" class="preview-image" alt="<?= h($material['title']) ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="image" class="form-label">新しい画像ファイル</label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*" onchange="previewNewImage(this)">
                                        <div class="form-text">PNG, JPEG, GIF対応。選択すると現在の画像が置き換わります。</div>
                                    </div>

                                    <div class="preview-container">
                                        <img id="newImagePreview" class="preview-image" style="display: none;" alt="新しい画像プレビュー">
                                    </div>
                                </div>
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
    </script>
</body>
</html>
