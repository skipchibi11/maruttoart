<?php
// データベース接続設定
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'maruttoart');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'password');

// サイト設定
define('SITE_URL', 'http://localhost');
define('UPLOADS_PATH', 'uploads');

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

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ログイン状態チェック関数
function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

// 管理者認証チェック関数（管理画面用）
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

// XSS対策
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ファイルアップロード関数
function uploadImage($file, $slug) {
    $uploadDir = __DIR__ . '/uploads/' . date('Y/m/');
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $slug . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // WebP変換
        $webpPath = $uploadDir . $slug . '.webp';
        convertToWebP($filepath, $webpPath);
        
        return [
            'original' => 'uploads/' . date('Y/m/') . $filename,
            'webp' => 'uploads/' . date('Y/m/') . $slug . '.webp'
        ];
    }
    return false;
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
?>
