<?php
// セキュリティ上の理由により、このファイルは無効化されています
// 本番環境では環境変数テストファイルは使用すべきではありません

// IPアドレス制限（必要に応じて）
$allowedIPs = ['127.0.0.1', '::1']; // localhostのみ許可
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($clientIP, $allowedIPs)) {
    http_response_code(403);
    die('Access denied');
}

require_once 'config.php';

echo "=== Environment Variables Test ===\n";
echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_NAME: " . DB_NAME . "\n";
echo "DB_USER: " . DB_USER . "\n";
echo "DB_PASS: " . (DB_PASS ? '[SET]' : '[NOT SET]') . "\n";
echo "SITE_URL: " . SITE_URL . "\n";

echo "\n=== Environment Array ===\n";
$envVars = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'SITE_URL'];
foreach ($envVars as $var) {
    if ($var === 'DB_PASSWORD') {
        echo "$var: " . (isset($_ENV[$var]) && !empty($_ENV[$var]) ? '[SET]' : '[NOT SET]') . "\n";
    } else {
        echo "$var: " . ($_ENV[$var] ?? '[NOT SET]') . "\n";
    }
}

echo "\n=== Database Connection Test ===\n";
try {
    $pdo = getDB();
    echo "✓ Database connection successful!\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}
?>
