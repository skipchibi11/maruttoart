<?php
require_once '../config.php';
require_once '../includes/mail.php';

$pdo = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'メールアドレスを入力してください。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '有効なメールアドレスを入力してください。';
    } else {
        try {
            // ユーザーが存在するかチェック
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // 既存の未使用トークンを無効化
                $stmt = $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE email = ? AND used = FALSE");
                $stmt->execute([$email]);
                
                // 新しいトークンを生成
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', time() + 86400); // 24時間後
                
                // トークンをデータベースに保存
                $stmt = $pdo->prepare("
                    INSERT INTO password_resets (email, token, expires_at) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$email, $token, $expires_at]);
                
                // 再設定URLを生成
                $base_url = ($_ENV['SITE_URL'] ?? 'https://marutto.art');
                $reset_url = $base_url . "/admin/reset-password.php?token=" . $token;
                
                // メール送信
                $mailSender = new MailSender();
                
                try {
                    $mail_sent = $mailSender->sendPasswordResetEmail($email, $reset_url);
                    
                    if ($mail_sent) {
                        $success = 'パスワード再設定用のメールを送信しました。メールをご確認ください。';
                    } else {
                        $error = 'メールの送信に失敗しました。時間をおいて再度お試しください。';
                    }
                } catch (Exception $e) {
                    error_log("Mail sending exception: " . $e->getMessage());
                    $error = 'メールの送信中にエラーが発生しました。時間をおいて再度お試しください。';
                }
            } else {
                // セキュリティのため、存在しないメールアドレスでも成功メッセージを表示
                $success = 'パスワード再設定用のメールを送信しました。メールをご確認ください。';
            }
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = 'システムエラーが発生しました。時間をおいて再度お試しください。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワード再設定 - マルットアート管理画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .card {
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border-radius: 15px;
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-header text-center py-4">
                        <h4 class="mb-0">
                            <i class="fas fa-key me-2"></i>
                            パスワード再設定
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= h($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= h($success) ?>
                            </div>
                            <div class="text-center mt-4">
                                <a href="login.php" class="btn btn-outline-primary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    ログイン画面に戻る
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="post" action="">
                                <div class="mb-4">
                                    <p class="text-muted small">
                                        登録済みのメールアドレスを入力してください。<br>
                                        パスワード再設定用のリンクをお送りします。
                                    </p>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-2"></i>
                                        メールアドレス
                                    </label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           value="<?= h($_POST['email'] ?? '') ?>" 
                                           required 
                                           autocomplete="email"
                                           placeholder="admin@example.com">
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary py-2">
                                        <i class="fas fa-paper-plane me-2"></i>
                                        再設定メールを送信
                                    </button>
                                </div>
                            </form>

                            <div class="text-center mt-4">
                                <a href="login.php" class="text-muted text-decoration-none small">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    ログイン画面に戻る
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
