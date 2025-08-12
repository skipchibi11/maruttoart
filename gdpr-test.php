<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(300, 600); // 5分 / CDN 10分

?><!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GDPR テスト - maruttoart</title>
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
        <h1>GDPR Cookie Consent テスト</h1>
        
        <div class="status" id="status">
            読み込み中...
        </div>
        
        <h2>テスト操作</h2>
        <button class="test-btn btn-clear" onclick="clearConsent()">同意状況をリセット</button>
        <button class="test-btn btn-accept" onclick="setConsent('accepted')">同意状態に設定</button>
        <button class="test-btn btn-decline" onclick="setConsent('declined')">拒否状態に設定</button>
        <button class="test-btn" onclick="checkStatus()">状況を再確認</button>
        
        <h2>説明</h2>
        <ul>
            <li>このテストページでGDPR機能の動作を確認できます</li>
            <li>セッションやCookieは使用せず、localStorageのみを使用</li>
            <li>CDNキャッシュに影響しません</li>
            <li>初回訪問時はすべてのページでバナーが表示されます</li>
            <li><strong>拒否した場合、YouTube動画が非表示になります</strong></li>
        </ul>
        
        <h2>テスト対象ページ</h2>
        <ul>
            <li><a href="/">トップページ</a> - GDPRバナー表示</li>
            <li><a href="/detail/lemon">詳細ページ</a> - YouTube表示制御テスト</li>
            <li><a href="/privacy-policy.php">プライバシーポリシー</a> - GDPRバナー表示</li>
            <li><a href="/404.php">404ページ</a> - GDPRバナー表示</li>
        </ul>
        
        <div style="margin-top: 30px;">
            <a href="/">← トップページ（バナー確認用）</a> | 
            <a href="/detail/lemon">詳細ページサンプル</a>
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
            status.innerHTML = '<strong>未設定</strong> - トップページでGDPRバナーが表示されます';
            status.style.background = '#fff3cd';
        } else if (consent === 'accepted') {
            status.innerHTML = '<strong>同意済み</strong> - アナリティクスが有効化されます';
            status.style.background = '#d4edda';
        } else if (consent === 'declined') {
            status.innerHTML = '<strong>拒否済み</strong> - アナリティクスが無効化されます';
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
