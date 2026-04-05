<?php
require_once __DIR__ . '/../config.php';

// ログファイルのパス
$logFile = __DIR__ . '/../logs/x_post.log';

// ログ出力関数
function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

// X API設定（環境変数から読み込み）
$xApiKey = $_ENV['X_API_KEY'] ?? '';
$xApiSecret = $_ENV['X_API_SECRET'] ?? '';
$xAccessToken = $_ENV['X_ACCESS_TOKEN'] ?? '';
$xAccessTokenSecret = $_ENV['X_ACCESS_TOKEN_SECRET'] ?? '';
$xBearerToken = $_ENV['X_BEARER_TOKEN'] ?? '';

if (empty($xApiKey) || empty($xApiSecret) || empty($xAccessToken) || empty($xAccessTokenSecret)) {
    logMessage("エラー: X APIの認証情報が設定されていません。", $logFile);
    logMessage(".envファイルに以下の変数を設定してください:", $logFile);
    logMessage("X_API_KEY, X_API_SECRET, X_ACCESS_TOKEN, X_ACCESS_TOKEN_SECRET, X_BEARER_TOKEN", $logFile);
    exit(1);
}

$pdo = getDB();

try {
    // 承認済みの作品からランダムに1件取得
    $stmt = $pdo->query("
        SELECT 
            id,
            title,
            file_path,
            webp_path
        FROM community_artworks
        WHERE status = 'approved'
        ORDER BY RAND()
        LIMIT 1
    ");
    
    $artwork = $stmt->fetch();
    
    if (!$artwork) {
        logMessage("投稿可能な作品が見つかりませんでした。", $logFile);
        exit(0);
    }
    
    logMessage("投稿する作品: {$artwork['title']}", $logFile);
    
    // 画像ファイルのパスを決定（WebPがあればWebP、なければPNG）
    $imagePath = '';
    if (!empty($artwork['webp_path']) && file_exists(__DIR__ . '/../' . $artwork['webp_path'])) {
        $imagePath = __DIR__ . '/../' . $artwork['webp_path'];
        logMessage("画像: WebP形式を使用", $logFile);
    } elseif (!empty($artwork['file_path']) && file_exists(__DIR__ . '/../' . $artwork['file_path'])) {
        $imagePath = __DIR__ . '/../' . $artwork['file_path'];
        logMessage("画像: PNG形式を使用", $logFile);
    } else {
        logMessage("エラー: 画像ファイルが見つかりません。", $logFile);
        exit(1);
    }
    
    // 画像をアップロード
    $mediaId = uploadMediaToX($imagePath, $xApiKey, $xApiSecret, $xAccessToken, $xAccessTokenSecret, $logFile);
    
    if (!$mediaId) {
        logMessage("エラー: 画像のアップロードに失敗しました。", $logFile);
        exit(1);
    }
    
    logMessage("画像アップロード成功: メディアID = $mediaId", $logFile);
    
    // ツイート本文を作成
    $tweetText =（タイトル + ハッシュタグ）
    $hashtags = "\n\n#イラスト #illustration #maruttoart";
    $tweetText = $artwork['title'] . $hashtags;
    
    // 280文字制限を考慮
    if (mb_strlen($tweetText) > 280) {
        $maxTitleLength = 280 - mb_strlen($hashtags);
        $tweetText = mb_substr($artwork['title'], 0, $maxTitleLength - 3) . "..." . $hashtags;
    }
    
    // ツイートを投稿
    $tweetId = postTweetToX($tweetText, $mediaId, $xApiKey, $xApiSecret, $xAccessToken, $xAccessTokenSecret, $logFile);
    
    if ($tweetId) {
        logMessage("ツイート投稿成功: https://x.com/i/status/$tweetId
        logMessage("エラー: ツイートの投稿に失敗しました。", $logFile);
        exit(1);
    }
    
} catch (Exception $e) {
    logMessage("エラー: " . $e->getMessage(), $logFile);
    exit(1);
}

/**
 * X (Twitter) に画像をアップロード
 */
function uploadMediaToX($imagePath, $apiKey, $apiSecret, $accessToken, $accessTokenSecret, $logFile) {
    $uploadUrl = 'https://upload.twitter.com/1.1/media/upload.json';
    
    // 画像を読み込む
    $imageData = file_get_contents($imagePath);
    if ($imageData === false) {
        return false;
    }
    
    // OAuth 1.0aの署名を生成
    $oauth = [
        'oauth_consumer_key' => $apiKey,
        'oauth_nonce' => time(),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => time(),
        'oauth_token' => $accessToken,
        'oauth_version' => '1.0'
    ];
    
    // 署名ベース文字列を作成
    $baseInfo = buildBaseString($uploadUrl, 'POST', $oauth);
    $compositeKey = rawurlencode($apiSecret) . '&' . rawurlencode($accessTokenSecret);
    $oauthSignature = base64_encode(hash_hmac('sha1', $baseInfo, $compositeKey, true));
    $oauth['oauth_signature'] = $oauthSignature;
    
    // Authorizationヘッダーを構築
    $authHeader = 'OAuth ' . http_build_query($oauth, '', ', ', PHP_QUERY_RFC3986);
    
    // cURLでアップロード
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $uploadUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'media' => new CURLFile($imagePath)
    ]);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authHeader
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $result = json_decode($response, true);
        return $result['media_id_string'] ?? false;
    }
    
    logMessage("画像アップロードエラー: HTTP $httpCode", $logFile);
    logMessage("レスポンス: $response", $logFile);
    return false;
}

/**
 * X (Twitter) にツイートを投稿
 */
function postTweetToX($text, $mediaId, $apiKey, $apiSecret, $accessToken, $accessTokenSecret, $logFile) {
    $url = 'https://api.twitter.com/2/tweets';
    
    // リクエストボディ
    $postData = [
        'text' => $text,
        'media' => [
            'media_ids' => [$mediaId]
        ]
    ];
    
    // OAuth 1.0aの署名を生成
    $oauth = [
        'oauth_consumer_key' => $apiKey,
        'oauth_nonce' => time(),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_timestamp' => time(),
        'oauth_token' => $accessToken,
        'oauth_version' => '1.0'
    ];
    
    // 署名ベース文字列を作成
    $baseInfo = buildBaseString($url, 'POST', $oauth);
    $compositeKey = rawurlencode($apiSecret) . '&' . rawurlencode($accessTokenSecret);
    $oauthSignature = base64_encode(hash_hmac('sha1', $baseInfo, $compositeKey, true));
    $oauth['oauth_signature'] = $oauthSignature;
    
    // Authorizationヘッダーを構築
    $authHeader = 'OAuth ' . http_build_query($oauth, '', ', ', PHP_QUERY_RFC3986);
    
    // cURLでツイート投稿
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authHeader,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $result = json_decode($response, true);
        return $result['data']['id'] ?? false;
    }
    
    logMessage("ツイート投稿エラー: HTTP $httpCode", $logFile);
    logMessage("レスポンス: $response", $logFile);
    return false;
}

/**
 * OAuth署名のベース文字列を構築
 */
function buildBaseString($url, $method, $params) {
    $return = [];
    ksort($params);
    
    foreach ($params as $key => $value) {
        $return[] = rawurlencode($key) . '=' . rawurlencode($value);
    }
    
    return $method . '&' . rawurlencode($url) . '&' . rawurlencode(implode('&', $return));
}
