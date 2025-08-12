<?php
// GTranslate 言語切り替え機能
function renderGTranslate() {
    ob_start();
    ?>
    <!-- GTranslate Script -->
    <script>
        window.gtranslateSettings = {
            "default_language": "ja",
            "languages": ["ja", "en", "es", "fr", "nl"],
            "wrapper_selector": ".gtranslate_wrapper",
            "horizontal_position": "right",
            "vertical_position": "top"
        }
    </script>
    <script src="https://cdn.gtranslate.net/widgets/latest/float.js" defer></script>
    
    <!-- 現在の言語を検出するJavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 現在のサブドメインから言語を検出
            const hostname = window.location.hostname;
            const subdomain = hostname.split('.')[0];
            
            // 言語マッピング
            const languageMap = {
                'maruttoart': 'ja',  // メインドメイン（日本語）
                'en': 'en',
                'es': 'es', 
                'fr': 'fr',
                'nl': 'nl'
            };
            
            const currentLang = languageMap[subdomain] || 'ja';
            
            // ドロップダウンの言語項目をハイライト
            const dropdownItems = document.querySelectorAll('#languageDropdown + .dropdown-menu .dropdown-item');
            dropdownItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href.includes(subdomain + '.') || (subdomain === 'maruttoart' && href === 'https://maruttoart.com')) {
                    item.classList.add('active');
                }
            });
        });
    </script>
    
    <style>
        /* GTranslateのスタイル調整 */
        .gtranslate_wrapper {
            display: none !important; /* デフォルトのウィジェットを非表示 */
        }
        
        /* 言語切り替えボタンのスタイル */
        .dropdown-item.active {
            background-color: #0d6efd;
            color: white;
        }
        
        .dropdown-item.active:hover {
            background-color: #0b5ed7;
            color: white;
        }
    </style>
    <?php
    return ob_get_clean();
}

// 現在のページURLを他の言語のサブドメインに変換
function getTranslatedUrl($targetLang) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    
    // 現在のサブドメインを取得
    $parts = explode('.', $host);
    
    if (count($parts) >= 2) {
        // サブドメインがある場合
        if ($targetLang === 'ja') {
            // 日本語の場合はメインドメイン
            $newHost = 'maruttoart.com';
        } else {
            // 他の言語の場合はサブドメイン
            $newHost = $targetLang . '.maruttoart.com';
        }
    } else {
        // サブドメインがない場合（localhost等）
        if ($targetLang === 'ja') {
            $newHost = $host;
        } else {
            $newHost = $targetLang . '.' . $host;
        }
    }
    
    return $protocol . '://' . $newHost . $uri;
}
?>
