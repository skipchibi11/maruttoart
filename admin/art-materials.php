<?php
require_once '../config.php';
startAdminSession(); // 管理画面専用セッション開始
requireLogin();

// 管理画面はキャッシュ無効化
setNoCache();

// データベース接続
$pdo = getDB();

// 削除処理
if (isset($_POST['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM art_materials WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
        $success_message = "画材を削除しました。";
    } catch (Exception $e) {
        $error_message = "削除に失敗しました: " . $e->getMessage();
    }
}

// 画材一覧の取得
try {
    $stmt = $pdo->prepare("
        SELECT am.*, 
               COUNT(mam.id) as usage_count
        FROM art_materials am
        LEFT JOIN material_art_materials mam ON am.id = mam.art_material_id
        GROUP BY am.id
        ORDER BY am.sort_order, am.name
    ");
    $stmt->execute();
    $art_materials = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = "データの取得に失敗しました: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>画材管理 - maruttoart 管理画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .color-preview {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid #ddd;
            display: inline-block;
            margin-right: 10px;
        }
        .usage-badge {
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- サイドバー -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <h6 class="sidebar-heading px-3 mt-4 mb-1 text-muted">
                        管理メニュー
                    </h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-house"></i> ダッシュボード
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="upload.php">
                                <i class="bi bi-cloud-upload"></i> 素材アップロード
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="bi bi-folder"></i> カテゴリ管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tags.php">
                                <i class="bi bi-tags"></i> タグ管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="art-materials.php">
                                <i class="bi bi-palette"></i> 画材管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> ログアウト
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- メインコンテンツ -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">画材管理</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="art-material-form.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> 新しい画材を追加
                        </a>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success" role="alert">
                        <?= h($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= h($error_message) ?>
                    </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>並び順</th>
                                <th>色</th>
                                <th>画材名</th>
                                <th>英語名</th>
                                <th>説明</th>
                                <th>使用回数</th>
                                <th>状態</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($art_materials as $material): ?>
                                <tr>
                                    <td><?= h($material['sort_order']) ?></td>
                                    <td>
                                        <?php if ($material['color_code']): ?>
                                            <div class="color-preview" style="background-color: <?= h($material['color_code']) ?>"></div>
                                        <?php else: ?>
                                            <div class="color-preview" style="background-color: #ccc"></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= h($material['name']) ?></td>
                                    <td><?= h($material['name_en']) ?></td>
                                    <td><?= h($material['description']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary usage-badge">
                                            <?= $material['usage_count'] ?>件
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($material['is_active']): ?>
                                            <span class="badge bg-success">有効</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">無効</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="art-material-form.php?id=<?= $material['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($material['usage_count'] == 0): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('本当に削除しますか？');">
                                                <input type="hidden" name="delete_id" value="<?= $material['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled title="使用中のため削除できません">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($art_materials)): ?>
                    <div class="text-center mt-5">
                        <p class="text-muted">画材が登録されていません。</p>
                        <a href="art-material-form.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> 最初の画材を追加する
                        </a>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
