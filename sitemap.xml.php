<?php
require_once 'config.php';

// XMLとして出力
header('Content-Type: application/xml; charset=utf-8');

$pdo = getDB();

// ベースURL（本番環境のURLを使用）
$baseUrl = 'https://marutto.art';

// XML開始
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

// トップページ
echo "  <url>\n";
echo "    <loc>{$baseUrl}/</loc>\n";
echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
echo "    <changefreq>daily</changefreq>\n";
echo "    <priority>1.0</priority>\n";
echo "  </url>\n";

// 一覧ページ
echo "  <url>\n";
echo "    <loc>{$baseUrl}/list.php</loc>\n";
echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
echo "    <changefreq>daily</changefreq>\n";
echo "    <priority>0.8</priority>\n";
echo "  </url>\n";

// みんなのアトリエ一覧
echo "  <url>\n";
echo "    <loc>{$baseUrl}/everyone-works.php</loc>\n";
echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
echo "    <changefreq>daily</changefreq>\n";
echo "    <priority>0.8</priority>\n";
echo "  </url>\n";

// 子供のアトリエ一覧
echo "  <url>\n";
echo "    <loc>{$baseUrl}/kids-works.php</loc>\n";
echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
echo "    <changefreq>daily</changefreq>\n";
echo "    <priority>0.8</priority>\n";
echo "  </url>\n";

// あなたのアトリエ
echo "  <url>\n";
echo "    <loc>{$baseUrl}/compose/</loc>\n";
echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
echo "    <changefreq>monthly</changefreq>\n";
echo "    <priority>0.7</priority>\n";
echo "  </url>\n";

// 子供のアトリエ作成
echo "  <url>\n";
echo "    <loc>{$baseUrl}/compose/kids.php</loc>\n";
echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
echo "    <changefreq>monthly</changefreq>\n";
echo "    <priority>0.7</priority>\n";
echo "  </url>\n";

// プライバシーポリシー
echo "  <url>\n";
echo "    <loc>{$baseUrl}/privacy-policy.php</loc>\n";
echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
echo "    <changefreq>monthly</changefreq>\n";
echo "    <priority>0.3</priority>\n";
echo "  </url>\n";

// 利用規約
echo "  <url>\n";
echo "    <loc>{$baseUrl}/terms-of-use.php</loc>\n";
echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
echo "    <changefreq>monthly</changefreq>\n";
echo "    <priority>0.3</priority>\n";
echo "  </url>\n";

// カテゴリページ
$categorySql = "SELECT slug, updated_at FROM categories ORDER BY slug";
$categoryStmt = $pdo->prepare($categorySql);
$categoryStmt->execute();
$categories = $categoryStmt->fetchAll();

foreach ($categories as $category) {
    $lastmod = $category['updated_at'] ? date('Y-m-d', strtotime($category['updated_at'])) : date('Y-m-d');
    echo "  <url>\n";
    echo "    <loc>{$baseUrl}/{$category['slug']}/</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.7</priority>\n";
    echo "  </url>\n";
}

// タグページ（素材数5件以上のタグのみ）
$tagSql = "SELECT DISTINCT t.slug, t.id
           FROM tags t
           INNER JOIN material_tags mt ON t.id = mt.tag_id
           WHERE t.slug IS NOT NULL AND t.slug != ''
           GROUP BY t.id, t.slug
           HAVING COUNT(DISTINCT mt.material_id) >= 5
           ORDER BY t.slug";
$tagStmt = $pdo->prepare($tagSql);
$tagStmt->execute();
$tags = $tagStmt->fetchAll();

foreach ($tags as $tag) {
    echo "  <url>\n";
    echo "    <loc>{$baseUrl}/tag/{$tag['slug']}/</loc>\n";
    echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.6</priority>\n";
    echo "  </url>\n";
}

// 素材詳細ページ
$materialSql = "SELECT m.slug, m.updated_at, c.slug as category_slug 
                FROM materials m 
                LEFT JOIN categories c ON m.category_id = c.id 
                WHERE m.slug IS NOT NULL AND m.slug != '' 
                AND c.slug IS NOT NULL AND c.slug != ''
                ORDER BY m.updated_at DESC";
$materialStmt = $pdo->prepare($materialSql);
$materialStmt->execute();
$materials = $materialStmt->fetchAll();

foreach ($materials as $material) {
    $lastmod = $material['updated_at'] ? date('Y-m-d', strtotime($material['updated_at'])) : date('Y-m-d');
    echo "  <url>\n";
    echo "    <loc>{$baseUrl}/{$material['category_slug']}/{$material['slug']}/</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo "    <priority>0.5</priority>\n";
    echo "  </url>\n";
}

// みんなのアトリエ詳細ページ
$communityArtworksSql = "SELECT id, created_at 
                        FROM community_artworks 
                        WHERE status = 'approved' 
                        ORDER BY created_at DESC";
$communityArtworksStmt = $pdo->prepare($communityArtworksSql);
$communityArtworksStmt->execute();
$communityArtworks = $communityArtworksStmt->fetchAll();

foreach ($communityArtworks as $artwork) {
    $lastmod = $artwork['created_at'] ? date('Y-m-d', strtotime($artwork['created_at'])) : date('Y-m-d');
    echo "  <url>\n";
    echo "    <loc>{$baseUrl}/everyone-work.php?id={$artwork['id']}</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo "    <priority>0.6</priority>\n";
    echo "  </url>\n";
}

// 子供のアトリエ詳細ページ
$kidsArtworksSql = "SELECT id, created_at 
                   FROM kids_artworks 
                   ORDER BY created_at DESC";
$kidsArtworksStmt = $pdo->prepare($kidsArtworksSql);
$kidsArtworksStmt->execute();
$kidsArtworks = $kidsArtworksStmt->fetchAll();

foreach ($kidsArtworks as $artwork) {
    $lastmod = $artwork['created_at'] ? date('Y-m-d', strtotime($artwork['created_at'])) : date('Y-m-d');
    echo "  <url>\n";
    echo "    <loc>{$baseUrl}/kids-work.php?id={$artwork['id']}</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>monthly</changefreq>\n";
    echo "    <priority>0.6</priority>\n";
    echo "  </url>\n";
}

echo "</urlset>\n";
?>
