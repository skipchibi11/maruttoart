<?php
require_once '../config.php';
startAdminSession(); // 管理画面専用セッション開始
requireLogin();

// 管理画面はキャッシュ無効化
setNoCache();

// データベース接続
$pdo = getDB();

$is_edit = isset($_GET['id']);
$art_material = null;

// 編集の場合、既存データを取得
if ($is_edit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM art_materials WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $art_material = $stmt->fetch();
        if (!$art_material) {
            header('Location: art-materials.php');
            exit();
        }
    } catch (Exception $e) {
        $error_message = "データの取得に失敗しました: " . $e->getMessage();
    }
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $name_en = trim($_POST['name_en']);
    $description = trim($_POST['description']);
    $color_code = trim($_POST['color_code']);
    $sort_order = (int)$_POST['sort_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // バリデーション
    $errors = [];
    if (empty($name)) {
        $errors[] = "画材名は必須です。";
    }
    if (!empty($color_code) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color_code)) {
        $errors[] = "カラーコードは正しい形式で入力してください（例: #FF6B6B）。";
    }

    // 名前の重複チェック
    if (empty($errors)) {
        try {
            if ($is_edit) {
                $stmt = $pdo->prepare("SELECT id FROM art_materials WHERE name = ? AND id != ?");
                $stmt->execute([$name, $_GET['id']]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM art_materials WHERE name = ?");
                $stmt->execute([$name]);
            }
            if ($stmt->fetch()) {
                $errors[] = "この画材名は既に使用されています。";
            }
        } catch (Exception $e) {
            $errors[] = "データベースエラー: " . $e->getMessage();
        }
    }

    // エラーがない場合、保存処理
    if (empty($errors)) {
        try {
            if ($is_edit) {
                $stmt = $pdo->prepare("
                    UPDATE art_materials 
                    SET name = ?, name_en = ?, description = ?, color_code = ?, sort_order = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $name_en, $description, $color_code, $sort_order, $is_active, $_GET['id']]);
                $success_message = "画材を更新しました。";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO art_materials (name, name_en, description, color_code, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $name_en, $description, $color_code, $sort_order, $is_active]);
                $success_message = "画材を追加しました。";
            }
            
            // 成功後はリダイレクト
            header('Location: art-materials.php?success=' . urlencode($success_message));
            exit();
        } catch (Exception $e) {
            $errors[] = "保存に失敗しました: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit ? '画材編集' : '画材追加' ?> - maruttoart 管理画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .color-preview {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            border: 2px solid #ddd;
            display: inline-block;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- サイドバー -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <h6 class="sidebar-heading px-3 mt-4 mb-1 text-muted">
                        管理メニュー
                    </h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-house"></i> ダッシュボード
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="upload.php">
                                <i class="bi bi-cloud-upload"></i> 素材アップロード
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="bi bi-folder"></i> カテゴリ管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tags.php">
                                <i class="bi bi-tags"></i> タグ管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="art-materials.php">
                                <i class="bi bi-palette"></i> 画材管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> ログアウト
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- メインコンテンツ -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?= $is_edit ? '画材編集' : '画材追加' ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="art-materials.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> 画材一覧に戻る
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= h($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <form method="post">
                            <div class="mb-3">
                                <label for="name" class="form-label">画材名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= h($art_material['name'] ?? $_POST['name'] ?? '') ?>" required>
                                <div class="form-text">例: 水彩、パステル、色鉛筆</div>
                            </div>

                            <div class="mb-3">
                                <label for="name_en" class="form-label">英語名</label>
                                <input type="text" class="form-control" id="name_en" name="name_en" 
                                       value="<?= h($art_material['name_en'] ?? $_POST['name_en'] ?? '') ?>">
                                <div class="form-text">例: watercolor、pastel、colored-pencil</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">説明</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= h($art_material['description'] ?? $_POST['description'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="color_code" class="form-label">代表色</label>
                                <div class="d-flex align-items-center">
                                    <input type="color" class="form-control form-control-color" id="color_picker" 
                                           value="<?= h($art_material['color_code'] ?? $_POST['color_code'] ?? '#4A90E2') ?>" 
                                           style="width: 60px;">
                                    <input type="text" class="form-control ms-2" id="color_code" name="color_code" 
                                           value="<?= h($art_material['color_code'] ?? $_POST['color_code'] ?? '') ?>"
                                           placeholder="#FF6B6B" pattern="^#[0-9A-Fa-f]{6}$">
                                </div>
                                <div class="form-text">画材を表す代表的な色を選択してください</div>
                            </div>

                            <div class="mb-3">
                                <label for="sort_order" class="form-label">並び順</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                       value="<?= h($art_material['sort_order'] ?? $_POST['sort_order'] ?? 0) ?>" min="0">
                                <div class="form-text">数値が小さいほど上に表示されます</div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?= ($art_material['is_active'] ?? $_POST['is_active'] ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    有効
                                </label>
                            </div>

                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i>
                                    <?= $is_edit ? '更新' : '追加' ?>
                                </button>
                                <a href="art-materials.php" class="btn btn-secondary ms-2">キャンセル</a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // カラーピッカーとテキスト入力の連携
        const colorPicker = document.getElementById('color_picker');
        const colorCode = document.getElementById('color_code');

        colorPicker.addEventListener('change', function() {
            colorCode.value = this.value;
        });

        colorCode.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                colorPicker.value = this.value;
            }
        });
    </script>
</body>
</html>
