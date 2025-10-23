<?php
/**
 * AI製品画像WebPファイル削除処理
 * 
 * 機能：
 * - uploadsフォルダを再帰的に巡回
 * - AI製品画像のWebPファイル（*_ai_product*.webp）を検索・削除
 * - データベース接続不要
 */

// 実行時間制限を設定（60秒）
set_time_limit(60);

// メモリ制限を設定
ini_set('memory_limit', '128M');

// エラー報告を有効にする
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// ログファイルのパス
$logFile = __DIR__ . '/../logs/cleanup_ai_webp.log';

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
 * ディレクトリを再帰的に巡回してAI製品画像のWebPファイルを削除
 */
function cleanupAiWebpFiles($directory) {
    $deletedCount = 0;
    $totalScanned = 0;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($iterator as $file) {
        $totalScanned++;
        
        // ファイル名を取得
        $fileName = $file->getFilename();
        $filePath = $file->getPathname();
        
        // AI製品画像のWebPファイルかチェック
        if (preg_match('/.*_ai_product.*\.webp$/', $fileName)) {
            writeLog("AI製品WebPファイル発見: {$filePath}");
            
            // ファイル削除を実行
            if (file_exists($filePath)) {
                if (unlink($filePath)) {
                    writeLog("削除成功: {$filePath}");
                    $deletedCount++;
                } else {
                    writeLog("削除失敗: {$filePath}");
                }
            } else {
                writeLog("ファイルが存在しません: {$filePath}");
            }
        }
        
        // 1000ファイルごとに進捗をログ出力
        if ($totalScanned % 1000 === 0) {
            writeLog("スキャン進捗: {$totalScanned}ファイル処理済み");
        }
    }
    
    return [$deletedCount, $totalScanned];
}

/**
 * 空のディレクトリを削除（オプション）
 */
function removeEmptyDirectories($directory) {
    $removedDirs = 0;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $dir) {
        if ($dir->isDir()) {
            $dirPath = $dir->getPathname();
            
            // ディレクトリが空かチェック
            $files = array_diff(scandir($dirPath), array('.', '..'));
            if (empty($files)) {
                if (rmdir($dirPath)) {
                    writeLog("空ディレクトリ削除: {$dirPath}");
                    $removedDirs++;
                } else {
                    writeLog("空ディレクトリ削除失敗: {$dirPath}");
                }
            }
        }
    }
    
    return $removedDirs;
}

try {
    writeLog("AI製品画像WebPファイル削除処理を開始");
    
    // uploadsディレクトリのパス
    $uploadsDir = __DIR__ . '/../uploads';
    
    if (!is_dir($uploadsDir)) {
        throw new Exception("uploadsディレクトリが見つかりません: {$uploadsDir}");
    }
    
    writeLog("対象ディレクトリ: {$uploadsDir}");
    
    // AI製品画像のWebPファイルを削除
    list($deletedCount, $totalScanned) = cleanupAiWebpFiles($uploadsDir);
    
    writeLog("ファイルスキャン完了: {$totalScanned}ファイル中 {$deletedCount}ファイルを削除");
    
    // 空のディレクトリを削除
    $removedDirs = removeEmptyDirectories($uploadsDir);
    
    writeLog("AI製品画像WebPファイル削除処理完了");
    writeLog("削除結果: WebPファイル {$deletedCount}個, 空ディレクトリ {$removedDirs}個");
    
} catch (Exception $e) {
    writeLog("エラー: " . $e->getMessage());
    exit(1);
}