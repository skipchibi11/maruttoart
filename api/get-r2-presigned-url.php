<?php
/**
 * R2 Presigned URL 生成API（SDK不使用版）
 * community_artworks アップロード用の署名付きURLを生成
 * AWS Signature Version 4 を PHP ネイティブで実装
 */

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/r2_presigned_errors.log');

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function sendError($message, $code = 400, $details = null) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'details' => $details,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendSuccess($data) {
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * AWS Signature Version 4 でPresigned URLを生成
 * S3互換APIに対応（Cloudflare R2）
 */
function generatePresignedUrl($method, $bucket, $key, $contentType, $accessKey, $secretKey, $region, $endpoint, $expiresIn = 900) {
    // タイムスタンプ
    $timestamp = time();
    $dateStamp = gmdate('Ymd', $timestamp);
    $amzDate = gmdate('Ymd\THis\Z', $timestamp);
    
    // URLエンコード（RFC 3986）
    $encodedKey = str_replace('%2F', '/', rawurlencode($key));
    
    // クエリパラメータ
    $algorithm = 'AWS4-HMAC-SHA256';
    $credentialScope = "{$dateStamp}/{$region}/s3/aws4_request";
    $credential = "{$accessKey}/{$credentialScope}";
    
    $queryParams = [
        'X-Amz-Algorithm' => $algorithm,
        'X-Amz-Credential' => $credential,
        'X-Amz-Date' => $amzDate,
        'X-Amz-Expires' => $expiresIn,
        'X-Amz-SignedHeaders' => 'host',
    ];
    
    // Content-Type を含める
    if (!empty($contentType)) {
        $queryParams['Content-Type'] = $contentType;
    }
    
    // クエリ文字列をソート
    ksort($queryParams);
    $canonicalQueryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
    
    // ホスト名を抽出
    $parsedEndpoint = parse_url($endpoint);
    $host = $parsedEndpoint['host'];
    
    // Canonical Request
    $canonicalUri = "/{$bucket}/{$encodedKey}";
    $canonicalHeaders = "host:{$host}\n";
    $signedHeaders = 'host';
    $payloadHash = 'UNSIGNED-PAYLOAD';
    
    $canonicalRequest = implode("\n", [
        $method,
        $canonicalUri,
        $canonicalQueryString,
        $canonicalHeaders,
        $signedHeaders,
        $payloadHash
    ]);
    
    // String to Sign
    $stringToSign = implode("\n", [
        $algorithm,
        $amzDate,
        $credentialScope,
        hash('sha256', $canonicalRequest)
    ]);
    
    // Signing Key
    $kDate = hash_hmac('sha256', $dateStamp, "AWS4{$secretKey}", true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    
    // Signature
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    
    // Presigned URL
    $presignedUrl = "{$endpoint}/{$bucket}/{$encodedKey}?{$canonicalQueryString}&X-Amz-Signature={$signature}";
    
    return $presignedUrl;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('POSTメソッドが必要です', 405);
    }

    // R2設定チェック
    if (empty(R2_ACCOUNT_ID) || empty(R2_BUCKET) || empty(R2_ACCESS_KEY_ID) || empty(R2_SECRET_ACCESS_KEY)) {
        sendError('R2設定が不完全です', 500);
    }

    // 投稿制限チェック（1日1回）
    $pdo = getDB();
    $userIP = $_SERVER['REMOTE_ADDR'];
    $today = date('Y-m-d');
    
    $checkStmt = $pdo->prepare("
        SELECT post_count 
        FROM post_limits 
        WHERE ip_address = ? AND post_date = ?
    ");
    $checkStmt->execute([$userIP, $today]);
    $limitRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($limitRecord && $limitRecord['post_count'] >= 1) {
        sendError('1日の投稿制限に達しました。明日また投稿してください。', 429);
    }

    // リクエストデータ取得
    $input = json_decode(file_get_contents('php://input'), true);
    $fileName = $input['fileName'] ?? '';
    $fileType = $input['fileType'] ?? '';
    $fileSize = $input['fileSize'] ?? 0;

    // バリデーション
    if (empty($fileName) || empty($fileType)) {
        sendError('ファイル名とファイルタイプが必要です');
    }

    // ファイルサイズ制限（10MB）
    if ($fileSize > 10 * 1024 * 1024) {
        sendError('ファイルサイズは10MB以下にしてください');
    }

    // MIMEタイプチェック
    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
    if (!in_array($fileType, $allowedTypes)) {
        sendError('PNG、JPEG、WebPファイルのみアップロード可能です');
    }

    // 拡張子取得
    if ($fileType === 'image/png') {
        $extension = 'png';
    } elseif ($fileType === 'image/webp') {
        $extension = 'webp';
    } else {
        $extension = 'jpg';
    }

    // R2キー生成
    $uniqueId = uniqid('custom_', true);
    $key = "works/{$uniqueId}.{$extension}";

    // Presigned URL を生成（15分有効）
    $presignedUrl = generatePresignedUrl(
        'PUT',
        R2_BUCKET,
        $key,
        $fileType,
        R2_ACCESS_KEY_ID,
        R2_SECRET_ACCESS_KEY,
        'auto',
        R2_ENDPOINT,
        900 // 15分
    );

    // レスポンス
    sendSuccess([
        'presignedUrl' => $presignedUrl,
        'key' => $key,
        'uniqueId' => $uniqueId,
        'expiresIn' => 900, // 15分
    ]);

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendError('サーバーエラーが発生しました: ' . $e->getMessage(), 500);
}
