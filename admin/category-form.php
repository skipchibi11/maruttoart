<?php
require_once '../config.php';
requireLogin();
setNoCache();

$pdo = getDB();
$isEdit = isset($_GET['id']);
$category = null;

if ($isEdit) {
    $category = getCategoryById($_GET['id'], $pdo);
    if (!$category) {
        header('Location: categories.php?error=category_not_found');
        exit;
    }
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'CSRFトークンが無効です。';
    } else {
        $data = [
            'title' => trim($_POST['title']),
            'slug' => trim($_POST['slug']),
            'sort_order' => (int)($_POST['sort_order'] ?? 0)
        ];
        
        // 画像アップロード処理
        $uploadedImagePath = null;
        if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
            try {
                $uploadDir = '../uploads/categories/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileInfo = pathinfo($_FILES['category_image']['name']);
                $extension = strtolower($fileInfo['extension']);
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($extension, $allowedExtensions)) {
                    throw new Exception('画像ファイル（JPG、PNG、GIF、WebP）のみアップロード可能です。');
                }
                
                // ファイル名を生成（カテゴリスラッグ + タイムスタンプ）
                $fileName = $data['slug'] . '_' . time() . '.' . $extension;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['category_image']['tmp_name'], $filePath)) {
                    $uploadedImagePath = 'uploads/categories/' . $fileName;
                } else {
                    throw new Exception('ファイルのアップロードに失敗しました。');
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        // 既存画像を保持するかチェック
        if ($isEdit && !$uploadedImagePath && isset($_POST['keep_current_image'])) {
            $uploadedImagePath = $category['category_image_path'];
        }
        
        if ($uploadedImagePath) {
            $data['category_image_path'] = $uploadedImagePath;
        }
        
        if (!isset($error)) {
            try {
                if ($isEdit) {
                    updateCategory($category['id'], $data, $pdo);
                    header('Location: categories.php?success=updated');
                    exit;
                } else {
                    createCategory($data, $pdo);
                    header('Location: categories.php?success=created');
                    exit;
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
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
    <title><?= $isEdit ? 'カテゴリ編集' : 'カテゴリ作成' ?> - maruttoart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">maruttoart 管理画面</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">ダッシュボード</a>
                <a class="nav-link active" href="categories.php">カテゴリ</a>
                <a class="nav-link" href="tags.php">タグ</a>
                <a class="nav-link" href="logout.php">ログアウト</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>
                        <i class="bi bi-folder<?= $isEdit ? '-plus' : '' ?>"></i>
                        <?= $isEdit ? 'カテゴリ編集' : '新規カテゴリ作成' ?>
                    </h1>
                    <a href="categories.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> 戻る
                    </a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?= h($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <?= $isEdit ? 'カテゴリ情報を編集' : 'カテゴリ情報を入力' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">
                                            カテゴリ名（日本語） <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?= h($_POST['title'] ?? $category['title'] ?? '') ?>" 
                                               required maxlength="255" oninput="generateSlug()">
                                        <div class="form-text">表示されるカテゴリ名を入力してください。</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="slug" class="form-label">
                                            スラッグ <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="slug" name="slug" 
                                               value="<?= h($_POST['slug'] ?? $category['slug'] ?? '') ?>" 
                                               required maxlength="255" pattern="[a-z0-9\-]+"
                                               title="小文字の英数字とハイフンのみ使用可能">
                                        <div class="form-text">
                                            URL用の識別子です。小文字の英数字とハイフンのみ使用できます。
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="sort_order" class="form-label">表示順</label>
                                        <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                               value="<?= h($_POST['sort_order'] ?? $category['sort_order'] ?? 0) ?>" 
                                               min="0" step="1">
                                        <div class="form-text">数字が小さいほど上位に表示されます。</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="category_image" class="form-label">カテゴリ画像</label>
                                        <input type="file" class="form-control" id="category_image" name="category_image" 
                                               accept="image/jpeg,image/png,image/gif,image/webp">
                                        <div class="form-text">JPG、PNG、GIF、WebP形式の画像ファイル（推奨サイズ: 200x200px）</div>
                                        
                                        <?php if ($isEdit && !empty($category['category_image_path'])): ?>
                                        <div class="mt-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="keep_current_image" name="keep_current_image" checked>
                                                <label class="form-check-label" for="keep_current_image">
                                                    現在の画像を保持する
                                                </label>
                                            </div>
                                            <div class="current-image mt-2">
                                                <img src="/<?= h($category['category_image_path']) ?>" 
                                                     alt="現在のカテゴリ画像" 
                                                     style="max-width: 100px; height: auto; border-radius: 8px; border: 1px solid #ddd;">
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="categories.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> キャンセル
                                </a>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i>
                                    <?= $isEdit ? 'カテゴリを更新' : 'カテゴリを作成' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if ($isEdit): ?>
                <!-- カテゴリ情報 -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">カテゴリ情報</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>作成日:</strong> <?= date('Y年m月d日 H:i', strtotime($category['created_at'])) ?></p>
                                <p><strong>更新日:</strong> <?= date('Y年m月d日 H:i', strtotime($category['updated_at'])) ?></p>
                            </div>
                            <div class="col-md-6">
                                <?php $materialCount = count(getCategoryMaterials($category['id'], $pdo)); ?>
                                <p><strong>属する素材数:</strong> <?= $materialCount ?> 個</p>
                                <?php if ($materialCount > 0): ?>
                                <a href="index.php?category=<?= $category['id'] ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-images"></i>
                                    素材を確認
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // スラッグの自動生成
    function generateSlug() {
        const title = document.getElementById('title').value;
        const slug = document.getElementById('slug');
        
        // 既にスラッグが手動で入力されている場合はスキップ
        if (slug.dataset.manual) return;
        
        // 日本語を英語風に変換（簡単なマッピング）
        const japaneseToEnglish = {
            '果物': 'fruits',
            '自然': 'nature',
            '動物': 'animals',
            '乗り物': 'vehicles',
            '建物': 'buildings',
            '宇宙': 'space',
            '天気': 'weather',
            '花': 'flowers',
            '植物': 'plants',
            '食べ物': 'food',
            '飲み物': 'drinks',
            '道具': 'tools',
            '家具': 'furniture',
            'スポーツ': 'sports',
            '音楽': 'music',
            'ファッション': 'fashion',
            '季節': 'seasons',
            '祭り': 'festivals'
        };
        
        let slugValue = '';
        if (japaneseToEnglish[title]) {
            slugValue = japaneseToEnglish[title];
        } else {
            // 一般的な変換
            slugValue = title
                .toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
        
        if (slugValue) {
            slug.value = slugValue;
        }
    }
    
    // スラッグフィールドに手動入力があった場合のフラグ設定
    document.getElementById('slug').addEventListener('input', function() {
        this.dataset.manual = 'true';
    });
        updateSlugPreview();
    }
    
    // スラッグフィールドに手動入力があった場合のフラグ設定
    document.getElementById('slug').addEventListener('input', function() {
        this.dataset.manual = 'true';
        updateSlugPreview();
    });
    
    // 初期プレビュー設定
    updateSlugPreview();
    </script>
</body>
</html>
