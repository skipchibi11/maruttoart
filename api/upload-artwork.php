<?php
/**
 * みんなの作品アップロードAPI
 * 安全・軽量・やさしいUXを優先した実装
 */

// デバッグ用：エラーログを有効化
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/upload_errors.log');

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// エラーハンドリング用の関数
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

try {
    // POSTメソッドチェック
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('POSTメソッドが必要です', 405);
    }

    // 入力データの取得・バリデーション
    $title = trim($_POST['title'] ?? '');
    $penName = trim($_POST['pen_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $usedMaterialIds = trim($_POST['used_material_ids'] ?? '');
    $freeConsent = 1; // 投稿=フリー素材提供同意とみなす

    // 必須項目チェック
    if (empty($title) || mb_strlen($title) > 100) {
        sendError('タイトルは1〜100文字で入力してください');
    }

    if (empty($penName) || mb_strlen($penName) > 50) {
        sendError('ペンネームは1〜50文字で入力してください');
    }

    if (mb_strlen($description) > 1000) {
        sendError('説明は1000文字以内で入力してください');
    }

    // 使用素材IDの検証
    if (!empty($usedMaterialIds)) {
        // カンマ区切りの数値のみ許可
        if (!preg_match('/^[0-9,]+$/', $usedMaterialIds)) {
            sendError('使用素材IDの形式が正しくありません');
        }
        // 最大長制限（1000文字以内）
        if (mb_strlen($usedMaterialIds) > 1000) {
            sendError('使用素材IDが長すぎます');
        }
    }

    // データベース接続
    $pdo = getDB();

    // used_material_idsカラムの存在確認（より安全な方法）
    $hasUsedMaterialIds = false;
    try {
        $pdo->query("SELECT used_material_ids FROM community_artworks LIMIT 1");
        $hasUsedMaterialIds = true;
    } catch (Exception $e) {
        // カラムが存在しない場合
        $hasUsedMaterialIds = false;
        $usedMaterialIds = '';
        error_log("used_material_ids column not found: " . $e->getMessage());
    }

    // ファイルアップロードチェック
    if (!isset($_FILES['artwork']) || $_FILES['artwork']['error'] !== UPLOAD_ERR_OK) {
        sendError('ファイルのアップロードに失敗しました');
    }

    $uploadedFile = $_FILES['artwork'];
    $tmpPath = $uploadedFile['tmp_name'];
    $originalFilename = $uploadedFile['name'];
    $fileSize = $uploadedFile['size'];

    // ファイルサイズ制限（10MB）
    if ($fileSize > 10 * 1024 * 1024) {
        sendError('ファイルサイズは10MB以下にしてください');
    }

    // MIMEタイプ検証
    $allowedMimes = [
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg'
    ];

    $detectedMime = mime_content_type($tmpPath);
    if (!isset($allowedMimes[$detectedMime])) {
        sendError('PNG、WebP、SVG形式のファイルのみアップロード可能です');
    }

    // ファイルハッシュ生成（重複チェック用）
    $fileHash = hash_file('sha256', $tmpPath);

    // 重複チェック
    $stmt = $pdo->prepare("SELECT id, title, pen_name FROM community_artworks WHERE file_hash = ?");
    $stmt->execute([$fileHash]);
    $duplicate = $stmt->fetch();

    if ($duplicate) {
        sendError('同じ画像が既に投稿されています', 409, [
            'existing_title' => $duplicate['title'],
            'existing_pen_name' => $duplicate['pen_name']
        ]);
    }

    // IPアドレス取得・投稿制限チェック
    $clientIP = getClientIP();
    $today = date('Y-m-d');

    // 管理者IPアドレスのホワイトリスト
    $adminIPs = [
        '127.0.0.1',
        '::1',
        // 必要に応じて管理者のIPアドレスを追加
        '133.201.147.225',
    ];
    
    // IPアドレスで管理者を判定
    $isAdmin = in_array($clientIP, $adminIPs);
    
    if ($isAdmin) {
        error_log("Upload API - Admin detected by IP: {$clientIP}");
    }

    // 管理者は投稿制限を完全に除外
    $todayCount = 0;
    if (!$isAdmin) {
        // 今日のアップロード数を取得（一般ユーザーのみ）
        $stmt = $pdo->prepare("
            SELECT upload_count FROM artwork_upload_limits 
            WHERE ip_address = ? AND upload_date = ?
        ");
        $stmt->execute([$clientIP, $today]);
        $todayCount = $stmt->fetchColumn() ?: 0;

        // 1日1件まで
        if ($todayCount >= 1) {
            sendError('1日の投稿上限（1件）に達しています。明日再度お試しください。', 429);
        }
    }

    // ファイル保存処理
    try {
        $year = date('Y');
        $month = date('m');
        $uploadDir = "../uploads/everyone-works/{$year}/{$month}/";
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // ユニークファイル名生成
        $extension = $allowedMimes[$detectedMime];
        $newFilename = uniqid('artwork_') . '_' . time() . '.' . $extension;
        $destinationPath = $uploadDir . $newFilename;

        if (!move_uploaded_file($tmpPath, $destinationPath)) {
            throw new Exception('ファイルの移動に失敗しました');
        }

        // 画像処理（PNG変換・WebP生成）
        $imageInfo = processImage($destinationPath, $destinationPath, $detectedMime);
        if (!$imageInfo) {
            throw new Exception('画像の処理に失敗しました');
        }

        // データベースに作品情報を保存（カラム存在チェック付き）
        if ($hasUsedMaterialIds) {
            $stmt = $pdo->prepare("
                INSERT INTO community_artworks (
                    title, pen_name, description, original_filename, 
                    file_path, webp_path, file_size, image_width, image_height,
                    file_hash, mime_type, free_material_consent, 
                    ip_address, user_agent, used_material_ids
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO community_artworks (
                    title, pen_name, description, original_filename, 
                    file_path, webp_path, file_size, image_width, image_height,
                    file_hash, mime_type, free_material_consent, 
                    ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
        }

        $relativePath = "uploads/everyone-works/{$year}/{$month}/" . $newFilename;
        $webpPath = $imageInfo['webp_path'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        if ($hasUsedMaterialIds) {
            $stmt->execute([
                $title, $penName, $description, $originalFilename,
                $relativePath, $webpPath, $fileSize, 
                $imageInfo['width'], $imageInfo['height'],
                $fileHash, $detectedMime, $freeConsent,
                $clientIP, $userAgent, $usedMaterialIds
            ]);
        } else {
            $stmt->execute([
                $title, $penName, $description, $originalFilename,
                $relativePath, $webpPath, $fileSize, 
                $imageInfo['width'], $imageInfo['height'],
                $fileHash, $detectedMime, $freeConsent,
                $clientIP, $userAgent
            ]);
        }

        $artworkId = $pdo->lastInsertId();

        // 投稿制限カウンター更新（管理者以外）
        if (!$isAdmin) {
            updateUploadLimit($pdo, $clientIP, $today);
        }

        // 成功レスポンス
        sendSuccess([
            'id' => $artworkId,
            'title' => $title,
            'pen_name' => $penName,
            'file_path' => $relativePath,
            'webp_path' => $webpPath,
            'remaining_uploads' => $isAdmin ? '無制限' : max(0, 1 - ($todayCount + 1)),
            'is_admin' => $isAdmin,
            'used_material_ids' => $usedMaterialIds
        ]);

    } catch (Exception $e) {
        // ファイル削除（エラー時）
        if (isset($destinationPath) && file_exists($destinationPath)) {
            unlink($destinationPath);
        }
        
        throw $e;
    }

} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // デバッグ情報を含めてエラーを返す
    sendError('アップロード処理中にエラーが発生しました', 500, [
        'error_message' => $e->getMessage(),
        'error_file' => basename($e->getFile()),
        'error_line' => $e->getLine(),
        'debug_info' => 'Check error logs for details'
    ]);
}

/**
 * 画像処理（リサイズ・WebP変換）
 */
function processImage($sourcePath, $destinationPath, $mimeType) {
    try {
        // 画像情報を取得
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new Exception('画像情報の取得に失敗しました');
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        // WebPサムネイル生成
        $webpPath = null;
        if ($mimeType === 'image/png') {
            $webpPath = generateWebPThumbnail($sourcePath, $destinationPath);
        }

        return [
            'width' => $width,
            'height' => $height,
            'webp_path' => $webpPath
        ];

    } catch (Exception $e) {
        error_log("Image processing error: " . $e->getMessage());
        return false;
    }
}

/**
 * WebPサムネイル生成
 */
function generateWebPThumbnail($sourcePath, $destinationPath) {
    try {
        $sourceImage = imagecreatefrompng($sourcePath);
        if (!$sourceImage) {
            return null;
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        // サムネイルサイズ（最大400px）
        $maxSize = 400;
        if ($sourceWidth > $sourceHeight) {
            $newWidth = $maxSize;
            $newHeight = intval($sourceHeight * ($maxSize / $sourceWidth));
        } else {
            $newHeight = $maxSize;
            $newWidth = intval($sourceWidth * ($maxSize / $sourceHeight));
        }

        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);

        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefill($thumbnail, 0, 0, $transparent);

        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);

        // WebPファイル名生成
        $webpFileName = pathinfo($destinationPath, PATHINFO_FILENAME) . '_thumb.webp';
        $webpPath = dirname($destinationPath) . '/' . $webpFileName;

        if (imagewebp($thumbnail, $webpPath, 80)) {
            imagedestroy($thumbnail);
            imagedestroy($sourceImage);
            
            // 相対パスを返す
            return str_replace('../', '', $webpPath);
        }

        imagedestroy($thumbnail);
        imagedestroy($sourceImage);
        return null;

    } catch (Exception $e) {
        error_log("WebP generation error: " . $e->getMessage());
        return null;
    }
}

/**
 * クライアントIPアドレス取得
 */
function getClientIP() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * 投稿制限更新
 */
function updateUploadLimit($pdo, $ipAddress, $date) {
    $stmt = $pdo->prepare("
        INSERT INTO artwork_upload_limits (ip_address, upload_date, upload_count) 
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE 
        upload_count = upload_count + 1,
        last_upload_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([$ipAddress, $date]);
}
?>