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
$xApiKey = trim($_ENV['X_API_KEY'] ?? '');
$xApiSecret = trim($_ENV['X_API_SECRET'] ?? '');
$xAccessToken = trim($_ENV['X_ACCESS_TOKEN'] ?? '');
$xAccessTokenSecret = trim($_ENV['X_ACCESS_TOKEN_SECRET'] ?? '');
$xBearerToken = trim($_ENV['X_BEARER_TOKEN'] ?? '');

if (empty($xApiKey) || empty($xApiSecret) || empty($xAccessToken) || empty($xAccessTokenSecret)) {
    logMessage("エラー: X APIの認証情報が設定されていません。", $logFile);
    logMessage(".envファイルに以下の変数を設定してください:", $logFile);
    logMessage("X_API_KEY, X_API_SECRET, X_ACCESS_TOKEN, X_ACCESS_TOKEN_SECRET, X_BEARER_TOKEN", $logFile);
    exit(1);
}

// デバッグ: APIキーの存在確認（実際の値は表示しない）
logMessage("API Key設定確認: " . (strlen($xApiKey) > 0 ? "OK (" . strlen($xApiKey) . "文字)" : "NG"), $logFile);
logMessage("API Secret設定確認: " . (strlen($xApiSecret) > 0 ? "OK (" . strlen($xApiSecret) . "文字)" : "NG"), $logFile);
logMessage("Access Token設定確認: " . (strlen($xAccessToken) > 0 ? "OK (" . strlen($xAccessToken) . "文字)" : "NG"), $logFile);
logMessage("Access Token Secret設定確認: " . (strlen($xAccessTokenSecret) > 0 ? "OK (" . strlen($xAccessTokenSecret) . "文字)" : "NG"), $logFile);

// 投稿時間のチェック（9時、15時、21時のみ実行）
$currentHour = (int)date('G'); // 0-23の時間（先頭のゼロなし）
$allowedHours = [9, 15, 21];

if (!in_array($currentHour, $allowedHours)) {
    logMessage("現在の時刻: {$currentHour}時 - 投稿時間外のためスキップします（投稿時間: 9時、15時、21時）", $logFile);
    exit(0);
}

logMessage("投稿時間確認: {$currentHour}時 - 投稿を開始します", $logFile);


$pdo = getDB();

// 一時ファイルのパスを保持（クリーンアップ用）
$tmpFile = null;

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
    
    // 画像ファイルのパスを決定（PNG優先、WebPフォールバック）
    $rawImagePath = !empty($artwork['file_path']) ? $artwork['file_path'] : $artwork['webp_path'];
    
    if (empty($rawImagePath)) {
        logMessage("エラー: 画像パスが設定されていません。", $logFile);
        exit(1);
    }
    
    logMessage("画像パス: $rawImagePath", $logFile);
    
    // 画像パスがURLか相対パスかを判定
    $isUrl = (strpos($rawImagePath, 'http://') === 0 || strpos($rawImagePath, 'https://') === 0);
    
    if ($isUrl) {
        // CloudflareのR2などのURL - 一時ファイルにダウンロード
        logMessage("画像: URL形式（R2など）からダウンロード", $logFile);
        
        $tmpFile = tempnam(sys_get_temp_dir(), 'x_post_');
        $imageData = file_get_contents($rawImagePath);
        
        if ($imageData === false) {
            logMessage("エラー: URLから画像をダウンロードできませんでした: $rawImagePath", $logFile);
            exit(1);
        }
        
        file_put_contents($tmpFile, $imageData);
        $imagePath = $tmpFile;
        logMessage("一時ファイルに保存: $tmpFile", $logFile);
    } else {
        // ローカルファイルパス
        $localPath = __DIR__ . '/../' . $rawImagePath;
        
        if (file_exists($localPath)) {
            $imagePath = $localPath;
            logMessage("画像: ローカルファイルを使用", $logFile);
        } else {
            logMessage("エラー: 画像ファイルが見つかりません: $localPath", $logFile);
            exit(1);
        }
    }
    
    // 画像をアップロード
    $mediaId = uploadMediaToX($imagePath, $xApiKey, $xApiSecret, $xAccessToken, $xAccessTokenSecret, $logFile);
    
    if (!$mediaId) {
        logMessage("エラー: 画像のアップロードに失敗しました。", $logFile);
        exit(1);
    }
    
    logMessage("画像アップロード成功: メディアID = $mediaId", $logFile);
    
    // ツイート本文を作成（タイトル + ハッシュタグ）
    $hashtags = "\n\n#イラスト #illustration #maruttoart";

    // リミックスURLを追加
    $remixUrl = rtrim(SITE_URL, '/') . '/compose/?artwork_id=' . $artwork['id'];
    $remixSuffix = "\n\nあなたもこの作品をリミックス！\n" . $remixUrl;

    $tweetText = $artwork['title'] . $hashtags . $remixSuffix;

    // 280文字制限を考慮
    if (mb_strlen($tweetText) > 280) {
        $suffix = $hashtags . $remixSuffix;
        $maxTitleLength = 280 - mb_strlen($suffix);
        $tweetText = mb_substr($artwork['title'], 0, $maxTitleLength - 3) . "..." . $suffix;
    }
    
    // ツイートを投稿
    $tweetId = postTweetToX($tweetText, $mediaId, $xApiKey, $xApiSecret, $xAccessToken, $xAccessTokenSecret, $logFile);
    
    if ($tweetId) {
        logMessage("ツイート投稿成功: https://x.com/i/status/$tweetId", $logFile);
    } else {
        logMessage("エラー: ツイートの投稿に失敗しました。", $logFile);
        
        // 一時ファイルをクリーンアップ
        if ($tmpFile && file_exists($tmpFile)) {
            unlink($tmpFile);
            logMessage("一時ファイルを削除: $tmpFile", $logFile);
        }
        
        exit(1);
    }
    
} catch (Exception $e) {
    logMessage("エラー: " . $e->getMessage(), $logFile);
    
    // 一時ファイルをクリーンアップ
    if ($tmpFile && file_exists($tmpFile)) {
        unlink($tmpFile);
        logMessage("一時ファイルを削除: $tmpFile", $logFile);
    }
    
    exit(1);
} finally {
    // 一時ファイルをクリーンアップ（成功時）
    if ($tmpFile && file_exists($tmpFile)) {
        unlink($tmpFile);
        logMessage("一時ファイルを削除: $tmpFile", $logFile);
    }
}

/**
 * X (Twitter) に画像をアップロード
 */
function uploadMediaToX($imagePath, $apiKey, $apiSecret, $accessToken, $accessTokenSecret, $logFile) {
    $uploadUrl = 'https://upload.twitter.com/1.1/media/upload.json';
    
    // 画像を読み込む
    if (!file_exists($imagePath)) {
        logMessage("画像ファイルが存在しません: $imagePath", $logFile);
        return false;
    }
    
    $imageSize = filesize($imagePath);
    logMessage("画像サイズ: $imageSize bytes", $logFile);
    
    // OAuth 1.0aの署名を生成（マルチパートアップロード時はパラメータなし）
    $oauth = [
        'oauth_consumer_key' => $apiKey,
        'oauth_nonce' => md5(microtime() . mt_rand()),
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
    $authHeader = 'OAuth ';
    $headerParts = [];
    foreach ($oauth as $key => $value) {
        $headerParts[] = $key . '="' . rawurlencode($value) . '"';
    }
    $authHeader .= implode(', ', $headerParts);
    
    // CURLFileを使ってマルチパート形式でアップロード
    $postFields = [
        'media' => new CURLFile($imagePath, mime_content_type($imagePath), basename($imagePath))
    ];
    
    // cURLでアップロード
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $uploadUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $authHeader
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        logMessage("画像アップロードcURLエラー: $error", $logFile);
        return false;
    }
    
    curl_close($ch);
    
    if ($httpCode == 200) {
        $result = json_decode($response, true);
        logMessage("メディアアップロード成功", $logFile);
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
        'oauth_nonce' => md5(microtime() . mt_rand()),
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
    $authHeader = 'OAuth ';
    $headerParts = [];
    foreach ($oauth as $key => $value) {
        $headerParts[] = $key . '="' . rawurlencode($value) . '"';
    }
    $authHeader .= implode(', ', $headerParts);
    
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
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        logMessage("ツイート投稿cURLエラー: $error", $logFile);
        return false;
    }
    
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
