<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/_helpers.php';

setCorsHeaders();
setJsonHeaders();

// OPTIONSプリフライトには空レスポンス
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondError('method_not_allowed', 'GETメソッドのみ対応しています。', 405);
}

$pdo = getDB();
checkRateLimit($pdo);

$slug = $_GET['slug'] ?? null;

// ---- 素材詳細: GET /api/v1/materials/{slug} ----
if ($slug !== null) {
    if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        respondError('invalid_slug', 'slugの形式が正しくありません。', 400);
    }

    $stmt = $pdo->prepare("
        SELECT m.*,
               c.title AS category_title,
               c.slug  AS category_slug
        FROM materials m
        LEFT JOIN categories c ON c.id = m.category_id
        WHERE m.slug = ?
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $row = $stmt->fetch();

    if (!$row) {
        respondError('not_found', '指定されたslugsの素材が見つかりません。', 404);
    }

    // タグを取得
    $tagStmt = $pdo->prepare("
        SELECT t.id, t.name, t.slug
        FROM tags t
        JOIN material_tags mt ON mt.tag_id = t.id
        WHERE mt.material_id = ?
        ORDER BY t.name ASC
    ");
    $tagStmt->execute([$row['id']]);
    $row['tags'] = $tagStmt->fetchAll();

    respondJson(['data' => formatMaterial($row)]);
}

// ---- 素材一覧: GET /api/v1/materials ----
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(API_PER_PAGE_MAX, max(1, (int)($_GET['per_page'] ?? API_PER_PAGE_DEFAULT)));

// カテゴリフィルタ（スラッグ指定）
$categorySlug = $_GET['category'] ?? null;
$categoryId   = null;
if ($categorySlug !== null) {
    if (!preg_match('/^[a-z0-9\-]+$/', $categorySlug)) {
        respondError('invalid_category', 'categoryの形式が正しくありません。', 400);
    }
    $catStmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
    $catStmt->execute([$categorySlug]);
    $catRow = $catStmt->fetch();
    if (!$catRow) {
        respondError('not_found', '指定されたカテゴリが見つかりません。', 404);
    }
    $categoryId = $catRow['id'];
}

$where  = 'WHERE 1=1';
$params = [];
if ($categoryId !== null) {
    $where .= ' AND m.category_id = ?';
    $params[] = $categoryId;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM materials m $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$offset = ($page - 1) * $perPage;
$dataStmt = $pdo->prepare("
    SELECT m.*,
           c.title AS category_title,
           c.slug  AS category_slug
    FROM materials m
    LEFT JOIN categories c ON c.id = m.category_id
    $where
    ORDER BY m.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

// 各素材のタグをまとめて取得
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

$baseUrl = rtrim(SITE_URL, '/') . '/api/v1/materials';
if ($categorySlug) $baseUrl .= '?category=' . rawurlencode($categorySlug);

respondJson([
    'data'       => array_map('formatMaterial', $rows),
    'pagination' => buildPagination($total, $page, $perPage, $baseUrl),
]);
