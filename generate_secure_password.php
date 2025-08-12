<?php
// 強力なパスワード生成例

function generateSecurePassword($length = 16) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
    
    $all = $uppercase . $lowercase . $numbers . $symbols;
    $password = '';
    
    // 各文字種から最低1文字を保証
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $symbols[random_int(0, strlen($symbols) - 1)];
    
    // 残りの文字をランダムに生成
    for ($i = 4; $i < $length; $i++) {
        $password .= $all[random_int(0, strlen($all) - 1)];
    }
    
    // 文字をシャッフル
    return str_shuffle($password);
}

echo "推奨する強力なパスワード例:\n\n";

for ($i = 1; $i <= 5; $i++) {
    $password = generateSecurePassword(16);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    echo "パスワード {$i}: {$password}\n";
    echo "ハッシュ: {$hash}\n\n";
}

echo "推奨事項:\n";
echo "- 最低16文字以上\n";
echo "- 大文字、小文字、数字、記号を含む\n";
echo "- 辞書にない文字列\n";
echo "- 定期的な変更\n";
?>
