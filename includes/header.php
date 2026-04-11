<style>
/* グローバル設定 */
html, body {
    width: 100%;
    box-sizing: border-box;
    max-width: 100vw;
}

*, *::before, *::after {
    box-sizing: border-box;
}

/* コンテナシステム - 統一スタイル */
.container {
    width: 100%;
    max-width: 1140px;
    margin: 0 auto;
    padding-top: 0px;
    padding-bottom: 0px;
    padding-left: 15px;
    padding-right: 15px;
    box-sizing: border-box;
}

/* 1400px以上: コンテナの最大幅を拡張 */
@media (min-width: 1400px) {
    .container {
        max-width: 1320px;
    }
}

/* ナビゲーション - 統一スタイル */
.navbar {
    position: relative;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 0rem;
    background: transparent;
    border-bottom: none;
}

.navbar-brand {
    display: inline-block;
    padding-top: 0.3125rem;
    padding-bottom: 0.3125rem;
    margin-right: 1rem;
    font-size: 2rem;
    font-weight: 700;
    color: #A0675C;
    text-decoration: none;
    letter-spacing: 0.5px;
}

.navbar-brand:hover {
    color: #8B5A4F;
    text-decoration: none;
}

.navbar .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0px;
    padding-bottom: 0px;
    max-width: 1200px;
    margin: 0 auto;
}

/* ホームページ専用ヘッダー */
.navbar-home {
    position: relative;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem 0rem;
    background: transparent;
    border-bottom: none;
}

.navbar-home .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0px;
    padding-bottom: 0px;
    max-width: 1200px;
    margin: 0 auto;
}

.navbar-home .navbar-brand {
    display: inline-block;
    padding-top: 0.3125rem;
    padding-bottom: 0.3125rem;
    margin-right: 1rem;
    font-size: 2rem;
    font-weight: 700;
    color: #A0675C;
    text-decoration: none;
    letter-spacing: 0.5px;
}

.navbar-home .navbar-brand:hover {
    color: #8B5A4F;
    text-decoration: none;
}

.tool-navigation {
    display: flex;
    gap: 0.5rem;
}

.tool-nav-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    text-decoration: none;
    color: #6c757d;
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    transition: all 0.2s ease;
}

.tool-nav-link:hover {
    color: #495057;
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
    text-decoration: none;
}

.tool-nav-link.active {
    color: #495057;
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.4);
}

.tool-nav-link svg {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

/* ナビゲーション - 統一スタイル */
.main-navigation {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.nav-link {
    color: #8B7355;
    text-decoration: none;
    padding: 0.5rem 0;
    border-radius: 4px;
    transition: all 0.2s ease;
    font-weight: 500;
    font-size: 1rem;
    position: relative;
}

.nav-link::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background-color: #A0675C;
    transition: width 0.3s ease;
}

.nav-link:hover {
    color: #A0675C;
    text-decoration: none;
    background-color: transparent;
}

.nav-link:hover::after {
    width: 100%;
}

.nav-link.active {
    color: #A0675C;
    font-weight: 600;
    background-color: transparent;
}

.nav-link.active::after {
    width: 100%;
}

/* 特徴的なメニュー項目（MixとWorks）*/
.nav-link-featured {
    font-weight: 600;
    font-size: 1rem;
    color: white !important;
    background: linear-gradient(135deg, #FFD4A3 0%, #FFABC5 100%);
    padding: 0.5rem 1.2rem !important;
    border-radius: 25px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

.nav-link-featured::after {
    display: none !important;
}

.nav-link-featured:hover {
    color: white !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 171, 197, 0.4);
    background: linear-gradient(135deg, #FFE0B8 0%, #FFC0D8 100%);
}

.nav-link-featured.active {
    font-weight: 700;
    color: white !important;
    box-shadow: 0 6px 20px rgba(255, 171, 197, 0.4);
}

/* navbar-home用の詳細度を高める */
.navbar-home .nav-link-featured,
.navbar .nav-link-featured {
    color: white !important;
    background: linear-gradient(135deg, #FFD4A3 0%, #FFABC5 100%);
    padding: 0.5rem 1.2rem !important;
    border-radius: 25px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.navbar-home .nav-link-featured:hover,
.navbar .nav-link-featured:hover {
    color: white !important;
    background: linear-gradient(135deg, #FFE0B8 0%, #FFC0D8 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(255, 171, 197, 0.4);
}

.navbar-home .nav-link-featured::after,
.navbar .nav-link-featured::after {
    display: none !important;
}

/* ホームページ用ナビゲーション（統一済み） */
.navbar-home .main-navigation {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.navbar-home .nav-link {
    color: #8B7355;
    text-decoration: none;
    padding: 0.5rem 0;
    border-radius: 4px;
    transition: all 0.2s ease;
    font-weight: 500;
    font-size: 1rem;
    position: relative;
}

.navbar-home .nav-link::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background-color: #A0675C;
    transition: width 0.3s ease;
}

.navbar-home .nav-link:hover {
    color: #A0675C;
    text-decoration: none;
    background-color: transparent;
}

.navbar-home .nav-link:hover::after {
    width: 100%;
}

.navbar-home .nav-link.active {
    color: #A0675C;
    font-weight: 600;
    background-color: transparent;
}

.navbar-home .nav-link.active::after {
    width: 100%;
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

/* レスポンシブ対応 */
/* PC・スマホ共通でヘッダーを非表示 */
.navbar,
.navbar-home {
    display: none;
}

.container {
    padding-bottom: 80px; /* 固定フッターメニューの高さ分 */
}

@media (max-width: 768px) {
    .container {
        padding-left: 15px;
        padding-right: 15px;
    }
}

/* PC・スマホ共通の固定フッターメニュー */
.mobile-bottom-nav {
    display: flex;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    border-top: 1px solid rgba(139, 115, 85, 0.2);
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    padding: 12px 0 max(12px, env(safe-area-inset-bottom));
    justify-content: center;
    align-items: center;
    gap: 40px;
}

/* 固定フッターの上に配置する言語選択バッジ */
.footer-language-badge-fixed {
    position: fixed;
    bottom: 70px;
    right: 20px;
    z-index: 999;
}

.footer-language-badge-fixed .language-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(160, 103, 92, 0.2);
    border-radius: 20px;
    color: #5A4A42;
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    cursor: pointer;
    user-select: none;
}

.footer-language-badge-fixed .language-toggle:hover {
    background: rgba(255, 255, 255, 1);
    border-color: #A0675C;
    color: #A0675C;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.footer-language-badge-fixed svg {
    width: 14px;
    height: 14px;
    transition: transform 0.3s ease;
}

.footer-language-badge-fixed.open svg {
    transform: rotate(180deg);
}

/* 言語ドロップダウンメニュー */
.language-dropdown {
    position: absolute;
    bottom: calc(100% + 8px);
    right: 0;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(160, 103, 92, 0.2);
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    min-width: 150px;
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all 0.3s ease;
}

.footer-language-badge-fixed.open .language-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.language-dropdown a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    color: #5A4A42;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
    border-bottom: 1px solid rgba(160, 103, 92, 0.1);
}

.language-dropdown a:last-child {
    border-bottom: none;
}

.language-dropdown a:hover {
    background: rgba(160, 103, 92, 0.08);
    color: #A0675C;
}

.language-dropdown a.active {
    background: rgba(160, 103, 92, 0.12);
    color: #A0675C;
    font-weight: 600;
}

.mobile-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #5A4A42;
    padding: 8px 20px;
    transition: color 0.2s;
}

.mobile-nav-item:hover,
.mobile-nav-item.active {
    color: #A0675C;
    text-decoration: none;
}

/* モバイルメニューの特徴的な項目（MixとWorks）*/
.mobile-nav-item-featured {
    background: linear-gradient(135deg, #FFD4A3 0%, #FFABC5 100%);
    border-radius: 25px;
    margin: 0 5px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
}

.mobile-nav-item-featured .mobile-nav-label {
    color: white;
    font-weight: 600;
}

.mobile-nav-item-featured:hover .mobile-nav-label,
.mobile-nav-item-featured.active .mobile-nav-label {
    color: white;
}

.mobile-nav-label {
    font-size: 0.95rem;
    font-weight: 500;
}

@media (max-width: 768px) {
    .mobile-bottom-nav {
        gap: 0;
        justify-content: space-around;
        padding: 8px 0 max(8px, env(safe-area-inset-bottom));
    }
    
    .mobile-nav-item {
        flex: 1;
        padding: 12px 0;
    }
    
    /* スマホではCalendarを非表示 */
    .mobile-nav-item-calendar {
        display: none;
    }
    
    .mobile-nav-label {
        font-size: 0.85rem;
    }
    
    .footer-language-badge-fixed {
        bottom: 65px;
        right: 10px;
    }
    
    .footer-language-badge-fixed .language-toggle {
        padding: 6px 12px;
        font-size: 0.75rem;
        gap: 4px;
    }
    
    .footer-language-badge-fixed svg {
        width: 12px;
        height: 12px;
    }
    
    .language-dropdown {
        min-width: 130px;
    }
    
    .language-dropdown a {
        padding: 8px 12px;
        font-size: 0.8rem;
    }
}
</style>

<?php if (isset($currentPage) && $currentPage === 'home'): ?>
<nav class="navbar-home">
    <div class="container">
        <a class="navbar-brand" href="/">marutto.art</a>
        
        <div class="main-navigation">
            <a href="/compose/" class="nav-link nav-link-featured">Mix</a>
            <a href="/everyone-works.php" class="nav-link nav-link-featured">Works</a>
            <a href="/list.php" class="nav-link">Items</a>
            <a href="/calendar/" class="nav-link">Calendar</a>
        </div>
    </div>
</nav>
<?php else: ?>
<nav class="navbar">
    <div class="container">
        <a class="navbar-brand" href="/">marutto.art</a>
        
        <div class="main-navigation">
            <a href="/compose/" class="nav-link nav-link-featured<?= isset($currentPage) && ($currentPage === 'compose' || $currentPage === 'index') ? ' active' : '' ?>">Mix</a>
            <a href="/everyone-works.php" class="nav-link nav-link-featured<?= isset($currentPage) && $currentPage === 'everyone-works' ? ' active' : '' ?>">Works</a>
            <a href="/list.php" class="nav-link<?= isset($currentPage) && $currentPage === 'list' ? ' active' : '' ?>">Items</a>
            <a href="/calendar/" class="nav-link<?= isset($currentPage) && $currentPage === 'calendar' ? ' active' : '' ?>">Calendar</a>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- 言語選択バッジ（固定フッターの上に配置） -->
<div class="footer-language-badge-fixed" id="languageSelector">
    <div class="language-toggle notranslate" onclick="toggleLanguageMenu()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="2" y1="12" x2="22" y2="12"></line>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
        </svg>
        Language
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px;">
            <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
    </div>
    <div class="language-dropdown">
        <a href="/" class="active nturl notranslate">🇯🇵 日本語</a>
        <a href="/en/" class="nturl notranslate">🇬🇧 English</a>
        <a href="/es/" class="nturl notranslate">🇪🇸 Español</a>
        <a href="/fr/" class="nturl notranslate">🇫🇷 Français</a>
        <a href="/nl/" class="nturl notranslate">🇳🇱 Nederlands</a>
        <a href="/zh-CN/" class="nturl notranslate">🇨🇳 简体中文</a>
        <a href="/ko/" class="nturl notranslate">🇰🇷 한국어</a>
    </div>
</div>

<script>
function toggleLanguageMenu() {
    const selector = document.getElementById('languageSelector');
    selector.classList.toggle('open');
}

// クリック外でメニューを閉じる
document.addEventListener('click', function(event) {
    const selector = document.getElementById('languageSelector');
    if (selector && !selector.contains(event.target)) {
        selector.classList.remove('open');
    }
});
</script>

<!-- スマホ用固定フッターメニュー -->
<nav class="mobile-bottom-nav">
    <a href="/" class="mobile-nav-item<?= isset($currentPage) && $currentPage === 'home' ? ' active' : '' ?>">
        <div class="mobile-nav-label">Marutto</div>
    </a>
    <a href="/compose/" class="mobile-nav-item mobile-nav-item-featured<?= isset($currentPage) && ($currentPage === 'compose' || $currentPage === 'index') ? ' active' : '' ?>">
        <div class="mobile-nav-label">Mix</div>
    </a>
    <a href="/everyone-works.php" class="mobile-nav-item mobile-nav-item-featured<?= isset($currentPage) && $currentPage === 'everyone-works' ? ' active' : '' ?>">
        <div class="mobile-nav-label">Works</div>
    </a>
    <a href="/list.php" class="mobile-nav-item<?= isset($currentPage) && $currentPage === 'list' ? ' active' : '' ?>">
        <div class="mobile-nav-label">Items</div>
    </a>
    <a href="/calendar/" class="mobile-nav-item mobile-nav-item-calendar<?= isset($currentPage) && $currentPage === 'calendar' ? ' active' : '' ?>">
        <div class="mobile-nav-label">Calendar</div>
    </a>
</nav>