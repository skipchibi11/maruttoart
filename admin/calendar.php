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

// ソート順の取得
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['created', 'date']) ? $_GET['sort'] : 'created';

// ページング設定
$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// 総件数を取得
$countStmt = $pdo->query("SELECT COUNT(*) FROM calendar_items");
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// カレンダーアイテム一覧取得（ページング付き）
if ($sort === 'date') {
    $stmt = $pdo->prepare("SELECT * FROM calendar_items ORDER BY year DESC, month DESC, day DESC LIMIT ? OFFSET ?");
} else {
    $stmt = $pdo->prepare("SELECT * FROM calendar_items ORDER BY created_at DESC LIMIT ? OFFSET ?");
}
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
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
            <div class="d-flex gap-2">
                <div class="btn-group" role="group">
                    <a href="?sort=created" class="btn btn-sm btn-outline-secondary <?= $sort === 'created' ? 'active' : '' ?>">
                        <i class="bi bi-clock-history"></i> 登録日順
                    </a>
                    <a href="?sort=date" class="btn btn-sm btn-outline-secondary <?= $sort === 'date' ? 'active' : '' ?>">
                        <i class="bi bi-calendar-date"></i> カレンダー順
                    </a>
                </div>
                <a href="calendar-form.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> 新規追加
                </a>
            </div>
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
                                        <strong><?= h($item['title']) ?></strong>
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
                                            <a href="/calendar-detail/?year=<?= h($item['year']) ?>&month=<?= h($item['month']) ?>&day=<?= h($item['day']) ?>" 
                                               target="_blank" 
                                               class="badge bg-success text-decoration-none">
                                                公開 <i class="bi bi-box-arrow-up-right"></i>
                                            </a>
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
                
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="ページネーション">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?sort=<?= h($sort) ?>&page=<?= $page - 1 ?>">
                                            <i class="bi bi-chevron-left"></i> 前へ
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?sort=<?= h($sort) ?>&page=1">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?sort=<?= h($sort) ?>&page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?sort=<?= h($sort) ?>&page=<?= $totalPages ?>"><?= $totalPages ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?sort=<?= h($sort) ?>&page=<?= $page + 1 ?>">
                                            次へ <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <div class="text-center mt-2 text-muted small">
                            全<?= $totalItems ?>件中 <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalItems) ?>件を表示
                        </div>
                    </div>
                <?php endif; ?>
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
