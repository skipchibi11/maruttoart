<?php
/**
 * Cloudflare R2 ユーティリティ関数
 * AWS Signature V4 を使用したファイル操作（削除など）
 */

/**
 * R2削除ログを記録
 */
function logR2Delete($message) {
    $logFile = __DIR__ . '/../logs/r2_delete.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

/**
 * AWS Signature Version 4 でリクエストに署名
 */
function signR2Request($method, $bucket, $key, $accessKey, $secretKey, $region = 'auto', $endpoint = null) {
    if (!$endpoint) {
        $endpoint = R2_ENDPOINT;
    }
    
    // タイムスタンプ
    $timestamp = time();
    $dateStamp = gmdate('Ymd', $timestamp);
    $amzDate = gmdate('Ymd\THis\Z', $timestamp);
    
    // URLエンコード（RFC 3986）
    $encodedKey = str_replace('%2F', '/', rawurlencode($key));
    
    // ホスト名を抽出
    $parsedEndpoint = parse_url($endpoint);
    $host = $parsedEndpoint['host'];
    
    // ペイロードハッシュ（DELETEリクエストは空ボディ）
    $payloadHash = hash('sha256', '');
    
    // Canonical Request
    $canonicalUri = "/{$bucket}/{$encodedKey}";
    $canonicalQueryString = '';
    $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$payloadHash}\nx-amz-date:{$amzDate}\n";
    $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
    
    $canonicalRequest = implode("\n", [
        $method,
        $canonicalUri,
        $canonicalQueryString,
        $canonicalHeaders,
        $signedHeaders,
        $payloadHash
    ]);
    
    // String to Sign
    $algorithm = 'AWS4-HMAC-SHA256';
    $credentialScope = "{$dateStamp}/{$region}/s3/aws4_request";
    $canonicalRequestHash = hash('sha256', $canonicalRequest);
    
    $stringToSign = implode("\n", [
        $algorithm,
        $amzDate,
        $credentialScope,
        $canonicalRequestHash
    ]);
    
    // Signing Key を計算
    $kDate = hash_hmac('sha256', $dateStamp, "AWS4{$secretKey}", true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    
    // 署名を計算
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    
    // Authorization ヘッダー
    $authorization = "{$algorithm} " .
        "Credential={$accessKey}/{$credentialScope}, " .
        "SignedHeaders={$signedHeaders}, " .
        "Signature={$signature}";
    
    // リクエストURL
    $url = "{$endpoint}/{$bucket}/{$encodedKey}";
    
    return [
        'url' => $url,
        'headers' => [
            'Host: ' . $host,
            'X-Amz-Content-Sha256: ' . $payloadHash,
            'X-Amz-Date: ' . $amzDate,
            'Authorization: ' . $authorization
        ],
        'amzDate' => $amzDate
    ];
}

/**
 * R2からファイルを削除
 * 
 * @param string $fileUrl R2の完全なURL（例: https://image.marutto.art/works/custom_xxx.png）
 * @return bool 成功時true、失敗時false
 */
function deleteR2File($fileUrl) {
    // R2 URLかどうかチェック
    if (strpos($fileUrl, 'http://') !== 0 && strpos($fileUrl, 'https://') !== 0) {
        logR2Delete("Not a URL, skipping: {$fileUrl}");
        return false; // ローカルファイルパスの場合は処理しない
    }
    
    // URL からキーを抽出
    // 例: https://image.marutto.art/works/custom_xxx.png -> works/custom_xxx.png
    $publicUrl = R2_PUBLIC_URL;
    
    logR2Delete("=== Attempting to delete file ===");
    logR2Delete("File URL: {$fileUrl}");
    logR2Delete("R2_PUBLIC_URL: {$publicUrl}");
    logR2Delete("R2_BUCKET: " . R2_BUCKET);
    logR2Delete("R2_ENDPOINT: " . R2_ENDPOINT);
    
    if (strpos($fileUrl, $publicUrl) !== 0) {
        logR2Delete("ERROR: URL does not match R2_PUBLIC_URL");
        return false;
    }
    
    $key = substr($fileUrl, strlen(rtrim($publicUrl, '/')) + 1);
    
    if (empty($key)) {
        logR2Delete("ERROR: Could not extract key from URL");
        return false;
    }
    
    logR2Delete("Extracted key: {$key}");
    
    try {
        $signedRequest = signR2Request(
            'DELETE',
            R2_BUCKET,
            $key,
            R2_ACCESS_KEY_ID,
            R2_SECRET_ACCESS_KEY
        );
        
        logR2Delete("Request URL: {$signedRequest['url']}");
        logR2Delete("Request headers: " . json_encode($signedRequest['headers']));
        
        // cURL でDELETEリクエスト送信
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $signedRequest['url']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $signedRequest['headers']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            logR2Delete("CURL error for {$key}: {$error}");
            return false;
        }
        
        logR2Delete("HTTP {$httpCode}, Response: {$response}");
        
        // 204 No Content = 削除成功
        // 404 Not Found = ファイルが存在しない（既に削除済みとして成功扱い）
        if ($httpCode === 204 || $httpCode === 404) {
            logR2Delete("SUCCESS: Deleted {$key} (HTTP {$httpCode})");
            return true;
        }
        
        logR2Delete("FAILED: HTTP {$httpCode} for {$key}");
        return false;
        
    } catch (Exception $e) {
        logR2Delete("EXCEPTION: " . $e->getMessage());
        return false;
    }
}

/**
 * ファイルを削除（R2またはローカル）
 * 
 * @param string $filePath ファイルのURLまたはパス
 * @param string $baseDir ローカルファイルのベースディレクトリ（デフォルト: ../)
 * @return bool 成功時true、失敗時false
 */
function deleteFile($filePath, $baseDir = '../') {
    if (empty($filePath)) {
        return true; // 空の場合は何もしない（成功扱い）
    }
    
    // R2 URL かどうかチェック
    $isRemoteUrl = (strpos($filePath, 'http://') === 0 || strpos($filePath, 'https://') === 0);
    
    if ($isRemoteUrl) {
        // R2 から削除
        logR2Delete("deleteFile() called with R2 URL: {$filePath}");
        return deleteR2File($filePath);
    } else {
        // ローカルファイルを削除
        $fullPath = rtrim($baseDir, '/') . '/' . ltrim($filePath, '/');
        logR2Delete("deleteFile() called with local path: {$filePath} -> {$fullPath}");
        if (file_exists($fullPath)) {
            if (unlink($fullPath)) {
                logR2Delete("Local file deleted: {$fullPath}");
                return true;
            } else {
                logR2Delete("Failed to delete local file: {$fullPath}");
                return false;
            }
        } else {
            logR2Delete("Local file does not exist (OK): {$fullPath}");
            // ファイルが存在しない場合は成功扱い
            return true;
        }
    }
}
