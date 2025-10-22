<?php
require_once '../config.php';
startAdminSession();
requireLogin();

// 管理画面はキャッシュ無効化
setNoCache();

$message = '';
$error = '';

if ($_POST && isset($_POST['execute_migration'])) {
    try {
        $pdo = getDB();
        
        // SVGパス用のカラムが既に存在するかチェック
        $stmt = $pdo->query("SHOW COLUMNS FROM materials LIKE 'svg_path'");
        $columnExists = $stmt->rowCount() > 0;
        
        if ($columnExists) {
            $message = 'svg_pathカラムは既に存在します。';
        } else {
            // SVGパス用のカラムを追加
            $pdo->exec("ALTER TABLE materials ADD COLUMN svg_path VARCHAR(255) DEFAULT NULL COMMENT 'SVGファイルのパス'");
            
            // インデックスを追加
            $pdo->exec("CREATE INDEX idx_materials_svg ON materials(svg_path)");
            
            $message = 'SVGパス用のカラムとインデックスが正常に追加されました。';
        }
    } catch (Exception $e) {
        $error = 'マイグレーション実行エラー: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SVG機能マイグレーション - maruttoart管理画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">SVG機能マイグレーション</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> <?= h($message) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle"></i> <?= h($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <p>このマイグレーションでは以下の作業を実行します：</p>
                        <ul>
                            <li>materialsテーブルに<code>svg_path</code>カラムを追加</li>
                            <li>SVGファイル検索用のインデックスを作成</li>
                        </ul>
                        
                        <form method="POST">
                            <div class="d-flex justify-content-between">
                                <a href="/admin/" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> 戻る
                                </a>
                                <button type="submit" name="execute_migration" class="btn btn-primary">
                                    <i class="bi bi-play-circle"></i> マイグレーションを実行
                                </button>
                            </div>
                        </form>
                        
                        <?php if ($message && !$error): ?>
                            <div class="mt-3">
                                <a href="/admin/upload.php" class="btn btn-success">
                                    <i class="bi bi-upload"></i> SVG機能を試す
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>