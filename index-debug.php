<?php
// デバッグ用のindex-debug.php

// エラー報告を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>";
echo "<html lang='ja'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>デバッグ情報</title>";
echo "<style>body { font-family: monospace; margin: 20px; } .error { color: red; } .success { color: green; }</style>";
echo "</head>";
echo "<body>";

echo "<h1>デバッグ情報</h1>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>Step 1: require_once 'config.php'</h2>";
try {
    require_once 'config.php';
    echo "<p class='success'>✅ config.php読み込み成功</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ config.phpエラー: " . $e->getMessage() . "</p>";
    echo "</body></html>";
    exit;
}

echo "<h2>Step 2: require_once 'includes/gdpr-banner-new.php'</h2>";
try {
    require_once 'includes/gdpr-banner-new.php';
    echo "<p class='success'>✅ gdpr-banner-new.php読み込み成功</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ gdpr-banner-new.phpエラー: " . $e->getMessage() . "</p>";
    echo "</body></html>";
    exit;
}

echo "<h2>Step 3: require_once 'includes/gtranslate.php'</h2>";
try {
    require_once 'includes/gtranslate.php';
    echo "<p class='success'>✅ gtranslate.php読み込み成功</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ gtranslate.phpエラー: " . $e->getMessage() . "</p>";
    echo "</body></html>";
    exit;
}

echo "<h2>Step 4: データベース接続テスト</h2>";
try {
    $pdo = getDB();
    echo "<p class='success'>✅ データベース接続成功</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ データベース接続エラー: " . $e->getMessage() . "</p>";
    echo "</body></html>";
    exit;
}

echo "<h2>Step 5: データクエリテスト</h2>";
try {
    $perPage = 20;
    $page = 1;
    $offset = 0;
    $search = '';
    
    $whereClause = "WHERE 1=1";
    $params = [];
    $countParams = [];
    
    $countSql = "SELECT COUNT(*) FROM materials " . $whereClause;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalItems = $countStmt->fetchColumn();
    
    echo "<p class='success'>✅ COUNT クエリ成功: {$totalItems}件</p>";
    
    $totalPages = ceil($totalItems / $perPage);
    
    $sql = "SELECT * FROM materials " . $whereClause . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $materials = $stmt->fetchAll();
    
    echo "<p class='success'>✅ SELECT クエリ成功: " . count($materials) . "件取得</p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ クエリエラー: " . $e->getMessage() . "</p>";
    echo "</body></html>";
    exit;
}

echo "<h2>✅ 全ステップ成功！</h2>";
echo "<p>アプリケーションは正常に動作可能です。</p>";
echo "<p><strong>materials データ:</strong></p>";
echo "<ul>";
foreach ($materials as $material) {
    echo "<li>" . h($material['title']) . " (" . h($material['slug']) . ")</li>";
}
echo "</ul>";

echo "</body>";
echo "</html>";
?>
