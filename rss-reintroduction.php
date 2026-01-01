<?php
ob_start();
require_once 'config.php';

// RSSフィードとしてキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

ob_end_clean();

header('Content-Type: application/xml; charset=utf-8');

$pdo = getDB();

// 再紹介アイテムを最新50件取得
$stmt = $pdo->prepare("
    SELECT * FROM reintroduction_items
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute();
$items = $stmt->fetchAll();

$baseUrl = 'https://' . $_SERVER['HTTP_HOST'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?><rss version="2.0" 
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:media="http://search.yahoo.com/mrss/"
     xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title>再紹介 - marutto.art</title>
        <link><?= htmlspecialchars($baseUrl, ENT_XML1) ?></link>
        <description>組み合わせで作れるミニマルなフリーイラスト素材と作品の再紹介</description>
        <language>ja</language>
        <atom:link href="<?= htmlspecialchars($baseUrl, ENT_XML1) ?>/rss-reintroduction.php" rel="self" type="application/rss+xml"/>
        <lastBuildDate><?= date('r') ?></lastBuildDate>
        
        <?php foreach ($items as $item): ?>
        <?php
            if (empty($item['image_url'])) {
                continue; // 画像がない場合はスキップ
            }
            
            // 説明文を構築（タグを除いた部分が100文字を超えたら省略）
            $baseDescription = !empty($item['description']) ? $item['description'] : $item['title'];
            if (mb_strlen($baseDescription) > 100) {
                $baseDescription = mb_substr($baseDescription, 0, 100) . '...';
            }
            $description = htmlspecialchars($baseDescription, ENT_XML1);
            
            // 固有ID（GUID）を生成: 年月 + アイテムタイプ + ID（一巡目、二巡目で別ID）
            $yearMonth = date('Ym', strtotime($item['created_at']));
            $guid = $baseUrl . '/reintroduction/' . $yearMonth . '-' . $item['item_type'] . '-' . $item['item_id'];
        ?>
        <item>
            <title><?= htmlspecialchars($item['title'], ENT_XML1) ?></title>
            <link><?= htmlspecialchars($item['page_url'], ENT_XML1) ?></link>
            <description><?= $description ?></description>
            <pubDate><?= date('r', strtotime($item['created_at'])) ?></pubDate>
            <guid isPermaLink="false"><?= htmlspecialchars($guid, ENT_XML1) ?></guid>
            <enclosure url="<?= htmlspecialchars($item['image_url'], ENT_XML1) ?>" type="image/png" />
            <media:content url="<?= htmlspecialchars($item['image_url'], ENT_XML1) ?>" type="image/png" />
        </item>
        <?php endforeach; ?>
    </channel>
</rss>