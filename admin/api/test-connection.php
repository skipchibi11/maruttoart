<?php
require_once '../../config.php';
startAdminSession();

header('Content-Type: application/json');

// ログイン状態を確認
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'ログインが必要です']);
    exit;
}

// 管理者権限の追加確認
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => '管理者権限が必要です']);
    exit;
}

// リファラーチェック（同一ドメインからのリクエストのみ許可）
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (empty($referer) || strpos($referer, $host) === false) {
    http_response_code(403);
    echo json_encode(['error' => '不正なリクエストです']);
    exit;
}

header('Content-Type: application/json');

// 簡単な接続テスト
echo json_encode([
    'success' => true,
    'message' => 'API接続成功',
    'timestamp' => date('Y-m-d H:i:s'),
    'openai_key_status' => !empty($_ENV['OPENAI_API_KEY']) ? 'SET' : 'NOT_SET',
    'openai_key_length' => strlen($_ENV['OPENAI_API_KEY'] ?? ''),
    'php_version' => PHP_VERSION,
    'curl_available' => function_exists('curl_init') ? 'YES' : 'NO'
]);
?>
