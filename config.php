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
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    // ファイルサイズチェック
    if ($file['size'] > $maxFileSize) {
        throw new Exception('ファイルサイズが大きすぎます（最大5MB）');
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
        // WebP変換
        $webpPath = $uploadDir . preg_replace('/[^a-zA-Z0-9\-_]/', '', $slug) . '.webp';
        convertToWebP($filepath, $webpPath);
        
        return [
            'original' => 'uploads/' . date('Y/m/') . $filename,
            'webp' => 'uploads/' . date('Y/m/') . preg_replace('/[^a-zA-Z0-9\-_]/', '', $slug) . '.webp'
        ];
    }
    throw new Exception('ファイルのアップロードに失敗しました');
}

// WebP変換関数
function convertToWebP($source, $destination) {
    $info = getimagesize($source);
    
    switch ($info['mime']) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    if ($image !== false) {
        imagewebp($image, $destination, 80);
        imagedestroy($image);
        return true;
    }
    return false;
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
    setLongCache(2592000); // 30日
}
?>
