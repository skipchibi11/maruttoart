<?php
require_once __DIR__ . '/../config.php';

$pdo = getDB();

try {
    // 再紹介可能なアイテム数を確認
    
    // 1. 素材の総数（SVGがあるもの）
    $materialCountStmt = $pdo->query("
        SELECT COUNT(*) FROM materials 
        WHERE svg_path IS NOT NULL AND svg_path != ''
    ");
    $totalMaterials = $materialCountStmt->fetchColumn();
    
    // 2. 作品の総数（承認済み、SVGデータがあるもの）
    $artworkCountStmt = $pdo->query("
        SELECT COUNT(*) FROM community_artworks 
        WHERE status = 'approved'
        AND svg_data IS NOT NULL
        AND svg_data != ''
    ");
    $totalArtworks = $artworkCountStmt->fetchColumn();
    
    $totalItems = $totalMaterials + $totalArtworks;
    
    // 3. 既に再紹介されたアイテム数
    $reintroducedCountStmt = $pdo->query("SELECT COUNT(*) FROM reintroduction_items");
    $reintroducedCount = $reintroducedCountStmt->fetchColumn();
    
    echo "総アイテム数: $totalItems (素材: $totalMaterials, 作品: $totalArtworks)\n";
    echo "再紹介済み: $reintroducedCount\n";
    
    // すべて再紹介済みの場合、テーブルをリセット
    if ($reintroducedCount >= $totalItems) {
        echo "すべてのアイテムを再紹介済み。テーブルをリセットします。\n";
        $pdo->exec("TRUNCATE TABLE reintroduction_items");
        $reintroducedCount = 0;
    }
    
    // 再紹介されていない素材をランダムに取得
    $materialStmt = $pdo->query("
        SELECT 
            m.id,
            m.title,
            m.description,
            m.structured_image_path,
            c.slug as category_slug,
            m.slug
        FROM materials m
        LEFT JOIN categories c ON m.category_id = c.id
        WHERE m.svg_path IS NOT NULL 
        AND m.svg_path != ''
        AND NOT EXISTS (
            SELECT 1 FROM reintroduction_items 
            WHERE item_type = 'material' AND item_id = m.id
        )
        ORDER BY RAND()
        LIMIT 1
    ");
    $material = $materialStmt->fetch();
    
    // 再紹介されていない作品をランダムに取得（SVGデータがあるもの）
    $artworkStmt = $pdo->query("
        SELECT 
            id,
            title,
            description,
            file_path
        FROM community_artworks
        WHERE status = 'approved'
        AND svg_data IS NOT NULL
        AND svg_data != ''
        AND NOT EXISTS (
            SELECT 1 FROM reintroduction_items 
            WHERE item_type = 'artwork' AND item_id = community_artworks.id
        )
        ORDER BY RAND()
        LIMIT 1
    ");
    $artwork = $artworkStmt->fetch();
    
    // 候補を配列に格納
    $candidates = [];
    if ($material) {
        $candidates[] = ['type' => 'material', 'data' => $material];
    }
    if ($artwork) {
        $candidates[] = ['type' => 'artwork', 'data' => $artwork];
    }
    
    if (empty($candidates)) {
        echo "再紹介可能なアイテムがありません。\n";
        exit(0);
    }
    
    // ランダムに1つ選択
    $selected = $candidates[array_rand($candidates)];
    $baseUrl = 'https://marutto.art';
    
    if ($selected['type'] === 'material') {
        $data = $selected['data'];
        $imageUrl = $baseUrl . '/' . $data['structured_image_path'];
        $pageUrl = $baseUrl . '/' . $data['category_slug'] . '/' . $data['slug'] . '/';
        $description = !empty($data['description']) ? $data['description'] : $data['title'];
        
        $insertStmt = $pdo->prepare("
            INSERT INTO reintroduction_items 
            (item_type, item_id, title, description, image_url, page_url) 
            VALUES ('material', :item_id, :title, :description, :image_url, :page_url)
        ");
        $insertStmt->execute([
            'item_id' => $data['id'],
            'title' => $data['title'],
            'description' => $description,
            'image_url' => $imageUrl,
            'page_url' => $pageUrl
        ]);
        
        echo "素材を再紹介リストに追加: {$data['title']}\n";
        
    } else {
        $data = $selected['data'];
        $imageUrl = $baseUrl . '/' . $data['file_path'];
        $pageUrl = $baseUrl . '/everyone-work.php?id=' . $data['id'];
        
        $insertStmt = $pdo->prepare("
            INSERT INTO reintroduction_items 
            (item_type, item_id, title, description, image_url, page_url) 
            VALUES ('artwork', :item_id, :title, :description, :image_url, :page_url)
        ");
        $insertStmt->execute([
            'item_id' => $data['id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'image_url' => $imageUrl,
            'page_url' => $pageUrl
        ]);
        
        echo "作品を再紹介リストに追加: {$data['title']}\n";
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
