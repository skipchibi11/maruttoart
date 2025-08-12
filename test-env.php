<?php
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
    echo "$var: " . ($_ENV[$var] ?? '[NOT SET]') . "\n";
}

echo "\n=== Database Connection Test ===\n";
try {
    $pdo = getDB();
    echo "✓ Database connection successful!\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
}
?>
