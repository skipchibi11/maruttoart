<?php
require_once '../config.php';
requireLogin();

$pdo = getDB();

// 素材一覧取得
$stmt = $pdo->prepare("SELECT * FROM materials ORDER BY created_at DESC");
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
                                <h3 class="text-primary"><?= count($materials) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 素材一覧 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">登録素材一覧</h5>
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
                                            <img src="/<?= h($material['webp_path']) ?>" alt="<?= h($material['title']) ?>" style="width: 50px; height: 50px; object-fit: cover;" class="rounded">
                                        </td>
                                        <td><?= h($material['title']) ?></td>
                                        <td><?= h($material['slug']) ?></td>
                                        <td><?= date('Y/m/d', strtotime($material['upload_date'])) ?></td>
                                        <td>
                                            <a href="/detail/<?= h($material['slug']) ?>" class="btn btn-sm btn-outline-primary" target="_blank">
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
    </script>
</body>
</html>
