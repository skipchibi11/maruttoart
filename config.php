<?php
// 環境変数を読み込む関数
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) {
            continue; // コメント行をスキップ
        }
        
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // クォートを除去
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
    return true;
}

// .envファイルを読み込み
loadEnv(__DIR__ . '/.env');

// OpenAI設定ファイルを読み込み（管理画面で使用）
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false) {
    require_once __DIR__ . '/includes/openai.php';
}

// データベース接続設定
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_DATABASE'] ?? 'maruttoart');
define('DB_USER', $_ENV['DB_USERNAME'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASSWORD'] ?? 'password');

// サイト設定
define('SITE_URL', $_ENV['SITE_URL'] ?? 'http://localhost');
define('UPLOADS_PATH', 'uploads');

// 管理画面専用セッション開始（CDNキャッシュ対策）
function startAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// データベース接続関数
function getDB() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die('データベース接続エラー: ' . $e->getMessage());
    }
}

// ログイン状態チェック関数
function isLoggedIn() {
    startAdminSession(); // セッション開始
    return isset($_SESSION['admin_id']);
}

// 管理者認証チェック関数（管理画面用）
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

// CSRF対策
function generateCSRFToken() {
    startAdminSession(); // セッション開始
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    startAdminSession(); // セッション開始
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// XSS対策
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ファイルアップロード関数（セキュリティ強化版）
function uploadImage($file, $slug) {
    // セキュリティチェック
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $maxFileSize = 10 * 1024 * 1024; // 10MB
    
    // ファイルサイズチェック
    if ($file['size'] > $maxFileSize) {
        throw new Exception('ファイルサイズが大きすぎます（最大10MB）');
    }
    
    // MIMEタイプチェック
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('許可されていないファイル形式です');
    }
    
    // 拡張子チェック
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception('許可されていないファイル拡張子です');
    }
    
    // スラッグの安全性チェック
    if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $slug)) {
        throw new Exception('スラッグに不正な文字が含まれています');
    }
    
    $uploadDir = __DIR__ . '/uploads/' . date('Y/m/');
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('アップロードディレクトリの作成に失敗しました');
        }
    }
    
    // ファイル名を安全に生成
    $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '', $slug) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // ファイルの重複チェック
    $counter = 1;
    while (file_exists($filepath)) {
        $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '', $slug) . '_' . $counter . '.' . $extension;
        $filepath = $uploadDir . $filename;
        $counter++;
    }
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // WebP変換（2つのサイズを生成）
        $webpBasePath = $uploadDir . preg_replace('/[^a-zA-Z0-9\-_]/', '', $slug);
        $webpResults = convertToWebP($filepath, $webpBasePath);
        
        if ($webpResults) {
            return [
                'original' => 'uploads/' . date('Y/m/') . $filename,
                'webp_small' => str_replace(__DIR__ . '/', '', $webpResults['_small']),
                'webp_medium' => str_replace(__DIR__ . '/', '', $webpResults['_medium'])
            ];
        } else {
            return [
                'original' => 'uploads/' . date('Y/m/') . $filename,
                'webp_small' => null,
                'webp_medium' => null
            ];
        }
    }
    throw new Exception('ファイルのアップロードに失敗しました');
}

// WebP変換関数（180x180と300x300の2つのサイズを生成）
function convertToWebP($source, $basePath) {
    $info = getimagesize($source);
    
    switch ($info['mime']) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    if ($sourceImage !== false) {
        // 元画像のサイズを取得
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);
        
        // 2つのサイズを生成
        $sizes = [
            ['width' => 180, 'height' => 180, 'suffix' => '_small'],
            ['width' => 300, 'height' => 300, 'suffix' => '_medium']
        ];
        
        $results = [];
        
        foreach ($sizes as $size) {
            // 新しい画像を作成
            $newImage = imagecreatetruecolor($size['width'], $size['height']);
            
            // PNG の透明度を保持
            if ($info['mime'] === 'image/png') {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefill($newImage, 0, 0, $transparent);
            }
            
            // 画像をリサイズしてコピー
            imagecopyresampled(
                $newImage, $sourceImage,
                0, 0, 0, 0,
                $size['width'], $size['height'], $originalWidth, $originalHeight
            );
            
            // 出力ファイル名を生成
            $pathInfo = pathinfo($basePath);
            $destination = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . $size['suffix'] . '.webp';
            
            // WebPとして保存
            if (imagewebp($newImage, $destination, 80)) {
                $results[$size['suffix']] = $destination;
            }
            
            // メモリを解放
            imagedestroy($newImage);
        }
        
        // 元画像のメモリを解放
        imagedestroy($sourceImage);
        
        return $results;
    }
    return false;
}

// SVGファイルアップロード関数
function uploadSvgFile($file, $slug, $strictSecurity = false, $oldSvgPath = null) {
    // セキュリティチェック
    $allowedMimeTypes = ['image/svg+xml'];
    $allowedExtensions = ['svg'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    // ファイルサイズチェック
    if ($file['size'] > $maxFileSize) {
        throw new Exception('SVGファイルサイズが大きすぎます（最大5MB）');
    }
    
    // 拡張子チェック
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception('SVGファイルの拡張子が正しくありません');
    }
    
    // ファイル内容を読み取ってSVGかどうかチェック
    $content = file_get_contents($file['tmp_name']);
    
    // デバッグ用：SVGの先頭部分を確認（一時的）
    error_log("SVG upload attempt - File: " . $file['name'] . ", Size: " . $file['size'] . " bytes");
    error_log("SVG content preview: " . substr($content, 0, 500));
    
    if (strpos($content, '<svg') === false || strpos($content, '</svg>') === false) {
        throw new Exception('有効なSVGファイルではありません');
    }
    
    // セキュリティチェック（開発中は緩和可能）
    if ($strictSecurity) {
        $dangerousPatterns = [
            '/<script[\s>]/i' => 'script要素',
            '/javascript\s*:/i' => 'JavaScript URL',
            '/\son(click|load|mouse|key|focus|blur|change|submit|error)\s*=/i' => 'イベントハンドラー',
            '/<iframe[\s>]/i' => 'iframe要素',
            '/<embed[\s>]/i' => 'embed要素',
            '/<object[\s>]/i' => 'object要素',
            '/data:text\/html/i' => 'HTML data URL',
            '/vbscript:/i' => 'VBScript URL'
        ];
        
        foreach ($dangerousPatterns as $pattern => $description) {
            if (preg_match($pattern, $content, $matches)) {
                error_log("SVG security check failed: {$description} - Matched: " . $matches[0]);
                throw new Exception("SVGファイルにセキュリティ上の問題があります: {$description}が検出されました (該当箇所: " . htmlspecialchars(substr($matches[0], 0, 50)) . ")");
            }
        }
    } else {
        // 最低限のセキュリティチェック（明らかに危険なもののみ）
        $criticalPatterns = [
            '/<script[\s>]/i' => 'script要素',
            '/javascript\s*:/i' => 'JavaScript URL'
        ];
        
        foreach ($criticalPatterns as $pattern => $description) {
            if (preg_match($pattern, $content, $matches)) {
                error_log("SVG critical security check failed: {$description} - Matched: " . $matches[0]);
                throw new Exception("SVGファイルに危険な要素があります: {$description}");
            }
        }
    }
    
    // SVGコンテンツをサニタイズ（危険な要素を除去）
    $content = sanitizeSvgContent($content);
    
    // スラッグの安全性チェック
    if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $slug)) {
        throw new Exception('スラッグに不正な文字が含まれています');
    }
    
    $uploadDir = __DIR__ . '/uploads/' . date('Y/m/');
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('アップロードディレクトリの作成に失敗しました');
        }
    }
    
    // 古いSVGファイルを削除
    if ($oldSvgPath && file_exists(__DIR__ . '/' . $oldSvgPath)) {
        unlink(__DIR__ . '/' . $oldSvgPath);
    }
    
    // ファイル名を安全に生成（重複チェックなし、既存ファイルを上書き）
    $filename = preg_replace('/[^a-zA-Z0-9\-_]/', '', $slug) . '.svg';
    $filepath = $uploadDir . $filename;
    
    // サニタイズされたコンテンツをファイルに保存
    if (file_put_contents($filepath, $content)) {
        return 'uploads/' . date('Y/m/') . $filename;
    }
    
    throw new Exception('SVGファイルのアップロードに失敗しました');
}

// SVGコンテンツをサニタイズする関数
function sanitizeSvgContent($content) {
    // 危険な属性を除去
    $dangerousAttributes = [
        '/\son[a-z]+\s*=/i', // イベントハンドラー
        '/\shref\s*=\s*["\']javascript:/i', // JavaScript URLs
        '/\shref\s*=\s*["\']data:text\/html/i', // HTML data URLs
    ];
    
    foreach ($dangerousAttributes as $pattern) {
        $content = preg_replace($pattern, '', $content);
    }
    
    // 危険な要素を除去
    $dangerousElements = [
        '/<script[^>]*>.*?<\/script>/is',
        '/<iframe[^>]*>.*?<\/iframe>/is',
        '/<embed[^>]*>/i',
        '/<object[^>]*>.*?<\/object>/is',
    ];
    
    foreach ($dangerousElements as $pattern) {
        $content = preg_replace($pattern, '', $content);
    }
    
    return $content;
}

// 現在のサイトのベースURLを取得（言語サブドメイン対応）
function getCurrentBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host;
}

// キャッシュ無効化（管理画面用）
function setNoCache() {
    header('Cache-Control: no-cache, no-store, must-revalidate, private');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// 公開ページ用キャッシュ設定
function setPublicCache($maxAge = 3600, $sMaxAge = 7200) {
    header('Cache-Control: public, max-age=' . $maxAge . ', s-maxage=' . $sMaxAge . ', stale-while-revalidate=120');
    header('Pragma: public');
    // LiteSpeed用ヒント
    header('X-LiteSpeed-Cache-Control: public,max-age=' . $sMaxAge);
}

// 静的ファイル用長期キャッシュ設定
function setLongCache($maxAge = 2592000) {
    header('Cache-Control: public, max-age=' . $maxAge . ', immutable');
    header('Pragma: public');
}

// 画像ファイル用キャッシュ設定
function setImageCache() {
    setLongCache(604800); // 7日
}

// タグ関連関数

// 全タグを取得
function getAllTags($pdo = null) {
    if (!$pdo) $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM tags ORDER BY name ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

// タグIDからタグ情報を取得
function getTagById($id, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM tags WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// スラッグからタグ情報を取得
function getTagBySlug($slug, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM tags WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

// 素材に関連付けられたタグを取得（5つ以上の素材を持つタグのみ）
function getMaterialTags($materialId, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT t.* 
        FROM tags t 
        JOIN material_tags mt ON t.id = mt.tag_id 
        WHERE mt.material_id = ? 
        AND (
            SELECT COUNT(DISTINCT mt2.material_id) 
            FROM material_tags mt2 
            WHERE mt2.tag_id = t.id
        ) >= 5
        ORDER BY t.name ASC
    ");
    $stmt->execute([$materialId]);
    return $stmt->fetchAll();
}

// タグに関連付けられた素材を取得
function getTagMaterials($tagId, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT m.* 
        FROM materials m 
        JOIN material_tags mt ON m.id = mt.material_id 
        WHERE mt.tag_id = ? 
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$tagId]);
    return $stmt->fetchAll();
}

// タグスラッグの重複チェック
function isTagSlugUnique($slug, $excludeId = null, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    $sql = "SELECT COUNT(*) FROM tags WHERE slug = ?";
    $params = [$slug];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() == 0;
}

// タグ作成
function createTag($data, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    // 必須項目チェック
    if (empty($data['name']) || empty($data['slug'])) {
        throw new Exception('名前とスラッグは必須です');
    }
    
    // スラッグの重複チェック
    if (!isTagSlugUnique($data['slug'], null, $pdo)) {
        throw new Exception('このスラッグは既に使用されています');
    }
    
    $sql = "INSERT INTO tags (name, slug) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        $data['name'],
        $data['slug']
    ]);
}

// タグ更新
function updateTag($id, $data, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    // 必須項目チェック
    if (empty($data['name']) || empty($data['slug'])) {
        throw new Exception('名前とスラッグは必須です');
    }
    
    // スラッグの重複チェック
    if (!isTagSlugUnique($data['slug'], $id, $pdo)) {
        throw new Exception('このスラッグは既に使用されています');
    }
    
    $sql = "UPDATE tags SET name = ?, slug = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([
        $data['name'],
        $data['slug'],
        $id
    ]);
}

// タグ削除
function deleteTag($id, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    // 関連する素材との関連付けを削除（CASCADE制約で自動削除されるが明示的に実行）
    $stmt = $pdo->prepare("DELETE FROM material_tags WHERE tag_id = ?");
    $stmt->execute([$id]);
    
    // タグを削除
    $stmt = $pdo->prepare("DELETE FROM tags WHERE id = ?");
    return $stmt->execute([$id]);
}

// 素材にタグを関連付け
function addMaterialTags($materialId, $tagIds, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    // 既存のタグ関連付けを削除
    $stmt = $pdo->prepare("DELETE FROM material_tags WHERE material_id = ?");
    $stmt->execute([$materialId]);
    
    // 新しいタグ関連付けを追加
    if (!empty($tagIds)) {
        $sql = "INSERT INTO material_tags (material_id, tag_id) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        
        foreach ($tagIds as $tagId) {
            $stmt->execute([$materialId, $tagId]);
        }
    }
    
    return true;
}

// 素材の検索（タグも含む）
function searchMaterials($searchTerm, $tagIds = [], $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    $sql = "SELECT DISTINCT m.* FROM materials m";
    $joins = [];
    $whereConditions = [];
    $params = [];
    
    // タグで絞り込みがある場合
    if (!empty($tagIds)) {
        $joins[] = "JOIN material_tags mt ON m.id = mt.material_id";
        $placeholders = str_repeat('?,', count($tagIds) - 1) . '?';
        $whereConditions[] = "mt.tag_id IN ($placeholders)";
        $params = array_merge($params, $tagIds);
    }
    
    // キーワード検索がある場合
    if (!empty($searchTerm)) {
        $whereConditions[] = "(m.title LIKE ? OR m.description LIKE ? OR m.search_keywords LIKE ?)";
        $searchParam = "%{$searchTerm}%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }
    
    // クエリ組み立て
    if (!empty($joins)) {
        $sql .= " " . implode(" ", $joins);
    }
    
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " ORDER BY m.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

// カテゴリ関連関数

// 全カテゴリを取得
function getAllCategories($pdo = null) {
    if (!$pdo) $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY sort_order ASC, title ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

// カテゴリIDからカテゴリ情報を取得
function getCategoryById($id, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// スラッグからカテゴリ情報を取得
function getCategoryBySlug($slug, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

// 素材のカテゴリを取得
function getMaterialCategory($materialId, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM categories c 
        JOIN materials m ON c.id = m.category_id 
        WHERE m.id = ?
    ");
    $stmt->execute([$materialId]);
    return $stmt->fetch();
}

// カテゴリに属する素材を取得
function getCategoryMaterials($categoryId, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT m.* 
        FROM materials m 
        WHERE m.category_id = ? 
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$categoryId]);
    return $stmt->fetchAll();
}

// カテゴリスラッグの重複チェック
function isCategorySlugUnique($slug, $excludeId = null, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    $sql = "SELECT COUNT(*) FROM categories WHERE slug = ?";
    $params = [$slug];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() == 0;
}

// カテゴリ作成
function createCategory($data, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    // 必須項目チェック
    if (empty($data['title']) || empty($data['slug'])) {
        throw new Exception('タイトルとスラッグは必須です');
    }
    
    // スラッグの重複チェック
    if (!isCategorySlugUnique($data['slug'], null, $pdo)) {
        throw new Exception('このスラッグは既に使用されています');
    }
    
    // カテゴリ画像パスが含まれている場合
    if (isset($data['category_image_path'])) {
        $sql = "INSERT INTO categories (title, slug, sort_order, category_image_path) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['sort_order'] ?? 0,
            $data['category_image_path']
        ]);
    } else {
        $sql = "INSERT INTO categories (title, slug, sort_order) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['sort_order'] ?? 0
        ]);
    }
}

// カテゴリ更新
function updateCategory($id, $data, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    // 必須項目チェック
    if (empty($data['title']) || empty($data['slug'])) {
        throw new Exception('タイトルとスラッグは必須です');
    }
    
    // スラッグの重複チェック
    if (!isCategorySlugUnique($data['slug'], $id, $pdo)) {
        throw new Exception('このスラッグは既に使用されています');
    }
    
    // カテゴリ画像パスが含まれている場合
    if (isset($data['category_image_path'])) {
        $sql = "UPDATE categories SET title = ?, slug = ?, sort_order = ?, category_image_path = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['sort_order'] ?? 0,
            $data['category_image_path'],
            $id
        ]);
    } else {
        $sql = "UPDATE categories SET title = ?, slug = ?, sort_order = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['sort_order'] ?? 0,
            $id
        ]);
    }
}

// カテゴリ削除
function deleteCategory($id, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    // 素材のカテゴリIDをNULLに設定（外部キー制約によりSET NULLが実行される）
    $stmt = $pdo->prepare("UPDATE materials SET category_id = NULL WHERE category_id = ?");
    $stmt->execute([$id]);
    
    // カテゴリを削除
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    return $stmt->execute([$id]);
}

// 素材にカテゴリを設定
function setMaterialCategory($materialId, $categoryId, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    $sql = "UPDATE materials SET category_id = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    
    return $stmt->execute([$categoryId, $materialId]);
}

/**
 * 類似画像を取得
 * @param int $materialId 基準となる素材ID
 * @param int $limit 取得件数（デフォルト: 5）
 * @param float $minSimilarity 最小類似度（デフォルト: 0.5）
 * @return array 類似画像の配列
 */
function getSimilarMaterials($materialId, $limit = 5, $minSimilarity = 0.5, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    $sql = "
        SELECT 
            m.id,
            m.title,
            m.slug,
            m.image_path,
            m.webp_small_path,
            m.webp_medium_path,
            c.slug as category_slug,
            c.title as category_title,
            ms.similarity_score
        FROM material_similarities ms
        JOIN materials m ON ms.similar_material_id = m.id
        LEFT JOIN categories c ON m.category_id = c.id
        WHERE ms.material_id = ? 
          AND ms.similarity_score >= ?
        ORDER BY ms.similarity_score DESC
        LIMIT ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$materialId, $minSimilarity, $limit]);
    
    return $stmt->fetchAll();
}

/**
 * 類似画像があるかチェック
 * @param int $materialId 素材ID
 * @param float $minSimilarity 最小類似度（デフォルト: 0.5）
 * @return bool 類似画像があるかどうか
 */
function hasSimilarMaterials($materialId, $minSimilarity = 0.5, $pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    $sql = "
        SELECT COUNT(*) 
        FROM material_similarities 
        WHERE material_id = ? AND similarity_score >= ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$materialId, $minSimilarity]);
    
    return $stmt->fetchColumn() > 0;
}

/**
 * 類似度計算の進捗を取得
 * @return array 進捗情報
 */
function getSimilarityCalculationProgress($pdo = null) {
    if (!$pdo) $pdo = getDB();
    
    $sql = "
        SELECT 
            status,
            COUNT(*) as count
        FROM similarity_calculation_progress
        GROUP BY status
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $progress = [
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'error' => 0
    ];
    
    foreach ($stmt->fetchAll() as $row) {
        $progress[$row['status']] = $row['count'];
    }
    
    return $progress;
}
?>
