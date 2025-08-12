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
        ],
        'litespeed' => [
            'detected' => false,
            'headers' => []
        ],
        'generic_cdn' => [
            'detected' => false,
            'headers' => []
        ]
    ],
    'cache_analysis' => [],
    'performance_metrics' => [
        'response_time_ms' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2),
        'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
        'file_size_kb' => round(filesize(__FILE__) / 1024, 2)
    ],
    'recommendations' => [],
    'next_steps' => []
];

// リクエストヘッダーを取得
foreach (getallheaders() as $name => $value) {
    $diagnostics['request_headers'][strtolower($name)] = $value;
    
    // CDN検出
    $header_lower = strtolower($name);
    
    // Cloudflare検出
    if (strpos($header_lower, 'cf-') === 0 || $header_lower === 'cf-ray') {
        $diagnostics['cdn_detection']['cloudflare']['detected'] = true;
        $diagnostics['cdn_detection']['cloudflare']['headers'][$name] = $value;
    }
    
    // Fastly検出
    if (strpos($header_lower, 'fastly-') === 0 || $header_lower === 'x-served-by') {
        $diagnostics['cdn_detection']['fastly']['detected'] = true;
        $diagnostics['cdn_detection']['fastly']['headers'][$name] = $value;
    }
    
    // AWS CloudFront検出
    if (strpos($header_lower, 'x-amz-') === 0 || $header_lower === 'x-cache') {
        $diagnostics['cdn_detection']['aws_cloudfront']['detected'] = true;
        $diagnostics['cdn_detection']['aws_cloudfront']['headers'][$name] = $value;
    }
    
    // LiteSpeed CDN検出
    if (strpos($header_lower, 'x-litespeed-') === 0 || 
        $header_lower === 'x-lscache' ||
        $header_lower === 'x-lsadc-cache' ||
        strpos($header_lower, 'lscache') !== false ||
        $header_lower === 'server' && strpos(strtolower($value), 'litespeed') !== false) {
        $diagnostics['cdn_detection']['litespeed']['detected'] = true;
        $diagnostics['cdn_detection']['litespeed']['headers'][$name] = $value;
    }
    
    // 一般的なCDNヘッダー検出
    if (in_array($header_lower, [
        'x-cache', 'x-cache-status', 'x-cache-hits', 'x-cache-lookup',
        'x-edge-location', 'x-proxy-cache', 'x-varnish',
        'via', 'x-forwarded-for', 'x-cdn', 'x-edge-request-id'
    ])) {
        $diagnostics['cdn_detection']['generic_cdn']['detected'] = true;
        $diagnostics['cdn_detection']['generic_cdn']['headers'][$name] = $value;
    }
}

// レスポンスヘッダーを設定し記録（LiteSpeed最適化）
$response_headers = [
    'Cache-Control' => 'public, max-age=3600, s-maxage=7200',
    'ETag' => '"' . md5($_SERVER['REQUEST_URI'] . time()) . '"',
    'Last-Modified' => gmdate('D, d M Y H:i:s', filemtime(__FILE__)) . ' GMT',
    'Vary' => 'Accept-Encoding',
    'X-Content-Type-Options' => 'nosniff',
    'X-Cache-Debug' => 'PHP-Generated',
    'X-Timestamp' => time(),
    // LiteSpeed特有のヘッダー
    'X-LiteSpeed-Cache' => 'miss',
    'X-LiteSpeed-Cache-Control' => 'public, max-age=3600'
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
        $diagnostics['cdn_detection']['aws_cloudfront']['detected'] ||
        $diagnostics['cdn_detection']['litespeed']['detected'] ||
        $diagnostics['cdn_detection']['generic_cdn']['detected']
    ),
    'recommendations' => []
];

// 推奨事項と次のステップ
if (!$diagnostics['cache_analysis']['cache_control_present']) {
    $diagnostics['cache_analysis']['recommendations'][] = 'Cache-Control ヘッダーを設定してください';
    $diagnostics['next_steps'][] = '.htaccess で Cache-Control ヘッダーを設定する';
}
if (!$diagnostics['cache_analysis']['etag_present']) {
    $diagnostics['cache_analysis']['recommendations'][] = 'ETag ヘッダーを設定してください';
    $diagnostics['next_steps'][] = 'PHPでETagヘッダーを生成・設定する';
}
if (!$diagnostics['cache_analysis']['cdn_detected']) {
    $diagnostics['cache_analysis']['recommendations'][] = 'CDN が検出されませんでした。CDN設定を確認してください';
    $diagnostics['next_steps'][] = 'CDNサービス（LiteSpeed/Cloudflare等）の設定を確認する';
}

// LiteSpeed特有の推奨事項
if ($diagnostics['cdn_detection']['litespeed']['detected']) {
    $diagnostics['recommendations'][] = '🚀 LiteSpeed CDN が検出されました！';
    $diagnostics['next_steps'][] = 'LiteSpeed Cache プラグインの設定を最適化する';
    $diagnostics['next_steps'][] = '.htaccess に LsCache ディレクティブを追加する';
} elseif ($diagnostics['cdn_detection']['generic_cdn']['detected']) {
    $diagnostics['recommendations'][] = '⚡ CDN が検出されました（種類不明）';
}

// パフォーマンス分析
if ($diagnostics['performance_metrics']['response_time_ms'] > 500) {
    $diagnostics['recommendations'][] = 'レスポンス時間が遅いです（' . $diagnostics['performance_metrics']['response_time_ms'] . 'ms）';
}

// 成功メッセージ
if ($diagnostics['cache_analysis']['cache_control_present'] && 
    $diagnostics['cache_analysis']['etag_present'] && 
    $diagnostics['cache_analysis']['cdn_detected']) {
    $diagnostics['recommendations'][] = '✅ キャッシュ設定は正常です！';
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
