<?php
require_once '../config.php';

// 管理画面専用セッション開始
startAdminSession();

// すでにログインしている場合はダッシュボードにリダイレクト
if (isLoggedIn()) {
    header('Location: /admin/');
    exit;
}

// ログインページもキャッシュ無効化
setNoCache();

$error = '';
$debug_info = '';

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'メールアドレスとパスワードを入力してください。';
    } else {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            $debug_info = "デバッグ情報:\n";
            $debug_info .= "- 入力メール: " . $email . "\n";
            $debug_info .= "- アカウント検索: " . ($admin ? "成功 (ID: {$admin['id']})" : "失敗") . "\n";
            
            if ($admin) {
                $passwordValid = password_verify($password, $admin['password']);
                $debug_info .= "- パスワード検証: " . ($passwordValid ? "成功" : "失敗") . "\n";
                $debug_info .= "- 保存されているハッシュ: " . substr($admin['password'], 0, 30) . "...\n";
                
                if ($passwordValid) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $debug_info .= "- セッション設定: 成功\n";
                    header('Location: /admin/');
                    exit;
                }
            }
            
            $error = 'メールアドレスまたはパスワードが間違っています。';
            
        } catch (Exception $e) {
            $error = 'ログイン処理中にエラーが発生しました: ' . $e->getMessage();
            $debug_info = "エラー詳細: " . $e->getTraceAsString();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理画面ログイン - maruttoart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold">maruttoart</h2>
                        <p class="text-muted">管理画面</p>
                    </div>

                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= h($error) ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($debug_info): ?>
                    <div class="alert alert-info" role="alert">
                        <small><pre><?= h($debug_info) ?></pre></small>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">メールアドレス</label>
                            <input type="email" class="form-control" id="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">パスワード</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">ログイン</button>
                    </form>

                    <div class="text-center mt-4">
                        <a href="/" class="text-decoration-none">← 公式サイトに戻る</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
