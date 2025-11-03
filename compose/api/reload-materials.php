<?php
require_once '../../config.php';

// JSONレスポンスを設定
header('Content-Type: application/json');

// キャッシュを無効化
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

try {
    $pdo = getDB();

    // ランダムに10個のSVG素材を取得
    $stmt = $pdo->prepare("
        SELECT id, title, slug, image_path, svg_path, webp_medium_path, category_id, structured_bg_color
        FROM materials 
        WHERE svg_path IS NOT NULL 
        AND svg_path != '' 
        ORDER BY RAND() 
        LIMIT 10
    ");
    $stmt->execute();
    $materials = $stmt->fetchAll();

    // カテゴリ情報も取得
    $categoryIds = array_column($materials, 'category_id');
    if (!empty($categoryIds)) {
        $placeholders = str_repeat('?,', count($categoryIds) - 1) . '?';
        $categoryStmt = $pdo->prepare("SELECT id, slug FROM categories WHERE id IN ($placeholders)");
        $categoryStmt->execute($categoryIds);
        $categoriesById = [];
        while ($cat = $categoryStmt->fetch()) {
            $categoriesById[$cat['id']] = $cat;
        }
        
        // 素材データにカテゴリ情報を追加
        foreach ($materials as &$material) {
            $material['category_slug'] = $categoriesById[$material['category_id']]['slug'] ?? '';
        }
    }

    echo json_encode([
        'success' => true,
        'materials' => $materials
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'データベースエラーが発生しました'
    ]);
}
?>