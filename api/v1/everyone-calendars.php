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

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(API_PER_PAGE_MAX, max(1, (int)($_GET['per_page'] ?? API_PER_PAGE_DEFAULT)));

// 月フィルタ（任意）
$month = isset($_GET['month']) ? (int)$_GET['month'] : null;
if ($month !== null && ($month < 1 || $month > 12)) {
    respondError('invalid_month', 'monthは1〜12の整数を指定してください。', 400);
}

$where  = "WHERE ca.status = 'approved' AND ca.free_material_consent = 1";
$params = [];

if ($month !== null) {
    $where   .= ' AND eci.month = ?';
    $params[] = $month;
}

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM everyone_calendar_items eci
    JOIN community_artworks ca ON ca.id = eci.artwork_id
    $where
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$offset   = ($page - 1) * $perPage;
$dataStmt = $pdo->prepare("
    SELECT eci.id, eci.month, eci.day,
           ca.id AS artwork_id, ca.title, ca.pen_name, ca.license_type,
           ca.file_path, ca.webp_path
    FROM everyone_calendar_items eci
    JOIN community_artworks ca ON ca.id = eci.artwork_id
    $where
    ORDER BY eci.month ASC, eci.day ASC
    LIMIT $perPage OFFSET $offset
");
$dataStmt->execute($params);
$rows = $dataStmt->fetchAll();

$baseUrl = rtrim(SITE_URL, '/') . '/api/v1/everyone-calendars';
if ($month !== null) {
    $baseUrl .= '?month=' . $month;
}

respondJson([
    'data'       => array_map('formatCalendarItem', $rows),
    'pagination' => buildPagination($total, $page, $perPage, $baseUrl),
]);

function formatCalendarItem(array $row): array
{
    $month = (int)$row['month'];
    $day   = (int)$row['day'];

    return [
        'id'         => (int)$row['id'],
        'month'      => $month,
        'day'        => $day,
        'title'      => sprintf('%d月%d日 %s', $month, $day, $row['title'] ?? ''),
        'pen_name'   => $row['pen_name'] ?? '',
        'license'    => $row['license_type'] ?? 'CC BY 4.0',
        'artwork_id' => (int)$row['artwork_id'],
        'images'     => [
            'original'   => buildImageUrl($row['file_path'] ?? null),
            'webp_small' => buildImageUrl($row['webp_path'] ?? null),
        ],
        'detail_url' => rtrim(SITE_URL, '/') . '/everyone-work.php?id=' . (int)$row['artwork_id'],
    ];
}
