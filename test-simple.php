<?php
// 簡易テスト用のindex.php

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "<!DOCTYPE html>";
echo "<html lang='ja'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>PHP アプリケーションテスト</title>";
echo "<style>body { font-family: Arial; margin: 40px; }</style>";
echo "</head>";
echo "<body>";
echo "<h1>🎉 新しいPHPアプリケーションが動作中！</h1>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP バージョン: " . phpversion() . "</p>";
echo "<p>サーバー: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "</p>";

// データベーステスト
try {
    require_once 'config.php';
    $pdo = getDB();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM materials");
    $result = $stmt->fetch();
    echo "<p>✅ データベース接続: 成功 (materials: {$result['count']}件)</p>";
} catch (Exception $e) {
    echo "<p>❌ データベースエラー: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>📁 ファイル確認</h2>";
$files = ['config.php', 'includes/gdpr-banner-new.php', 'includes/gtranslate.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p>✅ {$file}: 存在</p>";
    } else {
        echo "<p>❌ {$file}: 存在しない</p>";
    }
}

echo "<hr>";
echo "<p><a href='/'>← ホームに戻る</a></p>";
echo "</body>";
echo "</html>";
?>
