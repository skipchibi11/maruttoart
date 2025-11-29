<?php
/**
 * 子供向けアトリエ作品アップロードAPI
 * - 即時承認（審査なし）
 * - kids_artworksテーブルに保存
 * - uploads/kidsフォルダに保存
 * - AIによるストーリー生成
 */

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/kids_upload_errors.log');

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

// WebPサムネイル生成関数
function generateWebPThumbnail($sourcePath, $destinationPath) {
    try {
        // getimagesizeを使用（exif_imagetypeの代替）
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            return null;
        }
        
        $imageType = $imageInfo[2];
        
        // ソース画像読み込み
        switch ($imageType) {
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            default:
                return null;
        }

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
            
            // Webアクセス可能な相対パスを返す
            // 絶対パスから 'uploads/kids/' 以降を抽出
            if (preg_match('#(uploads/kids/.+)$#', $webpPath, $matches)) {
                return '/' . $matches[1];
            }
            return null;
        }

        imagedestroy($thumbnail);
        imagedestroy($sourceImage);
        return null;

    } catch (Exception $e) {
        error_log("WebP generation error: " . $e->getMessage());
        return null;
    }
}



try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('POSTメソッドが必要です', 405);
    }

    // IPアドレス取得
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (empty($ipAddress)) {
        sendError('IPアドレスを取得できません', 400);
    }

    // データベース接続
    $pdo = getDB();

    // 1日3回制限チェック
    $today = date('Y-m-d');
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) FROM kids_artworks 
        WHERE DATE(created_at) = ? 
        AND ip_address = ?
        LIMIT 1
    ");
    $checkStmt->execute([$today, $ipAddress]);
    $uploadCount = $checkStmt->fetchColumn();

    if ($uploadCount >= 3) {
        sendError('きょうは もう 3つ とどけたよ！ また あした きてね！', 429);
    }

    // 入力データの取得
    $description = trim($_POST['description'] ?? '');

    // データベース接続
    $pdo = getDB();

    // ファイルアップロードチェック
    if (!isset($_FILES['artwork']) || $_FILES['artwork']['error'] !== UPLOAD_ERR_OK) {
        sendError('ファイルのアップロードに失敗しました');
    }

    $uploadedFile = $_FILES['artwork'];
    $tmpPath = $uploadedFile['tmp_name'];
    $fileSize = $uploadedFile['size'];

    // ファイルサイズチェック（10MB制限）
    $maxFileSize = 10 * 1024 * 1024;
    if ($fileSize > $maxFileSize) {
        sendError('ファイルサイズが大きすぎます（最大10MB）');
    }

    // MIMEタイプチェック
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    $allowedMimes = ['image/png', 'image/jpeg', 'image/jpg'];
    if (!in_array($mimeType, $allowedMimes)) {
        sendError('PNG または JPEG 形式のファイルのみアップロードできます');
    }

    // アップロードディレクトリの準備（年月フォルダ構造）
    $yearMonth = date('Y/m'); // 例: 2025/11
    $uploadDir = __DIR__ . '/../uploads/kids/' . $yearMonth . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // ファイル名生成（タイムスタンプ + ランダム文字列）
    $extension = ($mimeType === 'image/png') ? 'png' : 'jpg';
    $filename = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $savePath = $uploadDir . $filename;
    $relativePath = '/uploads/kids/' . $yearMonth . '/' . $filename;

    // ファイルを保存
    if (!move_uploaded_file($tmpPath, $savePath)) {
        sendError('ファイルの保存に失敗しました', 500);
    }

    // 画像の最適化とサイズ調整
    try {
        $image = null;
        if ($mimeType === 'image/png') {
            $image = imagecreatefrompng($savePath);
        } else {
            $image = imagecreatefromjpeg($savePath);
        }

        if ($image !== false) {
            $width = imagesx($image);
            $height = imagesy($image);
            $maxDimension = 1200;

            if ($width > $maxDimension || $height > $maxDimension) {
                $ratio = min($maxDimension / $width, $maxDimension / $height);
                $newWidth = round($width * $ratio);
                $newHeight = round($height * $ratio);

                $resized = imagecreatetruecolor($newWidth, $newHeight);
                
                if ($mimeType === 'image/png') {
                    imagealphablending($resized, false);
                    imagesavealpha($resized, true);
                }

                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                
                if ($mimeType === 'image/png') {
                    imagepng($resized, $savePath, 6);
                } else {
                    imagejpeg($resized, $savePath, 85);
                }

                imagedestroy($resized);
            }
            
            imagedestroy($image);
        }
    } catch (Exception $e) {
        error_log("Image optimization error: " . $e->getMessage());
    }

    // WebPサムネイル生成
    $webpPath = generateWebPThumbnail($savePath, $savePath);
    if ($webpPath) {
        error_log("WebP thumbnail generated: " . $webpPath);
    }

    // タイトルとストーリーはCronで生成するため、初期値は待機メッセージ
    $title = 'おはなしを つくっているよ';
    $aiStory = "いま あなたの えから、すてきな おはなしを つくっています。\nすこし まっててね！";

    error_log("Artwork uploaded - Title and story will be generated by cron");    if (mb_strlen($description) > 1000) {
        $description = mb_substr($description, 0, 1000);
    }

    // データベースに保存（即時公開）
    $sql = "INSERT INTO kids_artworks (
        title, 
        description, 
        ai_story,
        image_path,
        webp_path,
        ip_address,
        created_at
    ) VALUES (
        :title, 
        :description, 
        :ai_story,
        :image_path,
        :webp_path,
        :ip_address,
        NOW()
    )";

    $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':ai_story' => $aiStory,
            ':image_path' => $relativePath,
            ':webp_path' => $webpPath,
            ':ip_address' => $ipAddress
        ]);    $artworkId = $pdo->lastInsertId();

    error_log("Kids artwork uploaded successfully - ID: $artworkId - Will be processed by cron");

    // 成功レスポンス
    sendSuccess([
        'id' => $artworkId,
        'message' => 'さくひんを とどけました！ありがとう！',
        'artwork_url' => '/kids-work.php?id=' . $artworkId,
        'has_story' => false // cronで生成されるまでfalse
    ]);

} catch (Exception $e) {
    error_log("Kids artwork upload error: " . $e->getMessage());
    sendError('アップロード処理中にエラーが発生しました', 500, $e->getMessage());
}
