<?php
require_once 'config.php';

echo "<h1>データベース接続テスト</h1>";

try {
    $pdo = getDB();
    echo "<p style='color: green;'>✓ データベース接続成功</p>";
    
    // 管理者データを確認
    $stmt = $pdo->prepare("SELECT email, password FROM admins WHERE email = ?");
    $stmt->execute(['example@example.com']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "<p style='color: green;'>✓ 管理者アカウントが見つかりました</p>";
        echo "<p>Email: " . htmlspecialchars($admin['email']) . "</p>";
        echo "<p>Password Hash: " . substr($admin['password'], 0, 20) . "...</p>";
        
        // パスワードハッシュの形式をチェック
        if (strlen($admin['password']) > 20 && substr($admin['password'], 0, 4) === '$2y$') {
            echo "<p style='color: green;'>✓ パスワードは正しくハッシュ化されています</p>";
            
            // テストパスワードで検証
            if (password_verify('example1234', $admin['password'])) {
                echo "<p style='color: green;'>✓ パスワード「example1234」は正しく検証されます</p>";
            } else {
                echo "<p style='color: red;'>✗ パスワード「example1234」の検証に失敗しました</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ パスワードがハッシュ化されていません（平文のようです）</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ 管理者アカウントが見つかりません</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
