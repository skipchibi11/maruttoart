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
        c.slug as category_slug,
        GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') as tag_names
    FROM materials m
    LEFT JOIN categories c ON m.category_id = c.id
    LEFT JOIN material_tags mt ON m.id = mt.material_id
    LEFT JOIN tags t ON mt.tag_id = t.id
    WHERE m.svg_path IS NOT NULL 
    AND m.svg_path != ''
    GROUP BY m.id
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
            if (!empty($material['ai_product_image_path'])) {
                $imageUrl .= $material['ai_product_image_path'];
            } elseif (!empty($material['file_path'])) {
                $imageUrl .= $material['file_path'];
            } else {
                continue; // 画像がない場合はスキップ
            }
            
            $materialUrl = $baseUrl . '/' . $material['category_slug'] . '/' . $material['slug'] . '/';
            
            // 説明文を構築
            $description = htmlspecialchars($material['title'], ENT_XML1);
            if (!empty($material['category_name'])) {
                $description .= ' - カテゴリー: ' . htmlspecialchars($material['category_name'], ENT_XML1);
            }
            if (!empty($material['tag_names'])) {
                $description .= ' / タグ: ' . htmlspecialchars($material['tag_names'], ENT_XML1);
            }
            if (!empty($material['primary_color'])) {
                $description .= ' / メインカラー: ' . htmlspecialchars($material['primary_color'], ENT_XML1);
            }
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