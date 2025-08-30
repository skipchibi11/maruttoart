<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();

// ページネーション設定
$perPage = 20; // 1ページあたりの表示件数
$page = max(1, intval($_GET['page'] ?? 1)); // 現在のページ（最小値は1）
$offset = ($page - 1) * $perPage;

// 検索処理
$search = $_GET['search'] ?? '';
$whereClause = "WHERE 1=1";
$params = [];
$countParams = [];

if (!empty($search)) {
    $whereClause .= " AND (m.title LIKE ? OR m.description LIKE ? OR m.search_keywords LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
    $countParams = $params;
}

// 総件数を取得
$countSql = "SELECT COUNT(*) FROM materials m " . $whereClause;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// データを取得（カテゴリ情報も含める）
$sql = "SELECT m.*, c.slug as category_slug FROM materials m 
        LEFT JOIN categories c ON m.category_id = c.id " . 
        $whereClause . " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google Tag Manager - GDPR対応 -->
    <script>
    // GDPR同意状況をチェックしてGTMを条件付き読み込み
    (function() {
        function getGdprConsent() {
            try {
                return localStorage.getItem('gdpr_consent_v1');
            } catch (e) {
                return null;
            }
        }
        
        function loadGTM() {
            if (window.gtmLoaded) return; // 重複読み込み防止
            window.gtmLoaded = true;
            
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','GTM-579HN546');
            
            console.log('GTM loaded after GDPR consent');
        }
        
        // 同意状況を確認
        const consent = getGdprConsent();
        if (consent === 'accepted') {
            // 既に同意済みの場合は即座に読み込み
            loadGTM();
        } else {
            // 同意していない場合は読み込まない
            console.log('GTM not loaded - GDPR consent required');
        }
        
        // GDPR同意イベントを監視（将来の同意に対応）
        window.addEventListener('gdpr-consent-accepted', loadGTM);
    })();
    </script>
    <!-- End Google Tag Manager -->
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>イラスト素材一覧｜maruttoart（商用利用OK）</title>
    <meta name="description" content="手描きイラスト素材の一覧ページ！手描きのやさしいタッチで描かれた動物、植物、食べ物などの素材を商用利用OK。個人・法人問わずご利用いただける無料素材集です。">
    <link rel="icon" href="/favicon.ico">
    
    <!-- カノニカルタグ -->
    <?php
    $canonicalUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . $_SERVER['HTTP_HOST'] . '/list.php';
    if (!empty($search)) {
        // 検索がある場合は検索クエリを含める
        $canonicalUrl .= '?search=' . urlencode($search);
        if ($page > 1) {
            $canonicalUrl .= '&page=' . $page;
        }
    } else {
        // 検索がない場合はページのみ
        if ($page > 1) {
            $canonicalUrl .= '?page=' . $page;
        }
    }
    ?>
    <link rel="canonical" href="<?= h($canonicalUrl) ?>">
    
    <!-- JSON-LD structured data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "WebSite",
                "name": "maruttoart",
                "url": "https://marutto.art/",
                "potentialAction": {
                    "@type": "SearchAction",
                    "target": {
                        "@type": "EntryPoint",
                        "urlTemplate": "https://marutto.art/list.php?search={search_term_string}"
                    },
                    "query-input": "required name=search_term_string"
                }
            },
            {
                "@type": "WebPage",
                "url": "https://marutto.art/list.php<?= !empty($search) ? '?search=' . urlencode($search) : '' ?>",
                "name": "<?php if (!empty($search)): ?>検索結果: <?= h($search) ?> - <?php endif; ?>イラスト素材一覧｜maruttoart",
                "description": "<?php if (!empty($search)): ?><?= h($search) ?>に関する無料イラスト素材の検索結果。<?php else: ?>かわいい無料イラスト素材の一覧ページ。<?php endif; ?>手描き水彩のやさしいタッチで描かれた動物、植物、食べ物などの素材を商用利用OK。",
                "isPartOf": {
                    "@type": "WebSite",
                    "name": "maruttoart",
                    "url": "https://marutto.art/"
                }<?php if (!empty($search)): ?>,
                "mainEntity": {
                    "@type": "SearchResultsPage",
                    "name": "<?= h($search) ?>の検索結果",
                    "description": "<?= h($search) ?>に関連する無料イラスト素材の検索結果一覧",
                    "url": "https://marutto.art/list.php?search=<?= urlencode($search) ?>",
                    "totalItems": <?= $totalItems ?>,
                    "itemsPerPage": <?= $perPage ?>,
                    "pageStart": <?= ($page - 1) * $perPage + 1 ?>,
                    "pageEnd": <?= min($page * $perPage, $totalItems) ?>
                }<?php endif; ?>
            },
            {
                "@type": "ItemList",
                "name": "<?php if (!empty($search)): ?><?= h($search) ?>の検索結果<?php else: ?>無料イラスト素材一覧<?php endif; ?>",
                "description": "<?php if (!empty($search)): ?><?= h($search) ?>に関連する手描き水彩イラスト素材<?php else: ?>手描き水彩のやさしいタッチで描かれたかわいい無料イラスト素材<?php endif; ?>",
                "numberOfItems": <?= $totalItems ?>,
                "url": "https://marutto.art/list.php<?= !empty($search) ? '?search=' . urlencode($search) : '' ?>",
                "itemListElement": [
                    <?php foreach (array_slice($materials, 0, 3) as $index => $material): ?>
                    {
                        "@type": "Product",
                        "position": <?= ($page - 1) * $perPage + $index + 1 ?>,
                        "name": "<?= addslashes(h($material['title'])) ?>",
                        "description": "<?= addslashes(h($material['description'] ?? $material['title'] . 'の手描き水彩イラスト素材です。商用利用可能で個人・法人問わずご利用いただけます。')) ?>",
                        "image": "https://marutto.art/<?= h($material['webp_medium_path'] ?? $material['image_path']) ?>",
                        "url": "https://marutto.art/<?= !empty($material['category_slug']) ? h($material['category_slug']) . '/' . h($material['slug']) . '/' : 'detail/' . h($material['slug']) ?>",
                        "sku": "<?= h($material['slug']) ?>-<?= date('Ymd', strtotime($material['created_at'])) ?>",
                        "brand": {
                            "@type": "Organization",
                            "name": "maruttoart"
                        },
                        "offers": {
                            "@type": "Offer",
                            "price": "0",
                            "priceCurrency": "JPY",
                            "availability": "https://schema.org/InStock"
                        },
                        "license": "https://creativecommons.org/publicdomain/zero/1.0/",
                        "keywords": "<?= addslashes(h($material['search_keywords'] ?? '')) ?>, 無料イラスト, 手描き, 水彩, 商用利用OK"
                    }<?= $index < min(2, count(array_slice($materials, 0, 3)) - 1) ? ',' : '' ?>
                    <?php endforeach; ?>
                ]
            }
        ]
    }
    </script>
    
    <style>
        /* リセットCSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            line-height: 1.5;
            color: #212529;
        }

        /* コンテナシステム */
        .container {
            width: 100%;
            max-width: 1140px;
            margin: 0 auto;
            padding-left: 15px;
            padding-right: 15px;
        }

        /* グリッドシステム */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-left: -15px;
            margin-right: -15px;
        }

        /* 基本カラム設定 */
        [class*="col-"] {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-12 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
            flex: 0 0 100%;
            max-width: 100%;
        }

        .col-6 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
            flex: 0 0 50%;
            max-width: 50%;
        }

        .col-md-4 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-lg-3 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-xl-2 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        .col-xxl-2 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        /* レスポンシブブレークポイント */
        /* ~576px: 2列表示 (col-6: 50%) */
        /* 768px~: 3列表示 (col-md-4: 33.33%) */
        /* 992px~: 4列表示 (col-lg-3: 25%) */
        /* 1200px~: 6列表示 (col-xl-2: 16.67%) */
        /* 1400px~: 6列表示維持 (col-xxl-2: 16.67%) */
        
        @media (min-width: 768px) {
            .col-md-4 {
                flex: 0 0 33.333333% !important;
                max-width: 33.333333% !important;
            }
        }

        @media (min-width: 992px) {
            .col-lg-3 {
                flex: 0 0 25% !important;
                max-width: 25% !important;
            }
        }

        /* 1200px以上: 6列表示 (16.666%) */
        @media (min-width: 1200px) {
            .col-xl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
        }

        /* 1400px以上: 6列表示を維持 */
        @media (min-width: 1400px) {
            .col-xxl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
            
            /* コンテナの最大幅を拡張 */
            .container {
                max-width: 1320px;
            }
        }

        /* 1600px以上: さらに大きな画面向け調整 */
        @media (min-width: 1600px) {
            .container {
                max-width: 1500px;
            }
            
            /* 大型画面で確実に6列表示 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
        }

        /* 1800px以上: 超大型画面向け調整 */
        @media (min-width: 1800px) {
            .container {
                max-width: 1680px;
            }
            
            /* 大型画面でも確実に6列表示 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
        }

        /* 2000px以上: 4K画面等の超大型画面 */
        @media (min-width: 2000px) {
            .container {
                max-width: 1860px;
            }
            
            /* 超大型画面でも確実に6列表示 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                flex: 0 0 16.666667% !important;
                max-width: 16.666667% !important;
            }
        }

        /* ナビゲーション */
        .navbar {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
            background-color: #ffffff;
            border-bottom: 1px solid rgba(0,0,0,.125);
        }

        .navbar-brand {
            display: inline-block;
            padding-top: 0.3125rem;
            padding-bottom: 0.3125rem;
            margin-right: 1rem;
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            text-decoration: none;
        }

        .navbar-brand:hover {
            color: #333;
            text-decoration: none;
        }

        /* カードコンポーネント */
        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.25rem;
        }

        .material-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            position: relative;
            border-radius: 0.25rem;
            will-change: transform, box-shadow;
            background-color: #fff;
            margin-bottom: 1.5rem;
        }

        .material-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            color: inherit;
            text-decoration: none;
            border-color: #0d6efd;
        }

        .material-card:focus {
            outline: none;
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .card-body {
            flex: 1 1 auto;
            padding: 0.75rem 1rem;
        }

        .card-title {
            color: #666;
            font-weight: 300;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 0;
        }

        /* h3のcard-titleで既存のh5と同じ見た目を維持 */
        h3.card-title {
            color: #666;
            font-weight: 300;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 0;
            margin-top: 0;
        }

        .material-card:hover .card-title,
        .material-card:hover h3.card-title {
            color: #0d6efd;
        }

        .material-image {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            border-radius: 0.25rem 0.25rem 0 0;
            transition: opacity 0.3s ease-in-out;
            background-color: #f8f9fa;
        }

        /* Lazyload用のスタイル */
        .material-image[loading="lazy"] {
            opacity: 0;
        }

        .material-image[loading="lazy"].loaded {
            opacity: 1;
        }

        /* 読み込み中のプレースホルダー */
        .material-image:not(.loaded) {
            background-image: linear-gradient(45deg, #f8f9fa 25%, transparent 25%, transparent 75%, #f8f9fa 75%, #f8f9fa),
                              linear-gradient(45deg, #f8f9fa 25%, transparent 25%, transparent 75%, #f8f9fa 75%, #f8f9fa);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
        }

        /* ユーティリティクラス */
        .mt-4 { margin-top: 1.5rem !important; }
        .mt-5 { margin-top: 3rem !important; }
        .mb-2 { margin-bottom: 0.5rem !important; }
        .mb-4 { margin-bottom: 1.5rem !important; }
        .mb-0 { margin-bottom: 0 !important; }
        .me-2 { margin-right: 0.5rem !important; }
        .ms-2 { margin-left: 0.5rem !important; }
        .text-muted { color: #6c757d !important; }
        .text-center { text-align: center !important; }
        .text-white { color: #fff !important; }
        .text-decoration-underline { text-decoration: underline !important; }
        .bg-light { background-color: #f8f9fa !important; }
        .py-4 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
        .h-100 { height: 100% !important; }
        .d-flex { display: flex !important; }
        .align-items-center { align-items: center !important; }
        .form-control {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #fff;
            background-image: none;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .form-control:focus {
            color: #212529;
            background-color: #fff;
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        /* ページネーション */
        .pagination {
            display: flex;
            padding-left: 0;
            list-style: none;
            border-radius: 0;
            gap: 5px;
        }

        .justify-content-center {
            justify-content: center !important;
        }

        .page-item {
            position: relative;
            display: block;
        }

        .page-item:first-child .page-link,
        .page-item:last-child .page-link {
            border-radius: 8px;
        }

        .page-item.active .page-link {
            z-index: 3;
            color: #fff;
            background-color: #daa520;
            border: 2px solid #daa520;
        }

        .page-item.disabled .page-link {
            color: #adb5bd;
            pointer-events: none;
            background-color: #f8f9fa;
            border: 2px solid #e9ecef;
        }

        .page-link {
            position: relative;
            display: block;
            padding: 0.75rem 1rem;
            margin: 0;
            line-height: 1.2;
            color: #6c757d;
            text-decoration: none;
            background-color: #fff;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-weight: 500;
            min-width: 44px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .page-link:hover {
            z-index: 2;
            color: #b8860b;
            text-decoration: none;
            background-color: #fef9e7;
            border-color: #daa520;
        }

        .page-link:focus {
            z-index: 3;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.2);
        }

        /* ページネーション全体のコンテナ */
        nav[aria-label="ページネーション"] {
            margin-top: 3rem;
            margin-bottom: 2rem;
        }

        /* ボタン */
        .btn {
            display: inline-block;
            font-weight: 400;
            color: #212529;
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            background-color: transparent;
            border: 1px solid transparent;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            line-height: 1.5;
            border-radius: 0.25rem;
            text-decoration: none;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .btn:hover {
            color: #212529;
            text-decoration: none;
        }

        .btn:focus {
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .btn-success {
            color: #fff;
            background-color: #198754;
            border-color: #198754;
        }

        .btn-success:hover {
            color: #fff;
            background-color: #157347;
            border-color: #146c43;
        }

        .btn-primary {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-primary:hover {
            color: #fff;
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
        }

        .btn-outline-secondary:hover {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-outline-light {
            color: #f8f9fa;
            border-color: #f8f9fa;
        }

        .btn-outline-light:hover {
            color: #000;
            background-color: #f8f9fa;
            border-color: #f8f9fa;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }

        /* フッター */
        .align-items-center {
            align-items: center !important;
        }

        .col-md-8, .col-md-4, .col-md-12 {
            position: relative;
            width: 100%;
            min-height: 1px;
            padding-left: 15px;
            padding-right: 15px;
        }

        @media (min-width: 768px) {
            .col-md-8 {
                flex: 0 0 66.666667%;
                max-width: 66.666667%;
            }
            .col-md-4 {
                flex: 0 0 33.333333%;
                max-width: 33.333333%;
            }
            .col-md-12 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            .text-md-end {
                text-align: right !important;
            }
        }

        /* レスポンシブ調整 */
        @media (max-width: 768px) {
            .material-image {
                height: auto;
                min-height: 200px;
                max-height: 250px;
            }
            .navbar-brand {
                font-size: 1.5rem;
            }
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            /* スマホでのカラムパディング調整 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                padding-left: 10px;
                padding-right: 10px;
            }
            .row {
                margin-left: -10px;
                margin-right: -10px;
            }
        }

        @media (max-width: 576px) {
            .material-image {
                min-height: 180px;
                max-height: 200px;
            }
            .card-body {
                padding: 0.75rem;
            }
            .card-title {
                font-size: 1rem;
            }
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            /* より小さなスマホでのカラムパディング調整 */
            .col-6, .col-md-4, .col-lg-3, .col-xl-2, .col-xxl-2 {
                padding-left: 8px;
                padding-right: 8px;
            }
            .row {
                margin-left: -8px;
                margin-right: -8px;
            }
        }

        /* GDPR Cookie Banner のスタイル */
        #gdpr-banner {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            right: 0 !important;
            background-color: #212529 !important;
            color: #ffffff !important;
            padding: 1rem !important;
            z-index: 1050 !important;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3) !important;
        }
        
        #gdpr-banner.hidden,
        .gdpr-cookie-banner.hidden {
            display: none !important;
        }
        
        .gdpr-text {
            font-size: 0.9rem !important;
            line-height: 1.4 !important;
            color: #ffffff !important;
        }
        
        .gdpr-text a {
            color: #ffffff !important;
            text-decoration: underline !important;
        }
        
        .gdpr-text a:hover {
            color: #e9ecef !important;
        }
        
        .gdpr-buttons {
            margin-top: 1rem !important;
            display: flex !important;
            gap: 0.5rem !important;
            flex-wrap: wrap !important;
        }
        
        .gdpr-buttons .btn {
            flex: 0 0 auto !important;
            white-space: nowrap !important;
        }
        
        /* GDPR専用のボタンスタイル（より強力な優先度） */
        #gdpr-banner .btn-outline-light {
            color: #ffffff !important;
            border-color: #ffffff !important;
            background-color: transparent !important;
            border-width: 1px !important;
            border-style: solid !important;
        }

        #gdpr-banner .btn-outline-light:hover {
            color: #212529 !important;
            background-color: #ffffff !important;
            border-color: #ffffff !important;
        }

        #gdpr-banner .btn-success {
            color: #000000 !important;
            background-color: #ffffff !important;
            border-color: #ffffff !important;
        }

        #gdpr-banner .btn-success:hover {
            color: #000000 !important;
            background-color: #f8f9fa !important;
            border-color: #f8f9fa !important;
        }
        
        @media (min-width: 768px) {
            .gdpr-buttons {
                margin-top: 0 !important;
                justify-content: flex-end !important;
            }
        }
        
        @media (max-width: 767px) {
            .gdpr-buttons {
                justify-content: center !important;
            }
        }
        
        /* YouTubeアイコンのスタイル */
        .youtube-icon {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            cursor: pointer;
            z-index: 10;
            transition: transform 0.2s ease, opacity 0.2s ease, background-color 0.2s ease;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
            opacity: 0.8;
            will-change: transform, opacity, background-color;
        }
        
        .youtube-icon:hover {
            background: rgba(0, 0, 0, 0.8);
            opacity: 1;
            transform: scale(1.05);
        }

        .youtube-icon::before {
            content: '▶';
            font-size: 10px;
        }
        
        /* YouTube動画ポップアップのスタイル */
        .youtube-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .youtube-modal.show {
            display: flex;
        }
        
        .youtube-modal-content {
            position: relative;
            width: 90%;
            max-width: 800px;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .youtube-modal iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .youtube-modal-close {
            position: absolute;
            top: -40px;
            right: 0;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
        }
        
        .youtube-modal-close:hover {
            color: #ccc;
        }

        /* 検索フォームのスタイル */
        .search-form {
            background-color: #fef9e7;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #f0e68c;
            margin-bottom: 2rem;
        }

        .search-form form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid #f0e68c;
            border-radius: 8px;
            font-size: 1rem;
            background-color: #fff;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            border-color: #daa520;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.1);
        }

        .search-input::placeholder {
            color: #adb5bd;
        }

        .search-button {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            white-space: nowrap;
            background-color: #daa520;
            border: 2px solid #daa520;
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .search-button:hover {
            background-color: #b8860b;
            border-color: #b8860b;
            color: #fff;
        }

        .search-button:focus {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.2);
        }

        /* クリアボタンのスタイル */
        .search-form .btn-secondary {
            background-color: #6c757d;
            border: 2px solid #6c757d;
            border-radius: 8px;
            color: #fff;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .search-form .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
            color: #fff;
        }

        /* レスポンシブ対応 */
        @media (max-width: 576px) {
            .search-form {
                padding: 1.25rem;
                border-radius: 10px;
            }
            
            .search-form form {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }
            
            .search-input {
                margin-bottom: 0;
            }
            
            .search-button,
            .search-form .btn-secondary {
                width: 100%;
            }
        }

        /* フッターのスタイル */
        .footer-custom {
            background-color: #fef9e7 !important;
        }

        /* フッター文字色の改善（コントラスト対応） */
        .footer-custom .footer-text {
            color: #1a1a1a !important;
        }

        .footer-custom .footer-text:hover {
            color: #000000 !important;
        }
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) - GDPR対応 -->
    <script>
    // GDPR同意状況をチェックしてnoscript GTMを条件付き表示
    (function() {
        function getGdprConsent() {
            try {
                return localStorage.getItem('gdpr_consent_v1');
            } catch (e) {
                return null;
            }
        }
        
        const consent = getGdprConsent();
        if (consent === 'accepted') {
            // 同意済みの場合はnoscript GTMを挿入
            document.write('<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-579HN546" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>');
        }
    })();
    </script>
    <!-- End Google Tag Manager (noscript) -->
    
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="/">maruttoart</a>
        </div>
    </nav>

    <div class="container mt-4" id="materials">
        <div class="row">
            <div class="col-12">
                <?php if (!empty($search)): ?>
                    <h1 class="mb-2">検索結果: "<?= h($search) ?>"</h1>
                    <p class="text-muted mb-4">
                        <?= number_format($totalItems) ?>件中 
                        <?= number_format(($page - 1) * $perPage + 1) ?>-<?= number_format(min($page * $perPage, $totalItems)) ?>件目を表示 
                        (<?= $page ?>/<?= $totalPages ?>ページ)
                    </p>
                <?php else: ?>
                    <h1 class="mb-2">無料で使えるかわいい水彩イラスト素材集</h1>
                    <p class="text-muted mb-4">
                        全<?= number_format($totalItems) ?>件中 
                        <?= number_format(($page - 1) * $perPage + 1) ?>-<?= number_format(min($page * $perPage, $totalItems)) ?>件目を表示 
                        (<?= $page ?>/<?= $totalPages ?>ページ)
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- 検索フォーム -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="search-form">
                    <form method="GET" action="/list.php" class="d-flex align-items-center">
                        <input type="text" 
                               name="search" 
                               value="<?= h($search) ?>" 
                               placeholder="素材を検索（例：猫、花、食べ物など）" 
                               class="search-input form-control me-2">
                        <button type="submit" class="search-button btn btn-primary">検索</button>
                        <?php if (!empty($search)): ?>
                            <a href="/list.php" class="btn btn-outline-secondary ms-2">クリア</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="row">
            <?php foreach ($materials as $material): ?>
            <div class="col-6 col-md-4 col-lg-3 col-xl-2 col-xxl-2 mb-4">
                <?php if (!empty($material['category_slug'])): ?>
                    <a href="/<?= h($material['category_slug']) ?>/<?= h($material['slug']) ?>/" class="card material-card h-100" role="button" tabindex="0" aria-label="<?= h($material['title']) ?>の詳細を見る">
                <?php else: ?>
                    <a href="/detail/<?= h($material['slug']) ?>" class="card material-card h-100" role="button" tabindex="0" aria-label="<?= h($material['title']) ?>の詳細を見る">
                <?php endif; ?>
                    <?php
                    // レスポンシブ画像の設定
                    $smallImage = $material['webp_small_path'] ?? $material['image_path'];
                    $mediumImage = $material['webp_medium_path'] ?? $material['image_path'];
                    ?>
                    <picture>
                        <!-- スマホ: 180x180のWebP画像 -->
                        <source media="(max-width: 768px)" srcset="/<?= h($smallImage) ?>" type="image/webp">
                        <!-- PC: 300x300のWebP画像 -->
                        <source media="(min-width: 769px)" srcset="/<?= h($mediumImage) ?>" type="image/webp">
                        <!-- フォールバック -->
                        <img src="/<?= h($material['image_path']) ?>" 
                             class="material-image" 
                             alt="<?= h($material['title']) ?>のイラスト"
                             loading="lazy"
                             decoding="async">
                    </picture>
                    
                    <!-- YouTubeアイコン -->
                    <?php 
                    // 動画が公開されているかチェック
                    $showVideo = !empty($material['youtube_url']);
                    if (!empty($material['video_publish_date'])) {
                        $publishDateTime = new DateTime($material['video_publish_date']);
                        $now = new DateTime();
                        $showVideo = $showVideo && ($now >= $publishDateTime);
                    }
                    // video_publish_dateが空の場合は、youtube_urlがあれば表示
                    ?>
                    <?php if ($showVideo): ?>
                        <div class="youtube-icon" 
                             onclick="openYouTubeModal(event, '<?= h($material['youtube_url']) ?>', '<?= h($material['title']) ?>')"
                             title="動画を見る">
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <h3 class="card-title"><?= h($material['title']) ?></h3>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ページネーション -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="ページネーション" class="mt-5">
            <ul class="pagination justify-content-center">
                <!-- 前のページ -->
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" aria-label="前のページ">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link" aria-label="前のページ">
                            <span aria-hidden="true">&laquo;</span>
                        </span>
                    </li>
                <?php endif; ?>

                <!-- ページ番号 -->
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                // 最初のページを表示
                if ($startPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">1</a>
                    </li>
                    <?php if ($startPage > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif;
                endif;

                // 現在のページ周辺を表示
                for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <?php if ($i == $page): ?>
                            <span class="page-link"><?= $i ?></span>
                        <?php else: ?>
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                        <?php endif; ?>
                    </li>
                <?php endfor;

                // 最後のページを表示
                if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $totalPages ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>"><?= $totalPages ?></a>
                    </li>
                <?php endif; ?>

                <!-- 次のページ -->
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" aria-label="次のページ">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="page-item disabled">
                        <span class="page-link" aria-label="次のページ">
                            <span aria-hidden="true">&raquo;</span>
                        </span>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <?php if (empty($materials)): ?>
        <div class="row">
            <div class="col-12 text-center">
                <p class="text-muted">
                    <?php if (!empty($search)): ?>
                        「<?= h($search) ?>」に該当する素材が見つかりませんでした。
                    <?php else: ?>
                        素材が見つかりませんでした。
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- GDPR Cookie Banner (CDN対応・セッション不使用) -->
    <div id="gdpr-banner" class="gdpr-cookie-banner hidden" style="position: fixed; bottom: 0; left: 0; right: 0; background-color: #212529; color: #ffffff; padding: 1rem; z-index: 1050; box-shadow: 0 -2px 10px rgba(0,0,0,0.3);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 col-md-8">
                    <div class="gdpr-text" style="color: #ffffff;">
                        当サイトではサイトの利便性向上のためCookieを使用しています。詳細は
                        <a href="/privacy-policy.php" class="text-white text-decoration-underline" style="color: #ffffff; text-decoration: underline;">プライバシーポリシー</a>
                        をご確認ください。
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="gdpr-buttons text-md-end" style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button id="gdpr-accept" class="btn btn-success btn-sm" style="color: #000000; background-color: #ffffff; border-color: #ffffff;">同意する</button>
                        <button id="gdpr-decline" class="btn btn-outline-light btn-sm" style="color: #ffffff; border-color: #ffffff; background-color: transparent;">拒否する</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer-custom mt-5 py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                </div>
                <div class="col-md-4 text-md-end">
                </div>
            </div>
            <div class="row align-items-center">
                <div class="col-md-12">
                    <p class="footer-text mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- YouTubeモーダル -->
    <div id="youtube-modal" class="youtube-modal">
        <div class="youtube-modal-content">
            <button class="youtube-modal-close" onclick="closeYouTubeModal()">&times;</button>
            <iframe id="youtube-iframe" src="" allowfullscreen></iframe>
        </div>
    </div>

    <!-- GDPR Cookie Consent Script (CDN対応・localStorage使用) -->
    <script>
    // GDPR Cookie Consent (セッション・Cookie不使用版)
    (function() {
        const GDPR_KEY = 'gdpr_consent_v1';
        let isInitialized = false;
        
        // 初期化関数
        function initGDPR() {
            if (isInitialized) return;
            isInitialized = true;
            
            const banner = document.getElementById('gdpr-banner');
            const acceptBtn = document.getElementById('gdpr-accept');
            const declineBtn = document.getElementById('gdpr-decline');
            
            console.log('GDPR initialization started');
            console.log('Banner element:', banner);
            console.log('Accept button:', acceptBtn);
            console.log('Decline button:', declineBtn);
            
            if (!banner || !acceptBtn || !declineBtn) {
                console.error('GDPR elements not found');
                return;
            }
            
            // localStorage から同意状況をチェック
            function getGdprConsent() {
                try {
                    return localStorage.getItem(GDPR_KEY);
                } catch (e) {
                    console.warn('localStorage not available:', e);
                    return null;
                }
            }
            
            // 同意状況を保存
            function setGdprConsent(value) {
                try {
                    localStorage.setItem(GDPR_KEY, value);
                    console.log('GDPR consent saved:', value);
                    return true;
                } catch (e) {
                    console.warn('localStorage save failed:', e);
                    return false;
                }
            }
            
            // バナーを表示
            function showBanner() {
                if (banner) {
                    banner.classList.remove('hidden');
                    console.log('GDPR banner shown');
                }
            }
            
            // バナーを非表示
            function hideBanner() {
                if (banner) {
                    banner.classList.add('hidden');
                    console.log('GDPR banner hidden');
                }
            }
            
            // 同意処理
            function acceptConsent() {
                console.log('Accept consent clicked');
                setGdprConsent('accepted');
                hideBanner();
                enableAnalytics();
                
                // GTM読み込みイベントを発火
                const event = new CustomEvent('gdpr-consent-accepted');
                window.dispatchEvent(event);
            }
            
            // 拒否処理
            function declineConsent() {
                console.log('Decline consent clicked');
                setGdprConsent('declined');
                hideBanner();
                disableAnalytics();
            }
            
            // アナリティクス有効化（プレースホルダー）
            function enableAnalytics() {
                console.log('Analytics enabled');
                
                // GTMが未読み込みの場合は読み込み
                if (!window.gtmLoaded) {
                    const event = new CustomEvent('gdpr-consent-accepted');
                    window.dispatchEvent(event);
                }
            }
            
            // アナリティクス無効化（プレースホルダー）
            function disableAnalytics() {
                console.log('Analytics disabled');
                // アナリティクス無効化のコードをここに追加
            }
            
            // イベントリスナーを設定
            acceptBtn.addEventListener('click', acceptConsent);
            declineBtn.addEventListener('click', declineConsent);
            console.log('GDPR event listeners attached');
            
            // 同意状況をチェックして初期化
            const consent = getGdprConsent();
            console.log('Current GDPR consent:', consent);
            
            if (consent === null) {
                // 未設定の場合はバナーを表示
                console.log('No consent found, showing banner');
                showBanner();
            } else if (consent === 'accepted') {
                // 同意済みの場合はアナリティクスを有効化
                console.log('Consent already accepted');
                hideBanner();
                enableAnalytics();
            } else if (consent === 'declined') {
                // 拒否済みの場合はアナリティクスを無効化
                console.log('Consent declined');
                hideBanner();
                disableAnalytics();
            }
        }
        
        // 複数の初期化方法を試行
        function tryInit() {
            console.log('Document ready state:', document.readyState);
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initGDPR);
            } else {
                // DOMが既に読み込まれている場合は即座に実行
                setTimeout(initGDPR, 0);
            }
            
            // フォールバック: window.onloadでも試行
            window.addEventListener('load', function() {
                if (!isInitialized) {
                    console.log('Fallback initialization on window load');
                    initGDPR();
                }
            });
        }
        
        tryInit();
    })();
    
    // カードのキーボードナビゲーション対応
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.material-card');
        
        cards.forEach(function(card) {
            card.addEventListener('keydown', function(e) {
                // Enterキーまたはスペースキーで詳細ページに遷移
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    window.location.href = card.href;
                }
            });
        });
    });
    
    // YouTube動画ポップアップ機能
    function openYouTubeModal(event, youtubeUrl, title) {
        event.preventDefault();
        event.stopPropagation();
        
        const modal = document.getElementById('youtube-modal');
        const iframe = document.getElementById('youtube-iframe');
        
        // YouTube URLをembed形式に変換
        let embedUrl = '';
        if (youtubeUrl.includes('youtube.com/watch?v=')) {
            const videoId = youtubeUrl.split('v=')[1].split('&')[0];
            embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
        } else if (youtubeUrl.includes('youtu.be/')) {
            const videoId = youtubeUrl.split('/').pop().split('?')[0];
            embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
        } else if (youtubeUrl.includes('youtube.com/embed/')) {
            embedUrl = youtubeUrl + (youtubeUrl.includes('?') ? '&' : '?') + 'autoplay=1';
        } else {
            embedUrl = youtubeUrl;
        }
        
        // リフロー最適化: 全ての変更をバッチ処理
        requestAnimationFrame(() => {
            iframe.src = embedUrl;
            modal.classList.add('show');
        });
        
        // Escキーでモーダルを閉じる
        document.addEventListener('keydown', function escKeyHandler(e) {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', escKeyHandler);
                closeYouTubeModal();
            }
        });
        
        // モーダル背景クリックで閉じる
        modal.addEventListener('click', function modalClickHandler(e) {
            if (e.target === modal) {
                modal.removeEventListener('click', modalClickHandler);
                closeYouTubeModal();
            }
        });
    }
    
    function closeYouTubeModal() {
        const modal = document.getElementById('youtube-modal');
        const iframe = document.getElementById('youtube-iframe');
        
        // リフロー最適化: 全ての変更をバッチ処理
        requestAnimationFrame(() => {
            modal.classList.remove('show');
            iframe.src = '';
        });
    }

    // Lazyload画像の読み込み完了処理
    document.addEventListener('DOMContentLoaded', function() {
        const lazyImages = document.querySelectorAll('img[loading="lazy"]');
        
        lazyImages.forEach(img => {
            // 画像が既に読み込まれている場合
            if (img.complete && img.naturalHeight !== 0) {
                img.classList.add('loaded');
            } else {
                // 画像の読み込み完了を待機
                img.addEventListener('load', function() {
                    this.classList.add('loaded');
                });
                
                // 読み込みエラーの場合
                img.addEventListener('error', function() {
                    this.classList.add('loaded'); // エラーでも表示状態にする
                });
            }
        });
    });
    </script>
</body>
</html>
