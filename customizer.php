<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(3600, 7200); // 1時間 / CDN 2時間

$pdo = getDB();



// 検索処理（JavaScriptからのエンコードされた文字列をデコード）
$search = isset($_GET['search']) ? urldecode($_GET['search']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// カテゴリ一覧を取得（参考用）
$categories = $pdo->query("SELECT * FROM categories ORDER BY categories.title ASC")->fetchAll();

// 検索条件の構築（list.phpと完全に同じ方式）
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
$total_count = $countStmt->fetchColumn();

// 素材を取得
$sql = "SELECT m.*, c.slug as category_slug FROM materials m 
        LEFT JOIN categories c ON m.category_id = c.id " .
        $whereClause . " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
// 新しいパラメータ配列を作成してLIMITとOFFSETを追加
$finalParams = $params; // 既存の検索パラメータをコピー
$finalParams[] = $limit;
$finalParams[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($finalParams);
$materials = $stmt->fetchAll();

$total_pages = ceil($total_count / $limit);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <!-- Google Tag Manager - GDPR対応 -->
    <script>
    // LocalStorageアクセス用のヘルパー関数
    function getGdprConsent() {
        try {
            return localStorage.getItem('gdpr_consent_v1');
        } catch (e) {
            return null;
        }
    }

    // GTM初期化関数（GDPR同意時に呼ばれる）
    window.initGTM = function() {
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-579HN546');
    };

    // 即時実行関数でGDPR対応GTMロード
    (function() {
        const consent = getGdprConsent();
        
        function loadGTM() {
            // GTMがまだロードされていない場合のみロード
            if (typeof window.google_tag_manager === 'undefined') {
                window.initGTM();
            }
        }

        // 既に同意済みの場合は即座にロード
        if (consent === 'accepted') {
            loadGTM();
        }

        // GDPR同意イベントを監視（将来の同意に対応）
        window.addEventListener('gdpr-consent-accepted', loadGTM);
    })();
    </script>
    <!-- End Google Tag Manager -->
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>素材を並べて自分のお気に入りを作ろう - ミニマルなフリーイラスト素材（商用利用OK）｜marutto.art</title>
    <meta name="description" content="フリーイラスト素材を組み合わせてオリジナル作品を作成できます。商用利用OK。">
    <link rel="icon" href="/favicon.ico">
    
    <!-- Alternate language tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/customizer.php" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/customizer.php" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/customizer.php" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/customizer.php" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/customizer.php" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/customizer.php" />
    
    <!-- Canonical tag -->
    <link rel="canonical" href="https://marutto.art/customizer.php">
    
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
            color: #222;
        }

        /* コンテナシステム */
        .container {
            width: 100%;
            max-width: 1140px;
            margin: 0 auto;
            padding-left: 15px;
            padding-right: 15px;
        }

        /* Bootstrap風のグリッドシステム */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-left: -15px;
            margin-right: -15px;
        }

        .col-12 {
            flex: 0 0 100%;
            max-width: 100%;
            padding-left: 15px;
            padding-right: 15px;
        }

        /* マージンとパディング */
        .mt-4 { margin-top: 1.5rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-4 { margin-bottom: 1.5rem; }
        .me-2 { margin-right: 0.5rem; }
        .ms-2 { margin-left: 0.5rem; }

        /* テキストスタイル */
        .text-muted {
            color: #6c757d;
        }

        /* フレックスボックス */
        .d-flex {
            display: flex;
        }

        .align-items-center {
            align-items: center;
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

        .navbar .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
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

        /* SNSリンク */
        .social-links {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
        }

        .social-link:hover {
            transform: translateY(-2px);
            text-decoration: none;
        }

        .social-link.twitter {
            color: #1da1f2;
        }

        .social-link.twitter:hover {
            background-color: #1da1f2;
            color: white;
            border-color: #1da1f2;
        }

        .social-link.youtube {
            color: #ff0000;
        }

        .social-link.youtube:hover {
            background-color: #ff0000;
            color: white;
            border-color: #ff0000;
        }

        .social-icon {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        /* フッター */
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

        /* プライバシーポリシーリンクのスタイル */
        .footer-custom a.footer-text {
            transition: color 0.2s ease;
        }

        .footer-custom a.footer-text:hover {
            color: #0d6efd !important;
            text-decoration: underline !important;
        }

        /* 言語切替のスタイル */
        .language-switcher {
            margin-top: 10px;
        }

        .language-switcher .language-links {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .language-switcher .language-link {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.9rem;
            padding: 2px 4px;
            border-radius: 3px;
            transition: all 0.2s ease;
        }

        .language-switcher .language-link:hover {
            color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.1);
        }

        .language-switcher .language-link.current {
            color: #0d6efd;
            font-weight: 600;
        }

        .language-switcher .separator {
            color: #dee2e6;
            margin: 0 2px;
        }

        /* レスポンシブコンテナ幅調整 */
        /* 1400px以上: コンテナ幅拡張 */
        @media (min-width: 1400px) {
            .container {
                max-width: 1320px;
            }
        }

        /* 1600px以上: さらに大きな画面向け調整 */
        @media (min-width: 1600px) {
            .container {
                max-width: 1500px;
            }
        }

        /* 1800px以上: 超大型画面向け調整 */
        @media (min-width: 1800px) {
            .container {
                max-width: 1680px;
            }
        }

        /* 2000px以上: 4K画面等の超大型画面 */
        @media (min-width: 2000px) {
            .container {
                max-width: 1860px;
            }
        }

        @media (max-width: 576px) {
            .language-switcher .language-links {
                gap: 6px;
            }
            
            .language-switcher .language-link {
                font-size: 0.85rem;
            }
        }

        /* レスポンシブ調整 */
        @media (max-width: 768px) {
            .navbar-brand {
                font-size: 1.5rem;
            }
            
            .social-links {
                gap: 10px;
            }
            
            .social-link {
                width: 35px;
                height: 35px;
            }
            
            .social-icon {
                width: 18px;
                height: 18px;
            }
        }

        /* ユーティリティクラス */
        .mt-5 { margin-top: 3rem !important; }
        .mb-2 { margin-bottom: 0.5rem !important; }
        .mb-0 { margin-bottom: 0 !important; }
        .py-4 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
        .text-center { text-align: center !important; }
        .text-decoration-none { text-decoration: none !important; }
        .me-3 { margin-right: 1rem !important; }

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

        /* ボタンスタイル */
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

        /* 検索フォーム */


        /* 検索フォームのスタイル */
        .search-form {
            background-color: #ffffff;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e9ecef;
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
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            background-color: #fff;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            border-color: #0d6efd;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        .search-input::placeholder {
            color: #adb5bd;
        }

        .form-control {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            background-color: #fff;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #0d6efd;
            outline: 0;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
        }

        .form-control::placeholder {
            color: #adb5bd;
        }

        .search-button {
            background-color: #ffffff;
            color: #444;
            border: 2px solid #ccc;
            border-radius: 12px;
            padding: 0.75em 2em;
            font-size: 1rem;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease-in-out;
            white-space: nowrap;
            cursor: pointer;
        }

        .search-button:hover {
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
        }

        .search-button:focus {
            outline: 0;
            box-shadow: 0 0 0 3px rgba(204, 204, 204, 0.3);
        }

        /* クリアボタンのスタイル */
        .search-form .btn-outline-secondary {
            background-color: #ffffff;
            color: #444;
            border: 2px solid #ccc;
            border-radius: 12px;
            padding: 0.75em 1.25em;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
        }

        .search-form .btn-outline-secondary:hover {
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
        }

        /* Bootstrap風のボタンスタイル */
        .btn {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            margin-bottom: 0;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            text-align: center;
            text-decoration: none;
            vertical-align: middle;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 0.25rem;
            transition: all 0.15s ease-in-out;
        }

        /* フォームコントロール */
        .form-control {
            display: block;
            width: 100%;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            color: #212529;
            background-color: #fff;
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.2s;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        /* 素材グリッド */
        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .material-item {
            background: #F9F5E9;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .material-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .material-item img {
            width: 100%;
            height: 120px;
            object-fit: contain;
            flex-shrink: 0;
        }

        /* ポップアップスタイル */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .popup-content {
            background: white;
            border-radius: 8px;
            max-width: 400px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .popup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        .popup-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }

        .popup-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .popup-close:hover {
            color: #333;
        }

        .popup-body {
            padding: 1rem;
            text-align: center;
        }

        .popup-body img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .popup-body p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .popup-footer {
            display: flex;
            gap: 0.5rem;
            padding: 1rem;
            border-top: 1px solid #eee;
        }

        .popup-btn {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.2s;
        }

        .popup-btn.cancel {
            background: #f8f9fa;
            color: #6c757d;
        }

        .popup-btn.cancel:hover {
            background: #e9ecef;
        }

        .popup-btn.add {
            background: #007bff;
            color: white;
        }

        .popup-btn.add:hover {
            background: #0056b3;
        }

        /* キャンバスセクション */
        .canvas-section {
            background-color: #fef9e7;
            padding: 2rem 0;
            margin-top: 3rem;
        }

        .canvas-container {
            display: flex;
            gap: 2rem;
            align-items: flex-start;
        }

        .canvas-area {
            flex: 1;
            text-align: center;
        }

        #canvas {
            border: 2px solid #ccc;
            background-color: white;
            max-width: 100%;
        }

        /* ダウンロードボタンエリア */
        .download-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
            margin-top: 1rem;
        }

        .download-buttons .search-button {
            width: 100%;
            max-width: 250px;
            white-space: nowrap;
            font-size: 0.9rem;
        }

        .canvas-controls {
            width: 300px;
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .control-group {
            margin-bottom: 1.5rem;
        }

        .control-group h4 {
            margin-bottom: 1rem;
            color: #333;
        }

        .canvas-layers {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }

        .layer-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem;
            border-bottom: 1px solid #e0e0e0;
            gap: 0.5rem;
        }

        .layer-preview {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border: 1px solid #ccc;
        }

        .layer-controls {
            display: flex;
            gap: 0.25rem;
        }

        .layer-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border: 1px solid #ccc;
            background: white;
            cursor: pointer;
            border-radius: 3px;
        }

        /* ページネーションボタン（list.phpと同じスタイル） */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .pagination-btn {
            background-color: #ffffff;
            color: #444;
            border: 2px solid #ccc;
            border-radius: 12px;
            padding: 0.75em 2em;
            font-size: 1rem;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }

        .pagination-btn:hover {
            background-color: #f5f5f5;
            border-color: #999;
            color: #444;
            text-decoration: none;
        }

        .pagination-btn:disabled {
            background-color: #f8f9fa;
            color: #ccc;
            border-color: #e0e0e0;
            cursor: not-allowed;
        }

        /* ローディングアニメーション */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* タッチ操作対応 */
        #canvas {
            touch-action: none; /* ブラウザのデフォルトタッチ動作を無効化 */
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            cursor: default; /* デフォルトカーソルを明示的に設定 */
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
            .search-form .btn-outline-secondary {
                width: 100%;
            }
        }

        /* スマートフォン用の改善 */
        @media (max-width: 768px) {
            
            .canvas-container {
                flex-direction: column;
            }
            
            .canvas-controls {
                width: 100%;
                margin-bottom: 1rem;
            }
            
            .materials-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }

            .download-buttons .search-button {
                font-size: 0.85rem;
                padding: 8px 12px;
                max-width: none;
            }
            
            .canvas-wrapper {
                overflow: visible; /* スマホでのタッチ操作を妨げない */
            }
            
            /* タッチターゲットのサイズを十分に確保 */
            .layer-btn {
                min-width: 44px;
                min-height: 44px;
            }
            
            .btn {
                min-height: 44px;
            }
        }
    </style>
</head>
<body>
    <!-- Google Tag Manager (noscript) - GDPR対応 -->
    <script>
    // グローバルGDPR同意チェック関数
    window.getGdprConsent = function() {
        try {
            return localStorage.getItem('gdpr_consent_v1');
        } catch (e) {
            return null;
        }
    };
    
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
            <div class="social-links">
                <a href="https://x.com/marutto_art" class="social-link twitter" target="_blank" rel="noopener noreferrer" title="X (Twitter)">
                    <svg class="social-icon" viewBox="0 0 24 24">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                </a>
                <a href="https://youtube.com/@marutto_art" class="social-link youtube" target="_blank" rel="noopener noreferrer" title="YouTube">
                    <svg class="social-icon" viewBox="0 0 24 24">
                        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                    </svg>
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4" id="materials">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-2">素材を並べて自分のお気に入りを作ろう</h1>
                <?php if (!empty($search)): ?>
                    <p class="text-muted mb-4">
                        "<?= h($search) ?>"の検索結果: <?= number_format($total_count) ?>件中 
                        <?= number_format(($page - 1) * $limit + 1) ?>-<?= number_format(min($page * $limit, $total_count)) ?>件目を表示 
                        (<?= $page ?>/<?= $total_pages ?>ページ)
                    </p>
                <?php else: ?>
                    <p class="text-muted mb-4">
                        お気に入りの素材を組み合わせてオリジナル作品を作成しましょう<br>
                        全<?= number_format($total_count) ?>件中 
                        <?= number_format(($page - 1) * $limit + 1) ?>-<?= number_format(min($page * $limit, $total_count)) ?>件目を表示 
                        (<?= $page ?>/<?= $total_pages ?>ページ)
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- 検索フォーム -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="search-form">
                    <form method="GET" action="" class="d-flex align-items-center">
                        <input type="text" 
                               name="search" 
                               value="<?= h($search) ?>" 
                               placeholder="素材を検索（例：猫、花、食べ物など）" 
                               class="search-input me-2">
                        <button type="submit" class="search-button">検索</button>
                        <?php if (!empty($search)): ?>
                            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary ms-2">クリア</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- 素材一覧 -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 style="margin-bottom: 1.5rem;">素材を選んで追加</h2>
            </div>
        </div>

        <div class="row" id="materialsContainer">
            <?php if (empty($materials)): ?>
                <div class="col-12">
                    <p style="text-align: center; color: #666; padding: 2rem;">
                        条件に合う素材が見つかりませんでした。
                    </p>
                </div>
            <?php else: ?>
                <div class="col-12">
                    <div class="materials-grid">
                        <?php foreach ($materials as $material): ?>
                            <div class="material-item" 
                                 onclick="showMaterialPopup('<?= h($material['image_path']) ?>', '<?= h($material['title']) ?>')">
                                <img src="/<?= h($material['image_path']) ?>" 
                                     alt="<?= h($material['title']) ?>"
                                     loading="lazy">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- ページネーション -->
        <div class="row mb-4" id="paginationContainer">
            <div class="col-12">
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                    <!-- 前のページ -->
                    <?php if ($page > 1): ?>
                        <a href="?search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" class="pagination-btn">
                            前へ
                        </a>
                    <?php endif; ?>

                    <!-- 次のページ -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" class="pagination-btn">
                            次へ
                        </a>
                    <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- キャンバスセクション -->
    <section class="canvas-section">
        <div class="container">
            <h2 style="margin-bottom: 1.5rem; text-align: center;">あなたのキャンバス</h2>
            
            <div class="canvas-container">
                <div class="canvas-area">
                    <canvas id="canvas" width="600" height="600"></canvas>
                    <div class="download-buttons">
                        <button class="search-button" onclick="clearCanvas()">キャンバスをクリア</button>
                        <button class="search-button" onclick="downloadCanvas()">背景色付きでダウンロード</button>
                        <button class="search-button" onclick="downloadCanvasTransparent()">透過背景でダウンロード</button>
                    </div>

                </div>
                
                <div class="canvas-controls">
                    <div class="control-group">
                        <h4>レイヤー管理</h4>
                        <div class="canvas-layers" id="layersList">
                            <div style="padding: 1rem; text-align: center; color: #666;">
                                素材を追加するとここに表示されます
                            </div>
                        </div>
                    </div>
                    
                    <div class="control-group">
                        <h4>キャンバス設定</h4>
                        <div style="margin-bottom: 1rem;">
                            <label for="canvasWidth">幅:</label>
                            <input type="number" id="canvasWidth" value="600" min="200" max="1200" 
                                   onchange="resizeCanvas()" style="width: 100%; padding: 0.5rem; margin-top: 0.25rem;">
                        </div>
                        <div>
                            <label for="canvasHeight">高さ:</label>
                            <input type="number" id="canvasHeight" value="600" min="200" max="1200" 
                                   onchange="resizeCanvas()" style="width: 100%; padding: 0.5rem; margin-top: 0.25rem;">
                        </div>
                    </div>
                    


                    <div class="control-group">
                        <h4>背景色</h4>
                        <input type="color" id="backgroundColor" value="#ffffff" 
                               onchange="setBackgroundColor()" style="width: 100%; height: 40px;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // キャンバス関連の変数
        const canvas = document.getElementById('canvas');
        const ctx = canvas.getContext('2d');
        let layers = [];
        let selectedLayer = null;
        let isDragging = false;
        let dragOffset = { x: 0, y: 0 };

        // 初期設定
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // ローカルストレージのキー（サーバー負荷なし）
        const STORAGE_KEY = 'marutto_customizer_canvas';

        // キャンバス状態をローカルストレージに保存
        function saveCanvasState() {
            const canvasState = {
                layers: layers.map(layer => ({
                    id: layer.id,
                    title: layer.title,
                    imageSrc: layer.image.src,
                    x: layer.x,
                    y: layer.y,
                    width: layer.width,
                    height: layer.height,
                    rotation: layer.rotation,
                    opacity: layer.opacity
                })),
                canvasWidth: canvas.width,
                canvasHeight: canvas.height,
                backgroundColor: document.getElementById('backgroundColor').value,
                timestamp: new Date().toISOString()
            };
            
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(canvasState));
            } catch (e) {
                // ローカル保存に失敗（サーバーには影響なし）
            }
        }

        // 選択ハンドルを描画（回転対応）
        function drawSelectionHandles(layer) {
            // タッチデバイス判定でハンドルサイズを調整
            const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            const handleSize = isTouchDevice ? 20 : 12;
            const borderWidth = 2;
            const rotateHandleDistance = isTouchDevice ? 40 : 30;
            
            ctx.save();
            
            // 素材の中心を基準に回転
            const centerX = layer.x + layer.width / 2;
            const centerY = layer.y + layer.height / 2;
            
            ctx.translate(centerX, centerY);
            ctx.rotate(layer.rotation * Math.PI / 180);
            ctx.translate(-centerX, -centerY);
            
            // 選択枠を描画（回転済み）
            ctx.strokeStyle = '#007bff';
            ctx.lineWidth = borderWidth;
            ctx.setLineDash([5, 5]);
            ctx.strokeRect(layer.x - borderWidth, layer.y - borderWidth, 
                          layer.width + borderWidth * 2, layer.height + borderWidth * 2);
            ctx.setLineDash([]);
            
            // リサイズハンドルを描画（四隅、回転済み）
            const resizeHandles = [
                { x: layer.x, y: layer.y, cursor: 'nw-resize' }, // 左上
                { x: layer.x + layer.width, y: layer.y, cursor: 'ne-resize' }, // 右上
                { x: layer.x, y: layer.y + layer.height, cursor: 'sw-resize' }, // 左下
                { x: layer.x + layer.width, y: layer.y + layer.height, cursor: 'se-resize' } // 右下
            ];
            
            ctx.fillStyle = '#ffffff';
            ctx.strokeStyle = '#007bff';
            ctx.lineWidth = 2;
            
            resizeHandles.forEach(handle => {
                ctx.fillRect(handle.x - handleSize/2, handle.y - handleSize/2, handleSize, handleSize);
                ctx.strokeRect(handle.x - handleSize/2, handle.y - handleSize/2, handleSize, handleSize);
            });
            
            // 回転ハンドルを描画（上中央から少し上、回転済み）
            const rotateX = centerX;
            const rotateY = layer.y - rotateHandleDistance;
            
            // 回転ハンドルへの線
            ctx.strokeStyle = '#007bff';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(centerX, layer.y);
            ctx.lineTo(rotateX, rotateY);
            ctx.stroke();
            
            // 回転ハンドル（円形）
            ctx.fillStyle = '#ffffff';
            ctx.strokeStyle = '#007bff';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(rotateX, rotateY, handleSize/2, 0, 2 * Math.PI);
            ctx.fill();
            ctx.stroke();
            
            ctx.restore();
        }
        
        // 点が回転した矩形内にあるかチェックする関数
        function isPointInRotatedRect(px, py, layer) {
            const centerX = layer.x + layer.width / 2;
            const centerY = layer.y + layer.height / 2;
            const rotation = -layer.rotation * Math.PI / 180; // 逆回転
            
            // 点を回転前の座標系に変換
            const dx = px - centerX;
            const dy = py - centerY;
            const rotatedX = centerX + (dx * Math.cos(rotation) - dy * Math.sin(rotation));
            const rotatedY = centerY + (dx * Math.sin(rotation) + dy * Math.cos(rotation));
            
            // 回転前の座標系で矩形内判定
            const isInside = rotatedX >= layer.x && rotatedX <= layer.x + layer.width &&
                           rotatedY >= layer.y && rotatedY <= layer.y + layer.height;
            
            return isInside;
        }

        // ハンドルの当たり判定（回転対応）
        function getHandleAtPosition(layer, x, y) {
            // タッチデバイス判定でハンドルサイズを調整
            const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
            const handleSize = isTouchDevice ? 20 : 12;
            const rotateHandleDistance = isTouchDevice ? 40 : 30;
            
            // 素材の中心を基準にマウス座標を逆回転
            const centerX = layer.x + layer.width / 2;
            const centerY = layer.y + layer.height / 2;
            const rotation = -layer.rotation * Math.PI / 180; // 逆回転
            
            // マウス座標を回転前の座標系に変換
            const dx = x - centerX;
            const dy = y - centerY;
            const rotatedX = centerX + (dx * Math.cos(rotation) - dy * Math.sin(rotation));
            const rotatedY = centerY + (dx * Math.sin(rotation) + dy * Math.cos(rotation));
            
            // 回転ハンドルのチェック（回転前の座標系で）
            const rotateX = layer.x + layer.width / 2;
            const rotateY = layer.y - rotateHandleDistance;
            const rotateDistance = Math.sqrt((rotatedX - rotateX) ** 2 + (rotatedY - rotateY) ** 2);
            if (rotateDistance <= handleSize/2) {
                return 'rotate';
            }
            
            // リサイズハンドルのチェック（回転前の座標系で）
            const resizeHandles = [
                { x: layer.x, y: layer.y, type: 'nw' },
                { x: layer.x + layer.width, y: layer.y, type: 'ne' },
                { x: layer.x, y: layer.y + layer.height, type: 'sw' },
                { x: layer.x + layer.width, y: layer.y + layer.height, type: 'se' }
            ];
            
            for (let handle of resizeHandles) {
                if (rotatedX >= handle.x - handleSize/2 && rotatedX <= handle.x + handleSize/2 &&
                    rotatedY >= handle.y - handleSize/2 && rotatedY <= handle.y + handleSize/2) {
                    return handle.type;
                }
            }
            return null;
        }

        // キャンバス状態をローカルストレージから復元
        function loadCanvasState() {
            try {
                const savedState = localStorage.getItem(STORAGE_KEY);
                if (!savedState) {
                    return false;
                }

                const canvasState = JSON.parse(savedState);

                
                // キャンバスサイズを復元
                if (canvasState.canvasWidth && canvasState.canvasHeight) {
                    document.getElementById('canvasWidth').value = canvasState.canvasWidth;
                    document.getElementById('canvasHeight').value = canvasState.canvasHeight;
                    canvas.width = canvasState.canvasWidth;
                    canvas.height = canvasState.canvasHeight;
                }
                
                // 背景色を復元
                if (canvasState.backgroundColor) {
                    document.getElementById('backgroundColor').value = canvasState.backgroundColor;
                }
                
                // レイヤーを復元
                if (canvasState.layers && canvasState.layers.length > 0) {
                    let loadedCount = 0;
                    const totalLayers = canvasState.layers.length;
                    
                    canvasState.layers.forEach(layerData => {
                        const img = new Image();
                        img.crossOrigin = 'anonymous';
                        img.onload = function() {
                            const layer = {
                                id: layerData.id,
                                title: layerData.title,
                                image: img,
                                x: layerData.x,
                                y: layerData.y,
                                width: layerData.width,
                                height: layerData.height,
                                rotation: layerData.rotation || 0,
                                opacity: layerData.opacity || 1
                            };
                            
                            layers.push(layer);
                            loadedCount++;
                            
                            // すべてのレイヤーが読み込まれたら描画を更新
                            if (loadedCount === totalLayers) {
                                updateLayersList();
                                redrawCanvas();

                            }
                        };
                        img.onerror = function() {
                            console.warn('画像の読み込みに失敗:', layerData.imageSrc);
                            loadedCount++;
                            if (loadedCount === totalLayers) {
                                updateLayersList();
                                redrawCanvas();
                            }
                        };
                        img.src = layerData.imageSrc;
                    });
                    return true;
                }
                
            } catch (e) {
                console.warn('作業の復元に失敗（サーバーには影響なし）:', e);
            }
            return false;
        }



        // ポップアップ関連の変数
        let currentMaterialPath = '';
        let currentMaterialTitle = '';

        // 素材ポップアップを表示
        function showMaterialPopup(imagePath, title) {
            currentMaterialPath = imagePath;
            currentMaterialTitle = title;
            
            document.getElementById('popupImage').src = '/' + imagePath;
            document.getElementById('popupTitle').textContent = title;
            document.getElementById('materialPopup').style.display = 'flex';
        }

        // 素材ポップアップを非表示
        function hideMaterialPopup() {
            document.getElementById('materialPopup').style.display = 'none';
            currentMaterialPath = '';
            currentMaterialTitle = '';
        }

        // キャンバスに追加を確定
        function confirmAddToCanvas() {
            if (currentMaterialPath && currentMaterialTitle) {
                addMaterialToCanvas(currentMaterialPath, currentMaterialTitle);
                hideMaterialPopup();
            }
        }

        // ポップアップ外クリックで閉じる
        document.addEventListener('click', function(e) {
            const popup = document.getElementById('materialPopup');
            if (e.target === popup) {
                hideMaterialPopup();
            }
        });

        // ESCキーでポップアップを閉じる
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideMaterialPopup();
            }
        });

        // 素材をキャンバスに追加
        function addMaterialToCanvas(imagePath, title) {
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function() {
                const layer = {
                    id: Date.now(),
                    title: title,
                    image: img,
                    x: Math.random() * (canvas.width - 100),
                    y: Math.random() * (canvas.height - 100),
                    width: Math.min(img.width, 150),
                    height: Math.min(img.height, 150),
                    rotation: 0,
                    opacity: 1
                };
                
                // アスペクト比を維持
                const aspectRatio = img.width / img.height;
                if (aspectRatio > 1) {
                    layer.height = layer.width / aspectRatio;
                } else {
                    layer.width = layer.height * aspectRatio;
                }
                
                layers.push(layer);

                updateLayersList();
                redrawCanvas();
            };
            img.src = '/' + imagePath;
        }

        // キャンバスを再描画
        function redrawCanvas() {
            // キャンバス情報をログ出力（最初の数回のみ）
            if (!window.canvasLogged) {
                console.log(`📐 Canvas size: ${canvas.width}x${canvas.height}, layers: ${layers.length}`);
                const rect = canvas.getBoundingClientRect();
                console.log(`📐 Canvas rect: left=${rect.left}, top=${rect.top}, width=${rect.width}, height=${rect.height}`);
                window.canvasLogged = true;
            }
            
            // 背景色で塗りつぶし
            const bgColor = document.getElementById('backgroundColor').value;
            ctx.fillStyle = bgColor;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // 各レイヤーを描画（配列の最初が最前面）
            layers.forEach(layer => {
                ctx.save();
                ctx.globalAlpha = layer.opacity;
                ctx.translate(layer.x + layer.width/2, layer.y + layer.height/2);
                ctx.rotate(layer.rotation * Math.PI / 180);
                ctx.drawImage(layer.image, -layer.width/2, -layer.height/2, layer.width, layer.height);
                ctx.restore();
            });
            
            // 選択中のレイヤーのハンドルを描画
            if (selectedLayer) {
                drawSelectionHandles(selectedLayer);
            }
            
            // ローカルに自動保存（サーバー負荷なし）
            if (layers.length > 0) {
                clearTimeout(window.autoSaveTimer);
                window.autoSaveTimer = setTimeout(saveCanvasState, 1000); // 1秒後に保存
            }
            

        }

        // レイヤーリストを更新
        function updateLayersList() {
            const layersList = document.getElementById('layersList');
            if (layers.length === 0) {
                layersList.innerHTML = '<div style="padding: 1rem; text-align: center; color: #666;">素材を追加するとここに表示されます</div>';
                return;
            }
            
            layersList.innerHTML = '';
            // レイヤーリストを逆順で表示（キャンバス上の最前面がリストの一番上）
            [...layers].reverse().forEach((layer, displayIndex) => {
                const actualIndex = layers.length - 1 - displayIndex; // 実際の配列インデックス
                const layerDiv = document.createElement('div');
                layerDiv.className = 'layer-item';
                layerDiv.innerHTML = `
                    <canvas class="layer-preview" width="40" height="40"></canvas>
                    <div class="layer-controls">
                        <button class="layer-btn" onclick="moveLayer(${actualIndex}, 1)" title="前面へ">↑</button>
                        <button class="layer-btn" onclick="moveLayer(${actualIndex}, -1)" title="背面へ">↓</button>
                        <button class="layer-btn" onclick="deleteLayer(${actualIndex})" title="削除">×</button>
                    </div>
                `;
                
                // プレビュー画像を描画
                const previewCanvas = layerDiv.querySelector('.layer-preview');
                const previewCtx = previewCanvas.getContext('2d');
                const scale = Math.min(40 / layer.width, 40 / layer.height);
                const w = layer.width * scale;
                const h = layer.height * scale;
                previewCtx.drawImage(layer.image, (40-w)/2, (40-h)/2, w, h);
                
                // 選択されたレイヤーをハイライト
                if (selectedLayer === layer) {
                    layerDiv.style.backgroundColor = '#e3f2fd';
                    layerDiv.style.borderColor = '#2196f3';
                }
                
                layersList.appendChild(layerDiv);
            });
        }

        // レイヤーを移動
        function moveLayer(index, direction) {
            const newIndex = index + direction;
            if (newIndex < 0 || newIndex >= layers.length) return;
            
            [layers[index], layers[newIndex]] = [layers[newIndex], layers[index]];
            updateLayersList();
            redrawCanvas();
        }

        // レイヤーを削除
        function deleteLayer(index) {
            layers.splice(index, 1);
            selectedLayer = null;
            updateLayersList();
            redrawCanvas();
        }



        // キャンバスをクリア
        function clearCanvas() {
            if (layers.length > 0 && !confirm('現在の作業内容をクリアしますか？この操作は取り消せません。')) {
                return;
            }
            
            layers = [];
            selectedLayer = null;
            updateLayersList();
            redrawCanvas();
            
            // ローカルストレージもクリア
            try {
                localStorage.removeItem(STORAGE_KEY);
            } catch (e) {
                // ローカルストレージのクリアに失敗
            }
        }

        // キャンバスサイズを変更
        function resizeCanvas() {
            const width = parseInt(document.getElementById('canvasWidth').value);
            const height = parseInt(document.getElementById('canvasHeight').value);
            
            canvas.width = width;
            canvas.height = height;
            redrawCanvas();
        }

        // 背景色を設定
        function setBackgroundColor() {
            redrawCanvas();
        }

        // 作品をダウンロード（選択枠なし）
        function downloadCanvas() {
            // 現在の選択状態を保存
            const currentSelectedLayer = selectedLayer;
            
            // 一時的に選択を解除
            selectedLayer = null;
            
            // 選択枠なしで再描画
            redrawCanvasForExport();
            
            // PNG として出力
            const link = document.createElement('a');
            link.download = 'my-artwork-' + new Date().getTime() + '.png';
            link.href = canvas.toDataURL();
            link.click();
            
            // 選択状態を復元
            selectedLayer = currentSelectedLayer;
            
            // 通常の描画に戻す
            redrawCanvas();
        }
        
        // 透過背景でダウンロード
        function downloadCanvasTransparent() {
            // 現在の選択状態を保存
            const currentSelectedLayer = selectedLayer;
            
            // 一時的に選択を解除
            selectedLayer = null;
            
            // 透過背景で再描画
            redrawCanvasForExportTransparent();
            
            // PNG として出力
            const link = document.createElement('a');
            link.download = 'my-artwork-transparent-' + new Date().getTime() + '.png';
            link.href = canvas.toDataURL();
            link.click();
            
            // 選択状態を復元
            selectedLayer = currentSelectedLayer;
            
            // 通常の描画に戻す
            redrawCanvas();
        }
        
        // エクスポート用の描画（選択枠なし・背景色付き）
        function redrawCanvasForExport() {
            // 背景色で塗りつぶし
            const bgColor = document.getElementById('backgroundColor').value;
            ctx.fillStyle = bgColor;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // 各レイヤーを描画（選択枠は描画しない）
            layers.forEach(layer => {
                ctx.save();
                ctx.globalAlpha = layer.opacity;
                ctx.translate(layer.x + layer.width/2, layer.y + layer.height/2);
                ctx.rotate(layer.rotation * Math.PI / 180);
                ctx.drawImage(layer.image, -layer.width/2, -layer.height/2, layer.width, layer.height);
                ctx.restore();
            });
        }
        
        // エクスポート用の描画（選択枠なし・透過背景）
        function redrawCanvasForExportTransparent() {
            // キャンバスをクリア（透過にする）
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // 各レイヤーを描画（選択枠は描画しない）
            layers.forEach(layer => {
                ctx.save();
                ctx.globalAlpha = layer.opacity;
                ctx.translate(layer.x + layer.width/2, layer.y + layer.height/2);
                ctx.rotate(layer.rotation * Math.PI / 180);
                ctx.drawImage(layer.image, -layer.width/2, -layer.height/2, layer.width, layer.height);
                ctx.restore();
            });
        }





        // イベント用の変数
        let isResizing = false;
        let isRotating = false;
        let resizeHandle = null;
        let resizeStartData = null;
        let rotateStartData = null;
        let lastEventType = null; // 最後のイベントタイプを記録
        let lastEventTime = 0; // 最後のイベント時刻を記録

        // 共通のダウン処理
        function handlePointerDown(x, y, eventType) {

            lastEventType = eventType;
            lastEventTime = Date.now();
            
            // 選択中のレイヤーのハンドルをチェック
            if (selectedLayer) {
                const handle = getHandleAtPosition(selectedLayer, x, y);
                if (handle === 'rotate') {
                    isRotating = true;
                    const centerX = selectedLayer.x + selectedLayer.width / 2;
                    const centerY = selectedLayer.y + selectedLayer.height / 2;
                    rotateStartData = {
                        centerX: centerX,
                        centerY: centerY,
                        startAngle: Math.atan2(y - centerY, x - centerX),
                        initialRotation: selectedLayer.rotation
                    };
                    canvas.style.cursor = 'grab';
                    return;
                } else if (handle) {
                    isResizing = true;
                    resizeHandle = handle;
                    resizeStartData = {
                        x: selectedLayer.x,
                        y: selectedLayer.y,
                        width: selectedLayer.width,
                        height: selectedLayer.height,
                        mouseX: x,
                        mouseY: y
                    };
                    canvas.style.cursor = handle + '-resize';
                    return;
                }
            }
            
            // クリックされたレイヤーを検索（最前面から順にチェック）
            let newSelectedLayer = null;

            
            for (let i = layers.length - 1; i >= 0; i--) {
                const layer = layers[i];

                
                // 回転を考慮した当たり判定
                if (isPointInRotatedRect(x, y, layer)) {

                    newSelectedLayer = layer;
                    isDragging = true;
                    
                    // ドラッグオフセットも回転を考慮
                    const centerX = layer.x + layer.width / 2;
                    const centerY = layer.y + layer.height / 2;
                    const rotation = -layer.rotation * Math.PI / 180;
                    const dx = x - centerX;
                    const dy = y - centerY;
                    const rotatedX = centerX + (dx * Math.cos(rotation) - dy * Math.sin(rotation));
                    const rotatedY = centerY + (dx * Math.sin(rotation) + dy * Math.cos(rotation));
                    
                    dragOffset.x = rotatedX - layer.x;
                    dragOffset.y = rotatedY - layer.y;
                    canvas.style.cursor = 'move';
                    break;
                } else {

                }
            }
            
            // 選択状態を更新
            selectedLayer = newSelectedLayer;
            
            if (!selectedLayer) {
                canvas.style.cursor = 'default';
            }
            
            redrawCanvas();
        }

        // 共通のムーブ処理
        function handlePointerMove(x, y, eventType) {
            if (isRotating && selectedLayer && rotateStartData) {
                // 回転処理
                const currentAngle = Math.atan2(y - rotateStartData.centerY, x - rotateStartData.centerX);
                const angleDiff = currentAngle - rotateStartData.startAngle;
                selectedLayer.rotation = rotateStartData.initialRotation + (angleDiff * 180 / Math.PI);
                
                // 角度を-180から180の範囲に正規化
                while (selectedLayer.rotation > 180) selectedLayer.rotation -= 360;
                while (selectedLayer.rotation < -180) selectedLayer.rotation += 360;
                
                redrawCanvas();
                
            } else if (isResizing && selectedLayer && resizeStartData) {
                // リサイズ処理
                const deltaX = x - resizeStartData.mouseX;
                const deltaY = y - resizeStartData.mouseY;
                
                let newX = resizeStartData.x;
                let newY = resizeStartData.y;
                let newWidth = resizeStartData.width;
                let newHeight = resizeStartData.height;
                
                // アスペクト比を維持するための基準値
                const aspectRatio = selectedLayer.image.width / selectedLayer.image.height;
                
                switch (resizeHandle) {
                    case 'se': // 右下
                        newWidth = Math.max(20, resizeStartData.width + deltaX);
                        newHeight = newWidth / aspectRatio;
                        break;
                    case 'sw': // 左下
                        newWidth = Math.max(20, resizeStartData.width - deltaX);
                        newHeight = newWidth / aspectRatio;
                        newX = resizeStartData.x + resizeStartData.width - newWidth;
                        break;
                    case 'ne': // 右上
                        newWidth = Math.max(20, resizeStartData.width + deltaX);
                        newHeight = newWidth / aspectRatio;
                        newY = resizeStartData.y + resizeStartData.height - newHeight;
                        break;
                    case 'nw': // 左上
                        newWidth = Math.max(20, resizeStartData.width - deltaX);
                        newHeight = newWidth / aspectRatio;
                        newX = resizeStartData.x + resizeStartData.width - newWidth;
                        newY = resizeStartData.y + resizeStartData.height - newHeight;
                        break;
                }
                
                // キャンバス内に収まるように調整
                if (newX < 0) {
                    newWidth += newX;
                    newHeight = newWidth / aspectRatio;
                    newX = 0;
                }
                if (newY < 0) {
                    newHeight += newY;
                    newWidth = newHeight * aspectRatio;
                    newY = 0;
                }
                if (newX + newWidth > canvas.width) {
                    newWidth = canvas.width - newX;
                    newHeight = newWidth / aspectRatio;
                }
                if (newY + newHeight > canvas.height) {
                    newHeight = canvas.height - newY;
                    newWidth = newHeight * aspectRatio;
                }
                
                selectedLayer.x = newX;
                selectedLayer.y = newY;
                selectedLayer.width = newWidth;
                selectedLayer.height = newHeight;
                
                redrawCanvas();
                
            } else if (isDragging && selectedLayer) {
                // 移動処理
                selectedLayer.x = x - dragOffset.x;
                selectedLayer.y = y - dragOffset.y;
                
                // キャンバス内に留める
                selectedLayer.x = Math.max(0, Math.min(canvas.width - selectedLayer.width, selectedLayer.x));
                selectedLayer.y = Math.max(0, Math.min(canvas.height - selectedLayer.height, selectedLayer.y));
                
                redrawCanvas();
            } else if (selectedLayer && !isDragging && !isResizing && !isRotating) {
                // ホバー時のカーソル変更（ドラッグ中でない場合のみ）
                const handle = getHandleAtPosition(selectedLayer, x, y);
                if (handle === 'rotate') {
                    canvas.style.cursor = 'grab';
                } else if (handle) {
                    canvas.style.cursor = handle + '-resize';
                } else if (isPointInRotatedRect(x, y, selectedLayer)) {
                    canvas.style.cursor = 'move';
                } else {
                    canvas.style.cursor = 'default';
                }
            }
        }

        // 共通のアップ処理
        function handlePointerUp() {
            isDragging = false;
            isResizing = false;
            isRotating = false;
            resizeHandle = null;
            resizeStartData = null;
            rotateStartData = null;
            canvas.style.cursor = 'default';
        }

        // マウスイベント処理
        canvas.addEventListener('mousedown', function(e) {
            // タッチイベントが既に処理されている場合はスキップ
            if (lastEventType === 'touch' && Date.now() - lastEventTime < 500) {

                return;
            }
            
            const rect = canvas.getBoundingClientRect();
            // スケーリングを考慮した座標計算
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            const x = (e.clientX - rect.left) * scaleX;
            const y = (e.clientY - rect.top) * scaleY;
            
            handlePointerDown(x, y, 'mouse');
        });

        canvas.addEventListener('mousemove', function(e) {
            const rect = canvas.getBoundingClientRect();
            // スケーリングを考慮した座標計算
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            const x = (e.clientX - rect.left) * scaleX;
            const y = (e.clientY - rect.top) * scaleY;
            
            handlePointerMove(x, y, 'mouse');
        });

        canvas.addEventListener('mouseup', function() {
            handlePointerUp();
        });

        // タッチイベント対応
        function getTouchPos(e) {
            const rect = canvas.getBoundingClientRect();
            const touch = e.touches[0] || e.changedTouches[0];
            
            // スケーリングを考慮した座標計算
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            
            const pos = {
                x: (touch.clientX - rect.left) * scaleX,
                y: (touch.clientY - rect.top) * scaleY
            };
            

            return pos;
        }

        canvas.addEventListener('touchstart', function(e) {

            e.preventDefault();
            
            if (!e.touches || e.touches.length === 0) {

                return;
            }
            
            const pos = getTouchPos(e);
            handlePointerDown(pos.x, pos.y, 'touch');
        }, { passive: false });

        canvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            
            if (!e.touches || e.touches.length === 0) {
                return;
            }
            
            const pos = getTouchPos(e);
            handlePointerMove(pos.x, pos.y, 'touch');
        }, { passive: false });

        canvas.addEventListener('touchend', function(e) {
            console.log('📱 touchend event fired');
            e.preventDefault();
            handlePointerUp();
        }, { passive: false });



        // キーボードショートカット
        document.addEventListener('keydown', function(e) {
            if (!selectedLayer) return;
            
            const layerIndex = layers.indexOf(selectedLayer);
            if (layerIndex === -1) return;
            
            switch(e.key) {
                case 'Delete':
                case 'Backspace':
                    e.preventDefault();
                    deleteLayer(layerIndex);
                    break;
            }
        });

        // ページ読み込み時に保存された作業を復元
        window.addEventListener('load', function() {
            const restored = loadCanvasState();
            // 復元されなかった場合は初期描画
            if (!restored) {
                redrawCanvas();
            }
        });

        // ページ離脱前にローカルに保存
        window.addEventListener('beforeunload', function() {
            if (layers.length > 0) {
                saveCanvasState();
            }
        });


        

        





    </script>

    <!-- 素材追加ポップアップ -->
    <div id="materialPopup" class="popup-overlay" style="display: none;">
        <div class="popup-content">
            <div class="popup-header">
                <h3>素材をキャンバスに追加</h3>
                <button class="popup-close" onclick="hideMaterialPopup()">&times;</button>
            </div>
            <div class="popup-body">
                <img id="popupImage" src="" alt="素材プレビュー">
                <p id="popupTitle"></p>
            </div>
            <div class="popup-footer">
                <button class="popup-btn cancel" onclick="hideMaterialPopup()">キャンセル</button>
                <button class="popup-btn add" id="addToCanvasBtn" onclick="confirmAddToCanvas()">キャンバスに追加</button>
            </div>
        </div>
    </div>

    <!-- GDPR Cookie Banner -->
    <div id="gdpr-banner" class="row hidden">
        <div class="col-md-8">
            <div class="gdpr-text">
                当サイトはGoogleアナリティクスを使用してアクセス解析を行っています。データ処理の詳細は
                <a href="/privacy-policy.php" target="_blank" rel="noopener">プライバシーポリシー</a>
                をご確認ください。引き続きサイトを利用される場合、Cookieの使用に同意したものとみなします。
            </div>
        </div>
        <div class="col-md-4">
            <div class="gdpr-buttons">
                <button type="button" class="btn btn-outline-light btn-sm" onclick="declineGdpr()">拒否</button>
                <button type="button" class="btn btn-success btn-sm" onclick="acceptGdpr()">同意</button>
            </div>
        </div>
    </div>

    <script>
    // GDPR Cookie Banner 管理
    (function() {
        const GDPR_KEY = 'gdpr_consent_v1';
        
        function showGdprBanner() {
            const banner = document.getElementById('gdpr-banner');
            if (banner) {
                banner.classList.remove('hidden');
                banner.style.display = 'flex';
            }
        }
        
        function hideGdprBanner() {
            const banner = document.getElementById('gdpr-banner');
            if (banner) {
                banner.classList.add('hidden');
                banner.style.display = 'none';
            }
        }
        
        // グローバル関数として定義
        window.acceptGdpr = function() {
            try {
                localStorage.setItem(GDPR_KEY, 'accepted');
                hideGdprBanner();
                
                // GTMを初期化（同意時のみ）
                if (typeof window.initGTM === 'function') {
                    window.initGTM();
                }
                
                // カスタムイベント発火
                window.dispatchEvent(new Event('gdpr-consent-accepted'));
            } catch (e) {
                console.warn('localStorage access failed:', e);
                hideGdprBanner();
            }
        };
        
        window.declineGdpr = function() {
            try {
                localStorage.setItem(GDPR_KEY, 'declined');
                hideGdprBanner();
            } catch (e) {
                console.warn('localStorage access failed:', e);
                hideGdprBanner();
            }
        };
        
        // 初期化
        function initGdprBanner() {
            try {
                const consent = localStorage.getItem(GDPR_KEY);
                if (!consent) {
                    // まだ同意/拒否していない場合のみバナーを表示
                    showGdprBanner();
                }
            } catch (e) {
                // LocalStorageアクセスできない場合は表示しない
                console.warn('localStorage access failed:', e);
            }
        }
        
        // DOM読み込み完了後に初期化
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initGdprBanner);
        } else {
            initGdprBanner();
        }
    })();
    </script>

    <footer class="footer-custom mt-5 py-4">
        <div class="container">
            <div class="text-center">
                <div class="mb-2">
                    <a href="/terms-of-use.php" class="footer-text text-decoration-none me-3">利用規約</a>
                    <a href="/privacy-policy.php" class="footer-text text-decoration-none">プライバシーポリシー</a>
                </div>
                <div class="language-switcher mb-2">
                    <div class="gtranslate_wrapper"></div>
                    <script>window.gtranslateSettings = {"default_language":"ja","native_language_names":true,"url_structure":"sub_directory","languages":["ja","en","fr","es","nl"],"wrapper_selector":".gtranslate_wrapper"}</script>
                    <script src="https://cdn.gtranslate.net/widgets/latest/ln.js" defer></script>
                </div>
                <div>
                    <p class="footer-text mb-0">&copy; 2024 maruttoart. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>