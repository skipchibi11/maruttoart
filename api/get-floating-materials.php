<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config.php';

try {
    $pdo = getDB();
    
    // SVGパスが存在する素材のみを取得（ランダムに30件）
    $stmt = $pdo->prepare("
        SELECT DISTINCT id, title, slug, image_path, webp_medium_path, svg_path
        FROM materials
        WHERE svg_path IS NOT NULL 
        AND svg_path != ''
        ORDER BY RAND()
        LIMIT 30
    ");
    
    $stmt->execute();
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 画像パスを正しい形式に変換
    foreach ($materials as &$material) {
        // webp_medium_pathがあればそれを使用、なければimage_pathを使用
        $imagePath = !empty($material['webp_medium_path']) ? $material['webp_medium_path'] : $material['image_path'];
        
        // 相対パスを絶対パスに変換
        if (!empty($imagePath) && strpos($imagePath, 'http') !== 0) {
            $material['image_path'] = '/' . ltrim($imagePath, '/');
        } else {
            $material['image_path'] = $imagePath;
        }
    }
    unset($material);
    
    echo json_encode([
        'success' => true,
        'materials' => $materials,
        'count' => count($materials)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('get-floating-materials.php error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
