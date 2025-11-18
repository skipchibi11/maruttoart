<?php
/**
 * コミュニティ作品類似画像計算処理スクリプト
 * ベクトル類似度（コサイン類似度）を計算して類似作品をDBに保存
 * 
 * 実行方法: php calculate_community_artwork_similarities.php
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

require_once __DIR__ . '/../config.php';

// ログファイルの設定
$logFile = __DIR__ . '/../logs/community_artwork_similarity.log';

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
 * 進捗テーブルにpriorityカラムがあるか確認し、なければ追加
 */
function ensurePriorityColumnExists($pdo) {
    try {
        $checkStmt = $pdo->query("
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'community_artwork_similarity_progress' 
              AND COLUMN_NAME = 'priority'
        ");
        
        if ($checkStmt->rowCount() == 0) {
            logMessage('Adding priority column to community_artwork_similarity_progress table');
            $pdo->exec("
                ALTER TABLE community_artwork_similarity_progress 
                ADD COLUMN priority INT DEFAULT 0 COMMENT '優先度（高い順に処理）'
            ");
            $pdo->exec("CREATE INDEX idx_priority ON community_artwork_similarity_progress(priority)");
            logMessage('Priority column added successfully');
        }
    } catch (Exception $e) {
        logMessage("Warning: Could not check/add priority column: " . $e->getMessage(), 'WARNING');
    }
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
 * 類似作品を計算・保存
 */
function calculateSimilarities($artworkId, $pdo) {
    logMessage("Starting similarity calculation for artwork ID: {$artworkId}");
    
    try {
        // 対象作品の情報とベクトルを取得
        $stmt = $pdo->prepare("
            SELECT id, title, pen_name, image_embedding
            FROM community_artworks 
            WHERE id = ? AND image_embedding IS NOT NULL AND status = 'approved'
        ");
        $stmt->execute([$artworkId]);
        $targetArtwork = $stmt->fetch();
        
        if (!$targetArtwork) {
            throw new Exception("Target artwork not found or no embedding: {$artworkId}");
        }
        
        $targetVector = json_decode($targetArtwork['image_embedding'], true);
        if (!$targetVector) {
            throw new Exception("Invalid embedding data for artwork: {$artworkId}");
        }
        
        logMessage("Target artwork: {$targetArtwork['title']} by {$targetArtwork['pen_name']} (ID: {$artworkId})");
        
        // 比較対象を取得（承認済み作品のみ、自分以外）
        $compareQuery = "
            SELECT id, title, pen_name, image_embedding
            FROM community_artworks
            WHERE id != ? 
              AND image_embedding IS NOT NULL
              AND status = 'approved'
        ";
        $compareStmt = $pdo->prepare($compareQuery);
        $compareStmt->execute([$artworkId]);
        $compareArtworks = $compareStmt->fetchAll();
        
        logMessage("Found " . count($compareArtworks) . " artworks to compare");
        
        // 既存の類似度データを削除
        $deleteStmt = $pdo->prepare("DELETE FROM community_artwork_similarities WHERE artwork_id = ?");
        $deleteStmt->execute([$artworkId]);
        
        $similarities = [];
        $processedCount = 0;
        
        // 各作品との類似度を計算
        foreach ($compareArtworks as $compareArtwork) {
            $compareVector = json_decode($compareArtwork['image_embedding'], true);
            if (!$compareVector) {
                logMessage("Invalid embedding for artwork ID: {$compareArtwork['id']}", 'WARNING');
                continue;
            }
            
            try {
                $similarity = calculateCosineSimilarity($targetVector, $compareVector);
                
                // 類似度が閾値以上の場合のみ保存（0.3以上）
                if ($similarity >= 0.3) {
                    $similarities[] = [
                        'similar_artwork_id' => $compareArtwork['id'],
                        'similarity_score' => $similarity,
                        'title' => $compareArtwork['title'],
                        'pen_name' => $compareArtwork['pen_name']
                    ];
                }
                
                $processedCount++;
            } catch (Exception $e) {
                logMessage("Error calculating similarity with artwork {$compareArtwork['id']}: " . $e->getMessage(), 'ERROR');
            }
        }
        
        logMessage("Processed {$processedCount} comparisons, found " . count($similarities) . " similar artworks");
        
        // 類似度の高い順にソート
        usort($similarities, function($a, $b) {
            return $b['similarity_score'] <=> $a['similarity_score'];
        });
        
        // 上位20件をデータベースに保存
        if (!empty($similarities)) {
            $similarities = array_slice($similarities, 0, 20);
            
            $insertStmt = $pdo->prepare("
                INSERT INTO community_artwork_similarities 
                (artwork_id, similar_artwork_id, similarity_score, calculation_method) 
                VALUES (?, ?, ?, 'cosine_similarity')
            ");
            
            $savedCount = 0;
            foreach ($similarities as $similarity) {
                $insertStmt->execute([
                    $artworkId,
                    $similarity['similar_artwork_id'],
                    $similarity['similarity_score']
                ]);
                $savedCount++;
            }
            
            logMessage("Saved {$savedCount} similar artworks for ID: {$artworkId}");
            
            // 上位3件をログに出力
            for ($i = 0; $i < min(3, count($similarities)); $i++) {
                $sim = $similarities[$i];
                logMessage("  #" . ($i+1) . ": {$sim['title']} by {$sim['pen_name']} (score: " . round($sim['similarity_score'], 4) . ")");
            }
        } else {
            logMessage("No similar artworks found above threshold for ID: {$artworkId}");
        }
        
        return count($similarities);
    } catch (Exception $e) {
        logMessage("Error in calculateSimilarities: " . $e->getMessage(), 'ERROR');
        throw $e;
    }
}

/**
 * 進捗ステータスを更新
 */
function updateProgress($artworkId, $status, $errorMessage = null, $pdo) {
    $sql = "
        UPDATE community_artwork_similarity_progress 
        SET status = ?, 
            error_message = ?,
            processed_at = NOW(),
            updated_at = NOW()
        WHERE artwork_id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $errorMessage, $artworkId]);
}

/**
 * 処理統計を取得
 */
function getProcessingStats($pdo) {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_artworks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_count
        FROM community_artwork_similarity_progress
    ");
    
    return $stmt->fetch();
}

/**
 * メイン処理
 */
function main() {
    logMessage('Starting community artwork similarity calculation process');
    
    try {
        $pdo = getDB();
        
        // priorityカラムの存在確認と追加
        ensurePriorityColumnExists($pdo);
        
        // 新しい作品を進捗テーブルに追加
        addNewArtworksToProgress($pdo);
        
        // 処理待ちの作品を優先度順に取得
        $stmt = $pdo->prepare("
            SELECT 
                casp.artwork_id, 
                casp.status,
                casp.priority,
                ca.title
            FROM community_artwork_similarity_progress casp
            JOIN community_artworks ca ON casp.artwork_id = ca.id
            WHERE casp.status = 'pending'
              AND ca.image_embedding IS NOT NULL
              AND ca.status = 'approved'
            ORDER BY casp.priority DESC, casp.created_at ASC
            LIMIT 1
        ");
        $stmt->execute();
        $progress = $stmt->fetch();
        
        if (!$progress) {
            // 待機中の作品がない場合は、循環処理（古い完了済みを再処理）
            $stats = getProcessingStats($pdo);
            logMessage("Process statistics: Total={$stats['total_artworks']}, Completed={$stats['completed_count']}, Pending={$stats['pending_count']}, Error={$stats['error_count']}");
            logMessage('No pending artworks found, starting circulation process for freshness');
            
            // 最も古く処理された完了済み作品を取得
            $circulationStmt = $pdo->prepare("
                SELECT casp.artwork_id, casp.status, casp.processed_at
                FROM community_artwork_similarity_progress casp
                JOIN community_artworks ca ON casp.artwork_id = ca.id
                WHERE casp.status = 'completed' 
                  AND ca.image_embedding IS NOT NULL
                  AND ca.status = 'approved'
                ORDER BY casp.processed_at ASC 
                LIMIT 1
            ");
            $circulationStmt->execute();
            $progress = $circulationStmt->fetch();
            
            if ($progress) {
                logMessage("Starting circulation: Re-processing artwork ID {$progress['artwork_id']} (last processed: {$progress['processed_at']})");
                
                // ステータスをpendingに戻す
                $updateStmt = $pdo->prepare("
                    UPDATE community_artwork_similarity_progress 
                    SET status = 'pending', updated_at = NOW()
                    WHERE artwork_id = ?
                ");
                $updateStmt->execute([$progress['artwork_id']]);
                
                // 再取得
                $stmt->execute();
                $progress = $stmt->fetch();
            }
        }
        
        if (!$progress) {
            logMessage('No artworks found for processing (including circulation)');
            return;
        }
        
        $artworkId = $progress['artwork_id'];
        logMessage("Processing artwork ID: {$artworkId} (status: {$progress['status']}, priority: {$progress['priority']})");
        
        // ステータスを処理中に更新
        updateProgress($artworkId, 'processing', null, $pdo);
        
        // 類似度計算を実行
        $similarCount = calculateSimilarities($artworkId, $pdo);
        
        // 完了ステータスに更新
        updateProgress($artworkId, 'completed', null, $pdo);
        
        logMessage("Successfully calculated {$similarCount} similarities for artwork ID: {$artworkId}");
        
    } catch (Exception $e) {
        logMessage("Error in main process: " . $e->getMessage(), 'ERROR');
        
        // エラーステータスに更新
        if (isset($artworkId)) {
            updateProgress($artworkId, 'error', $e->getMessage(), $pdo);
        }
        
        exit(1);
    }
}

/**
 * 新しい作品を進捗テーブルに追加
 */
function addNewArtworksToProgress($pdo) {
    try {
        // 画像埋め込みがある承認済み作品で、まだ進捗テーブルに登録されていないものを取得
        $stmt = $pdo->prepare("
            SELECT ca.id, ca.created_at
            FROM community_artworks ca
            LEFT JOIN community_artwork_similarity_progress casp ON ca.id = casp.artwork_id
            WHERE ca.image_embedding IS NOT NULL 
              AND ca.status = 'approved'
              AND casp.artwork_id IS NULL
            ORDER BY ca.created_at DESC
        ");
        $stmt->execute();
        $newArtworks = $stmt->fetchAll();
        
        // 新しい作品を進捗テーブルに追加（作成日時の新しい順で優先度設定）
        if (!empty($newArtworks)) {
            $insertStmt = $pdo->prepare("
                INSERT INTO community_artwork_similarity_progress 
                (artwork_id, status, created_at, updated_at, priority) 
                VALUES (?, 'pending', NOW(), NOW(), ?)
            ");
            
            $addedCount = 0;
            foreach ($newArtworks as $artwork) {
                // より新しい作品により高い優先度を付与
                $priority = 1000 - $addedCount;
                $insertStmt->execute([$artwork['id'], $priority]);
                $addedCount++;
            }
            
            logMessage("Added {$addedCount} new artworks to similarity calculation queue (newest first)");
        }
        
        // 既存の完了済み作品で画像埋め込みが更新された場合、再計算対象にする
        $updateStmt = $pdo->prepare("
            UPDATE community_artwork_similarity_progress casp
            JOIN community_artworks ca ON casp.artwork_id = ca.id
            SET casp.status = 'pending', casp.updated_at = NOW()
            WHERE casp.status = 'completed' 
              AND ca.image_embedding IS NOT NULL
              AND ca.status = 'approved'
              AND ca.updated_at > casp.processed_at
        ");
        $updateStmt->execute();
        $updatedCount = $updateStmt->rowCount();
        
        if ($updatedCount > 0) {
            logMessage("Updated {$updatedCount} artworks for re-calculation due to embedding updates");
        }
        
        if (empty($newArtworks) && $updatedCount == 0) {
            logMessage('No new artworks found to add to similarity calculation queue');
        }
        
    } catch (Exception $e) {
        logMessage("Error adding new artworks to progress: " . $e->getMessage(), 'ERROR');
        throw $e;
    }
}

// スクリプト実行
main();
logMessage('Community artwork similarity calculation process completed');
