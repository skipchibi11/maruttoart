<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(300, 600); // 5分 / CDN 10分

?><!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GDPR詳細ページテスト - maruttoart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 50px;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-btn {
            margin: 10px;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-clear {
            background: #dc3545;
            color: white;
        }
        .btn-accept {
            background: #28a745;
            color: white;
        }
        .btn-decline {
            background: #6c757d;
            color: white;
        }
        .status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>詳細ページGDPR機能テスト</h1>
        
        <div class="status" id="status">
            読み込み中...
        </div>
        
        <h2>テスト操作</h2>
        <button class="test-btn btn-clear" onclick="clearConsent()">同意状況をリセット</button>
        <button class="test-btn btn-accept" onclick="setConsent('accepted')">同意状態に設定</button>
        <button class="test-btn btn-decline" onclick="setConsent('declined')">拒否状態に設定</button>
        <button class="test-btn" onclick="checkStatus()">状況を再確認</button>
        
        <h2>詳細ページでのGDPR機能確認</h2>
        <div class="alert alert-info">
            <strong>確認ポイント：</strong>
            <ul>
                <li><strong>未設定時</strong>: GDPRバナー表示、YouTube動画非表示</li>
                <li><strong>同意後</strong>: バナー非表示、YouTube動画表示</li>
                <li><strong>拒否後</strong>: バナー非表示、YouTube動画非表示（代替メッセージ表示）</li>
            </ul>
        </div>
        
        <h3>テスト用詳細ページリンク</h3>
        <div class="d-grid gap-2">
            <a href="/detail/lemon" class="btn btn-primary" target="_blank">
                🍋 レモン詳細ページを開く（YouTube動画あり）
            </a>
            <a href="/detail/peach" class="btn btn-primary" target="_blank">
                🍑 ピーチ詳細ページを開く（YouTube動画あり）
            </a>
        </div>
        
        <h3>他のページテスト</h3>
        <div class="row">
            <div class="col-md-6">
                <a href="/" class="btn btn-outline-secondary w-100 mb-2">トップページ</a>
                <a href="/privacy-policy.php" class="btn btn-outline-secondary w-100 mb-2">プライバシーポリシー</a>
            </div>
            <div class="col-md-6">
                <a href="/404.php" class="btn btn-outline-secondary w-100 mb-2">404ページ</a>
                <a href="/cache-test.php" class="btn btn-outline-secondary w-100 mb-2">キャッシュテスト</a>
            </div>
        </div>
        
        <div class="mt-4">
            <small class="text-muted">
                ※ 各設定変更後は詳細ページを再読み込みして動作を確認してください
            </small>
        </div>
    </div>

    <script>
    const GDPR_KEY = 'gdpr_consent_v1';
    
    // 同意状況を取得
    function getConsent() {
        try {
            return localStorage.getItem(GDPR_KEY);
        } catch (e) {
            return null;
        }
    }
    
    // 同意状況を設定
    function setConsent(value) {
        try {
            if (value === null) {
                localStorage.removeItem(GDPR_KEY);
            } else {
                localStorage.setItem(GDPR_KEY, value);
            }
            checkStatus();
        } catch (e) {
            document.getElementById('status').innerHTML = 'エラー: localStorageが使用できません';
        }
    }
    
    // 同意状況をクリア
    function clearConsent() {
        setConsent(null);
    }
    
    // 状況を確認
    function checkStatus() {
        const consent = getConsent();
        const status = document.getElementById('status');
        
        if (consent === null) {
            status.innerHTML = '<strong>未設定</strong> - 詳細ページでGDPRバナー表示、YouTube動画非表示';
            status.style.background = '#fff3cd';
        } else if (consent === 'accepted') {
            status.innerHTML = '<strong>同意済み</strong> - バナー非表示、YouTube動画表示、アナリティクス有効';
            status.style.background = '#d4edda';
        } else if (consent === 'declined') {
            status.innerHTML = '<strong>拒否済み</strong> - バナー非表示、YouTube動画非表示（代替メッセージ表示）';
            status.style.background = '#f8d7da';
        } else {
            status.innerHTML = '<strong>不明な状態</strong> - 値: ' + consent;
            status.style.background = '#e2e3e5';
        }
    }
    
    // 初期表示
    document.addEventListener('DOMContentLoaded', checkStatus);
    </script>
</body>
</html>
