<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// カテゴリ一覧を取得
$categorySql = "SELECT id, title, slug FROM categories ORDER BY sort_order ASC, title ASC";
$categoryStmt = $pdo->prepare($categorySql);
$categoryStmt->execute();
$categories = $categoryStmt->fetchAll();

// タグ一覧を取得（素材数5件以上のタグのみ）
$tagSql = "SELECT DISTINCT t.name, t.slug
           FROM tags t
           INNER JOIN material_tags mt ON t.id = mt.tag_id
           WHERE t.slug IS NOT NULL AND t.slug != ''
           GROUP BY t.id, t.name, t.slug
           HAVING COUNT(DISTINCT mt.material_id) >= 5
           ORDER BY t.name";
$tagStmt = $pdo->prepare($tagSql);
$tagStmt->execute();
$tags = $tagStmt->fetchAll();

// 各カテゴリの素材一覧を取得
$categoryMaterials = [];
foreach ($categories as $category) {
    $materialSql = "SELECT title, slug FROM materials WHERE category_id = ? AND slug IS NOT NULL AND slug != '' ORDER BY created_at DESC LIMIT 10";
    $materialStmt = $pdo->prepare($materialSql);
    $materialStmt->execute([$category['id']]);
    $categoryMaterials[$category['id']] = $materialStmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <?php include 'includes/gdpr-gtm-inline.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>サイトマップ｜maruttoart（商用利用OK）</title>
    <meta name="description" content="maruttoartのサイトマップ。カテゴリ、タグ、すべてのイラスト素材ページへのリンクを一覧で表示しています。">
    <link rel="icon" href="/favicon.ico">
    <link rel="canonical" href="https://marutto.art/sitemap.php">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 40px 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .subtitle {
            color: #6c757d;
            font-size: 1.1rem;
        }

        .sitemap-section {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }

        .sitemap-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .category-section {
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 20px;
            background: #f8f9fa;
        }

        .category-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #495057;
        }

        .category-link {
            display: inline-block;
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .category-link:hover {
            color: #2980b9;
            text-decoration: underline;
        }

        .material-list {
            list-style: none;
            margin-left: 15px;
        }

        .material-list li {
            margin-bottom: 5px;
        }

        .material-link {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .material-link:hover {
            color: #495057;
            text-decoration: underline;
        }

        .tag-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .tag-link {
            display: block;
            padding: 10px 15px;
            background: #e9ecef;
            color: #495057;
            text-decoration: none;
            border-radius: 4px;
            text-align: center;
            transition: background-color 0.2s;
        }

        .tag-link:hover {
            background: #dee2e6;
            color: #343a40;
        }

        .main-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .main-link {
            display: block;
            padding: 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            text-align: center;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .main-link:hover {
            background: #2980b9;
            color: white;
        }

        .back-link {
            display: inline-block;
            margin-top: 30px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .back-link:hover {
            background: #5a6268;
            color: white;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            h1 {
                font-size: 2rem;
            }

            .sitemap-section {
                padding: 20px;
            }

            .sitemap-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/gdpr-gtm-noscript.php'; ?>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="header">
            <h1>サイトマップ</h1>
            <p class="subtitle">maruttoartのすべてのページへのリンク一覧</p>
        </div>

        <!-- メインページ -->
        <div class="sitemap-section">
            <h2 class="section-title">メインページ</h2>
            <div class="main-links">
                <a href="/" class="main-link">トップページ</a>
                <a href="/list.php" class="main-link">イラスト素材一覧</a>
                <a href="/everyone-works.php" class="main-link">みんなのアトリエ</a>
                <a href="/compose2/" class="main-link">あなたのアトリエ</a>
                <a href="/compose2/custom-size.php" class="main-link">カスタムサイズのアトリエ</a>
                <a href="/privacy-policy.php" class="main-link">プライバシーポリシー</a>
                <a href="/terms-of-use.php" class="main-link">利用規約</a>
            </div>
        </div>

        <!-- カテゴリ・素材ページ -->
        <div class="sitemap-section">
            <h2 class="section-title">カテゴリ・素材ページ</h2>
            <div class="sitemap-grid">
                <?php foreach ($categories as $category): ?>
                <div class="category-section">
                    <h3 class="category-title"><?= h($category['title']) ?></h3>
                    <a href="/<?= h($category['slug']) ?>/" class="category-link">
                        <?= h($category['title']) ?>一覧ページ
                    </a>
                    
                    <?php if (!empty($categoryMaterials[$category['id']])): ?>
                    <ul class="material-list">
                        <?php foreach ($categoryMaterials[$category['id']] as $material): ?>
                        <li>
                            <a href="/<?= h($category['slug']) ?>/<?= h($material['slug']) ?>/" class="material-link">
                                <?= h($material['title']) ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- タグページ -->
        <div class="sitemap-section">
            <h2 class="section-title">タグページ</h2>
            <div class="tag-grid">
                <?php foreach ($tags as $tag): ?>
                <a href="/tag/<?= h($tag['slug']) ?>/" class="tag-link">
                    <?= h($tag['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <a href="/" class="back-link">← トップページに戻る</a>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
