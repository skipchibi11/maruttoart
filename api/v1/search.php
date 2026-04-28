<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/_helpers.php';

setCorsHeaders();
setJsonHeaders();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondError('method_not_allowed', 'GETメソッドのみ対応しています。', 405);
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    respondError('missing_parameter', 'クエリパラメータ "q" は必須です。', 400);
}
if (mb_strlen($q) > 100) {
    respondError('invalid_parameter', 'クエリは100文字以内で指定してください。', 400);
}

$pdo = getDB();
checkRateLimit($pdo);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(API_PER_PAGE_MAX, max(1, (int)($_GET['per_page'] ?? API_PER_PAGE_DEFAULT)));

$keyword = '%' . $q . '%';
$params  = [$keyword, $keyword, $keyword];

$countStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT m.id)
    FROM materials m
    WHERE m.title LIKE ? OR m.description LIKE ? OR m.search_keywords LIKE ?
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$offset   = ($page - 1) * $perPage;
$dataStmt = $pdo->prepare("
    SELECT m.*,
           c.title AS category_title,
           c.slug  AS category_slug
    FROM materials m
    LEFT JOIN categories c ON c.id = m.category_id
    WHERE m.title LIKE ? OR m.description LIKE ? OR m.search_keywords LIKE ?
    ORDER BY m.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

// タグをまとめて取得
if (!empty($rows)) {
    $ids = array_column($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $tagStmt = $pdo->prepare("
        SELECT mt.material_id, t.id, t.name, t.slug
        FROM material_tags mt
        JOIN tags t ON t.id = mt.tag_id
        WHERE mt.material_id IN ($placeholders)
        ORDER BY t.name ASC
    ");
    $tagStmt->execute($ids);
    $tagMap = [];
    foreach ($tagStmt->fetchAll() as $tag) {
        $tagMap[$tag['material_id']][] = [
            'id'   => (int)$tag['id'],
            'name' => $tag['name'],
            'slug' => $tag['slug'],
        ];
    }
    foreach ($rows as &$row) {
        $row['tags'] = $tagMap[$row['id']] ?? [];
    }
    unset($row);
}

$baseUrl = rtrim(SITE_URL, '/') . '/api/v1/search?q=' . rawurlencode($q);

respondJson([
    'query'      => $q,
    'data'       => array_map('formatMaterial', $rows),
    'pagination' => buildPagination($total, $page, $perPage, $baseUrl),
]);
