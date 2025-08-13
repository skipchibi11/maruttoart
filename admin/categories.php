<?php
require_once '../config.php';
requireLogin();
setNoCache();

$pdo = getDB();

// カテゴリ削除処理
if ($_POST['action'] ?? '' === 'delete') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        header('Location: categories.php?error=invalid_token');
        exit;
    }
    
    $categoryId = $_POST['category_id'];
    
    try {
        deleteCategory($categoryId, $pdo);
        header('Location: categories.php?success=deleted');
        exit;
    } catch (Exception $e) {
        header('Location: categories.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// カテゴリ一覧取得
$categories = getAllCategories($pdo);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>カテゴリ管理 - maruttoart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .category-card {
            transition: transform 0.2s;
            border: 1px solid #e0e0e0;
        }
        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .language-info {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .material-count {
            color: #007bff;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">maruttoart 管理画面</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">ダッシュボード</a>
                <a class="nav-link" href="categories.php">カテゴリ</a>
                <a class="nav-link" href="tags.php">タグ</a>
                <a class="nav-link" href="logout.php">ログアウト</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- ヘッダー -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="mb-0">
                    <i class="bi bi-folder"></i>
                    カテゴリ管理
                </h1>
                <p class="text-muted mb-0">サイトのカテゴリを管理します</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="category-form.php" class="btn btn-success">
                    <i class="bi bi-plus-circle"></i>
                    新しいカテゴリを追加
                </a>
            </div>
        </div>

        <!-- メッセージ表示 -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php
            $message = '';
            switch ($_GET['success']) {
                case 'created':
                    $message = 'カテゴリが正常に作成されました。';
                    break;
                case 'updated':
                    $message = 'カテゴリが正常に更新されました。';
                    break;
                case 'deleted':
                    $message = 'カテゴリが正常に削除されました。';
                    break;
            }
            echo h($message);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php
            $message = $_GET['error'];
            if ($message === 'invalid_token') {
                $message = 'CSRFトークンが無効です。';
            }
            echo h($message);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- カテゴリ一覧 -->
        <?php if (empty($categories)): ?>
        <div class="text-center py-5">
            <i class="bi bi-folder-x" style="font-size: 3rem; color: #6c757d;"></i>
            <h3 class="mt-3 text-muted">カテゴリがありません</h3>
            <p class="text-muted">新しいカテゴリを作成してください。</p>
            <a href="category-form.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i>
                最初のカテゴリを作成
            </a>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">カテゴリ一覧</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>表示順</th>
                                <th>カテゴリ名</th>
                                <th>スラッグ</th>
                                <th>素材数</th>
                                <th>作成日</th>
                                <th class="text-end">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <?php 
                                // カテゴリに属する素材数を取得
                                $materialCount = count(getCategoryMaterials($category['id'], $pdo));
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?= $category['sort_order'] ?></span>
                                    </td>
                                    <td>
                                        <strong><?= h($category['title']) ?></strong>
                                    </td>
                                    <td>
                                        <code><?= h($category['slug']) ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= $materialCount ?></span>
                                    </td>
                                    <td>
                                        <?= date('Y/m/d', strtotime($category['created_at'])) ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="category-form.php?id=<?= $category['id'] ?>" 
                                               class="btn btn-outline-primary">
                                                <i class="bi bi-pencil"></i>
                                                編集
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="confirmDelete(<?= $category['id'] ?>, '<?= h($category['title']) ?>')">
                                                <i class="bi bi-trash"></i>
                                                削除
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 削除確認モーダル -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">カテゴリの削除確認</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>カテゴリ「<span id="deleteCategoryName"></span>」を削除してもよろしいですか？</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>注意:</strong> カテゴリを削除すると、そのカテゴリに属する素材のカテゴリ設定はクリアされます。
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="category_id" id="deleteCategoryId">
                        <button type="submit" class="btn btn-danger">削除する</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function confirmDelete(categoryId, categoryName) {
        document.getElementById('deleteCategoryId').value = categoryId;
        document.getElementById('deleteCategoryName').textContent = categoryName;
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }
    </script>
</body>
</html>
