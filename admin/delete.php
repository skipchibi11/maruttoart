<?php
require_once '../config.php';
startAdminSession(); // 管理画面専用セッション開始
requireLogin();

// 管理画面はキャッシュ無効化
setNoCache();
header("Cache-Control: no-cache");

$id = $_GET['id'] ?? '';
if (empty($id) || !is_numeric($id)) {
    header('Location: /admin/');
    exit;
}

$pdo = getDB();

// 素材の存在確認
$stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
$stmt->execute([$id]);
$material = $stmt->fetch();

if (!$material) {
    header('Location: /admin/');
    exit;
}

// 削除処理
try {
    // ファイル削除
    $imagePath = __DIR__ . '/../' . $material['image_path'];
    $webpPath = __DIR__ . '/../' . $material['webp_path'];
    
    if (file_exists($imagePath)) {
        unlink($imagePath);
    }
    if (file_exists($webpPath)) {
        unlink($webpPath);
    }
    
    // データベースから削除
    $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ?");
    $stmt->execute([$id]);
    
    header('Location: /admin/?deleted=1');
    exit;
    
} catch (Exception $e) {
    header('Location: /admin/?error=delete_failed');
    exit;
}
?>
