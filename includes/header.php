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
@media (max-width: 768px) {
    .navbar {
        padding: 1rem 0rem;
    }
    
    .navbar-brand {
        font-size: 1.5rem;
    }
    
    .container {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    .main-navigation {
        gap: 1rem;
    }
    
    .nav-link {
        font-size: 0.9rem;
        padding: 0.4rem 0;
    }
    
    .navbar-home {
        padding: 1rem 0rem;
    }
    
    .navbar-home .navbar-brand {
        font-size: 1.5rem;
    }
    
    .navbar-home .main-navigation {
        gap: 1rem;
    }
    
    .navbar-home .nav-link {
        font-size: 0.9rem;
        padding: 0.4rem 0;
    }
}

@media (max-width: 480px) {
    .navbar-brand {
        font-size: 1.3rem;
    }
    
    .main-navigation {
        gap: 0.8rem;
    }
    
    .nav-link {
        font-size: 0.85rem;
    }
    
    .navbar-home .navbar-brand {
        font-size: 1.3rem;
    }
    
    .navbar-home .main-navigation {
        gap: 0.8rem;
    }
    
    .navbar-home .nav-link {
        font-size: 0.85rem;
    }
}
</style>

<?php if (isset($currentPage) && $currentPage === 'home'): ?>
<nav class="navbar-home">
    <div class="container">
        <a class="navbar-brand" href="/">marutto.art</a>
        
        <div class="main-navigation">
            <a href="/list.php" class="nav-link">Materials</a>
            <a href="/everyone-works.php" class="nav-link">Works</a>
            <a href="/compose/" class="nav-link">Atelier</a>
        </div>
    </div>
</nav>
<?php else: ?>
<nav class="navbar">
    <div class="container">
        <a class="navbar-brand" href="/">marutto.art</a>
        
        <div class="main-navigation">
            <a href="/list.php" class="nav-link<?= isset($currentPage) && $currentPage === 'list' ? ' active' : '' ?>">Materials</a>
            <a href="/everyone-works.php" class="nav-link<?= isset($currentPage) && $currentPage === 'everyone-works' ? ' active' : '' ?>">Works</a>
            <a href="/compose/" class="nav-link<?= isset($currentPage) && ($currentPage === 'compose' || $currentPage === 'index') ? ' active' : '' ?>">Atelier</a>
        </div>
    </div>
</nav>
<?php endif; ?>