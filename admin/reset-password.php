<?php
require_once '../config.php';

$pdo = getDB();
$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$valid_token = false;
$email = '';

// トークンの有効性をチェック
if ($token) {
    try {
        $stmt = $pdo->prepare("
            SELECT email FROM password_resets 
            WHERE token = ? AND used = FALSE AND expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $reset_record = $stmt->fetch();
        
        if ($reset_record) {
            $valid_token = true;
            $email = $reset_record['email'];
        } else {
            $error = 'パスワード再設定リンクが無効か、有効期限が切れています。';
        }
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        $error = 'システムエラーが発生しました。';
    }
} else {
    $error = '無効なアクセスです。';
}

// パスワード変更処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    if (empty($password)) {
        $error = 'パスワードを入力してください。';
    } elseif (strlen($password) < 8) {
        $error = 'パスワードは8文字以上で入力してください。';
    } elseif ($password !== $password_confirm) {
        $error = 'パスワードが一致しません。';
    } else {
        try {
            // パスワードをハッシュ化
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // データベーストランザクション開始
            $pdo->beginTransaction();
            
            // ユーザーのパスワードを更新
            $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $email]);
            
            // トークンを使用済みにする
            $stmt = $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE token = ?");
            $stmt->execute([$token]);
            
            // コミット
            $pdo->commit();
            
            $success = 'パスワードが正常に変更されました。新しいパスワードでログインしてください。';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Password reset error: " . $e->getMessage());
            $error = 'パスワードの変更に失敗しました。時間をおいて再度お試しください。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新しいパスワードの設定 - マルットアート管理画面</title>
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
        .password-strength {
            font-size: 12px;
            margin-top: 5px;
        }
        .password-strength.weak { color: #dc3545; }
        .password-strength.medium { color: #fd7e14; }
        .password-strength.strong { color: #28a745; }
        .input-group button {
            border-left: 1px solid #dee2e6;
        }
        .input-group button:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card">
                    <div class="card-header text-center py-4">
                        <h4 class="mb-0">
                            <i class="fas fa-lock me-2"></i>
                            新しいパスワードの設定
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= h($error) ?>
                            </div>
                            
                            <?php if (!$valid_token): ?>
                                <div class="text-center mt-4">
                                    <a href="forgot-password.php" class="btn btn-outline-primary">
                                        <i class="fas fa-arrow-left me-2"></i>
                                        パスワード再設定をやり直す
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= h($success) ?>
                            </div>
                            <div class="text-center mt-4">
                                <a href="login.php" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    ログイン画面へ
                                </a>
                            </div>
                        <?php elseif ($valid_token): ?>
                            <div class="mb-4">
                                <p class="text-muted small">
                                    <i class="fas fa-user-circle me-2"></i>
                                    <strong><?= h($email) ?></strong> の新しいパスワードを設定してください。
                                </p>
                            </div>

                            <form method="post" action="" id="resetForm">
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-key me-2"></i>
                                        新しいパスワード
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               required 
                                               minlength="8"
                                               autocomplete="new-password"
                                               placeholder="8文字以上で入力してください">
                                        <button type="button" 
                                                class="btn btn-outline-secondary"
                                                onclick="togglePassword('password')">
                                            <i class="fas fa-eye" id="password-eye"></i>
                                        </button>
                                    </div>
                                    <div id="password-strength" class="password-strength"></div>
                                </div>

                                <div class="mb-4">
                                    <label for="password_confirm" class="form-label">
                                        <i class="fas fa-key me-2"></i>
                                        パスワード確認
                                    </label>
                                    <div class="input-group">
                                        <input type="password" 
                                               class="form-control" 
                                               id="password_confirm" 
                                               name="password_confirm" 
                                               required 
                                               autocomplete="new-password"
                                               placeholder="もう一度同じパスワードを入力">
                                        <button type="button" 
                                                class="btn btn-outline-secondary"
                                                onclick="togglePassword('password_confirm')">
                                            <i class="fas fa-eye" id="password_confirm-eye"></i>
                                        </button>
                                    </div>
                                    <div id="password-match" class="password-strength"></div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary py-2">
                                        <i class="fas fa-save me-2"></i>
                                        パスワードを変更する
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // パスワード表示切り替え
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '-eye');
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                eye.className = 'fas fa-eye';
            }
        }

        // パスワード強度チェック
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('password-strength');
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
                return;
            }
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            if (strength < 3) {
                strengthDiv.textContent = '弱いパスワードです';
                strengthDiv.className = 'password-strength weak';
            } else if (strength < 4) {
                strengthDiv.textContent = '普通のパスワードです';
                strengthDiv.className = 'password-strength medium';
            } else {
                strengthDiv.textContent = '強いパスワードです';
                strengthDiv.className = 'password-strength strong';
            }
            
            checkPasswordMatch();
        });

        // パスワード一致チェック
        document.getElementById('password_confirm').addEventListener('input', checkPasswordMatch);

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirm').value;
            const matchDiv = document.getElementById('password-match');
            
            if (confirm.length === 0) {
                matchDiv.textContent = '';
                return;
            }
            
            if (password === confirm) {
                matchDiv.textContent = 'パスワードが一致しています';
                matchDiv.className = 'password-strength strong';
            } else {
                matchDiv.textContent = 'パスワードが一致しません';
                matchDiv.className = 'password-strength weak';
            }
        }
    </script>
</body>
</html>
