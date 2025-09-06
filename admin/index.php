<?php
require_once '../config.php';
startAdminSession(); // 管理画面専用セッション開始
requireLogin();

// 管理画面はキャッシュ無効化
setNoCache();

$pdo = getDB();

// ページング設定
$items_per_page = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 20; // 1ページあたりの表示件数（10-100の範囲）
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// 総素材数を取得
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM materials");
$count_stmt->execute();
$total_count = $count_stmt->fetchColumn();
$total_pages = ceil($total_count / $items_per_page);

// ページング対応の素材一覧を取得（カテゴリ情報も含める）
$stmt = $pdo->prepare("SELECT m.*, c.slug as category_slug FROM materials m LEFT JOIN categories c ON m.category_id = c.id ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$materials = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面 - maruttoart</title>
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
                            <a class="nav-link active" href="/admin/">
                                <i class="bi bi-house-door"></i> ダッシュボード
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/upload.php">
                                <i class="bi bi-plus-circle"></i> 素材アップロード
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/categories.php">
                                <i class="bi bi-folder"></i> カテゴリ管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/tags.php">
                                <i class="bi bi-tags"></i> タグ管理
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
                    <h1 class="h2">ダッシュボード</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="/admin/upload.php" class="btn btn-sm btn-success">
                                <i class="bi bi-plus-circle"></i> 新規アップロード
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 統計情報 -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title">総素材数</h5>
                                <h3 class="text-primary"><?= $total_count ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 素材一覧 -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">登録素材一覧</h5>
                        <div class="d-flex align-items-center gap-3">
                            <small class="text-muted">
                                <?php if ($total_count > 0): ?>
                                    <?= (($current_page - 1) * $items_per_page + 1) ?>-<?= min($current_page * $items_per_page, $total_count) ?> / <?= $total_count ?> 件
                                <?php endif; ?>
                            </small>
                            <div class="d-flex align-items-center gap-2">
                                <label for="per_page" class="form-label mb-0 small">表示件数:</label>
                                <select id="per_page" class="form-select form-select-sm" style="width: auto;" onchange="changePerPage(this.value)">
                                    <option value="10" <?= $items_per_page == 10 ? 'selected' : '' ?>>10件</option>
                                    <option value="20" <?= $items_per_page == 20 ? 'selected' : '' ?>>20件</option>
                                    <option value="50" <?= $items_per_page == 50 ? 'selected' : '' ?>>50件</option>
                                    <option value="100" <?= $items_per_page == 100 ? 'selected' : '' ?>>100件</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>サムネイル</th>
                                        <th>タイトル</th>
                                        <th>スラッグ</th>
                                        <th>投稿日</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materials as $material): ?>
                                    <tr>
                                        <td>
                                            <img src="/<?= h($material['webp_small_path'] ?? $material['image_path']) ?>" alt="<?= h($material['title']) ?>" style="width: 50px; height: 50px; object-fit: cover;" class="rounded">
                                        </td>
                                        <td><?= h($material['title']) ?></td>
                                        <td><?= h($material['slug']) ?></td>
                                        <td><?= date('Y/m/d', strtotime($material['upload_date'])) ?></td>
                                        <td>
                                            <?php if (!empty($material['category_slug'])): ?>
                                                <a href="/<?= h($material['category_slug']) ?>/<?= h($material['slug']) ?>/" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <?php else: ?>
                                                <a href="/detail/<?= h($material['slug']) ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <?php endif; ?>
                                                <i class="bi bi-eye"></i> 確認
                                            </a>
                                            <a href="/admin/edit.php?id=<?= $material['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i> 編集
                                            </a>
                                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $material['id'] ?>, '<?= h($material['title']) ?>')">
                                                <i class="bi bi-trash"></i> 削除
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <?php if (empty($materials)): ?>
                            <p class="text-center text-muted">登録されている素材がありません。</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- ページング -->
                        <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-center mt-3">
                            <nav aria-label="素材一覧ページング">
                                <ul class="pagination">
                                    <!-- 最初のページ -->
                                    <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&per_page=<?= $items_per_page ?>" aria-label="最初のページ">
                                            <i class="bi bi-chevron-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $current_page - 1 ?>&per_page=<?= $items_per_page ?>" aria-label="前のページ">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="bi bi-chevron-double-left"></i></span>
                                    </li>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="bi bi-chevron-left"></i></span>
                                    </li>
                                    <?php endif; ?>

                                    <!-- ページ番号 -->
                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    
                                    if ($start_page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="?page=1&per_page=<?= $items_per_page ?>">1</a></li>
                                    <?php if ($start_page > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&per_page=<?= $items_per_page ?>"><?= $i ?></a>
                                    </li>
                                    <?php endfor; ?>

                                    <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item"><a class="page-link" href="?page=<?= $total_pages ?>&per_page=<?= $items_per_page ?>"><?= $total_pages ?></a></li>
                                    <?php endif; ?>

                                    <!-- 最後のページ -->
                                    <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $current_page + 1 ?>&per_page=<?= $items_per_page ?>" aria-label="次のページ">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $total_pages ?>&per_page=<?= $items_per_page ?>" aria-label="最後のページ">
                                            <i class="bi bi-chevron-double-right"></i>
                                        </a>
                                    </li>
                                    <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="bi bi-chevron-right"></i></span>
                                    </li>
                                    <li class="page-item disabled">
                                        <span class="page-link"><i class="bi bi-chevron-double-right"></i></span>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(id, title) {
            if (confirm(`「${title}」を削除しますか？この操作は取り消せません。`)) {
                window.location.href = `/admin/delete.php?id=${id}`;
            }
        }
        
        function changePerPage(perPage) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', perPage);
            url.searchParams.set('page', '1'); // ページ数変更時は1ページ目に戻る
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
