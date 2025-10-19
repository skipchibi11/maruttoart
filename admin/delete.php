<?php
require_once '../config.php';
startAdminSession(); // 管理画面専用セッション開始
requireLogin();

// 管理画面はキャッシュ無効化
setNoCache();

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
    // すべての関連画像ファイルを削除
    $imageFields = [
        'image_path',
        'webp_path',
        'webp_small_path',
        'webp_medium_path',
        'structured_image_path',
        'ai_product_image_path'
    ];
    
    $deletedFiles = [];
    $failedFiles = [];
    
    foreach ($imageFields as $field) {
        if (!empty($material[$field])) {
            $filePath = __DIR__ . '/../' . $material[$field];
            if (file_exists($filePath)) {
                if (unlink($filePath)) {
                    $deletedFiles[] = $material[$field];
                } else {
                    $failedFiles[] = $material[$field];
                }
            }
        }
    }
    
    // データベースから削除（関連データも含む）
    $pdo->beginTransaction();
    
    try {
        // material_tagsの関連レコードを削除
        $stmt = $pdo->prepare("DELETE FROM material_tags WHERE material_id = ?");
        $stmt->execute([$id]);
        
        // materialsレコードを削除
        $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
    // ログ記録（オプション）
    if (!empty($deletedFiles)) {
        error_log("Deleted files for material ID {$id}: " . implode(', ', $deletedFiles));
    }
    if (!empty($failedFiles)) {
        error_log("Failed to delete files for material ID {$id}: " . implode(', ', $failedFiles));
    }
    
    header('Location: /admin/?deleted=1');
    exit;
    
} catch (Exception $e) {
    error_log("Delete material error: " . $e->getMessage());
    header('Location: /admin/?error=delete_failed');
    exit;
}
?>
