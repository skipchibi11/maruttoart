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
    $whereClause .= " AND (title LIKE ? OR description LIKE ? OR search_keywords LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
    $countParams = $params;
}

// 総件数を取得
$countSql = "SELECT COUNT(*) FROM materials " . $whereClause;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// データを取得
$sql = "SELECT * FROM materials " . $whereClause . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>無料のかわいい水彩イラスト素材集｜maruttoart（商用利用OK）</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #ffffff;
        }
        .material-card {
            transition: transform 0.2s;
        }
        .material-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .header-logo {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        .material-image {
            width: 100%;
            aspect-ratio: 1 / 1; /* 正方形を維持 */
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        
        /* スマホ用のレスポンシブ調整 */
        @media (max-width: 768px) {
            .material-card {
                margin-bottom: 1rem;
            }
            .material-image {
                height: auto;
                min-height: 200px;
                max-height: 250px;
            }
            .header-logo {
                font-size: 1.5rem;
            }
            .container {
                padding-left: 15px;
                padding-right: 15px;
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
            .card-text {
                font-size: 0.875rem;
            }
        }
        
        /* GDPR Cookie Banner のスタイル */
        #gdpr-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #343a40;
            color: white;
            padding: 1rem;
            z-index: 1050;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.3);
        }
        
        #gdpr-banner.hidden {
            display: none;
        }
        
        .gdpr-text {
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .gdpr-buttons {
            margin-top: 1rem;
        }
        
        .gdpr-buttons .btn {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        @media (min-width: 768px) {
            .gdpr-buttons {
                margin-top: 0;
            }
        }
    </style>
</head>
</head>
<body>
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand header-logo" href="/">maruttoart</a>
            <div class="navbar-nav ms-auto d-flex align-items-center">
                <!-- gTranslate言語切り替え -->
                <div class="gtranslate_wrapper me-3"></div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
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

        <div class="row">
            <?php foreach ($materials as $material): ?>
            <div class="col-md-4 col-lg-3 col-6 mb-4">
                <div class="card material-card h-100">
                    <img src="<?= h($material['webp_path']) ?>" class="material-image" alt="<?= h($material['title']) ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?= h($material['title']) ?></h5>
                        <p class="card-text"><?= h(substr($material['description'], 0, 100)) ?>...</p>
                        <a href="/detail/<?= h($material['slug']) ?>" class="btn btn-primary">詳細を見る</a>
                    </div>
                </div>
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
    <div id="gdpr-banner" class="hidden">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="gdpr-text">
                        当サイトではサイトの利便性向上のためCookieを使用しています。詳細は
                        <a href="/privacy-policy.php" class="text-white text-decoration-underline">プライバシーポリシー</a>
                        をご確認ください。
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="gdpr-buttons text-md-end">
                        <button id="gdpr-accept" class="btn btn-success btn-sm">同意する</button>
                        <button id="gdpr-decline" class="btn btn-outline-light btn-sm">拒否する</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="text-muted mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <?php
    // 既存のGTranslate機能は削除
    // echo renderGTranslate();
    ?>

    <script>window.gtranslateSettings = {"default_language":"ja","native_language_names":true,"detect_browser_language":true,"url_structure":"sub_domain","languages":["ja","en","fr","es","nl"],"wrapper_selector":".gtranslate_wrapper","alt_flags":{"en":"usa"}}</script>
    <script src="https://cdn.gtranslate.net/widgets/latest/flags.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- GDPR Cookie Consent Script (CDN対応・localStorage使用) -->
    <script>
    // GDPR Cookie Consent (セッション・Cookie不使用版)
    (function() {
        const GDPR_KEY = 'gdpr_consent_v1';
        const banner = document.getElementById('gdpr-banner');
        const acceptBtn = document.getElementById('gdpr-accept');
        const declineBtn = document.getElementById('gdpr-decline');
        
        // localStorage から同意状況をチェック
        function getGdprConsent() {
            try {
                return localStorage.getItem(GDPR_KEY);
            } catch (e) {
                return null; // localStorage が使用できない場合
            }
        }
        
        // 同意状況を保存
        function setGdprConsent(value) {
            try {
                localStorage.setItem(GDPR_KEY, value);
                return true;
            } catch (e) {
                return false; // localStorage が使用できない場合
            }
        }
        
        // バナーを表示
        function showBanner() {
            if (banner) {
                banner.classList.remove('hidden');
            }
        }
        
        // バナーを非表示
        function hideBanner() {
            if (banner) {
                banner.classList.add('hidden');
            }
        }
        
        // 同意処理
        function acceptConsent() {
            setGdprConsent('accepted');
            hideBanner();
            // 必要に応じて Google Analytics などを有効化
            enableAnalytics();
        }
        
        // 拒否処理
        function declineConsent() {
            setGdprConsent('declined');
            hideBanner();
            // アナリティクスを無効化
            disableAnalytics();
        }
        
        // アナリティクス有効化（プレースホルダー）
        function enableAnalytics() {
            console.log('Analytics enabled (placeholder)');
            // ここに Google Analytics などの初期化コードを追加
        }
        
        // アナリティクス無効化（プレースホルダー）
        function disableAnalytics() {
            console.log('Analytics disabled (placeholder)');
            // ここにアナリティクス無効化のコードを追加
        }
        
        // 初期化
        function init() {
            const consent = getGdprConsent();
            
            if (consent === null) {
                // 未設定の場合はバナーを表示
                showBanner();
            } else if (consent === 'accepted') {
                // 同意済みの場合はアナリティクスを有効化
                enableAnalytics();
            } else if (consent === 'declined') {
                // 拒否済みの場合はアナリティクスを無効化
                disableAnalytics();
            }
        }
        
        // イベントリスナーを設定
        if (acceptBtn) {
            acceptBtn.addEventListener('click', acceptConsent);
        }
        
        if (declineBtn) {
            declineBtn.addEventListener('click', declineConsent);
        }
        
        // DOMContentLoaded で初期化
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>
</body>
</html>
