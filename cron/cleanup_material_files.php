<?php
/**
 * 素材ファイル整理定期処理
 * 
 * 機能：
 * - 更新時に作成された異なる年月フォルダのファイルを、新規登録時の年月フォルダに移動
 * - 不要なファイルを削除
 * - 1回の実行で1素材分を処理
 */

// 実行時間制限を設定（60秒）
set_time_limit(60);

// メモリ制限を設定
ini_set('memory_limit', '128M');

// エラー報告を有効にする
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

try {
    require_once __DIR__ . '/../config.php';
} catch (Exception $e) {
    $logFile = __DIR__ . '/../logs/cleanup_material_files.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] config.php読み込みエラー: " . $e->getMessage() . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    exit(1);
}

// ログファイルのパス
$logFile = __DIR__ . '/../logs/cleanup_material_files.log';

/**
 * ログを出力
 */
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    echo $logMessage;
}

/**
 * ディレクトリ内のファイルを再帰的に削除
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

/**
 * ファイルを移動（ディレクトリも作成）
 */
function moveFile($source, $destination) {
    $destinationDir = dirname($destination);
    if (!is_dir($destinationDir)) {
        mkdir($destinationDir, 0755, true);
    }
    
    return rename($source, $destination);
}

/**
 * 素材の正しい年月フォルダパスを取得
 */
function getCorrectFolderPath($createdAt) {
    $date = new DateTime($createdAt);
    return $date->format('Y/m');
}

try {
    writeLog("素材ファイル整理処理を開始");
    
    // データベース接続を取得
    try {
        $pdo = getDB();
        writeLog("データベース接続確立済み");
    } catch (Exception $e) {
        throw new Exception("データベース接続失敗: " . $e->getMessage());
    }
    
    // 処理対象の素材を1つ取得（ファイルパス内の年月が新規登録日と異なる素材）
    $stmt = $pdo->prepare("
        SELECT id, title, created_at, updated_at,
               image_path, image_medium_path, image_small_path,
               svg_path, ai_product_image_path
        FROM materials 
        WHERE (
            (image_path IS NOT NULL AND image_path NOT LIKE CONCAT(DATE_FORMAT(created_at, '%Y/%m'), '/%')) OR
            (image_medium_path IS NOT NULL AND image_medium_path NOT LIKE CONCAT(DATE_FORMAT(created_at, '%Y/%m'), '/%')) OR
            (image_small_path IS NOT NULL AND image_small_path NOT LIKE CONCAT(DATE_FORMAT(created_at, '%Y/%m'), '/%')) OR
            (svg_path IS NOT NULL AND svg_path NOT LIKE CONCAT(DATE_FORMAT(created_at, '%Y/%m'), '/%')) OR
            (ai_product_image_path IS NOT NULL AND ai_product_image_path NOT LIKE CONCAT(DATE_FORMAT(created_at, '%Y/%m'), '/%'))
        )
        ORDER BY updated_at DESC
        LIMIT 1
    ");
    
    if (!$stmt->execute()) {
        throw new Exception("クエリの実行に失敗しました: " . implode(', ', $stmt->errorInfo()));
    }
    
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$material) {
        writeLog("整理が必要な素材が見つかりませんでした");
        exit(0);
    }
    
    writeLog("処理対象素材: ID={$material['id']}, タイトル={$material['title']}");
    
    $materialId = $material['id'];
    $correctFolder = getCorrectFolderPath($material['created_at']);
    
    // uploadsディレクトリのパス
    $uploadsDir = __DIR__ . '/../uploads';
    
    // 移動対象のファイルパス配列
    $filesToMove = [];
    $dbUpdates = [];
    
    // 各ファイルパスをチェックして移動リストを作成
    $fileFields = [
        'image_path' => 'image_path',
        'image_medium_path' => 'image_medium_path', 
        'image_small_path' => 'image_small_path',
        'svg_path' => 'svg_path',
        'ai_product_image_path' => 'ai_product_image_path'
    ];
    
    foreach ($fileFields as $field => $dbField) {
        if (!empty($material[$field])) {
            $currentPath = $uploadsDir . '/' . $material[$field];
            
            // ファイルパス内の年月を抽出
            $pathPattern = '/^(\d{4}\/\d{2})\//';
            if (preg_match($pathPattern, $material[$field], $matches)) {
                $fileFolder = $matches[1];
                
                // ファイルの年月が正しいフォルダと異なる場合のみ移動対象とする
                if ($fileFolder !== $correctFolder && file_exists($currentPath)) {
                    // 正しいフォルダパスに変更
                    $newRelativePath = str_replace($fileFolder, $correctFolder, $material[$field]);
                    $newFullPath = $uploadsDir . '/' . $newRelativePath;
                    
                    $filesToMove[] = [
                        'source' => $currentPath,
                        'destination' => $newFullPath,
                        'field' => $dbField,
                        'new_path' => $newRelativePath,
                        'old_folder' => $fileFolder
                    ];
                    
                    $dbUpdates[$dbField] = $newRelativePath;
                }
            }
        }
    }
    
    if (empty($filesToMove)) {
        writeLog("移動対象のファイルが見つかりませんでした");
        exit(0);
    }
    
    // ファイルを移動
    $movedFiles = 0;
    foreach ($filesToMove as $fileInfo) {
        if (moveFile($fileInfo['source'], $fileInfo['destination'])) {
            $movedFiles++;
        } else {
            writeLog("ファイル移動失敗: {$fileInfo['source']}");
        }
    }
    
    // データベースのパスを更新
    if ($movedFiles > 0 && !empty($dbUpdates)) {
        $updateFields = [];
        $params = ['id' => $materialId];
        
        foreach ($dbUpdates as $field => $newPath) {
            $updateFields[] = "{$field} = :{$field}";
            $params[$field] = $newPath;
        }
        
        $sql = "UPDATE materials SET " . implode(', ', $updateFields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute($params)) {
            writeLog("データベース更新完了: {$movedFiles}個のファイル");
        } else {
            writeLog("データベース更新失敗");
        }
    }
    
    // 空になったディレクトリを削除
    $processedFolders = [];
    foreach ($filesToMove as $fileInfo) {
        if (isset($fileInfo['old_folder'])) {
            $processedFolders[$fileInfo['old_folder']] = true;
        }
    }
    
    foreach (array_keys($processedFolders) as $oldFolder) {
        $oldDir = $uploadsDir . '/' . $oldFolder;
        if (is_dir($oldDir)) {
            $oldDirFiles = array_diff(scandir($oldDir), array('.', '..'));
            
            // 他の素材のファイルがないかチェック
            $hasOtherFiles = false;
            foreach ($oldDirFiles as $file) {
                $filePath = $oldDir . '/' . $file;
                if (is_file($filePath)) {
                    $hasOtherFiles = true;
                    break;
                }
            }
            
            if (!$hasOtherFiles) {
                if (deleteDirectory($oldDir)) {
                    writeLog("空ディレクトリ削除: {$oldFolder}");
                }
            }
        }
    }
    
    writeLog("素材ファイル整理完了: ID={$materialId}, 移動ファイル数={$movedFiles}");
    
} catch (Exception $e) {
    writeLog("エラー: " . $e->getMessage());
    exit(1);
}