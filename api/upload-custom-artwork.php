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

    // アップロードディレクトリの準備
    $uploadBaseDir = '../uploads/community_artworks';
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
    $relativePath = 'uploads/community_artworks/' . $filename;

    // ファイル移動
    if (!move_uploaded_file($tmpPath, $destinationPath)) {
        sendError('ファイルの保存に失敗しました', 500);
    }

    // 権限設定
    chmod($destinationPath, 0644);

    // データベース接続
    $pdo = getDB();

    // デフォルトタイトル生成
    $defaultTitle = 'カスタム作品 ' . date('Y/m/d H:i');

    // SVGデータを取得
    $svgData = $_POST['svg_data'] ?? null;

    // データベースに挿入（必須カラムを追加）
    $originalFilename = $filename;
    $fileHash = hash_file('sha256', $destinationPath);
    
    $stmt = $pdo->prepare("
        INSERT INTO community_artworks 
        (title, pen_name, description, original_filename, file_path, file_size, file_hash, mime_type, svg_data, status, free_material_consent, created_at) 
        VALUES 
        (:title, :pen_name, :description, :original_filename, :file_path, :file_size, :file_hash, :mime_type, :svg_data, 'approved', 1, NOW())
    ");

    $stmt->execute([
        ':title' => $defaultTitle,
        ':pen_name' => 'カスタム作品',
        ':description' => '',
        ':original_filename' => $originalFilename,
        ':file_path' => $relativePath,
        ':file_size' => $fileSize,
        ':file_hash' => $fileHash,
        ':mime_type' => $mimeType,
        ':svg_data' => $svgData
    ]);

    $artworkId = $pdo->lastInsertId();

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
