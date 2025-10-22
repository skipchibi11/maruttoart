<?php
require_once '../config.php';
startAdminSession(); // 管理画面専用セッション開始
requireLogin();

// 管理画面はキャッシュ無効化
setNoCache();

$pdo = getDB();

// 検索パラメータの取得
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_category = isset($_GET['category']) ? intval($_GET['category']) : 0;

// ページング設定
$items_per_page = isset($_GET['per_page']) ? max(10, min(100, intval($_GET['per_page']))) : 20; // 1ページあたりの表示件数（10-100の範囲）
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// 検索条件を組み立て（より安全な方法）
$where_conditions = [];
$bind_params = [];

if (!empty($search_query)) {
    $where_conditions[] = "(m.title LIKE ? OR m.slug LIKE ? OR m.description LIKE ?)";
    $search_term = '%' . $search_query . '%';
    $bind_params[] = $search_term;
    $bind_params[] = $search_term;
    $bind_params[] = $search_term;
}

if ($search_category > 0) {
    $where_conditions[] = "m.category_id = ?";
    $bind_params[] = $search_category;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 総素材数を取得（検索条件を適用）
$count_sql = "SELECT COUNT(*) as total FROM materials m LEFT JOIN categories c ON m.category_id = c.id " . $where_clause;
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($bind_params);
$total_count = $count_stmt->fetchColumn();
$total_pages = ceil($total_count / $items_per_page);

// ページング対応の素材一覧を取得（カテゴリ情報も含める、検索条件を適用）
$sql = "SELECT m.*, c.slug as category_slug, c.title as category_name FROM materials m LEFT JOIN categories c ON m.category_id = c.id " . $where_clause . " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
// 検索パラメータとページングパラメータを結合
$all_params = array_merge($bind_params, [$items_per_page, $offset]);
$stmt->execute($all_params);
$materials = $stmt->fetchAll();

// カテゴリ一覧を取得（検索フォーム用）
$categories_stmt = $pdo->prepare("SELECT id, title as name FROM categories ORDER BY title");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll();

// ページング用URL生成関数
function buildPagingUrl($page, $per_page, $search_query, $search_category) {
    $params = ['page' => $page, 'per_page' => $per_page];
    if (!empty($search_query)) {
        $params['search'] = $search_query;
    }
    if ($search_category > 0) {
        $params['category'] = $search_category;
    }
    return '?' . http_build_query($params);
}
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

                <!-- メッセージ表示 -->
                <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> 素材と関連する画像ファイルが正常に削除されました。
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php elseif (isset($_GET['error']) && $_GET['error'] == 'delete_failed'): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> 素材の削除中にエラーが発生しました。
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

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

                <!-- SVGマイグレーション案内 -->
                <?php
                // SVGパス列が存在するかチェック
                try {
                    $pdo->query("SELECT svg_path FROM materials LIMIT 1");
                    $svg_migrated = true;
                } catch (PDOException $e) {
                    $svg_migrated = false;
                }
                
                if (!$svg_migrated):
                ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle me-3" style="font-size: 1.5rem;"></i>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading mb-1">SVG機能のためのデータベース更新が必要です</h6>
                            <p class="mb-2">SVGファイルのアップロード機能を有効にするには、データベースの更新が必要です。</p>
                            <a href="/admin/migrate_svg.php" class="btn btn-primary btn-sm">
                                <i class="bi bi-database-up"></i> データベース更新を実行
                            </a>
                            <a href="/admin/svg_debug.php" class="btn btn-outline-info btn-sm ms-2">
                                <i class="bi bi-bug"></i> デバッグ情報
                            </a>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- 検索フォーム -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-search"></i> 素材検索</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">キーワード</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= h($search_query) ?>" 
                                       placeholder="タイトル、スラッグ、説明で検索">
                            </div>
                            <div class="col-md-4">
                                <label for="category" class="form-label">カテゴリ</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="0">すべてのカテゴリ</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= $search_category == $category['id'] ? 'selected' : '' ?>>
                                        <?= h($category['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-search"></i> 検索
                                </button>
                                <?php if (!empty($search_query) || $search_category > 0): ?>
                                <a href="?" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> クリア
                                </a>
                                <?php endif; ?>
                            </div>
                            <!-- 現在のper_pageを保持 -->
                            <input type="hidden" name="per_page" value="<?= $items_per_page ?>">
                        </form>
                        
                        <?php if (!empty($search_query) || $search_category > 0): ?>
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> 
                                検索条件: 
                                <?php if (!empty($search_query)): ?>
                                    キーワード「<?= h($search_query) ?>」
                                <?php endif; ?>
                                <?php if ($search_category > 0): ?>
                                    <?php 
                                    $selected_category = array_filter($categories, function($cat) use ($search_category) {
                                        return $cat['id'] == $search_category;
                                    });
                                    $selected_category = reset($selected_category);
                                    ?>
                                    <?= !empty($search_query) ? ', ' : '' ?>カテゴリ「<?= h($selected_category['name']) ?>」
                                <?php endif; ?>
                                で検索中
                            </small>
                        </div>
                        <?php endif; ?>
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
                                        <th>カテゴリ</th>
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
                                        <td>
                                            <?= h($material['title']) ?>
                                            <?php if (!empty($material['svg_path'])): ?>
                                                <span class="badge bg-success ms-2" title="SVGファイル付き">
                                                    <i class="bi bi-vector-pen"></i> SVG
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($material['category_name'])): ?>
                                                <span class="badge bg-secondary"><?= h($material['category_name']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
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
                                        <a class="page-link" href="<?= buildPagingUrl(1, $items_per_page, $search_query, $search_category) ?>" aria-label="最初のページ">
                                            <i class="bi bi-chevron-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= buildPagingUrl($current_page - 1, $items_per_page, $search_query, $search_category) ?>" aria-label="前のページ">
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
                                    <li class="page-item"><a class="page-link" href="<?= buildPagingUrl(1, $items_per_page, $search_query, $search_category) ?>">1</a></li>
                                    <?php if ($start_page > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <?php endif; ?>

                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= buildPagingUrl($i, $items_per_page, $search_query, $search_category) ?>"><?= $i ?></a>
                                    </li>
                                    <?php endfor; ?>

                                    <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item"><a class="page-link" href="<?= buildPagingUrl($total_pages, $items_per_page, $search_query, $search_category) ?>"><?= $total_pages ?></a></li>
                                    <?php endif; ?>

                                    <!-- 最後のページ -->
                                    <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= buildPagingUrl($current_page + 1, $items_per_page, $search_query, $search_category) ?>" aria-label="次のページ">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= buildPagingUrl($total_pages, $items_per_page, $search_query, $search_category) ?>" aria-label="最後のページ">
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

        // 検索パラメータを含むURLを生成する関数
        function buildUrl(page) {
            const url = new URL(window.location);
            url.searchParams.set('page', page);
            return url.toString();
        }
    </script>
</body>
</html>
