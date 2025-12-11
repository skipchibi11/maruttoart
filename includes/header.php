<style>
/* コンテナシステム - 統一スタイル */
.container {
    width: 100%;
    max-width: 1140px;
    margin: 0 auto;
    padding-top: 0px;
    padding-bottom: 0px;
    padding-left: 15px;
    padding-right: 15px;
}

/* 1400px以上: コンテナの最大幅を拡張 */
@media (min-width: 1400px) {
    .container {
        max-width: 1320px;
    }
}

/* ナビゲーション - Bootstrap標準に合わせる */
.navbar {
    position: relative;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0rem;
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

.navbar .container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0px;
    padding-bottom: 0px;
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

/* 他のページ用ナビゲーション */
.main-navigation {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.nav-link {
    color: #6c757d;
    text-decoration: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    transition: all 0.2s ease;
    font-weight: 500;
}

.nav-link:hover {
    color: #495057;
    background-color: rgba(0, 0, 0, 0.05);
    text-decoration: none;
}

.nav-link.active {
    color: #495057;
    background-color: rgba(0, 0, 0, 0.1);
    font-weight: 600;
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
@media (max-width: 768px) {
    .navbar-brand {
        font-size: 1.5rem;
    }
    .container {
        padding-left: 15px;
        padding-right: 15px;
    }
}
</style>

<nav class="navbar">
    <div class="container">
        <a class="navbar-brand" href="/">maruttoart</a>
        
        <!-- 全ページ共通ツールナビゲーション -->
        <div class="tool-navigation">
            <a href="/list.php" class="tool-nav-link<?= $currentPage === 'list' ? ' active' : '' ?>" title="作品一覧">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-layout-list-icon lucide-layout-list">
                    <rect width="7" height="7" x="3" y="3" rx="1"/>
                    <rect width="7" height="7" x="3" y="14" rx="1"/>
                    <path d="M14 4h7"/>
                    <path d="M14 9h7"/>
                    <path d="M14 15h7"/>
                    <path d="M14 20h7"/>
                </svg>
            </a>
            <a href="/everyone-works.php" class="tool-nav-link<?= $currentPage === 'everyone-works' ? ' active' : '' ?>" title="みんなのアトリエ">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-handshake-icon lucide-handshake">
                    <path d="m11 17 2 2a1 1 0 1 0 3-3"/>
                    <path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l .47.28a2 2 0 0 0 1.42.25L21 4"/>
                    <path d="m21 3 1 11h-2"/>
                    <path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/>
                    <path d="M3 4h8"/>
                </svg>
            </a>
            <a href="/compose/index.php" class="tool-nav-link<?= $currentPage === 'index' ? ' active' : '' ?>" title="標準編集">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-pencil-icon lucide-pencil">
                    <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/>
                    <path d="m15 5 4 4"/>
                </svg>
            </a>
        </div>
    </div>
</nav>