<?php
/**
 * カスタムサイズページ作品アップロードAPI
 * ペンネーム・タイトル・説明なしのシンプル投稿
 */

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/upload_errors.log');

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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('POSTメソッドが必要です', 405);
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

    // 使用素材IDの取得（オプション）
    $usedMaterialIds = trim($_POST['used_material_ids'] ?? '');
    
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

    // ファイルアップロードチェック
    if (!isset($_FILES['artwork']) || $_FILES['artwork']['error'] !== UPLOAD_ERR_OK) {
        sendError('ファイルのアップロードに失敗しました');
    }

    $uploadedFile = $_FILES['artwork'];
    $tmpPath = $uploadedFile['tmp_name'];
    $fileSize = $uploadedFile['size'];

    // ファイルサイズ制限（10MB）
    if ($fileSize > 10 * 1024 * 1024) {
        sendError('ファイルサイズは10MB以下にしてください');
    }

    // 画像形式チェック
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        sendError('有効な画像ファイルをアップロードしてください');
    }

    $mimeType = $imageInfo['mime'];
    if (!in_array($mimeType, ['image/png', 'image/jpeg', 'image/jpg'])) {
        sendError('PNG、JPEGファイルのみアップロード可能です');
    }

    // アップロードディレクトリの準備（年月フォルダ構成）
    $yearMonth = date('Y/m');
    $uploadBaseDir = '../uploads/everyone-works/' . $yearMonth;
    if (!is_dir($uploadBaseDir)) {
        if (!mkdir($uploadBaseDir, 0755, true)) {
            sendError('アップロードディレクトリの作成に失敗しました', 500);
        }
    }

    // ファイル名生成
    $extension = ($mimeType === 'image/png') ? 'png' : 'jpg';
    $uniqueId = uniqid('custom_', true);
    $filename = $uniqueId . '.' . $extension;
    $destinationPath = $uploadBaseDir . '/' . $filename;
    $relativePath = 'uploads/everyone-works/' . $yearMonth . '/' . $filename;

    // ファイル移動
    if (!move_uploaded_file($tmpPath, $destinationPath)) {
        sendError('ファイルの保存に失敗しました', 500);
    }

    // 権限設定
    chmod($destinationPath, 0644);

    // WebPサムネイル生成処理（300px最大サイズ）
    $webpPath = null;
    try {
        // 元画像を読み込み
        $sourceImage = null;
        if ($mimeType === 'image/png') {
            $sourceImage = imagecreatefrompng($destinationPath);
        } elseif ($mimeType === 'image/jpeg' || $mimeType === 'image/jpg') {
            $sourceImage = imagecreatefromjpeg($destinationPath);
        }

        if ($sourceImage) {
            // 元画像のサイズを取得
            $originalWidth = imagesx($sourceImage);
            $originalHeight = imagesy($sourceImage);
            error_log("Original size: {$originalWidth}x{$originalHeight}");

            // サムネイルのサイズ計算（最大300px）
            $maxSize = 300;
            $scale = min($maxSize / $originalWidth, $maxSize / $originalHeight, 1);
            $newWidth = (int)round($originalWidth * $scale);
            $newHeight = (int)round($originalHeight * $scale);
            error_log("Thumbnail size: {$newWidth}x{$newHeight}");

            // サムネイル画像作成
            $thumbnailImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // 透過対応
            imagealphablending($thumbnailImage, false);
            imagesavealpha($thumbnailImage, true);
            $transparent = imagecolorallocatealpha($thumbnailImage, 0, 0, 0, 127);
            imagefill($thumbnailImage, 0, 0, $transparent);
            imagealphablending($thumbnailImage, true);
            
            // リサイズ
            imagecopyresampled(
                $thumbnailImage, $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $originalWidth, $originalHeight
            );

            // WebPファイル名生成（_thumb.webp サフィックス）
            $webpFilename = $uniqueId . '_thumb.webp';
            $webpDestination = $uploadBaseDir . '/' . $webpFilename;
            $webpRelativePath = 'uploads/everyone-works/' . $yearMonth . '/' . $webpFilename;

            // WebP形式で保存（品質80）
            if (imagewebp($thumbnailImage, $webpDestination, 80)) {
                chmod($webpDestination, 0644);
                $webpPath = $webpRelativePath;
                $webpSize = filesize($webpDestination);
                error_log("WebP thumbnail created: {$webpRelativePath} ({$newWidth}x{$newHeight}, {$webpSize} bytes)");
            } else {
                error_log("Failed to create WebP thumbnail: " . $webpDestination);
            }

            imagedestroy($thumbnailImage);
            imagedestroy($sourceImage);
        }
    } catch (Exception $e) {
        error_log("WebP thumbnail conversion error: " . $e->getMessage());
        // WebP変換失敗しても処理は継続
    }

    // データベース接続
    $pdo = getDB();

    // デフォルトタイトル生成
    $defaultTitle = 'カスタム作品 ' . date('Y/m/d H:i');

    // SVGデータを取得
    $svgData = $_POST['svg_data'] ?? null;

    // データベースに挿入（必須カラムを追加）
    $originalFilename = $filename;
    $fileHash = hash_file('sha256', $destinationPath);
    
    // used_material_idsカラムの存在確認
    $hasUsedMaterialIds = false;
    try {
        $pdo->query("SELECT used_material_ids FROM community_artworks LIMIT 1");
        $hasUsedMaterialIds = true;
    } catch (Exception $e) {
        $hasUsedMaterialIds = false;
        error_log("used_material_ids column not found: " . $e->getMessage());
    }
    
    if ($hasUsedMaterialIds) {
        $stmt = $pdo->prepare("
            INSERT INTO community_artworks 
            (title, pen_name, description, original_filename, file_path, webp_path, file_size, file_hash, mime_type, svg_data, used_material_ids, status, free_material_consent, created_at) 
            VALUES 
            (:title, :pen_name, :description, :original_filename, :file_path, :webp_path, :file_size, :file_hash, :mime_type, :svg_data, :used_material_ids, 'approved', 1, NOW())
        ");
        
        $stmt->execute([
            ':title' => $defaultTitle,
            ':pen_name' => 'カスタム作品',
            ':description' => '',
            ':original_filename' => $originalFilename,
            ':file_path' => $relativePath,
            ':webp_path' => $webpPath,
            ':file_size' => $fileSize,
            ':file_hash' => $fileHash,
            ':mime_type' => $mimeType,
            ':svg_data' => $svgData,
            ':used_material_ids' => $usedMaterialIds
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO community_artworks 
            (title, pen_name, description, original_filename, file_path, webp_path, file_size, file_hash, mime_type, svg_data, status, free_material_consent, created_at) 
            VALUES 
            (:title, :pen_name, :description, :original_filename, :file_path, :webp_path, :file_size, :file_hash, :mime_type, :svg_data, 'approved', 1, NOW())
        ");
        
        $stmt->execute([
            ':title' => $defaultTitle,
            ':pen_name' => 'カスタム作品',
            ':description' => '',
            ':original_filename' => $originalFilename,
            ':file_path' => $relativePath,
            ':webp_path' => $webpPath,
            ':file_size' => $fileSize,
            ':file_hash' => $fileHash,
            ':mime_type' => $mimeType,
            ':svg_data' => $svgData
        ]);
    }

    $artworkId = $pdo->lastInsertId();

    // 投稿制限を記録
    $updateStmt = $pdo->prepare("
        INSERT INTO post_limits (ip_address, post_date, post_count) 
        VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE 
        post_count = post_count + 1,
        updated_at = CURRENT_TIMESTAMP
    ");
    $updateStmt->execute([$userIP, $today]);

    // 成功レスポンス
    sendSuccess([
        'artwork_id' => $artworkId,
        'message' => '作品を投稿しました！'
    ]);

} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendError('システムエラーが発生しました', 500, $e->getMessage());
}
