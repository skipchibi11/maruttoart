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

$pdo = getDB();
checkRateLimit($pdo);

$stmt = $pdo->prepare("
    SELECT c.id,
           c.title,
           c.slug,
           c.sort_order,
           COUNT(m.id) AS material_count
    FROM categories c
    LEFT JOIN materials m ON m.category_id = c.id
    GROUP BY c.id, c.title, c.slug, c.sort_order
    ORDER BY c.sort_order ASC, c.title ASC
");
$stmt->execute();
$categories = $stmt->fetchAll();

$data = array_map(fn($row) => [
    'id'             => (int)$row['id'],
    'title'          => $row['title'],
    'slug'           => $row['slug'],
    'material_count' => (int)$row['material_count'],
    'url'            => rtrim(SITE_URL, '/') . '/' . $row['slug'] . '/',
], $categories);

respondJson(['data' => $data]);
