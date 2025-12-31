<?php
ob_start();
require_once 'config.php';

// RSSフィードとしてキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

ob_end_clean();

header('Content-Type: application/xml; charset=utf-8');

$pdo = getDB();

// 承認済みの作品を50件取得
$stmt = $pdo->prepare("
    SELECT * FROM community_artworks 
    WHERE status = 'approved'
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute();
$artworks = $stmt->fetchAll();

$baseUrl = 'https://' . $_SERVER['HTTP_HOST'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?><rss version="2.0" 
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:media="http://search.yahoo.com/mrss/"
     xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title>みんなのアトリエ - marutto.art</title>
        <link><?= htmlspecialchars($baseUrl, ENT_XML1) ?>/everyone-works.php</link>
        <description>組み合わせで作れるフリーイラスト素材のコミュニティ作品</description>
        <language>ja</language>
        <atom:link href="<?= htmlspecialchars($baseUrl, ENT_XML1) ?>/rss-everyone-works.php" rel="self" type="application/rss+xml"/>
        <lastBuildDate><?= date('r') ?></lastBuildDate>
        
        <?php foreach ($artworks as $artwork): ?>
        <?php
            // PNG画像URL（file_path）を使用
            if (empty($artwork['file_path'])) {
                continue; // 画像がない場合はスキップ
            }
            
            $imageUrl = $baseUrl . '/' . $artwork['file_path'];
            $artworkUrl = $baseUrl . '/everyone-work.php?id=' . $artwork['id'];
            
            // 説明文を構築（タグを除いた部分が100文字を超えたら省略）
            $baseDescription = '';
            if (!empty($artwork['description'])) {
                $baseDescription = $artwork['description'];
                if (mb_strlen($baseDescription) > 100) {
                    $baseDescription = mb_substr($baseDescription, 0, 100) . '...';
                }
            }
            $description = htmlspecialchars($baseDescription, ENT_XML1);
        ?>
        <item>
            <title><?= htmlspecialchars($artwork['title'], ENT_XML1) ?></title>
            <link><?= htmlspecialchars($artworkUrl, ENT_XML1) ?></link>
            <description><?= $description ?></description>
            <pubDate><?= date('r', strtotime($artwork['created_at'])) ?></pubDate>
            <guid isPermaLink="true"><?= htmlspecialchars($artworkUrl, ENT_XML1) ?></guid>
            <?php if (!empty($artwork['pen_name'])): ?>
            <dc:creator><?= htmlspecialchars($artwork['pen_name'], ENT_XML1) ?></dc:creator>
            <?php endif; ?>
            <enclosure url="<?= htmlspecialchars($imageUrl, ENT_XML1) ?>" type="image/png" />
            <media:content url="<?= htmlspecialchars($imageUrl, ENT_XML1) ?>" type="image/png" />
        </item>
        <?php endforeach; ?>
    </channel>
</rss>