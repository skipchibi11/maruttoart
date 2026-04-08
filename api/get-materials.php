<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// キャッシュヘッダー（1時間）
header('Cache-Control: public, max-age=3600');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

$pdo = getDB();

try {
    // ランダム取得（AI自動作成用）
    if (isset($_GET['random'])) {
        $limit = min(20, max(1, intval($_GET['random']))); // 1-20の範囲
        $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        $hasSvg = isset($_GET['has_svg']) && $_GET['has_svg'] == '1';
        
        $sql = "
            SELECT m.id, m.title, m.slug, m.description, m.svg_path, m.webp_small_path, 
                   m.structured_bg_color, m.search_keywords,
                   c.title as category_name, c.slug as category_slug
            FROM materials m
            LEFT JOIN categories c ON m.category_id = c.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($hasSvg) {
            $sql .= " AND m.svg_path IS NOT NULL AND m.svg_path != ''";
        }
        
        if ($categoryId > 0) {
            $sql .= " AND m.category_id = ?";
            $params[] = $categoryId;
        }
        
        // LIMITは整数なので直接埋め込み（$limitは既にintvalでバリデーション済み）
        $sql .= " ORDER BY RAND() LIMIT " . $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $materials = $stmt->fetchAll();
        echo json_encode($materials, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    // カテゴリ名での取得
    elseif (isset($_GET['category'])) {
        $category = $_GET['category'];
        
        if ($category === 'all') {
            // 全素材を取得
            $sql = "
                SELECT m.id, m.title, m.slug, m.svg_path, m.webp_small_path, m.structured_bg_color, m.search_keywords,
                       c.title as category_name, c.slug as category_slug
                FROM materials m
                LEFT JOIN categories c ON m.category_id = c.id
                WHERE m.svg_path IS NOT NULL AND m.svg_path != ''
                ORDER BY c.title, m.title
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        } else {
            // カテゴリ指定で取得
            $sql = "
                SELECT m.id, m.title, m.slug, m.svg_path, m.webp_small_path, m.structured_bg_color, m.search_keywords,
                       c.title as category_name, c.slug as category_slug
                FROM materials m
                LEFT JOIN categories c ON m.category_id = c.id
                WHERE c.title = ? AND m.svg_path IS NOT NULL AND m.svg_path != ''
                ORDER BY m.title
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$category]);
        }
        
        $materials = $stmt->fetchAll();
        echo json_encode($materials, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    // ID指定での取得（おすすめ素材用）
    elseif (isset($_GET['ids'])) {
        $idsParam = $_GET['ids'];
        $ids = is_array($idsParam) ? $idsParam : explode(',', $idsParam);
        $ids = array_map('intval', $ids);
        
        if (empty($ids)) {
            echo json_encode([]);
            exit;
        }
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "
            SELECT m.id, m.title, m.slug, m.svg_path, m.webp_small_path, m.structured_bg_color, m.search_keywords,
                   c.title as category_name, c.slug as category_slug
            FROM materials m
            LEFT JOIN categories c ON m.category_id = c.id
            WHERE m.id IN ($placeholders) AND m.svg_path IS NOT NULL AND m.svg_path != ''
            ORDER BY FIELD(m.id, $placeholders)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($ids, $ids));
        
        $materials = $stmt->fetchAll();
        echo json_encode($materials, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    // 検索キーワードでの取得
    elseif (isset($_GET['search'])) {
        $keyword = '%' . $_GET['search'] . '%';
        
        $sql = "
            SELECT m.id, m.title, m.slug, m.svg_path, m.webp_small_path, m.structured_bg_color, m.search_keywords,
                   c.title as category_name, c.slug as category_slug
            FROM materials m
            LEFT JOIN categories c ON m.category_id = c.id
            WHERE (m.title LIKE ? OR m.search_keywords LIKE ?)
                  AND m.svg_path IS NOT NULL AND m.svg_path != ''
            ORDER BY m.title
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$keyword, $keyword]);
        
        $materials = $stmt->fetchAll();
        echo json_encode($materials, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    else {
        echo json_encode(['error' => 'パラメーターが不正です'], JSON_UNESCAPED_UNICODE);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー'], JSON_UNESCAPED_UNICODE);
    error_log('get-materials.php error: ' . $e->getMessage());
}
