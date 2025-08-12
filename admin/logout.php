<?php
require_once '../config.php';

// 管理画面専用セッション開始
startAdminSession();
header("Cache-Control: no-cache");

// セッション変数をすべて削除
$_SESSION = array();

// セッションクッキーも削除
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// セッションを破棄
session_destroy();

// ログインページにリダイレクト
header('Location: /admin/login.php?logout=1');
exit;
?>
