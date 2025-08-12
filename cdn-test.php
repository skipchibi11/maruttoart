<?php
// CDNキャッシュ診断ツール
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'request_uri' => $_SERVER['REQUEST_URI'],
        'http_host' => $_SERVER['HTTP_HOST'],
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ],
    'request_headers' => [],
    'response_headers' => [],
    'cdn_detection' => [
        'cloudflare' => [
            'detected' => false,
            'headers' => []
        ],
        'fastly' => [
            'detected' => false,
            'headers' => []
        ],
        'aws_cloudfront' => [
            'detected' => false,
            'headers' => []
        ]
    ],
    'cache_analysis' => []
];

// リクエストヘッダーを取得
foreach (getallheaders() as $name => $value) {
    $diagnostics['request_headers'][strtolower($name)] = $value;
    
    // CDN検出
    $header_lower = strtolower($name);
    if (strpos($header_lower, 'cf-') === 0 || $header_lower === 'cf-ray') {
        $diagnostics['cdn_detection']['cloudflare']['detected'] = true;
        $diagnostics['cdn_detection']['cloudflare']['headers'][$name] = $value;
    }
    if (strpos($header_lower, 'fastly-') === 0 || $header_lower === 'x-served-by') {
        $diagnostics['cdn_detection']['fastly']['detected'] = true;
        $diagnostics['cdn_detection']['fastly']['headers'][$name] = $value;
    }
    if (strpos($header_lower, 'x-amz-') === 0 || $header_lower === 'x-cache') {
        $diagnostics['cdn_detection']['aws_cloudfront']['detected'] = true;
        $diagnostics['cdn_detection']['aws_cloudfront']['headers'][$name] = $value;
    }
}

// レスポンスヘッダーを設定し記録
$response_headers = [
    'Cache-Control' => 'public, max-age=3600, s-maxage=7200',
    'ETag' => '"' . md5($_SERVER['REQUEST_URI'] . time()) . '"',
    'Last-Modified' => gmdate('D, d M Y H:i:s', filemtime(__FILE__)) . ' GMT',
    'Vary' => 'Accept-Encoding',
    'X-Content-Type-Options' => 'nosniff',
    'X-Cache-Debug' => 'PHP-Generated',
    'X-Timestamp' => time()
];

foreach ($response_headers as $header => $value) {
    header("$header: $value");
    $diagnostics['response_headers'][$header] = $value;
}

// キャッシュ分析
$diagnostics['cache_analysis'] = [
    'cache_control_present' => isset($diagnostics['response_headers']['Cache-Control']),
    'etag_present' => isset($diagnostics['response_headers']['ETag']),
    'last_modified_present' => isset($diagnostics['response_headers']['Last-Modified']),
    'vary_header' => $diagnostics['response_headers']['Vary'] ?? null,
    'cdn_detected' => (
        $diagnostics['cdn_detection']['cloudflare']['detected'] ||
        $diagnostics['cdn_detection']['fastly']['detected'] ||
        $diagnostics['cdn_detection']['aws_cloudfront']['detected']
    ),
    'recommendations' => []
];

// 推奨事項
if (!$diagnostics['cache_analysis']['cache_control_present']) {
    $diagnostics['cache_analysis']['recommendations'][] = 'Cache-Control ヘッダーを設定してください';
}
if (!$diagnostics['cache_analysis']['etag_present']) {
    $diagnostics['cache_analysis']['recommendations'][] = 'ETag ヘッダーを設定してください';
}
if (!$diagnostics['cache_analysis']['cdn_detected']) {
    $diagnostics['cache_analysis']['recommendations'][] = 'CDN が検出されませんでした。CDN設定を確認してください';
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
