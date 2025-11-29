<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?>maruttoart - こどもアトリエ</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Hiragino Kaku Gothic ProN', 'ヒラギノ角ゴ ProN W3', 'Meiryo', 'メイリオ', sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #fff9e6 0%, #ffe6f0 50%, #e6f3ff 100%);
            min-height: 100vh;
        }

        .kids-navbar {
            background: linear-gradient(135deg, #ff6b9d 0%, #ffa06b 50%, #ffd93d 100%);
            padding: 1rem 0;
            box-shadow: 0 4px 12px rgba(255, 107, 157, 0.3);
            position: relative;
        }

        .kids-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }

        .kids-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            width: 100%;
        }
        
        .kids-brand-wrapper {
            flex: 0 0 auto;
        }
        
        .kids-tool-navigation {
            flex: 0 0 auto;
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .kids-brand {
            font-size: 1.8rem;
            font-weight: 900;
            color: white;
            text-decoration: none;
            text-shadow: 
                3px 3px 0 rgba(0, 0, 0, 0.2),
                -1px -1px 0 rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .kids-brand:hover {
            transform: scale(1.05);
            text-shadow: 
                4px 4px 0 rgba(0, 0, 0, 0.3),
                -2px -2px 0 rgba(255, 255, 255, 0.6);
        }



        .kids-tool-nav-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
            background: white;
            color: #ff6b9d;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            font-size: 1.2rem;
            font-weight: 700;
        }

        .kids-tool-nav-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(255, 107, 157, 0.3);
            background: #ff6b9d;
            color: white;
        }

        .kids-tool-nav-link.active {
            background: white;
            color: #ff6b9d;
        }

        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .kids-brand {
                font-size: 1.5rem;
            }

            .kids-tool-nav-link {
                padding: 0.6rem 1.2rem;
                font-size: 1.1rem;
            }

            .kids-container {
                padding: 0 15px;
            }

            .kids-header-content {
                gap: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .kids-brand {
                font-size: 1.2rem;
            }

            .kids-tool-nav-link {
                padding: 0.5rem 1rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>

<nav class="kids-navbar">
    <div class="kids-container">
        <div class="kids-header-content">
            <div class="kids-brand-wrapper">
                <a class="kids-brand" href="/compose2/kids.php">
                    まるっとあーと
                </a>
            </div>
            
            <div class="kids-tool-navigation">
                <a href="/compose2/kids.php" class="kids-tool-nav-link<?= $currentPage === 'kids' ? ' active' : '' ?>" title="つくる">
                    つくる
                </a>
                <a href="/kids-works.php" class="kids-tool-nav-link<?= $currentPage === 'kids-works' || $currentPage === 'kids-work' ? ' active' : '' ?>" title="みる">
                    みる
                </a>
            </div>
        </div>
    </div>
</nav>
