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

// タグ削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $tagId = intval($_POST['tag_id']);
        deleteTag($tagId, $pdo);
        $message = 'タグを削除しました。';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// 全タグを取得
$tags = getAllTags($pdo);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>タグ管理 - maruttoart</title>
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
                    <a class="nav-link" href="artworks.php">みんなのアトリエ</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">ログアウト</a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>タグ管理</h1>
                    <a href="tag-form.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> 新しいタグを追加
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
                        <h5 class="card-title mb-0">タグ一覧</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tags)): ?>
                            <p class="text-muted">タグが登録されていません。</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>タグ名</th>
                                            <th>スラッグ</th>
                                            <th>スラッグ</th>
                                            <th>作成日時</th>
                                            <th class="text-end">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tags as $tag): ?>
                                            <tr>
                                                <td><strong><?= h($tag['name']) ?></strong></td>
                                                <td><code><?= h($tag['slug']) ?></code></td>
                                                <td><?= date('Y-m-d H:i', strtotime($tag['created_at'])) ?></td>
                                                <td class="text-end">
                                                    <a href="tag-form.php?id=<?= $tag['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                        <i class="bi bi-pencil"></i> 編集
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?= $tag['id'] ?>, '<?= h($tag['name']) ?>')">
                                                        <i class="bi bi-trash"></i> 削除
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 削除確認モーダル -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">削除確認</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="deleteMessage"></p>
                </div>
                <div class="modal-footer">
                    <form id="deleteForm" method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="tag_id" id="deleteTagId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="submit" class="btn btn-danger">削除</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(tagId, tagName) {
            document.getElementById('deleteTagId').value = tagId;
            document.getElementById('deleteMessage').textContent = 
                `タグ「${tagName}」を削除してもよろしいですか？関連する素材からもタグが削除されます。この操作は取り消せません。`;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
