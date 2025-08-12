<?php
// キャッシュクリア用のファイル
// このファイルにアクセスすることで様々なキャッシュをクリアします

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "<!DOCTYPE html>";
echo "<html lang='ja'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>キャッシュクリア実行中...</title>";
echo "</head>";
echo "<body>";
echo "<h1>キャッシュクリア実行中...</h1>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";

// LiteSpeedキャッシュクリア用のヘッダーを送信
if (function_exists('litespeed_purge_all')) {
    litespeed_purge_all();
    echo "<p>✓ LiteSpeed キャッシュをクリアしました</p>";
} else {
    header('X-LiteSpeed-Purge: *');
    echo "<p>✓ LiteSpeed キャッシュクリア指示を送信しました</p>";
}

// OPcacheクリア
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<p>✓ OPcache をクリアしました</p>";
}

// サーバー情報を表示
echo "<h2>サーバー情報</h2>";
echo "<p>サーバーソフトウェア: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "</p>";
echo "<p>PHP バージョン: " . phpversion() . "</p>";
echo "<p>ドキュメントルート: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . "</p>";

// 現在のファイル一覧を確認
echo "<h2>現在のファイル一覧 (トップディレクトリ)</h2>";
echo "<ul>";
$files = scandir('.');
foreach($files as $file) {
    if ($file !== '.' && $file !== '..') {
        echo "<li>" . htmlspecialchars($file) . "</li>";
    }
}
echo "</ul>";

echo "<p><a href='/'>メインページに戻る</a></p>";
echo "<script>setTimeout(function(){ window.location.href = '/'; }, 5000);</script>";
echo "</body>";
echo "</html>";
?>
