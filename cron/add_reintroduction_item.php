<?php
require_once __DIR__ . '/../config.php';

// 偶数時間のみ実行（0, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22時）
$currentHour = (int)date('G');
if ($currentHour % 2 !== 0) {
    echo "現在は奇数時間（{$currentHour}時）です。偶数時間のみ実行します。\n";
    exit(0);
}

echo "偶数時間（{$currentHour}時）を確認。処理を開始します。\n";

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
    
    // Claude APIを使用して英語翻訳
    function translateToClaude($title, $description) {
        $anthropicApiKey = getenv('ANTHROPIC_API_KEY');
        if (empty($anthropicApiKey)) {
            echo "警告: ANTHROPIC_API_KEYが設定されていません。翻訳をスキップします。\n";
            return ['title' => $title, 'description' => $description];
        }
        
        $prompt = "以下の日本語のタイトルと説明を自然な英語に翻訳してください。タイトルは短く簡潔に、説明は100文字以内で。JSON形式で返してください。\n\nタイトル: {$title}\n説明: {$description}\n\n期待する出力形式:\n{\"title\": \"英語のタイトル\", \"description\": \"英語の説明\"}";
        
        $data = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 500,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $anthropicApiKey,
                'anthropic-version: 2023-06-01'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['content'][0]['text'])) {
                $text = $result['content'][0]['text'];
                // JSON部分を抽出
                if (preg_match('/\{[^}]+\}/', $text, $matches)) {
                    $translation = json_decode($matches[0], true);
                    if ($translation && isset($translation['title']) && isset($translation['description'])) {
                        return $translation;
                    }
                }
            }
        }
        
        echo "警告: 翻訳に失敗しました。元のテキストを使用します。\n";
        return ['title' => $title, 'description' => $description];
    }
    
    if ($selected['type'] === 'material') {
        $data = $selected['data'];
        $imageUrl = $baseUrl . '/' . $data['structured_image_path'];
        $pageUrl = $baseUrl . '/' . $data['category_slug'] . '/' . $data['slug'] . '/';
        $description = !empty($data['description']) ? $data['description'] : $data['title'];
        
        // 英語翻訳
        $translated = translateToClaude($data['title'], $description);
        
        $insertStmt = $pdo->prepare("
            INSERT INTO reintroduction_items 
            (item_type, item_id, title, description, image_url, page_url) 
            VALUES ('material', :item_id, :title, :description, :image_url, :page_url)
        ");
        $insertStmt->execute([
            'item_id' => $data['id'],
            'title' => $translated['title'],
            'description' => $translated['description'],
            'image_url' => $imageUrl,
            'page_url' => $pageUrl
        ]);
        
        echo "素材を再紹介リストに追加: {$data['title']} → {$translated['title']}\n";
        
    } else {
        $data = $selected['data'];
        $imageUrl = $baseUrl . '/' . $data['file_path'];
        $pageUrl = $baseUrl . '/everyone-work.php?id=' . $data['id'];
        $description = $data['description'] ?? '';
        
        // 英語翻訳
        $translated = translateToClaude($data['title'], $description);
        
        $insertStmt = $pdo->prepare("
            INSERT INTO reintroduction_items 
            (item_type, item_id, title, description, image_url, page_url) 
            VALUES ('artwork', :item_id, :title, :description, :image_url, :page_url)
        ");
        $insertStmt->execute([
            'item_id' => $data['id'],
            'title' => $translated['title'],
            'description' => $translated['description'],
            'image_url' => $imageUrl,
            'page_url' => $pageUrl
        ]);
        
        echo "作品を再紹介リストに追加: {$data['title']} → {$translated['title']}\n";
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
