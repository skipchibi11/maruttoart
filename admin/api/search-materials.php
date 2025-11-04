<?php
// エラー表示を無効化（JSONレスポンスを壊さないため）
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// CORS対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit(0);
}

// config.phpの読み込みを試行
if (!file_exists('../../config.php')) {
    echo json_encode([
        'success' => false,
        'error' => 'config.phpが見つかりません',
        'materials' => [],
        'total' => 0
    ]);
    exit;
}

try {
    require_once '../../config.php';
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'config.php読み込みエラー: ' . $e->getMessage(),
        'materials' => [],
        'total' => 0
    ]);
    exit;
}

// データベース接続を試行
try {
    $pdo = getDB();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'データベース接続エラー: ' . $e->getMessage(),
        'materials' => [],
        'total' => 0
    ]);
    exit;
}

try {
    // 検索クエリを取得
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    // 空の検索クエリの場合は全件取得
    if (empty($query)) {
        $stmt = $pdo->prepare("
            SELECT 
                m.id, 
                m.title, 
                m.svg_path,
                m.image_path,
                m.webp_medium_path,
                c.title as category_name,
                GROUP_CONCAT(DISTINCT t.name) as tags,
                m.search_keywords_jp as keywords,
                m.created_at
            FROM materials m
            LEFT JOIN categories c ON m.category_id = c.id
            LEFT JOIN material_tags mt ON m.id = mt.material_id
            LEFT JOIN tags t ON mt.tag_id = t.id
            WHERE m.svg_path IS NOT NULL 
            GROUP BY m.id
            ORDER BY m.created_at DESC
            LIMIT 100
        ");
        $stmt->execute();
        $materials = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'materials' => $materials,
            'total' => count($materials),
            'query' => '',
            'isSearch' => false
        ]);
        exit;
    }
    
    // 検索クエリを全角・半角スペースで分割（OR検索用）
    $searchTerms = preg_split('/[\s　]+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
    
    if (empty($searchTerms)) {
        echo json_encode([
            'success' => false,
            'error' => '検索キーワードが無効です',
            'materials' => [],
            'total' => 0
        ]);
        exit;
    }
    
    // OR検索用のSQL条件を構築
    $searchConditions = [];
    $params = [];
    
    foreach ($searchTerms as $index => $term) {
        $titleParam = "title_{$index}";
        $categoryParam = "category_{$index}";
        $tagParam = "tag_{$index}";
        $keywordParam = "keyword_{$index}";
        
        $searchConditions[] = "(
            m.title LIKE :{$titleParam} OR 
            c.title LIKE :{$categoryParam} OR 
            t.name LIKE :{$tagParam} OR 
            m.search_keywords_jp LIKE :{$keywordParam}
        )";
        
        $params[$titleParam] = '%' . $term . '%';
        $params[$categoryParam] = '%' . $term . '%';
        $params[$tagParam] = '%' . $term . '%';
        $params[$keywordParam] = '%' . $term . '%';
    }
    
    // OR条件でSQL文を構築
    $whereClause = '(' . implode(' OR ', $searchConditions) . ')';
    
    // 完全一致・前方一致用のパラメータを追加
    $params['exact_title'] = $query;
    $params['start_title'] = $query . '%';
    
    $sql = "
        SELECT 
            m.id, 
            m.title, 
            m.svg_path,
            m.image_path,
            m.webp_medium_path,
            c.title as category_name,
            GROUP_CONCAT(DISTINCT t.name) as tags,
            m.search_keywords_jp as keywords,
            m.created_at
        FROM materials m
        LEFT JOIN categories c ON m.category_id = c.id
        LEFT JOIN material_tags mt ON m.id = mt.material_id
        LEFT JOIN tags t ON mt.tag_id = t.id
        WHERE m.svg_path IS NOT NULL 
        AND {$whereClause}
        GROUP BY m.id
        ORDER BY 
            CASE 
                WHEN m.title LIKE :exact_title THEN 1
                WHEN m.title LIKE :start_title THEN 2
                ELSE 3
            END,
            m.created_at DESC
        LIMIT 200
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $materials = $stmt->fetchAll();
    
    // 検索結果を返す
    echo json_encode([
        'success' => true,
        'materials' => $materials,
        'total' => count($materials),
        'query' => $query,
        'searchTerms' => $searchTerms,
        'isSearch' => true
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'データベースエラー: ' . $e->getMessage(),
        'materials' => [],
        'total' => 0
    ]);
}
?>