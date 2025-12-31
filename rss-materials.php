<?php
ob_start();
require_once 'config.php';

// RSSフィードとしてキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

ob_end_clean();

header('Content-Type: application/xml; charset=utf-8');

$pdo = getDB();

// 新着素材を50件取得
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        c.title as category_name,
        c.slug as category_slug
    FROM materials m
    LEFT JOIN categories c ON m.category_id = c.id
    WHERE m.svg_path IS NOT NULL 
    AND m.svg_path != ''
    ORDER BY m.created_at DESC
    LIMIT 50
");
$stmt->execute();
$materials = $stmt->fetchAll();

$baseUrl = 'https://' . $_SERVER['HTTP_HOST'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?><rss version="2.0" 
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:media="http://search.yahoo.com/mrss/"
     xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <title>素材一覧 - marutto.art</title>
        <link><?= htmlspecialchars($baseUrl, ENT_XML1) ?>/list.php</link>
        <description>組み合わせで作れるミニマルなフリーイラスト素材（商用利用OK）の新着素材</description>
        <language>ja</language>
        <atom:link href="<?= htmlspecialchars($baseUrl, ENT_XML1) ?>/rss-materials.php" rel="self" type="application/rss+xml"/>
        <lastBuildDate><?= date('r') ?></lastBuildDate>
        
        <?php foreach ($materials as $material): ?>
        <?php
            // 背景色が設定された画像（ai_product_image_path）を優先、なければfile_path
            $imageUrl = $baseUrl . '/';
            if (!empty($material['structured_image_path'])) {
                $imageUrl .= $material['structured_image_path'];
            } else {
                continue; // 画像がない場合はスキップ
            }
            
            $materialUrl = $baseUrl . '/' . $material['category_slug'] . '/' . $material['slug'] . '/';
            
            // 説明文を構築
            $description = htmlspecialchars($material['title'], ENT_XML1);
            $description .= ' #フリー素材 #イラスト素材 #freeillustration #minimalart';
        ?>
        <item>
            <title><?= htmlspecialchars($material['title'], ENT_XML1) ?></title>
            <link><?= htmlspecialchars($materialUrl, ENT_XML1) ?></link>
            <description><?= $description ?></description>
            <pubDate><?= date('r', strtotime($material['created_at'])) ?></pubDate>
            <guid isPermaLink="true"><?= htmlspecialchars($materialUrl, ENT_XML1) ?></guid>
            <?php if (!empty($material['category_name'])): ?>
            <category><?= htmlspecialchars($material['category_name'], ENT_XML1) ?></category>
            <?php endif; ?>
            <enclosure url="<?= htmlspecialchars($imageUrl, ENT_XML1) ?>" type="image/png" />
            <media:content url="<?= htmlspecialchars($imageUrl, ENT_XML1) ?>" type="image/png" />
        </item>
        <?php endforeach; ?>
    </channel>
</rss>