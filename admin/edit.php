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

// 画材データを取得
$stmt = $pdo->prepare("SELECT * FROM art_materials WHERE is_active = 1 ORDER BY sort_order, name");
$stmt->execute();
$art_materials = $stmt->fetchAll();

// 素材の取得
$stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
$stmt->execute([$id]);
$material = $stmt->fetch();

if (!$material) {
    header('Location: /admin/');
    exit;
}

// 素材に関連付けられたタグを取得
$materialTags = getMaterialTags($id, $pdo);
$materialTagIds = array_column($materialTags, 'id');

// 素材に関連付けられた画材を取得
$stmt = $pdo->prepare("SELECT art_material_id FROM material_art_materials WHERE material_id = ?");
$stmt->execute([$id]);
$materialArtMaterials = $stmt->fetchAll(PDO::FETCH_COLUMN);
$materialArtMaterialIds = $materialArtMaterials;

$error = '';
$success = '';

if ($_POST) {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $youtube_url = trim($_POST['youtube_url'] ?? '');
    $video_publish_date = trim($_POST['video_publish_date'] ?? '');
    $search_keywords = trim($_POST['search_keywords'] ?? '');
    $tag_ids = $_POST['tag_ids'] ?? [];
    $art_material_ids = $_POST['art_material_ids'] ?? [];
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
                
                // 新しい画像をアップロード
                $uploadResult = uploadImage($_FILES['image'], $slug);
                if ($uploadResult) {
                    $imagePath = $uploadResult['original'];
                    $webpSmallPath = $uploadResult['webp_small'];
                    $webpMediumPath = $uploadResult['webp_medium'];
                } else {
                    $error = '画像のアップロードに失敗しました。';
                }
            }
            
            if (empty($error)) {
                // データベースを更新
                $stmt = $pdo->prepare("
                    UPDATE materials 
                    SET title = ?, slug = ?, description = ?, youtube_url = ?, video_publish_date = ?,
                        search_keywords = ?, 
                        image_path = ?, webp_small_path = ?, webp_medium_path = ?, category_id = ?
                    WHERE id = ?
                ");
                
                // video_publish_dateの処理
                $formatted_video_publish_date = null;
                if (!empty($video_publish_date)) {
                    $formatted_video_publish_date = date('Y-m-d H:i:s', strtotime($video_publish_date));
                }
                
                if ($stmt->execute([
                    $title, $slug, $description, $youtube_url, $formatted_video_publish_date,
                    $search_keywords,
                    $imagePath, $webpSmallPath, $webpMediumPath, $category_id, $id
                ])) {
                    // タグを更新
                    addMaterialTags($id, $tag_ids, $pdo);
                    
                    // 画材を更新
                    // 既存の画材関連付けを削除
                    $stmt = $pdo->prepare("DELETE FROM material_art_materials WHERE material_id = ?");
                    $stmt->execute([$id]);
                    
                    // 新しい画材を関連付け
                    if (!empty($art_material_ids)) {
                        foreach ($art_material_ids as $art_material_id) {
                            $stmt = $pdo->prepare("INSERT INTO material_art_materials (material_id, art_material_id) VALUES (?, ?)");
                            $stmt->execute([$id, $art_material_id]);
                        }
                    }
                    
                    $success = '素材が正常に更新されました。';
                    // 更新された情報を再取得
                    $stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
                    $stmt->execute([$id]);
                    $material = $stmt->fetch();
                    
                    // 更新されたタグ情報も再取得
                    $materialTags = getMaterialTags($id, $pdo);
                    $materialTagIds = array_column($materialTags, 'id');
                    
                    // 更新された画材情報も再取得
                    $stmt = $pdo->prepare("SELECT art_material_id FROM material_art_materials WHERE material_id = ?");
                    $stmt->execute([$id]);
                    $materialArtMaterialIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
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
                                <label class="form-label">画材選択</label>
                                <div class="row" id="artMaterialsContainer">
                                    <?php foreach ($art_materials as $material_item): ?>
                                        <div class="col-md-4 col-sm-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="art_material_ids[]" 
                                                       id="art_material_<?= $material_item['id'] ?>" value="<?= $material_item['id'] ?>"
                                                       <?= in_array($material_item['id'], $materialArtMaterialIds) ? 'checked' : '' ?>>
                                                <label class="form-check-label d-flex align-items-center" for="art_material_<?= $material_item['id'] ?>">
                                                    <?php if ($material_item['color_code']): ?>
                                                        <span class="badge me-2" style="background-color: <?= h($material_item['color_code']) ?>; width: 12px; height: 12px; border-radius: 50%;"></span>
                                                    <?php endif; ?>
                                                    <?= h($material_item['name']) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">使用した画材を複数選択できます（水彩、パステルなど）</div>
                            </div>

                            <div class="mb-3">
                                <label for="youtube_url" class="form-label">YouTube URL</label>
                                <input type="url" class="form-control" id="youtube_url" name="youtube_url" value="<?= h($material['youtube_url']) ?>" placeholder="https://www.youtube.com/watch?v=...">
                            </div>

                            <div class="mb-3">
                                <label for="video_publish_date" class="form-label">動画公開日時</label>
                                <?php 
                                $video_publish_value = '';
                                if (!empty($material['video_publish_date'])) {
                                    $video_publish_value = date('Y-m-d\TH:i', strtotime($material['video_publish_date']));
                                }
                                ?>
                                <input type="datetime-local" class="form-control" id="video_publish_date" name="video_publish_date" value="<?= h($video_publish_value) ?>">
                                <div class="form-text">
                                    指定された日時になると、カード一覧に動画アイコンが表示され、詳細ページでも動画が表示されます。<br>
                                    空の場合は即座に動画が表示されます。
                                </div>
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
