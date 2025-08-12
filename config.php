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

// GDPR Cookie同意関連の関数
function hasGDPRConsent() {
    return isset($_COOKIE['gdpr_consent']) && $_COOKIE['gdpr_consent'] === 'accepted';
}

function shouldShowGDPRBanner() {
    // GDPRクッキーが設定されていない場合のみバナーを表示
    return !isset($_COOKIE['gdpr_consent']);
}

// 第三者サービス（YouTube等）の読み込み許可を判定
function canLoadThirdPartyServices() {
    // 同意している場合のみ第三者サービスを読み込む
    return hasGDPRConsent();
}

// 現在のサイトのベースURLを取得（言語サブドメイン対応）
function getCurrentBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host;
}

// GDPR同意文のテキスト（サーバーサイドでレンダリング用）
function getGDPRConsentText() {
    return [
        'title' => 'Cookieの使用について',
        'message' => '当サイトでは、サービスの向上や翻訳機能（GTranslate）の提供のためにCookieを使用しています。引き続きサイトをご利用いただくには、Cookieの使用に同意していただく必要があります。',
        'accept_button' => 'すべて同意する',
        'settings_button' => '設定',
        'reject_button' => '拒否',
        'privacy_policy' => 'プライバシーポリシー',
        'learn_more' => '詳細はこちら'
    ];
}
?>
