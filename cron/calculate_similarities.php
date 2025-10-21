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
        'material_similarities',
        'similarity_calculation_progress'
    ];
    
    foreach ($requiredTables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            throw new Exception("Required table '{$table}' does not exist. Please run database migration: add_material_similarities.sql");
        }
    }
    
    logMessage("All required tables exist");
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
        $tagStmt = $pdo->prepare("
            SELECT tag_id 
            FROM material_tags 
            WHERE material_id = ?
        ");
        $tagStmt->execute([$materialId]);
        $targetTags = array_column($tagStmt->fetchAll(), 'tag_id');
        
        // 比較対象を取得（同じカテゴリまたは共通タグを持つ素材）
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
                        AND mt.tag_id IN (" . (empty($targetTags) ? '0' : implode(',', array_fill(0, count($targetTags), '?'))) . ")
                  )
              )
        ";
        
        $compareParams = [$materialId, $targetMaterial['category_id']];
        if (!empty($targetTags)) {
            $compareParams = array_merge($compareParams, $targetTags);
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
        
        // 未処理の素材を1件取得
        $stmt = $pdo->prepare("
            SELECT material_id 
            FROM similarity_calculation_progress 
            WHERE status IN ('pending', 'error')
            ORDER BY 
                CASE WHEN status = 'error' THEN 1 ELSE 0 END,
                created_at ASC 
            LIMIT 1
        ");
        $stmt->execute();
        $progress = $stmt->fetch();
        
        if (!$progress) {
            logMessage('No materials found that need similarity calculation');
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

// スクリプト実行
main();
logMessage('Similarity calculation process completed');