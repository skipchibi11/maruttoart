<?php
require_once 'config.php';

echo "<h1>管理者アカウント追加</h1>";

try {
    $pdo = getDB();
    
    // 追加する管理者の情報
    $newAdmins = [
        [
            'email' => 'test@test.com',
            'password' => 'itsumo5963'
        ],
        [
            'email' => 'test2@test.com',
            'password' => 'itsumo5963'
        ]
    ];
    
    $addedCount = 0;
    $skippedCount = 0;
    
    foreach ($newAdmins as $adminData) {
        $email = $adminData['email'];
        $password = $adminData['password'];
        
        // パスワードをハッシュ化
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // 重複チェック
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetchColumn() > 0) {
            echo "<p style='color: orange;'>⚠️ メールアドレス「{$email}」は既に登録されています。スキップします。</p>";
            $skippedCount++;
        } else {
            // データベースに追加
            $stmt = $pdo->prepare("INSERT INTO admins (email, password) VALUES (?, ?)");
            $result = $stmt->execute([$email, $hashedPassword]);
            
            if ($result) {
                echo "<p style='color: green;'>✓ 管理者アカウントが正常に追加されました: {$email}</p>";
                echo "<p><strong>メールアドレス:</strong> {$email}</p>";
                echo "<p><strong>パスワード:</strong> {$password}</p>";
                echo "<p><strong>ハッシュ:</strong> " . substr($hashedPassword, 0, 30) . "...</p>";
                
                // パスワード検証テスト
                if (password_verify($password, $hashedPassword)) {
                    echo "<p style='color: green;'>✓ パスワード検証テスト: 成功</p>";
                } else {
                    echo "<p style='color: red;'>✗ パスワード検証テスト: 失敗</p>";
                }
                
                echo "<hr style='margin: 20px 0;'>";
                $addedCount++;
                
            } else {
                echo "<p style='color: red;'>✗ 管理者アカウント「{$email}」の追加に失敗しました</p>";
            }
        }
    }
    
    echo "<h3>追加結果サマリー</h3>";
    echo "<p>✓ 追加されたアカウント: {$addedCount}件</p>";
    echo "<p>⚠️ スキップされたアカウント: {$skippedCount}件</p>";
    
    // 現在の全管理者を表示
    echo "<hr>";
    echo "<h3>現在の管理者一覧</h3>";
    $stmt = $pdo->prepare("SELECT id, email, created_at FROM admins ORDER BY id");
    $stmt->execute();
    $admins = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
    echo "<tr style='background-color: #f8f9fa;'><th style='padding: 8px;'>ID</th><th style='padding: 8px;'>メールアドレス</th><th style='padding: 8px;'>作成日時</th></tr>";
    foreach ($admins as $admin) {
        echo "<tr>";
        echo "<td style='padding: 8px; text-align: center;'>{$admin['id']}</td>";
        echo "<td style='padding: 8px;'>{$admin['email']}</td>";
        echo "<td style='padding: 8px;'>{$admin['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='/admin/login.php' style='display: inline-block; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;'>ログインページへ</a></p>";
echo "<p style='color: #dc3545;'><strong>重要:</strong> このファイルはセキュリティ上の理由により、確認後必ず削除してください。</p>";
echo "<p style='font-family: monospace; background-color: #f8f9fa; padding: 8px; border-radius: 4px;'>削除コマンド: rm -f add_admin.php</p>";
?>
