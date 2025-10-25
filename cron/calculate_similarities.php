<?php
/**
 * 類似画像計算処理スクリプト
 * ベクトル類似度（コサイン類似度）を計算して類似画像をDBに保存
 */

// エラー表示を有効化（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

require_once __DIR__ . '/../config.php';

// ログファイルの設定
$logFile = __DIR__ . '/../logs/similarity_calculation.log';

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
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry;
}

/**
 * 必要なデータベーステーブルが存在するかチェック
 */
function checkRequiredTables($pdo) {
    $requiredTables = [
        'materials',
        'material_tags',
        'material_similarities',
        'similarity_calculation_progress'
    ];
    
    foreach ($requiredTables as $table) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as table_count 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
        ");
        $stmt->execute([$table]);
        $result = $stmt->fetch();
        if ($result['table_count'] == 0) {
            throw new Exception("Required table '{$table}' does not exist. Please run database migration: add_material_similarities.sql");
        }
    }
    
    logMessage("All required tables exist");
    
    // similarity_calculation_progressテーブルにpriorityカラムが存在するかチェック
    checkAndAddPriorityColumn($pdo);
}

/**
 * similarity_calculation_progressテーブルにpriorityカラムを追加（存在しない場合）
 */
function checkAndAddPriorityColumn($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as column_count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'similarity_calculation_progress' 
              AND COLUMN_NAME = 'priority'
        ");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['column_count'] == 0) {
            // priorityカラムを追加
            $pdo->exec("
                ALTER TABLE similarity_calculation_progress 
                ADD COLUMN priority INT DEFAULT 0 AFTER status
            ");
            logMessage("Added priority column to similarity_calculation_progress table");
        } else {
            logMessage("Priority column already exists in similarity_calculation_progress table");
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
 * 類似画像を計算・保存
 */
function calculateSimilarities($materialId, $pdo) {
    logMessage("Starting similarity calculation for material ID: {$materialId}");
    
    try {
        // 対象素材の情報とベクトルを取得
        $stmt = $pdo->prepare("
            SELECT id, title, category_id, image_embedding
            FROM materials 
            WHERE id = ? AND image_embedding IS NOT NULL
        ");
        $stmt->execute([$materialId]);
        $targetMaterial = $stmt->fetch();
        
        if (!$targetMaterial) {
            throw new Exception("Target material not found or no embedding: {$materialId}");
        }
        
        $targetVector = json_decode($targetMaterial['image_embedding'], true);
        if (!$targetVector) {
            throw new Exception("Invalid embedding data for material: {$materialId}");
        }
        
        logMessage("Target material: {$targetMaterial['title']} (ID: {$materialId})");
        
        // 対象素材のタグを取得
        try {
            $tagStmt = $pdo->prepare("
                SELECT tag_id 
                FROM material_tags 
                WHERE material_id = ?
            ");
            $tagStmt->execute([$materialId]);
            $targetTags = array_column($tagStmt->fetchAll(), 'tag_id');
        } catch (Exception $e) {
            logMessage("Warning: Could not fetch tags (table might not exist): " . $e->getMessage(), 'WARNING');
            $targetTags = [];
        }
        
        // 比較対象を取得（同じカテゴリまたは共通タグを持つ素材）
        if (!empty($targetTags)) {
            $compareQuery = "
                SELECT DISTINCT m.id, m.title, m.image_embedding
                FROM materials m
                WHERE m.id != ? 
                  AND m.image_embedding IS NOT NULL
                  AND (
                      m.category_id = ?
                      OR EXISTS (
                          SELECT 1 FROM material_tags mt 
                          WHERE mt.material_id = m.id 
                            AND mt.tag_id IN (" . implode(',', array_fill(0, count($targetTags), '?')) . ")
                      )
                  )
            ";
            $compareParams = [$materialId, $targetMaterial['category_id']];
            $compareParams = array_merge($compareParams, $targetTags);
        } else {
            $compareQuery = "
                SELECT DISTINCT m.id, m.title, m.image_embedding
                FROM materials m
                WHERE m.id != ? 
                  AND m.image_embedding IS NOT NULL
                  AND m.category_id = ?
            ";
            $compareParams = [$materialId, $targetMaterial['category_id']];
        }
        
        $compareStmt = $pdo->prepare($compareQuery);
        $compareStmt->execute($compareParams);
        $compareMaterials = $compareStmt->fetchAll();
        
        logMessage("Found " . count($compareMaterials) . " materials to compare");
        
        // 既存の類似度データを削除
        $deleteStmt = $pdo->prepare("DELETE FROM material_similarities WHERE material_id = ?");
        $deleteStmt->execute([$materialId]);
        
        $similarities = [];
        $processedCount = 0;
        
        // 各素材との類似度を計算
        foreach ($compareMaterials as $compareMaterial) {
            $compareVector = json_decode($compareMaterial['image_embedding'], true);
            if (!$compareVector) {
                logMessage("Invalid embedding for material ID: {$compareMaterial['id']}", 'WARNING');
                continue;
            }
            
            try {
                $similarity = calculateCosineSimilarity($targetVector, $compareVector);
                
                // 類似度が閾値以上の場合のみ保存（0.3以上）
                if ($similarity >= 0.3) {
                    $similarities[] = [
                        'similar_material_id' => $compareMaterial['id'],
                        'similarity_score' => $similarity,
                        'title' => $compareMaterial['title']
                    ];
                }
                
                $processedCount++;
                
                // 100件ごとに進捗をログ出力
                if ($processedCount % 100 === 0) {
                    logMessage("Processed {$processedCount}/" . count($compareMaterials) . " comparisons");
                }
                
            } catch (Exception $e) {
                logMessage("Error calculating similarity with material {$compareMaterial['id']}: " . $e->getMessage(), 'WARNING');
            }
        }
        
        // 類似度でソート（降順）
        usort($similarities, function($a, $b) {
            return $b['similarity_score'] <=> $a['similarity_score'];
        });
        
        // 上位20件のみ保存
        $similarities = array_slice($similarities, 0, 20);
        
        // データベースに保存
        if (!empty($similarities)) {
            $insertStmt = $pdo->prepare("
                INSERT INTO material_similarities 
                (material_id, similar_material_id, similarity_score, calculation_method) 
                VALUES (?, ?, ?, 'cosine_similarity')
            ");
            
            $savedCount = 0;
            foreach ($similarities as $similarity) {
                $insertStmt->execute([
                    $materialId,
                    $similarity['similar_material_id'],
                    $similarity['similarity_score']
                ]);
                $savedCount++;
            }
            
            logMessage("Saved {$savedCount} similar materials for ID: {$materialId}");
            
            // 上位3件をログに出力
            for ($i = 0; $i < min(3, count($similarities)); $i++) {
                $sim = $similarities[$i];
                logMessage("  #" . ($i+1) . ": {$sim['title']} (score: " . round($sim['similarity_score'], 4) . ")");
            }
        } else {
            logMessage("No similar materials found above threshold for ID: {$materialId}");
        }
        
        return count($similarities);
        
    } catch (Exception $e) {
        logMessage("Error in similarity calculation: " . $e->getMessage(), 'ERROR');
        throw $e;
    }
}

/**
 * メイン処理
 */
function main() {
    logMessage('Starting similarity calculation process');
    
    try {
        $pdo = getDB();
        
        // 必要なテーブルの存在チェック
        checkRequiredTables($pdo);
        
        // 新しい素材を進捗テーブルに追加
        addNewMaterialsToProgress($pdo);
        
        // 処理対象の素材を取得（新規優先 + 巡回処理）
        $stmt = $pdo->prepare("
            SELECT material_id, status, priority
            FROM similarity_calculation_progress 
            WHERE status IN ('pending', 'error')
            ORDER BY 
                CASE WHEN status = 'error' THEN 1 ELSE 0 END,
                priority DESC,
                created_at ASC 
            LIMIT 1
        ");
        $stmt->execute();
        $progress = $stmt->fetch();
        
        // 新規・エラー素材がない場合、巡回処理として完了済み素材を再処理
        if (!$progress) {
            // 統計情報を取得
            $statsStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_materials,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_count
                FROM similarity_calculation_progress scp
                JOIN materials m ON scp.material_id = m.id
                WHERE m.image_embedding IS NOT NULL
            ");
            $statsStmt->execute();
            $stats = $statsStmt->fetch();
            
            logMessage("Process statistics: Total={$stats['total_materials']}, Completed={$stats['completed_count']}, Pending={$stats['pending_count']}, Error={$stats['error_count']}");
            logMessage('No pending materials found, starting circulation process for freshness');
            
            // 最も古く処理された完了済み素材を取得
            $circulationStmt = $pdo->prepare("
                SELECT scp.material_id, scp.status, scp.processed_at
                FROM similarity_calculation_progress scp
                JOIN materials m ON scp.material_id = m.id
                WHERE scp.status = 'completed' 
                  AND m.image_embedding IS NOT NULL
                ORDER BY scp.processed_at ASC 
                LIMIT 1
            ");
            $circulationStmt->execute();
            $progress = $circulationStmt->fetch();
            
            if ($progress) {
                logMessage("Starting circulation: Re-processing material ID {$progress['material_id']} (last processed: {$progress['processed_at']})");
                
                // 巡回処理の場合、既存の類似度データを削除
                $deleteStmt = $pdo->prepare("
                    DELETE FROM material_similarities 
                    WHERE material_id = ?
                ");
                $deleteStmt->execute([$progress['material_id']]);
                logMessage("Cleared existing similarity data for fresh calculation");
            }
        }
        
        if (!$progress) {
            logMessage('No materials found for processing (including circulation)');
            return;
        }
        
        $materialId = $progress['material_id'];
        
        // ステータスを処理中に更新
        $updateStmt = $pdo->prepare("
            UPDATE similarity_calculation_progress 
            SET status = 'processing', updated_at = NOW() 
            WHERE material_id = ?
        ");
        $updateStmt->execute([$materialId]);
        
        // 類似度計算を実行
        $similarityCount = calculateSimilarities($materialId, $pdo);
        
        // ステータスを完了に更新
        $completeStmt = $pdo->prepare("
            UPDATE similarity_calculation_progress 
            SET status = 'completed', processed_at = NOW(), error_message = NULL, updated_at = NOW() 
            WHERE material_id = ?
        ");
        $completeStmt->execute([$materialId]);
        
        logMessage("Successfully processed material ID: {$materialId}, found {$similarityCount} similar materials");
        
    } catch (Exception $e) {
        // エラー時にステータスを更新
        if (isset($materialId)) {
            $errorStmt = $pdo->prepare("
                UPDATE similarity_calculation_progress 
                SET status = 'error', error_message = ?, updated_at = NOW() 
                WHERE material_id = ?
            ");
            $errorStmt->execute([$e->getMessage(), $materialId]);
        }
        
        logMessage("Error: " . $e->getMessage(), 'ERROR');
        exit(1);
    }
}

/**
 * 新しい素材を進捗テーブルに追加
 */
function addNewMaterialsToProgress($pdo) {
    try {
        // 画像埋め込みがある素材で、まだ進捗テーブルに登録されていないものを取得（新規優先順）
        $stmt = $pdo->prepare("
            SELECT m.id, m.created_at
            FROM materials m
            LEFT JOIN similarity_calculation_progress scp ON m.id = scp.material_id
            WHERE m.image_embedding IS NOT NULL 
              AND scp.material_id IS NULL
            ORDER BY m.created_at DESC
        ");
        $stmt->execute();
        $newMaterials = $stmt->fetchAll();
        
        // 新しい素材を進捗テーブルに追加（作成日時の新しい順で優先度設定）
        if (!empty($newMaterials)) {
            $insertStmt = $pdo->prepare("
                INSERT INTO similarity_calculation_progress 
                (material_id, status, created_at, updated_at, priority) 
                VALUES (?, 'pending', NOW(), NOW(), ?)
            ");
            
            $addedCount = 0;
            foreach ($newMaterials as $material) {
                // より新しい素材により高い優先度を付与（1000 - インデックス）
                $priority = 1000 - $addedCount;
                $insertStmt->execute([$material['id'], $priority]);
                $addedCount++;
            }
            
            logMessage("Added {$addedCount} new materials to similarity calculation queue (newest first)");
        }
        
        // 既存の完了済み素材で画像埋め込みが更新された場合、再計算対象にする
        $updateStmt = $pdo->prepare("
            UPDATE similarity_calculation_progress scp
            JOIN materials m ON scp.material_id = m.id
            SET scp.status = 'pending', scp.updated_at = NOW()
            WHERE scp.status = 'completed' 
              AND m.image_embedding IS NOT NULL
              AND m.updated_at > scp.processed_at
        ");
        $updateStmt->execute();
        $updatedCount = $updateStmt->rowCount();
        
        if ($updatedCount > 0) {
            logMessage("Updated {$updatedCount} materials for re-calculation due to embedding updates");
        }
        
        if (empty($newMaterials) && $updatedCount == 0) {
            logMessage('No new materials found to add to similarity calculation queue');
        }
        
    } catch (Exception $e) {
        logMessage("Error adding new materials to progress: " . $e->getMessage(), 'ERROR');
        throw $e;
    }
}

// スクリプト実行
main();
logMessage('Similarity calculation process completed');