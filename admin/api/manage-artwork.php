<?php
/**
 * 作品管理API - 管理者専用
 */

require_once '../../config.php';
require_once '../../includes/r2-utils.php'; // R2ファイル削除用
startAdminSession(); // 管理画面専用セッション開始
requireLogin(); // 管理者認証

header('Content-Type: application/json; charset=utf-8');

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendSuccess($data = null) {
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('POSTメソッドが必要です', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendError('不正なJSONデータです');
    }

    $action = $input['action'] ?? '';
    $artworkId = intval($input['artwork_id'] ?? 0);

    if (!$artworkId) {
        sendError('作品IDが必要です');
    }

    $pdo = getDB();

    switch ($action) {
        case 'update_status':
            $status = $input['status'] ?? '';
            $reason = $input['reason'] ?? '';
            
            if (!in_array($status, ['pending', 'approved', 'rejected'])) {
                sendError('無効な状態です');
            }
            
            $pdo->beginTransaction();
            
            try {
                // 作品の存在確認
                $stmt = $pdo->prepare("SELECT id, file_path, webp_path FROM community_artworks WHERE id = ?");
                $stmt->execute([$artworkId]);
                $artwork = $stmt->fetch();
                
                if (!$artwork) {
                    sendError('作品が見つかりません', 404);
                }
                
                // 状態を更新
                $updateData = [
                    'status' => $status,
                    'rejection_reason' => $reason
                ];
                
                if ($status === 'approved') {
                    $updateData['approved_at'] = date('Y-m-d H:i:s');
                } else {
                    $updateData['approved_at'] = null;
                }
                
                $stmt = $pdo->prepare("
                    UPDATE community_artworks 
                    SET status = ?, approved_at = ?, rejection_reason = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $updateData['status'],
                    $updateData['approved_at'],
                    $updateData['rejection_reason'],
                    $artworkId
                ]);
                
                $pdo->commit();
                
                sendSuccess([
                    'artwork_id' => $artworkId,
                    'new_status' => $status,
                    'message' => '作品の状態を更新しました'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;
            
        case 'toggle_featured':
            $isFeatured = intval($input['is_featured'] ?? 0);
            
            $stmt = $pdo->prepare("UPDATE community_artworks SET is_featured = ? WHERE id = ?");
            $stmt->execute([$isFeatured, $artworkId]);
            
            if ($stmt->rowCount() === 0) {
                sendError('作品が見つかりません', 404);
            }
            
            sendSuccess([
                'artwork_id' => $artworkId,
                'is_featured' => $isFeatured,
                'message' => 'おすすめ設定を更新しました'
            ]);
            break;
            
        case 'delete':
            $pdo->beginTransaction();
            
            try {
                // 作品情報を取得
                $stmt = $pdo->prepare("SELECT file_path, webp_path FROM community_artworks WHERE id = ?");
                $stmt->execute([$artworkId]);
                $artwork = $stmt->fetch();
                
                if (!$artwork) {
                    sendError('作品が見つかりません', 404);
                }
                
                // 関連データを削除（いいね、など）
                $stmt = $pdo->prepare("DELETE FROM artwork_likes WHERE artwork_id = ?");
                $stmt->execute([$artworkId]);
                
                // 作品データを削除
                $stmt = $pdo->prepare("DELETE FROM community_artworks WHERE id = ?");
                $stmt->execute([$artworkId]);
                
                // ファイルを削除（R2またはローカル）
                $deletedFiles = [];
                $failedFiles = [];
                
                $filesToDelete = [$artwork['file_path'], $artwork['webp_path']];
                
                // ログに削除対象を記録
                logR2Delete("=== Deleting artwork ID {$artworkId} ===");
                logR2Delete("PNG: " . ($artwork['file_path'] ?: 'N/A'));
                logR2Delete("WebP: " . ($artwork['webp_path'] ?: 'N/A'));
                
                foreach ($filesToDelete as $filePath) {
                    if (!empty($filePath)) {
                        if (deleteFile($filePath, '../../')) {
                            $deletedFiles[] = $filePath;
                        } else {
                            $failedFiles[] = $filePath;
                        }
                    }
                }
                
                // ログに記録
                if (!empty($deletedFiles)) {
                    logR2Delete("Successfully deleted: " . implode(', ', $deletedFiles));
                }
                if (!empty($failedFiles)) {
                    logR2Delete("Failed to delete: " . implode(', ', $failedFiles));
                }
                
                $pdo->commit();
                
                sendSuccess([
                    'artwork_id' => $artworkId,
                    'message' => '作品を削除しました'
                ]);
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;
            
        case 'bulk_action':
            $artworkIds = $input['artwork_ids'] ?? [];
            $bulkAction = $input['bulk_action'] ?? '';
            
            if (!is_array($artworkIds) || empty($artworkIds)) {
                sendError('作品IDが必要です');
            }
            
            if (!in_array($bulkAction, ['approve', 'reject', 'delete'])) {
                sendError('無効な一括操作です');
            }
            
            $pdo->beginTransaction();
            
            try {
                $processedCount = 0;
                
                foreach ($artworkIds as $id) {
                    $id = intval($id);
                    
                    switch ($bulkAction) {
                        case 'approve':
                            $stmt = $pdo->prepare("
                                UPDATE community_artworks 
                                SET status = 'approved', approved_at = NOW() 
                                WHERE id = ? AND status = 'pending'
                            ");
                            $stmt->execute([$id]);
                            $processedCount += $stmt->rowCount();
                            break;
                            
                        case 'reject':
                            $stmt = $pdo->prepare("
                                UPDATE community_artworks 
                                SET status = 'rejected', approved_at = NULL
                                WHERE id = ? AND status = 'pending'
                            ");
                            $stmt->execute([$id]);
                            $processedCount += $stmt->rowCount();
                            break;
                            
                        case 'delete':
                            // ファイル情報を取得
                            $stmt = $pdo->prepare("SELECT file_path, webp_path FROM community_artworks WHERE id = ?");
                            $stmt->execute([$id]);
                            $artwork = $stmt->fetch();
                            
                            if ($artwork) {
                                // 関連データ削除
                                $stmt = $pdo->prepare("DELETE FROM artwork_likes WHERE artwork_id = ?");
                                $stmt->execute([$id]);
                                
                                // 作品削除
                                $stmt = $pdo->prepare("DELETE FROM community_artworks WHERE id = ?");
                                $stmt->execute([$id]);
                                
                                if ($stmt->rowCount() > 0) {
                                    // ファイル削除（R2またはローカル）
                                    $filesToDelete = [$artwork['file_path'], $artwork['webp_path']];
                                    foreach ($filesToDelete as $filePath) {
                                        if (!empty($filePath)) {
                                            deleteFile($filePath, '../../');
                                        }
                                    }
                                    $processedCount++;
                                }
                            }
                            break;
                    }
                }
                
                $pdo->commit();
                
                sendSuccess([
                    'processed_count' => $processedCount,
                    'total_count' => count($artworkIds),
                    'action' => $bulkAction,
                    'message' => "{$processedCount}件の作品を処理しました"
                ]);
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
            break;
            
        default:
            sendError('無効なアクションです');
    }

} catch (Exception $e) {
    error_log("Artwork management error: " . $e->getMessage());
    sendError('処理中にエラーが発生しました: ' . $e->getMessage(), 500);
}
?>