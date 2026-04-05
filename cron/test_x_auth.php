<?php
/**
 * X API認証テストスクリプト
 * シンプルなAPIコール（アカウント情報取得）で認証をテスト
 */

require_once __DIR__ . '/../config.php';

// X API設定
$xApiKey = trim($_ENV['X_API_KEY'] ?? '');
$xApiSecret = trim($_ENV['X_API_SECRET'] ?? '');
$xAccessToken = trim($_ENV['X_ACCESS_TOKEN'] ?? '');
$xAccessTokenSecret = trim($_ENV['X_ACCESS_TOKEN_SECRET'] ?? '');

echo "=== X API認証テスト ===\n\n";

// キーの確認
echo "API Key: " . substr($xApiKey, 0, 10) . "... (" . strlen($xApiKey) . "文字)\n";
echo "API Secret: " . substr($xApiSecret, 0, 10) . "... (" . strlen($xApiSecret) . "文字)\n";
echo "Access Token: " . substr($xAccessToken, 0, 10) . "... (" . strlen($xAccessToken) . "文字)\n";
echo "Access Token Secret: " . substr($xAccessTokenSecret, 0, 10) . "... (" . strlen($xAccessTokenSecret) . "文字)\n\n";

if (empty($xApiKey) || empty($xApiSecret) || empty($xAccessToken) || empty($xAccessTokenSecret)) {
    echo "エラー: X APIの認証情報が不完全です。\n";
    exit(1);
}

// シンプルなGETリクエストでテスト（アカウント情報取得）
$url = 'https://api.twitter.com/1.1/account/verify_credentials.json';

$oauth = [
    'oauth_consumer_key' => $xApiKey,
    'oauth_nonce' => md5(microtime() . mt_rand()),
    'oauth_signature_method' => 'HMAC-SHA1',
    'oauth_timestamp' => time(),
    'oauth_token' => $xAccessToken,
    'oauth_version' => '1.0'
];

// 署名ベース文字列を作成
$baseString = buildBaseString($url, 'GET', $oauth);
$compositeKey = rawurlencode($xApiSecret) . '&' . rawurlencode($xAccessTokenSecret);
$oauthSignature = base64_encode(hash_hmac('sha1', $baseString, $compositeKey, true));
$oauth['oauth_signature'] = $oauthSignature;

// Authorizationヘッダー構築
$authHeader = 'OAuth ';
$headerParts = [];
foreach ($oauth as $key => $value) {
    $headerParts[] = $key . '="' . rawurlencode($value) . '"';
}
$authHeader .= implode(', ', $headerParts);

echo "リクエストURL: $url\n";
echo "OAuth Signature: " . substr($oauthSignature, 0, 20) . "...\n\n";

// cURLでリクエスト
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $authHeader]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTPステータス: $httpCode\n";
echo "レスポンス:\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    echo "✓ 認証成功！\n";
    echo "アカウント名: @{$data['screen_name']}\n";
    echo "ユーザー名: {$data['name']}\n";
} else {
    echo "✗ 認証失敗\n";
    echo $response . "\n";
}

function buildBaseString($url, $method, $params) {
    $return = [];
    ksort($params);
    
    foreach ($params as $key => $value) {
        $return[] = rawurlencode($key) . '=' . rawurlencode($value);
    }
    
    return $method . '&' . rawurlencode($url) . '&' . rawurlencode(implode('&', $return));
}
