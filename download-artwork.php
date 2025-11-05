<?php
require_once 'config.php';

$id = intval($_GET['id'] ?? 0);

if (empty($id)) {
    http_response_code(404);
    echo 'Artwork ID not provided';
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
    // 作品が存在するか確認（ステータス関係なく）
    $checkStmt = $pdo->prepare("SELECT id, status FROM community_artworks WHERE id = ?");
    $checkStmt->execute([$id]);
    $checkArtwork = $checkStmt->fetch();
    
    if ($checkArtwork) {
        echo 'Artwork not approved for download';
    } else {
        echo 'Artwork not found';
    }
    http_response_code(404);
    exit;
}

// ダウンロード数を増加（カラムが存在する場合のみ）
try {
    $updateDownloadStmt = $pdo->prepare("UPDATE community_artworks SET download_count = download_count + 1 WHERE id = ?");
    $updateDownloadStmt->execute([$id]);
} catch (PDOException $e) {
    // download_count カラムが存在しない場合は無視
}

// ファイルパスを特定（PNG優先）
function findArtworkFile($artwork) {
    $basePath = __DIR__ . '/';
    
    // file_path（PNGファイル）を探す - file_pathにフルパスが含まれている場合
    if (!empty($artwork['file_path'])) {
        $filePath = $basePath . $artwork['file_path'];
        if (file_exists($filePath)) {
            return [
                'path' => $filePath,
                'filename' => basename($artwork['file_path']),
                'type' => 'png_file_path'
            ];
        }
    }
    
    return null;
}

$fileInfo = findArtworkFile($artwork);

if (!$fileInfo) {
    http_response_code(404);
    echo 'PNG file not found';
    exit;
}

$filePath = $fileInfo['path'];
$filename = $fileInfo['filename'];

// PNGファイルのみ対応
$mimeType = 'image/png';

// ファイル名を安全にする（日本語対応・常にPNG拡張子）
$cleanTitle = $artwork['title'] ? 
    preg_replace('/[^\p{L}\p{N}\s\-_\.]/u', '', $artwork['title']) : 
    'artwork';

// 常にPNG拡張子でダウンロード
$safeFilename = $cleanTitle . '.png';

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