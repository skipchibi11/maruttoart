<?php
require_once '../config.php';

// 認証チェック
startAdminSession();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// キャッシュ無効化
setNoCache();

$pdo = getDB();
$message = '';
$error = '';
$tag = null;
$isEdit = false;

// 編集の場合、タグ情報を取得
if (isset($_GET['id'])) {
    $tagId = intval($_GET['id']);
    $tag = getTagById($tagId, $pdo);
    if ($tag) {
        $isEdit = true;
    } else {
        header('Location: tags.php');
        exit;
    }
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'slug' => trim($_POST['slug'] ?? '')
        ];

        // バリデーション
        if (empty($data['name'])) {
            throw new Exception('タグ名は必須です。');
        }

        if (empty($data['slug'])) {
            throw new Exception('スラッグは必須です。');
        }

        if (!preg_match('/^[a-z0-9\-_]+$/', $data['slug'])) {
            throw new Exception('スラッグには半角英数字とハイフン、アンダースコアのみ使用できます。');
        }

        if ($isEdit) {
            updateTag($tagId, $data, $pdo);
            $message = 'タグを更新しました。';
        } else {
            createTag($data, $pdo);
            $message = 'タグを作成しました。';
            // 作成後はリダイレクト
            header('Location: tags.php?message=' . urlencode($message));
            exit;
        }

        // 更新後は情報を再取得
        $tag = getTagById($tagId, $pdo);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'タグ編集' : '新規タグ登録' ?> - maruttoart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">maruttoart 管理画面</a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">素材管理</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="tags.php">タグ管理</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">ログアウト</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><?= $isEdit ? 'タグ編集' : '新規タグ登録' ?></h1>
                    <a href="tags.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> 戻る
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= h($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= h($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <?= $isEdit ? 'タグ情報を編集' : 'タグ情報を入力' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">
                                            タグ名（日本語） <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= h($tag['name'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="slug" class="form-label">
                                            スラッグ <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="slug" name="slug" 
                                               value="<?= h($tag['slug'] ?? '') ?>" required
                                               pattern="^[a-z0-9\-_]+$"
                                               title="半角英数字とハイフン、アンダースコアのみ使用可能">
                                        <div class="form-text">半角英数字とハイフン、アンダースコアのみ</div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="tags.php" class="btn btn-secondary">キャンセル</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> 
                                    <?= $isEdit ? '更新' : '登録' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">タグ情報</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>作成日時:</strong> <?= date('Y-m-d H:i:s', strtotime($tag['created_at'])) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>更新日時:</strong> <?= date('Y-m-d H:i:s', strtotime($tag['updated_at'])) ?></p>
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
        // タグ名からスラッグを自動生成
        document.getElementById('name').addEventListener('input', function() {
            // 日本語の場合は英語名を優先してスラッグ生成
            const slug = this.value
                .toLowerCase()
                .replace(/[^a-z0-9\s\-]/g, '') // 英数字とスペース、ハイフンのみ残す
                .replace(/\s+/g, '-') // スペースをハイフンに変換
                .replace(/\-+/g, '-') // 連続するハイフンを1つに
                .replace(/^\-|\-$/g, ''); // 先頭・末尾のハイフンを削除
                
            // 編集時でない場合、または現在のスラッグが空の場合のみ自動設定
            const slugInput = document.getElementById('slug');
            if (!slugInput.value || !<?= $isEdit ? 'true' : 'false' ?>) {
                slugInput.value = slug;
            }
        });

        // 英語名からもスラッグを生成できるように
        document.getElementById('name_en').addEventListener('input', function() {
            const slug = this.value
                .toLowerCase()
                .replace(/[^a-z0-9\s\-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/\-+/g, '-')
                .replace(/^\-|\-$/g, '');
                
            const slugInput = document.getElementById('slug');
            if (!slugInput.value || !<?= $isEdit ? 'true' : 'false' ?>) {
                slugInput.value = slug;
            }
        });
    </script>
</body>
</html>
