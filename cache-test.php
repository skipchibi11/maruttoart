<?php
// キャッシュテスト用ファイル
header('Content-Type: application/json');

$headers = [];
foreach (getallheaders() as $name => $value) {
    $headers[strtolower($name)] = $value;
}

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'cache_headers' => [],
    'client_headers' => $headers,
    'server_info' => [
        'php_version' => PHP_VERSION,
        'apache_modules' => function_exists('apache_get_modules') ? apache_get_modules() : 'N/A'
    ]
];

// レスポンスヘッダーを設定
header('Cache-Control: public, max-age=3600');
header('ETag: "test-' . time() . '"');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime(__FILE__)) . ' GMT');

$response['cache_headers'] = [
    'cache_control' => 'public, max-age=3600',
    'etag' => '"test-' . time() . '"',
    'last_modified' => gmdate('D, d M Y H:i:s', filemtime(__FILE__)) . ' GMT'
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
