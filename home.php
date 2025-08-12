<?php
// WordPress から新しいPHPアプリケーションへの緊急リダイレクト

// 強力なキャッシュ無効化ヘッダーを送信
header('Cache-Control: no-cache, no-store, must-revalidate, proxy-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Cache-Control: no-cache');
header('X-LiteSpeed-Cache-Control: no-cache');
header('X-LiteSpeed-Purge: *');

// WordPressヘッダーを削除
header_remove('Link');
header_remove('X-Pingback');
header_remove('X-Powered-By');

// クエリパラメータを保持してindex.phpにリダイレクト
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$redirectUrl = '/index.php';

if (!empty($queryString)) {
    $redirectUrl .= '?' . $queryString;
}

// 303リダイレクト（キャッシュを避ける）
header('HTTP/1.1 303 See Other');
header('Location: ' . $redirectUrl);
exit;
?>
