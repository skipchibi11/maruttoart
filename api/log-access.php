<?php
/**
 * クライアントサイド アクセスログ記録API
 * 既存のaccess_logsテーブルを活用
 */

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// POSTメソッドのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getDB();
    
    // リクエストボディを取得
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        // JSON形式
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
    } else {
        // FormData形式（Beacon API）
        $data = $_POST;
    }
    
    // データ検証
    $pageUrl = $data['page_url'] ?? '';
    $hasScrolled = intval($data['has_scrolled'] ?? 0);
    
    if (empty($pageUrl)) {
        throw new Exception('page_url is required');
    }
    
    // IPアドレスを取得
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // 既存のaccess_logsテーブルにログを記録
    $stmt = $pdo->prepare("
        INSERT INTO access_logs 
        (ip_address, page_url, accessed_at) 
        VALUES (?, ?, NOW())
    ");
    
    $stmt->execute([
        $ipAddress,
        substr($pageUrl, 0, 255)
    ]);
    
    echo json_encode([
        'success' => true,
        'id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    error_log("Client log error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
