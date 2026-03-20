<?php
// フッター用：最新15件の作品を取得
$pdo = getDB();
$footerArtworksSql = "SELECT id, title, webp_path, image_width, image_height FROM community_artworks 
    WHERE status = 'approved'
    ORDER BY created_at DESC 
    LIMIT 15";
$footerArtworksStmt = $pdo->prepare($footerArtworksSql);
$footerArtworksStmt->execute();
$footerArtworks = $footerArtworksStmt->fetchAll();
?>

<style>
.footer-custom {
    background: transparent;
    padding: 80px 0 20px;
    margin-top: 80px;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.footer-menu-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 40px;
}

.footer-menu-section a {
    color: #5A4A42;
    text-decoration: none;
    font-size: 0.95rem;
    font-weight: 500;
    transition: color 0.2s ease;
    display: block;
}

.footer-menu-section a:hover {
    color: #A0675C;
    text-decoration: none;
}

.footer-divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(160, 103, 92, 0.2), transparent);
    margin: 40px 0;
}

.footer-language {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 40px;
    align-items: center;
}

.footer-language a {
    color: #5A4A42;
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.2s ease;
}

.footer-language a:hover {
    color: #A0675C;
}

.footer-artworks-scroll {
    overflow: hidden;
    position: relative;
    height: 100px;
    margin: 40px 0;
    display: flex;
    align-items: center;
    width: 100vw;
    margin-left: calc(-50vw + 50%);
}

.footer-artworks-track {
    display: flex;
    gap: 16px;
    animation: footerScroll 180s linear infinite;
    will-change: transform;
}

.footer-artwork-item {
    width: 80px;
    height: 80px;
    flex-shrink: 0;
    border-radius: 8px;
    overflow: visible;
    background: white;
    padding: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.footer-artwork-item:hover {
    transform: scale(1.05);
}

.footer-artwork-item img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

@keyframes footerScroll {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-50%);
    }
}

.footer-copyright {
    text-align: center;
    color: #A0675C;
    font-size: 0.9rem;
    font-weight: 500;
    padding-top: 20px;
    border-top: 1px solid rgba(160, 103, 92, 0.2);
}

@media (max-width: 768px) {
    .footer-custom {
        padding: 60px 0 20px;
        margin-top: 60px;
    }
    
    .footer-menu-section {
        margin-bottom: 30px;
        gap: 10px;
    }
    
    .footer-menu-section a {
        font-size: 0.9rem;
    }
    
    .footer-artworks-scroll {
        height: 80px;
        margin: 30px 0;
    }
    
    .footer-artwork-item {
        width: 60px;
        height: 60px;
    }
    
    .footer-artworks-track {
        gap: 12px;
    }
}
</style>

<footer class="footer-custom">
    <div class="footer-content">
        <!-- 上段メニュー -->
        <div class="footer-menu-section">
            <a href="/compose/kids.php">こども向けのアトリエ</a>
            <a href="/blog/">使い方とブログ</a>
        </div>

        <div class="footer-divider"></div>

        <!-- 中段メニュー -->
        <div class="footer-menu-section">
            <a href="/terms-of-use.php">利用規約</a>
            <a href="/privacy-policy.php">プライバシーポリシー</a>
            <a href="/sitemap.php">サイトマップ</a>
        </div>

        <div class="footer-divider"></div>

        <!-- みんなの作品スクロール -->
        <?php if (!empty($footerArtworks)): ?>
        <div class="footer-artworks-scroll">
            <div class="footer-artworks-track">
                <?php 
                // 4回繰り返して途切れないようにする
                for ($i = 0; $i < 4; $i++):
                    foreach ($footerArtworks as $artwork): 
                        if (!empty($artwork['webp_path'])):
                            // フルURL（R2など）の場合はそのまま、相対パスの場合は先頭に / を追加
                            $footerArtworkUrl = (strpos($artwork['webp_path'], 'http://') === 0 || strpos($artwork['webp_path'], 'https://') === 0) ? $artwork['webp_path'] : '/' . $artwork['webp_path'];
                ?>
                    <a href="/everyone-work.php?id=<?= h($artwork['id']) ?>" class="footer-artwork-item">
                        <img src="<?= h($footerArtworkUrl) ?>" alt="<?= h($artwork['title']) ?>" loading="lazy">
                    </a>
                <?php 
                        endif;
                    endforeach;
                endfor;
                ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- コピーライト -->
        <div class="footer-copyright">
            © 2025 marutto.art
        </div>
    </div>
</footer>