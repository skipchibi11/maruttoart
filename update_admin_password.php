<?php
require_once 'config.php';

echo "<h1>パスワード更新実行</h1>";

try {
    $pdo = getDB();
    
    // 新しいハッシュ化パスワードを生成
    $newPassword = password_hash('example1234', PASSWORD_DEFAULT);
    
    // データベースを更新
    $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE email = ?");
    $result = $stmt->execute([$newPassword, 'example@example.com']);
    
    if ($result) {
        $affectedRows = $stmt->rowCount();
        echo "<p style='color: green;'>✓ パスワード更新成功！ ({$affectedRows}行が更新されました)</p>";
        
        // 更新後の確認
        $stmt = $pdo->prepare("SELECT email, password FROM admins WHERE email = ?");
        $stmt->execute(['example@example.com']);
        $admin = $stmt->fetch();
        
        if ($admin) {
            echo "<p>Email: " . htmlspecialchars($admin['email']) . "</p>";
            echo "<p>New Password Hash: " . substr($admin['password'], 0, 20) . "...</p>";
            
            // パスワード検証テスト
            if (password_verify('example1234', $admin['password'])) {
                echo "<p style='color: green;'>✓ パスワード「example1234」の検証成功！</p>";
                echo "<p style='color: blue;'><strong>ログインが可能になりました。</strong></p>";
            } else {
                echo "<p style='color: red;'>✗ パスワード検証に失敗</p>";
            }
        }
        
    } else {
        echo "<p style='color: red;'>✗ パスワード更新に失敗しました</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='/admin/login.php'>管理画面ログインへ</a></p>";
echo "<p><a href='/db_test.php'>データベーステストへ戻る</a></p>";
?>
