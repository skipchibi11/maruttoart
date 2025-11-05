<?php
require_once '../config.php';
startAdminSession(); // 管理画面専用セッション開始
requireLogin(); // 管理者認証

$pdo = getDB();

// ページネーション設定
$perPage = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// フィルタリング
$status = $_GET['status'] ?? 'all';

// WHERE句構築
$whereClause = "WHERE 1=1";
$params = [];

if ($status !== 'all') {
    $whereClause .= " AND status = ?";
    $params[] = $status;
}

// 総件数を取得
$countSql = "SELECT COUNT(*) FROM community_artworks " . $whereClause;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// 作品データを取得
$sql = "SELECT * FROM community_artworks " . $whereClause . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$artworks = $stmt->fetchAll();

// 統計データ取得
$statsStmt = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM community_artworks 
    GROUP BY status
");
$stats = $statsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>作品管理 - 管理画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
        }
        
        .stats-cards .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .artwork-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .status-pending { background: #ffc107; color: #000; }
        .status-approved { background: #28a745; color: #fff; }
        .status-rejected { background: #dc3545; color: #fff; }
        
        .action-buttons .btn {
            margin: 0 2px;
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .filter-tabs {
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 2rem;
        }
        
        .filter-tab {
            padding: 0.75rem 1rem;
            margin-right: 1rem;
            text-decoration: none;
            color: #6c757d;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .filter-tab:hover, .filter-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <div class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-palette2"></i> 作品管理</h1>
                    <p class="mb-0">コミュニティ投稿作品の承認・管理</p>
                </div>
                <div>
                    <a href="/admin/" class="btn btn-outline-light">
                        <i class="bi bi-house"></i> ダッシュボードに戻る
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container mt-4">
        <!-- 統計カード -->
        <div class="stats-cards row g-3 mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-warning"><?= $stats['pending'] ?? 0 ?></h5>
                        <p class="card-text">承認待ち</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-success"><?= $stats['approved'] ?? 0 ?></h5>
                        <p class="card-text">承認済み</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-danger"><?= $stats['rejected'] ?? 0 ?></h5>
                        <p class="card-text">却下済み</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-info"><?= array_sum($stats) ?></h5>
                        <p class="card-text">総作品数</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- フィルタータブ -->
        <div class="filter-tabs">
            <a href="?status=all" class="filter-tab <?= $status === 'all' ? 'active' : '' ?>">
                すべて (<?= array_sum($stats) ?>)
            </a>
            <a href="?status=pending" class="filter-tab <?= $status === 'pending' ? 'active' : '' ?>">
                承認待ち (<?= $stats['pending'] ?? 0 ?>)
            </a>
            <a href="?status=approved" class="filter-tab <?= $status === 'approved' ? 'active' : '' ?>">
                承認済み (<?= $stats['approved'] ?? 0 ?>)
            </a>
            <a href="?status=rejected" class="filter-tab <?= $status === 'rejected' ? 'active' : '' ?>">
                却下済み (<?= $stats['rejected'] ?? 0 ?>)
            </a>
        </div>
        
        <!-- 作品一覧 -->
        <?php if (empty($artworks)): ?>
            <div class="text-center py-5">
                <i class="bi bi-image" style="font-size: 4rem; color: #dee2e6;"></i>
                <h4 class="mt-3">作品がありません</h4>
                <p class="text-muted">まだ作品が投稿されていません。</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="100">サムネイル</th>
                            <th>作品情報</th>
                            <th width="120">状態</th>
                            <th width="150">投稿日時</th>
                            <th width="200">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($artworks as $artwork): ?>
                            <tr id="artwork-<?= $artwork['id'] ?>">
                                <td>
                                    <img src="/<?= h($artwork['webp_path'] ?: $artwork['file_path']) ?>" 
                                         alt="<?= h($artwork['title']) ?>" 
                                         class="artwork-thumbnail">
                                </td>
                                <td>
                                    <h6 class="mb-1"><?= h($artwork['title']) ?></h6>
                                    <small class="text-muted">by <?= h($artwork['pen_name']) ?></small>
                                    <?php if (!empty($artwork['description'])): ?>
                                        <div class="mt-1">
                                            <small><?= h(mb_substr($artwork['description'], 0, 100)) ?><?= mb_strlen($artwork['description']) > 100 ? '...' : '' ?></small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-2">
                                        <?php if ($artwork['free_material_consent']): ?>
                                            <span class="badge bg-success">フリー素材</span>
                                        <?php endif; ?>
                                        <?php if ($artwork['is_featured']): ?>
                                            <span class="badge bg-warning text-dark">おすすめ</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge status-badge status-<?= $artwork['status'] ?>">
                                        <?php
                                        $statusText = [
                                            'pending' => '承認待ち',
                                            'approved' => '承認済み',
                                            'rejected' => '却下済み'
                                        ];
                                        echo $statusText[$artwork['status']] ?? $artwork['status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?= date('Y/m/d H:i', strtotime($artwork['created_at'])) ?></small>
                                    <?php if ($artwork['approved_at']): ?>
                                        <br><small class="text-success">承認: <?= date('m/d H:i', strtotime($artwork['approved_at'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($artwork['status'] === 'pending'): ?>
                                            <button class="btn btn-success btn-approve" data-id="<?= $artwork['id'] ?>">
                                                <i class="bi bi-check"></i> 承認
                                            </button>
                                            <button class="btn btn-warning btn-reject" data-id="<?= $artwork['id'] ?>">
                                                <i class="bi bi-x"></i> 却下
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-outline-primary btn-feature" 
                                                data-id="<?= $artwork['id'] ?>" 
                                                data-featured="<?= $artwork['is_featured'] ?>">
                                            <i class="bi bi-star<?= $artwork['is_featured'] ? '-fill' : '' ?>"></i>
                                        </button>
                                        
                                        <a href="/<?= h($artwork['file_path']) ?>" target="_blank" class="btn btn-outline-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        
                                        <button class="btn btn-danger btn-delete" data-id="<?= $artwork['id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- ページネーション -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="作品管理ページナビゲーション">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">前へ</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">次へ</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- 却下理由モーダル -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">作品を却下</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="rejectReason" class="form-label">却下理由</label>
                        <textarea class="form-control" id="rejectReason" rows="3" 
                                  placeholder="却下理由を入力してください（投稿者には表示されません）"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-danger" id="confirmReject">却下する</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentArtworkId = null;
        
        // 承認
        document.querySelectorAll('.btn-approve').forEach(btn => {
            btn.addEventListener('click', function() {
                const artworkId = this.dataset.id;
                
                if (confirm('この作品を承認しますか？')) {
                    updateArtworkStatus(artworkId, 'approved');
                }
            });
        });
        
        // 却下
        document.querySelectorAll('.btn-reject').forEach(btn => {
            btn.addEventListener('click', function() {
                currentArtworkId = this.dataset.id;
                const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
                modal.show();
            });
        });
        
        // 却下確認
        document.getElementById('confirmReject').addEventListener('click', function() {
            const reason = document.getElementById('rejectReason').value;
            updateArtworkStatus(currentArtworkId, 'rejected', reason);
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('rejectModal'));
            modal.hide();
        });
        
        // おすすめ切り替え
        document.querySelectorAll('.btn-feature').forEach(btn => {
            btn.addEventListener('click', function() {
                const artworkId = this.dataset.id;
                const isFeatured = this.dataset.featured === '1';
                const newStatus = isFeatured ? '0' : '1';
                
                toggleFeatured(artworkId, newStatus);
            });
        });
        
        // 削除
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function() {
                const artworkId = this.dataset.id;
                
                if (confirm('この作品を完全に削除しますか？この操作は取り消せません。')) {
                    deleteArtwork(artworkId);
                }
            });
        });
        
        // 作品状態更新
        function updateArtworkStatus(artworkId, status, reason = '') {
            fetch('/admin/api/manage-artwork.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_status',
                    artwork_id: artworkId,
                    status: status,
                    reason: reason
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('エラー: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('処理中にエラーが発生しました');
            });
        }
        
        // おすすめ切り替え
        function toggleFeatured(artworkId, featured) {
            fetch('/admin/api/manage-artwork.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'toggle_featured',
                    artwork_id: artworkId,
                    is_featured: featured
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('エラー: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('処理中にエラーが発生しました');
            });
        }
        
        // 作品削除
        function deleteArtwork(artworkId) {
            fetch('/admin/api/manage-artwork.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete',
                    artwork_id: artworkId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('artwork-' + artworkId).remove();
                } else {
                    alert('エラー: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('処理中にエラーが発生しました');
            });
        }
    </script>
</body>
</html>