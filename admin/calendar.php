<?php
require_once '../config.php';
requireLogin();
setNoCache();

$pdo = getDB();

// カレンダーアイテム削除処理
if ($_POST['action'] ?? '' === 'delete') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        header('Location: calendar.php?error=invalid_token');
        exit;
    }
    
    $itemId = $_POST['item_id'];
    
    try {
        // ファイルも削除
        $stmt = $pdo->prepare("SELECT image_path, gif_path FROM calendar_items WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        
        if ($item) {
            if ($item['image_path'] && file_exists('../' . $item['image_path'])) {
                unlink('../' . $item['image_path']);
            }
            if ($item['gif_path'] && file_exists('../' . $item['gif_path'])) {
                unlink('../' . $item['gif_path']);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM calendar_items WHERE id = ?");
        $stmt->execute([$itemId]);
        
        header('Location: calendar.php?success=deleted');
        exit;
    } catch (Exception $e) {
        header('Location: calendar.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// カレンダーアイテム一覧取得
$stmt = $pdo->query("SELECT * FROM calendar_items ORDER BY year DESC, month DESC, day DESC");
$items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>カレンダー管理 - maruttoart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .item-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        .gif-badge {
            background: #198754;
            color: white;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">maruttoart 管理画面</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">ダッシュボード</a>
                <a class="nav-link" href="categories.php">カテゴリ</a>
                <a class="nav-link" href="tags.php">タグ</a>
                <a class="nav-link" href="artworks.php">みんなのアトリエ</a>
                <a class="nav-link" href="calendar.php">カレンダー</a>
                <a class="nav-link" href="logout.php">ログアウト</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-calendar3"></i> カレンダー管理</h1>
            <a href="calendar-form.php" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> 新規追加
            </a>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php if ($_GET['success'] === 'created'): ?>
                    カレンダーアイテムを作成しました。
                <?php elseif ($_GET['success'] === 'updated'): ?>
                    カレンダーアイテムを更新しました。
                <?php elseif ($_GET['success'] === 'deleted'): ?>
                    カレンダーアイテムを削除しました。
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                エラー: <?= h($_GET['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>サムネイル</th>
                                <th>日付</th>
                                <th>タイトル</th>
                                <th>GIF</th>
                                <th>公開</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <?php if ($item['image_path']): ?>
                                            <img src="/<?= h($item['image_path']) ?>" 
                                                 alt="<?= h($item['title']) ?>" 
                                                 class="item-thumbnail">
                                        <?php else: ?>
                                            <div class="item-thumbnail bg-light d-flex align-items-center justify-content-center">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($item['year']) ?>年<?= h($item['month']) ?>月<?= h($item['day']) ?>日</td>
                                    <td>
                                        <strong><?= h($item['title']) ?></strong><br>
                                        <small class="text-muted"><?= h($item['slug']) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($item['gif_path']): ?>
                                            <span class="gif-badge">GIF有</span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($item['is_published']): ?>
                                            <span class="badge bg-success">公開</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">非公開</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="calendar-form.php?id=<?= $item['id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i> 編集
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="confirmDelete(<?= $item['id'] ?>, '<?= h($item['title']) ?>')">
                                            <i class="bi bi-trash"></i> 削除
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        カレンダーアイテムがありません
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                    <p><strong id="deleteItemTitle"></strong> を削除してもよろしいですか？</p>
                    <p class="text-danger">この操作は取り消せません。</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="item_id" id="deleteItemId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="submit" class="btn btn-danger">削除する</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        
        function confirmDelete(itemId, title) {
            document.getElementById('deleteItemId').value = itemId;
            document.getElementById('deleteItemTitle').textContent = title;
            deleteModal.show();
        }
    </script>
</body>
</html>
