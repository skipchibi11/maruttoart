<?php
require_once '../config.php';
startAdminSession(); // 管理画面専用セッション開始

echo "<h1>セッション・設定確認</h1>";

// セッション情報
echo "<h2>セッション情報</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Status: " . session_status() . "</p>";
echo "<p>admin_logged_in: " . (isset($_SESSION['admin_logged_in']) ? ($_SESSION['admin_logged_in'] ? 'true' : 'false') : 'not set') . "</p>";
echo "<p>isLoggedIn(): " . (isLoggedIn() ? 'true' : 'false') . "</p>";

// 定数情報
echo "<h2>定数情報</h2>";
echo "<p>ADMIN_PASSWORD: '" . ADMIN_PASSWORD . "'</p>";

// POST データ確認
if ($_POST) {
    echo "<h2>POST データ</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    $password = $_POST['password'] ?? '';
    echo "<p>入力パスワード: '{$password}'</p>";
    echo "<p>パスワード一致: " . ($password === ADMIN_PASSWORD ? 'true' : 'false') . "</p>";
}

echo "<hr>";
echo "<form method='POST'>";
echo "<input type='password' name='password' placeholder='テストパスワード'>";
echo "<button type='submit'>テスト</button>";
echo "</form>";

echo "<p><a href='/admin/login.php'>ログインページに戻る</a></p>";
?>
