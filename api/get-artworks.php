<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// キャッシュヘッダー（10分）
header('Cache-Control: public, max-age=600');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 600) . ' GMT');

$pdo = getDB();

try {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
    $limit = max(1, min($limit, 20)); // 1〜20件に制限
    
    $sql = "
        SELECT id, title, webp_path, svg_data, used_material_ids, created_at
        FROM community_artworks
        WHERE status = 'approved' AND svg_data IS NOT NULL AND svg_data != ''
        ORDER BY created_at DESC
        LIMIT ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    
    $artworks = $stmt->fetchAll();
    
    echo json_encode($artworks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー'], JSON_UNESCAPED_UNICODE);
    error_log('get-artworks.php error: ' . $e->getMessage());
}
