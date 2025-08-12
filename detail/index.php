<?php
require_once '../config.php';
require_once '../includes/gdpr-banner-new.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM materials WHERE slug = ?");
$stmt->execute([$slug]);
$material = $stmt->fetch();

if (!$material) {
    header('HTTP/1.0 404 Not Found');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($material['title']) ?> - maruttoart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #ffffff;
        }
        .header-logo {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }
        .material-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .download-btn {
            font-size: 1.2rem;
            padding: 12px 30px;
        }
        .youtube-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%;
        }
        .youtube-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand header-logo" href="/">maruttoart</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/">戻る</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="/<?= h($material['image_path']) ?>" class="material-image mb-4" alt="<?= h($material['title']) ?>">
                        <a href="/<?= h($material['image_path']) ?>" download class="btn btn-success download-btn">
                            <i class="bi bi-download"></i> ダウンロード
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h1 class="card-title"><?= h($material['title']) ?></h1>
                        <p class="card-text"><?= nl2br(h($material['description'])) ?></p>
                        
                        <?php if (!empty($material['search_keywords_jp'])): ?>
                        <div class="mb-3">
                            <strong>キーワード：</strong>
                            <span class="text-muted"><?= h($material['search_keywords_jp']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <small class="text-muted">投稿日：<?= date('Y年m月d日', strtotime($material['upload_date'])) ?></small>
                        </div>
                    </div>
                </div>

                <?php if (!empty($material['youtube_url'])): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">関連動画</h5>
                    </div>
                    <div class="card-body">
                        <div class="youtube-container">
                            <?php
                            $youtube_url = $material['youtube_url'];
                            // YouTube URLをembed形式に変換
                            if (strpos($youtube_url, 'youtube.com/watch?v=') !== false) {
                                $video_id = substr($youtube_url, strpos($youtube_url, 'v=') + 2);
                                $embed_url = "https://www.youtube.com/embed/{$video_id}";
                            } else if (strpos($youtube_url, 'youtu.be/') !== false) {
                                $video_id = substr($youtube_url, strrpos($youtube_url, '/') + 1);
                                $embed_url = "https://www.youtube.com/embed/{$video_id}";
                            } else {
                                $embed_url = $youtube_url;
                            }
                            ?>
                            <iframe src="<?= h($embed_url) ?>" frameborder="0" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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
    // GDPR バナーを表示
    echo renderGDPRBanner();
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // GDPR 同意管理のJavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const acceptBtn = document.getElementById('gdpr-accept');
            const declineBtn = document.getElementById('gdpr-decline');
            const banner = document.getElementById('gdpr-banner');
            
            if (acceptBtn) {
                acceptBtn.addEventListener('click', function() {
                    handleGDPRConsent(true);
                });
            }
            
            if (declineBtn) {
                declineBtn.addEventListener('click', function() {
                    handleGDPRConsent(false);
                });
            }
        });
        
        function handleGDPRConsent(consent) {
            console.log('handleGDPRConsent called with:', consent);
            
            fetch('../gdpr-consent.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ consent: consent })
            })
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.status === 'success') {
                    const banner = document.getElementById('gdpr-banner');
                    if (banner) {
                        console.log('Hiding banner');
                        banner.style.display = 'none';
                        // ページをリロードして最新の状態を反映
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>
