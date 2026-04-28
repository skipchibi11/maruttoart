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

$id = $_GET['id'] ?? null;

// ---- 詳細取得: GET /api/v1/community-artworks?id={id} ----
if ($id !== null) {
    if (!ctype_digit((string)$id)) {
        respondError('invalid_id', 'idは正の整数を指定してください。', 400);
    }
    $stmt = $pdo->prepare("
        SELECT * FROM community_artworks
        WHERE id = ? AND status = 'approved' AND free_material_consent = 1
        LIMIT 1
    ");
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch();
    if (!$row) {
        respondError('not_found', '指定された作品が見つかりません。', 404);
    }
    respondJson(['data' => formatCommunityArtwork($row)]);
}

// ---- 一覧取得: GET /api/v1/community-artworks ----
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(API_PER_PAGE_MAX, max(1, (int)($_GET['per_page'] ?? API_PER_PAGE_DEFAULT)));

$where  = "WHERE status = 'approved' AND free_material_consent = 1";
$params = [];

// タイトル検索（任意）
$q = isset($_GET['q']) ? trim($_GET['q']) : null;
if ($q !== null && $q !== '') {
    $where   .= ' AND title LIKE ?';
    $params[] = '%' . $q . '%';
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM community_artworks $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$offset   = ($page - 1) * $perPage;
$dataStmt = $pdo->prepare("
    SELECT * FROM community_artworks
    $where
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

$baseUrl = rtrim(SITE_URL, '/') . '/api/v1/community-artworks';
if ($q !== null && $q !== '') {
    $baseUrl .= '?q=' . rawurlencode($q);
}

respondJson([
    'data'       => array_map('formatCommunityArtwork', $rows),
    'pagination' => buildPagination($total, $page, $perPage, $baseUrl),
]);

function formatCommunityArtwork(array $row): array
{
    return [
        'id'          => (int)$row['id'],
        'title'       => $row['title'] ?? '',
        'pen_name'    => $row['pen_name'] ?? '',
        'description' => $row['description'] ?? null,
        'license'     => $row['license_type'] ?? 'CC BY 4.0',
        'images'      => [
            'original'   => buildImageUrl($row['file_path'] ?? null),
            'webp_small' => buildImageUrl($row['webp_path'] ?? null),
        ],
        'detail_url'  => rtrim(SITE_URL, '/') . '/everyone-work.php?id=' . (int)$row['id'],
        'created_at'  => $row['created_at'] ?? null,
    ];
}
