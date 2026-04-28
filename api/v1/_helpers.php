<?php

define('API_RATE_LIMIT', 1000);     // 1日あたりの最大リクエスト数
define('API_PER_PAGE_MAX', 100);    // 1回あたりの最大取得件数
define('API_PER_PAGE_DEFAULT', 20);

function setCorsHeaders(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Access-Control-Max-Age: 86400');
}

function setJsonHeaders(): void {
    header('Content-Type: application/json; charset=utf-8');
}

function respondJson(mixed $data, int $statusCode = 200): never {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function respondError(string $code, string $message, int $statusCode): never {
    respondJson(['error' => ['code' => $code, 'message' => $message]], $statusCode);
}

/**
 * IPベースのレートリミットチェック（1000リクエスト/日）
 * 超過した場合は429を返して終了する
 */
function checkRateLimit(PDO $pdo): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ipHash = hash('sha256', $ip);
    $today = date('Y-m-d');

    try {
        $pdo->prepare("
            INSERT INTO api_rate_limits (ip_hash, request_count, reset_date)
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE request_count = request_count + 1
        ")->execute([$ipHash, $today]);

        $stmt = $pdo->prepare("SELECT request_count FROM api_rate_limits WHERE ip_hash = ? AND reset_date = ?");
        $stmt->execute([$ipHash, $today]);
        $count = (int)($stmt->fetchColumn() ?: 0);

        $remaining = max(0, API_RATE_LIMIT - $count);
        $resetTs = strtotime('tomorrow midnight');

        header('X-RateLimit-Limit: ' . API_RATE_LIMIT);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: ' . $resetTs);

        if ($count > API_RATE_LIMIT) {
            header('Retry-After: ' . ($resetTs - time()));
            respondError('rate_limit_exceeded', '1日あたりのリクエスト上限（' . API_RATE_LIMIT . '回）に達しました。翌日にリセットされます。', 429);
        }
    } catch (PDOException $e) {
        // レートリミットDBエラーは無視して処理を続行
        error_log('API rate limit DB error: ' . $e->getMessage());
    }
}

/**
 * 相対パスをフルURLに変換する
 * R2の場合はすでにhttpsで始まる完全URLが入っている
 */
function buildImageUrl(?string $path): ?string {
    if (empty($path)) return null;
    if (str_starts_with($path, 'http')) return $path;
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * materials行をAPI用レスポンス形式に変換する
 */
function formatMaterial(array $row): array {
    $categorySlug = $row['category_slug'] ?? null;
    $slug = $row['slug'] ?? '';

    return [
        'id'           => (int)$row['id'],
        'slug'         => $slug,
        'title'        => $row['title'] ?? '',
        'description'  => $row['description'] ?? null,
        'category'     => $categorySlug ? [
            'title' => $row['category_title'] ?? null,
            'slug'  => $categorySlug,
        ] : null,
        'tags'         => $row['tags'] ?? [],
        'images'       => [
            'original'    => buildImageUrl($row['image_path'] ?? null),
            'webp_small'  => buildImageUrl($row['webp_small_path'] ?? null),
            'webp_medium' => buildImageUrl($row['webp_medium_path'] ?? null),
            'svg'         => buildImageUrl($row['svg_path'] ?? null),
        ],
        'detail_url'   => $categorySlug
            ? rtrim(SITE_URL, '/') . '/' . $categorySlug . '/' . $slug . '/'
            : null,
        'upload_date'  => $row['upload_date'] ?? null,
        'created_at'   => $row['created_at'] ?? null,
    ];
}

/**
 * ページネーションメタデータを生成する
 */
function buildPagination(int $total, int $page, int $perPage, string $baseUrl): array {
    $lastPage = max(1, (int)ceil($total / $perPage));

    $buildUrl = function(int $p) use ($baseUrl, $perPage): string {
        $sep = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl . $sep . 'page=' . $p . '&per_page=' . $perPage;
    };

    return [
        'total'         => $total,
        'per_page'      => $perPage,
        'current_page'  => $page,
        'last_page'     => $lastPage,
        'next_page_url' => $page < $lastPage ? $buildUrl($page + 1) : null,
        'prev_page_url' => $page > 1 ? $buildUrl($page - 1) : null,
    ];
}
