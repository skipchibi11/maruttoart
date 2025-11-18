<?php
/**
 * コミュニティ作品と素材のクロス類似度計算スクリプト
 * 既存のimage_embeddingを活用して、コミュニティ作品と素材の類似度を計算
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

require_once __DIR__ . '/../config.php';

// ログファイルの設定
$logFile = __DIR__ . '/../logs/cross_similarity_calculation.log';

// ログディレクトリを作成
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * ログ出力関数
 */
function logMessage($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

/**
 * コサイン類似度を計算
 */
function calculateCosineSimilarity($vector1, $vector2) {
    if (count($vector1) !== count($vector2)) {
        throw new Exception('Vector dimensions do not match');
    }
    
    $dotProduct = 0;
    $magnitude1 = 0;
    $magnitude2 = 0;
    
    for ($i = 0; $i < count($vector1); $i++) {
        $dotProduct += $vector1[$i] * $vector2[$i];
        $magnitude1 += $vector1[$i] * $vector1[$i];
        $magnitude2 += $vector2[$i] * $vector2[$i];
    }
    
    $magnitude1 = sqrt($magnitude1);
    $magnitude2 = sqrt($magnitude2);
    
    if ($magnitude1 == 0 || $magnitude2 == 0) {
        return 0;
    }
    
    return $dotProduct / ($magnitude1 * $magnitude2);
}

/**
 * 新しいコミュニティ作品を進捗テーブルに追加
 */
function addNewArtworksToProgress($pdo) {
    try {
        // 画像埋め込みがある承認済み作品で、まだ進捗テーブルに登録されていないものを取得
        $stmt = $pdo->prepare("
            SELECT ca.id, ca.created_at
            FROM community_artworks ca
            LEFT JOIN cross_similarity_progress csp ON ca.id = csp.community_artwork_id
            WHERE ca.image_embedding IS NOT NULL 
              AND ca.status = 'approved'
              AND csp.community_artwork_id IS NULL
            ORDER BY ca.created_at DESC
        ");
        $stmt->execute();
        $newArtworks = $stmt->fetchAll();
        
        // 新しい作品を進捗テーブルに追加
        if (!empty($newArtworks)) {
            $insertStmt = $pdo->prepare("
                INSERT INTO cross_similarity_progress 
                (community_artwork_id, status, created_at, updated_at) 
                VALUES (?, 'pending', NOW(), NOW())
            ");
            
            $addedCount = 0;
            foreach ($newArtworks as $artwork) {
                $insertStmt->execute([$artwork['id']]);
                $addedCount++;
            }
            
            logMessage("Added {$addedCount} new artworks to cross similarity calculation queue");
        }
        
        // 既存の完了済み作品で画像埋め込みが更新された場合、再計算対象にする
        $updateStmt = $pdo->prepare("
            UPDATE cross_similarity_progress csp
            JOIN community_artworks ca ON csp.community_artwork_id = ca.id
            SET csp.status = 'pending', csp.updated_at = NOW()
            WHERE csp.status = 'completed' 
              AND ca.image_embedding IS NOT NULL
              AND ca.status = 'approved'
              AND ca.updated_at > csp.processed_at
        ");
        $updateStmt->execute();
        $updatedCount = $updateStmt->rowCount();
        
        if ($updatedCount > 0) {
            logMessage("Updated {$updatedCount} artworks for re-calculation due to embedding updates");
        }
        
        if (empty($newArtworks) && $updatedCount == 0) {
            logMessage('No new artworks found to add to cross similarity calculation queue');
        }
        
    } catch (Exception $e) {
        logMessage("Error adding new artworks to progress: " . $e->getMessage(), 'ERROR');
        throw $e;
    }
}

/**
 * メイン処理
 */
function main() {
    logMessage('Starting cross similarity calculation process');
    
    try {
        $pdo = getDB();
        
        // 新しい作品を進捗テーブルに追加
        addNewArtworksToProgress($pdo);
        
        // 処理対象のコミュニティ作品を取得
        $stmt = $pdo->prepare("
            SELECT 
                csp.community_artwork_id, 
                csp.status,
                ca.title
            FROM cross_similarity_progress csp
            JOIN community_artworks ca ON csp.community_artwork_id = ca.id
            WHERE csp.status = 'pending'
              AND ca.image_embedding IS NOT NULL
              AND ca.status = 'approved'
            ORDER BY csp.created_at ASC 
            LIMIT 1
        ");
        $stmt->execute();
        $target = $stmt->fetch();
        
        if (!$target) {
            // 待機中の作品がない場合は、循環処理（古い完了済みを再処理）
            logMessage('No pending artworks found, starting circulation process');
            
            $circulationStmt = $pdo->prepare("
                SELECT csp.community_artwork_id, csp.status, csp.processed_at
                FROM cross_similarity_progress csp
                JOIN community_artworks ca ON csp.community_artwork_id = ca.id
                WHERE csp.status = 'completed' 
                  AND ca.image_embedding IS NOT NULL
                  AND ca.status = 'approved'
                ORDER BY csp.processed_at ASC 
                LIMIT 1
            ");
            $circulationStmt->execute();
            $target = $circulationStmt->fetch();
            
            if ($target) {
                logMessage("Starting circulation: Re-processing artwork ID {$target['community_artwork_id']} (last processed: {$target['processed_at']})");
                
                // ステータスをpendingに戻す
                $updateStmt = $pdo->prepare("
                    UPDATE cross_similarity_progress 
                    SET status = 'pending', updated_at = NOW()
                    WHERE community_artwork_id = ?
                ");
                $updateStmt->execute([$target['community_artwork_id']]);
                
                // 再取得
                $stmt->execute();
                $target = $stmt->fetch();
            }
        }
        
        if (!$target) {
            logMessage('No artworks found for processing (including circulation)');
            return;
        }
        
        $artworkId = $target['community_artwork_id'];
        logMessage("Processing community artwork ID: {$artworkId} (title: {$target['title']})");
        
        // ステータスを処理中に更新
        $stmt = $pdo->prepare("
            UPDATE cross_similarity_progress 
            SET status = 'processing', updated_at = NOW() 
            WHERE community_artwork_id = ?
        ");
        $stmt->execute([$artworkId]);
        
        // コミュニティ作品のベクトルを取得
        $stmt = $pdo->prepare("
            SELECT image_embedding 
            FROM community_artworks 
            WHERE id = ? AND status = 'approved' AND image_embedding IS NOT NULL
        ");
        $stmt->execute([$artworkId]);
        $artwork = $stmt->fetch();
        
        if (!$artwork || !$artwork['image_embedding']) {
            throw new Exception("No embedding found for community artwork ID {$artworkId}");
        }
        
        $artworkVector = json_decode($artwork['image_embedding'], true);
        
        if (!$artworkVector) {
            throw new Exception("Invalid embedding format for community artwork ID {$artworkId}");
        }
        
        logMessage("Artwork vector loaded, dimension: " . count($artworkVector));
        
        // 全素材のベクトルを取得して類似度を計算
        $stmt = $pdo->query("
            SELECT id, title, image_embedding 
            FROM materials 
            WHERE image_embedding IS NOT NULL
        ");
        
        $similarities = [];
        $processedCount = 0;
        
        while ($material = $stmt->fetch()) {
            $materialVector = json_decode($material['image_embedding'], true);
            
            if ($materialVector) {
                try {
                    $similarity = calculateCosineSimilarity($artworkVector, $materialVector);
                    
                    // 閾値0.7以上のみ保存
                    if ($similarity >= 0.7) {
                        $similarities[] = [
                            'material_id' => $material['id'],
                            'material_title' => $material['title'],
                            'score' => $similarity
                        ];
                    }
                    $processedCount++;
                } catch (Exception $e) {
                    logMessage("Error calculating similarity with material {$material['id']}: " . $e->getMessage(), 'WARNING');
                }
            }
        }
        
        logMessage("Processed {$processedCount} materials");
        
        // 類似度で降順ソート、上位20件のみ保存
        usort($similarities, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        $similarities = array_slice($similarities, 0, 20);
        
        logMessage("Found " . count($similarities) . " similar materials (score >= 0.7)");
        
        // 既存の類似度データを削除
        $stmt = $pdo->prepare("
            DELETE FROM community_artwork_material_similarities 
            WHERE community_artwork_id = ?
        ");
        $stmt->execute([$artworkId]);
        
        // 新しい類似度データを保存
        if (count($similarities) > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO community_artwork_material_similarities 
                (community_artwork_id, material_id, similarity_score) 
                VALUES (?, ?, ?)
            ");
            
            foreach ($similarities as $sim) {
                $stmt->execute([
                    $artworkId,
                    $sim['material_id'],
                    $sim['score']
                ]);
            }
            
            // 上位3件をログに出力
            for ($i = 0; $i < min(3, count($similarities)); $i++) {
                $sim = $similarities[$i];
                logMessage("  #" . ($i+1) . ": {$sim['material_title']} (score: " . round($sim['score'], 4) . ")");
            }
        }
        
        // 完了ステータスに更新
        $stmt = $pdo->prepare("
            UPDATE cross_similarity_progress 
            SET status = 'completed', 
                processed_at = NOW(), 
                error_message = NULL, 
                updated_at = NOW()
            WHERE community_artwork_id = ?
        ");
        $stmt->execute([$artworkId]);
        
        logMessage("Successfully calculated cross similarities for artwork ID {$artworkId}");
        
    } catch (Exception $e) {
        logMessage("Error in main process: " . $e->getMessage(), 'ERROR');
        
        // エラーステータスに更新
        if (isset($artworkId)) {
            $stmt = $pdo->prepare("
                UPDATE cross_similarity_progress 
                SET status = 'error', 
                    error_message = ?, 
                    updated_at = NOW()
                WHERE community_artwork_id = ?
            ");
            $stmt->execute([$e->getMessage(), $artworkId]);
        }
        
        exit(1);
    }
}

// スクリプト実行
main();
logMessage('Cross similarity calculation process completed');
