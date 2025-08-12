<?php
// 画像配信用のファイル - キャッシュ最適化
require_once 'config.php';

// 静的リソース用の長期キャッシュヘッダーを設定
setStaticCacheHeaders();

$imagePath = $_GET['path'] ?? '';
if (empty($imagePath)) {
    http_response_code(404);
    exit;
}

// セキュリティチェック: パストラバーサル攻撃を防ぐ
$imagePath = str_replace(['../', '.\\'], '', $imagePath);
$fullPath = __DIR__ . '/' . $imagePath;

// ファイルが存在し、uploadsディレクトリ内かチェック
if (!file_exists($fullPath) || strpos(realpath($fullPath), realpath(__DIR__ . '/uploads/')) !== 0) {
    http_response_code(404);
    exit;
}

// ファイルタイプを取得
$fileInfo = getimagesize($fullPath);
if ($fileInfo === false) {
    http_response_code(404);
    exit;
}

// Content-Typeヘッダーを設定
header('Content-Type: ' . $fileInfo['mime']);
header('Content-Length: ' . filesize($fullPath));

// ファイルを出力
readfile($fullPath);
?>
