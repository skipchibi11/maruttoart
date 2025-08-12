<?php
require_once 'config.php';

$pdo = getDB();

// 検索処理
$search = $_GET['search'] ?? '';
$sql = "SELECT * FROM materials WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR description LIKE ? OR search_keywords_en LIKE ? OR search_keywords_jp LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>maruttoart - 無料素材ダウンロード</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #ffffff;
        }
        .material-card {
            transition: transform 0.2s;
        }
        .material-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .header-logo {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand header-logo" href="/">maruttoart</a>
            <div class="navbar-nav ms-auto">
                <form class="d-flex" method="GET">
                    <input class="form-control me-2" type="search" name="search" placeholder="素材を検索..." value="<?= h($search) ?>">
                    <button class="btn btn-outline-secondary" type="submit">検索</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">無料素材一覧</h1>
            </div>
        </div>

        <div class="row">
            <?php foreach ($materials as $material): ?>
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card material-card h-100">
                    <img src="<?= h($material['webp_path']) ?>" class="card-img-top" alt="<?= h($material['title']) ?>" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="card-title"><?= h($material['title']) ?></h5>
                        <p class="card-text"><?= h(substr($material['description'], 0, 100)) ?>...</p>
                        <a href="/detail/<?= h($material['slug']) ?>" class="btn btn-primary">詳細を見る</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($materials)): ?>
        <div class="row">
            <div class="col-12 text-center">
                <p class="text-muted">素材が見つかりませんでした。</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="text-muted mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
