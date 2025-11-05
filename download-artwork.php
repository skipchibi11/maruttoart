<?php
require_once 'config.php';

$id = intval($_GET['id'] ?? 0);

if (empty($id)) {
    http_response_code(404);
    echo 'Artwork not found';
    exit;
}

$pdo = getDB();

// 作品情報を取得（承認済みのみ）
$stmt = $pdo->prepare("
    SELECT * FROM community_artworks 
    WHERE id = ? AND status = 'approved'
");
$stmt->execute([$id]);
$artwork = $stmt->fetch();

if (!$artwork) {
    http_response_code(404);
    echo 'Artwork not found';
    exit;
}

// ダウンロード数を増加（カラムが存在する場合のみ）
try {
    $updateDownloadStmt = $pdo->prepare("UPDATE community_artworks SET download_count = download_count + 1 WHERE id = ?");
    $updateDownloadStmt->execute([$id]);
} catch (PDOException $e) {
    // download_count カラムが存在しない場合は無視
    error_log("Download count update failed: " . $e->getMessage());
}

// ファイルパスを特定
function findArtworkFile($artwork) {
    $basePath = __DIR__ . '/uploads/everyone-works/';
    
    // オリジナルファイルを最優先で探す
    if (!empty($artwork['original_filename'])) {
        $filePath = $basePath . $artwork['original_filename'];
        if (file_exists($filePath)) {
            return [
                'path' => $filePath,
                'filename' => $artwork['original_filename'],
                'type' => 'original'
            ];
        }
    }
    
    // image_pathを確認（年/月構造）
    if (!empty($artwork['image_path'])) {
        $year = date('Y', strtotime($artwork['created_at']));
        $month = date('m', strtotime($artwork['created_at']));
        
        // 年/月/ファイル名の構造
        $filePath = $basePath . $year . '/' . $month . '/' . $artwork['image_path'];
        if (file_exists($filePath)) {
            return [
                'path' => $filePath,
                'filename' => $artwork['image_path'],
                'type' => 'dated'
            ];
        }
        
        // 直接パス
        $filePath = $basePath . $artwork['image_path'];
        if (file_exists($filePath)) {
            return [
                'path' => $filePath,
                'filename' => $artwork['image_path'],
                'type' => 'direct'
            ];
        }
    }
    
    // WebPファイルを代替として使用
    if (!empty($artwork['webp_path'])) {
        $filePath = __DIR__ . '/' . $artwork['webp_path'];
        if (file_exists($filePath)) {
            return [
                'path' => $filePath,
                'filename' => basename($artwork['webp_path']),
                'type' => 'webp'
            ];
        }
    }
    
    return null;
}

$fileInfo = findArtworkFile($artwork);

if (!$fileInfo) {
    http_response_code(404);
    echo 'File not found';
    exit;
}

$filePath = $fileInfo['path'];
$filename = $fileInfo['filename'];

// MIMEタイプを取得
$mimeType = mime_content_type($filePath);
if (!$mimeType) {
    $mimeType = 'application/octet-stream';
}

// ファイル名を安全にする（日本語対応）
$safeFilename = $artwork['title'] ? 
    preg_replace('/[^\p{L}\p{N}\s\-_\.]/u', '', $artwork['title']) . '_' . $filename :
    $filename;

// HTTPヘッダーを設定
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// ファイルを出力
if (readfile($filePath) === false) {
    http_response_code(500);
    echo 'Error reading file';
    exit;
}

exit;
?>