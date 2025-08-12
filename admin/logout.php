<?php
require_once '../config.php';
requireLogin();

// ログアウト処理
session_destroy();
header('Location: /admin/login.php');
exit;
?>
