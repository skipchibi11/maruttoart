<?php
require_once 'config.php';

$id = intval($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'community'; // 'community' または 'kids'

if (empty($id)) {
    http_response_code(404);
    echo 'Artwork ID not provided';
    exit;
}

$pdo = getDB();

// テーブルを選択
$table = ($type === 'kids') ? 'kids_artworks' : 'community_artworks';

// 作品情報を取得
// kids_artworksにはstatusカラムがないため、テーブルに応じて異なるクエリを使用
if ($type === 'kids') {
    // 子供のアトリエは全て公開
    $stmt = $pdo->prepare("SELECT * FROM kids_artworks WHERE id = ?");
    $stmt->execute([$id]);
} else {
    // みんなのアトリエは承認済みのみ
    $stmt = $pdo->prepare("SELECT * FROM community_artworks WHERE id = ? AND status = 'approved'");
    $stmt->execute([$id]);
}

$artwork = $stmt->fetch();

if (!$artwork) {
    // 作品が存在するか確認
    if ($type === 'kids') {
        $checkStmt = $pdo->prepare("SELECT id FROM kids_artworks WHERE id = ?");
    } else {
        $checkStmt = $pdo->prepare("SELECT id, status FROM community_artworks WHERE id = ?");
    }
    $checkStmt->execute([$id]);
    $checkArtwork = $checkStmt->fetch();
    
    if ($checkArtwork) {
        if ($type === 'kids') {
            echo 'Artwork not found';
        } else {
            echo 'Artwork not approved for download';
        }
    } else {
        echo 'Artwork not found';
    }
    http_response_code(404);
    exit;
}

// ダウンロード数を増加
try {
    // kids_artworksはdownloadsカラム、community_artworksはdownload_countカラム
    if ($type === 'kids') {
        $updateDownloadStmt = $pdo->prepare("UPDATE kids_artworks SET downloads = downloads + 1 WHERE id = ?");
    } else {
        $updateDownloadStmt = $pdo->prepare("UPDATE community_artworks SET download_count = download_count + 1 WHERE id = ?");
    }
    $updateDownloadStmt->execute([$id]);
} catch (PDOException $e) {
    // カラムが存在しない場合は無視
}

// R2などのリモートファイルかどうかをチェック
$pathColumn = ($type === 'kids') ? 'image_path' : 'file_path';
$rawPath = $artwork[$pathColumn] ?? '';

// R2のフルURLの場合はリモートファイルを取得して出力
if (!empty($rawPath) && (strpos($rawPath, 'http://') === 0 || strpos($rawPath, 'https://') === 0)) {
    // ファイル名を安全にする（日本語対応・常にPNG拡張子）
    $cleanTitle = $artwork['title'] ? 
        preg_replace('/[^\p{L}\p{N}\s\-_\.]/u', '', $artwork['title']) : 
        'artwork';
    $safeFilename = $cleanTitle . '.png';
    
    // リモートファイルを取得
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'ignore_errors' => true
        ]
    ]);
    
    $fileContent = @file_get_contents($rawPath, false, $context);
    
    if ($fileContent === false) {
        http_response_code(404);
        echo 'Failed to fetch remote file';
        exit;
    }
    
    // HTTPヘッダーを設定
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Content-Length: ' . strlen($fileContent));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    echo $fileContent;
    exit;
}

// ファイルパスを特定（PNG優先）
function findArtworkFile($artwork, $type = 'community') {
    $basePath = __DIR__ . '/';
    
    // kids_artworksはimage_pathカラム、community_artworksはfile_pathカラム
    $pathColumn = ($type === 'kids') ? 'image_path' : 'file_path';
    
    if (!empty($artwork[$pathColumn])) {
        $filePath = $basePath . $artwork[$pathColumn];
        if (file_exists($filePath)) {
            return [
                'path' => $filePath,
                'filename' => basename($artwork[$pathColumn]),
                'type' => 'png_' . $pathColumn
            ];
        }
    }
    
    return null;
}

$fileInfo = findArtworkFile($artwork, $type);

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