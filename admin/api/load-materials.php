<?php
require_once '../../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDB();
    
    // ページ番号を取得（デフォルト: 1）
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 20; // 1ページあたりの表示件数
    $offset = ($page - 1) * $perPage;
    
    // 総件数を取得
    $countSql = "SELECT COUNT(DISTINCT id) FROM materials WHERE svg_path IS NOT NULL AND svg_path != ''";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $perPage);
    
    // ページネーション付きでSVG素材を取得
    $stmt = $pdo->prepare("
        SELECT DISTINCT id, title, slug, image_path, svg_path, webp_medium_path, category_id, created_at
        FROM materials 
        WHERE svg_path IS NOT NULL 
        AND svg_path != '' 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$perPage, $offset]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // レスポンス作成
    $response = [
        'success' => true,
        'materials' => $materials,
        'page' => $page,
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'per_page' => $perPage
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
