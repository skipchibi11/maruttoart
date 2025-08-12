<?php
require_once 'config.php';

// キャッシュを無効化（GDPR APIはキャッシュしない）
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// CORS対応
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . getCurrentBaseUrl());
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['consent'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$consent = $input['consent'];

// Cookieドメインを計算（クライアントサイドで使用）
$cookieDomain = '';
$host = $_SERVER['HTTP_HOST'];
if (strpos($host, '.') !== false && $host !== 'localhost') {
    // サブドメインがある場合は親ドメインで設定
    $parts = explode('.', $host);
    if (count($parts) >= 2) {
        $cookieDomain = '.' . implode('.', array_slice($parts, -2));
    }
}

if ($consent === true || $consent === 'true') {
    // 同意した場合 - Cookie設定情報をクライアントに返す
    echo json_encode([
        'status' => 'success', 
        'message' => 'Consent accepted',
        'cookie' => [
            'name' => 'gdpr_consent',
            'value' => 'accepted',
            'expires' => time() + (365 * 24 * 60 * 60), // 1年
            'domain' => $cookieDomain,
            'path' => '/'
        ]
    ]);
} else {
    // 拒否した場合 - Cookie設定情報をクライアントに返す
    echo json_encode([
        'status' => 'success', 
        'message' => 'Consent rejected',
        'cookie' => [
            'name' => 'gdpr_consent',
            'value' => 'rejected',
            'expires' => time() + (365 * 24 * 60 * 60), // 1年
            'domain' => $cookieDomain,
            'path' => '/'
        ]
    ]);
}
?>
