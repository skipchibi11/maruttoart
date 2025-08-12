<?php
require_once 'config.php';

// 404ページは短期キャッシュ
setPublicCache(300, 600); // 5分 / CDN 10分
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - ページが見つかりません | maruttoart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #ffffff;
        }
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="text-center">
            <h1 class="display-1 fw-bold text-muted">404</h1>
            <h2 class="mb-4">ページが見つかりません</h2>
            <p class="mb-4">お探しのページは存在しないか、移動された可能性があります。</p>
            <div>
                <a href="/" class="btn btn-primary me-2">トップページに戻る</a>
            </div>
        </div>
    </div>
</body>
</html>
