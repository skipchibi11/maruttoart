<?php
require_once 'config.php';

// 公開ページなのでキャッシュを有効化
setPublicCache(86400, 172800); // 24時間 / CDN 48時間
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8053468089362860"
     crossorigin="anonymous"></script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>利用規約 - ミニマルなフリーイラスト素材（商用利用OK）｜maruttoart</title>
    <meta name="description" content="maruttoartのミニマルなフリーイラスト素材の利用規約。無料・商用利用可能な素材の使用条件、禁止事項、著作権について詳しく説明しています。">

    <!-- Site Icons -->
    <link rel="icon" href="/favicon.ico">
    
    <!-- Canonical tag -->
    <link rel="canonical" href="https://marutto.art/terms-of-use.php">
    
    <!-- Alternate language tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/terms-of-use.php" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/terms-of-use.php" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/terms-of-use.php" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/terms-of-use.php" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/terms-of-use.php" />
    <link rel="alternate" hreflang="zh-CN" href="https://marutto.art/zh-CN/terms-of-use.php" />
    <link rel="alternate" hreflang="ko" href="https://marutto.art/ko/terms-of-use.php" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/terms-of-use.php" />
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/terms-of-use.php">
    <meta property="og:title" content="利用規約 - maruttoart">
    <meta property="og:description" content="maruttoartのやさしいイラスト素材の利用規約。無料・商用利用可能な素材の使用条件について説明しています。">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary">
    <meta property="twitter:url" content="<?= h($_SERVER['REQUEST_SCHEME'] ?? 'https') ?>://<?= h($_SERVER['HTTP_HOST']) ?>/terms-of-use.php">
    <meta property="twitter:title" content="利用規約 - maruttoart">
    <meta property="twitter:description" content="maruttoartのやさしいイラスト素材の利用規約。無料・商用利用可能な素材の使用条件について説明しています。">
    
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
            line-height: 1.6;
            color: #222;
        }

        /* コンテナシステム */
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding-left: 15px;
            padding-right: 15px;
        }

        /* カードコンポーネント */
        .card {
            position: relative;
            display: flex;
            flex-direction: column;
            min-width: 0;
            word-wrap: break-word;
            background-color: #fff;
            background-clip: border-box;
            border: 1px solid rgba(0,0,0,.125);
            border-radius: 0.25rem;
            margin-bottom: 1.5rem;
        }

        .card-body {
            flex: 1 1 auto;
            padding: 2rem;
        }

        /* ユーティリティクラス */
        .mt-3 { margin-top: 1rem !important; }
        .mt-4 { margin-top: 1.5rem !important; }
        .mt-5 { margin-top: 3rem !important; }
        .mb-2 { margin-bottom: 0.5rem !important; }
        .mb-3 { margin-bottom: 1rem !important; }
        .mb-4 { margin-bottom: 1.5rem !important; }
        .py-4 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }

        /* テキストスタイル */
        h1 {
            color: #222;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 1rem;
        }

        h2 {
            color: #222;
            font-size: 1.5rem;
            font-weight: 500;
            margin-top: 2rem;
            margin-bottom: 1rem;
            border-left: 4px solid #007bff;
            padding-left: 1rem;
        }

        h3 {
            color: #222;
            font-size: 1.25rem;
            font-weight: 500;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }

        p {
            color: #222;
            margin-bottom: 1rem;
            text-align: justify;
        }

        ul, ol {
            color: #222;
            margin-bottom: 1rem;
            padding-left: 2rem;
        }

        li {
            margin-bottom: 0.5rem;
        }

        a {
            color: #007bff;
            text-decoration: none;
        }

        a:hover {
            color: #0056b3;
            text-decoration: underline;
        }

        /* 強調テキスト */
        .text-important {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 1rem;
            border-radius: 0.25rem;
            margin: 1rem 0;
        }

        .text-warning {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 1rem;
            border-radius: 0.25rem;
            margin: 1rem 0;
        }

        /* パンくずリスト */
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
            font-size: 0.875rem;
        }

        .breadcrumb-item + .breadcrumb-item::before {
            content: ">";
            color: #222;
        }

        .breadcrumb-item a {
            color: #222;
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: #0d6efd;
            text-decoration: underline;
        }

        .breadcrumb-item.active {
            color: #222;
        }

        /* フッターのスタイル */
        .footer-custom {
            background-color: #fef9e7 !important;
            color: #222;
        }

        .footer-custom .footer-text {
            color: #222 !important;
        }

        .footer-custom .footer-text:hover {
            color: #0d6efd !important;
            text-decoration: underline !important;
        }

        .btn {
            flex: 0 0 auto !important;
            white-space: nowrap !important;
        }

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
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
        }

        .btn:hover {
            color: #212529;
            text-decoration: none;
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
        
        /* レスポンシブ調整 */
        @media (max-width: 768px) {
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            .navbar-brand {
                font-size: 1.5rem;
            }
            h1 {
                font-size: 1.75rem;
            }
            h2 {
                font-size: 1.25rem;
            }
            .card-body {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            .card-body {
                padding: 1rem;
            }
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    
    <?php 
    $currentPage = 'terms-of-use';
    include 'includes/header.php'; 
    ?>
    
    <!-- パンくずリスト -->
    <div class="container mt-3">
        <nav aria-label="breadcrumb">
            <ol style="list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap;">
                <li style="margin-right: 0.5rem;">
                    <a href="/" style="color: #222; text-decoration: none;">ホーム</a>
                    <span style="margin-left: 0.5rem; color: #222;"> &gt; </span>
                </li>
                <li style="color: #222;">
                    利用規約
                </li>
            </ol>
        </nav>
    </div>
    
    <div class="container mt-4">
        <div class="card">
            <div class="card-body">
                <h1>利用規約</h1>
                
                <div class="text-important">
                    <p><strong>最終更新日：2025年8月29日</strong></p>
                    <p>本利用規約は、maruttoart（以下「当サイト」）が提供するやさしいイラスト素材の利用に関する条件を定めたものです。素材をダウンロード・利用される前に必ずお読みください。</p>
                </div>

                <h2>1. 基本的な利用条件</h2>
                
                <h3>1.1 無料利用</h3>
                <p>当サイトで提供するすべてのやさしいイラスト素材は、個人・法人問わず無料でご利用いただけます。</p>

                <h3>1.2 商用利用</h3>
                <p>商用利用が可能です。以下のような用途でご利用いただけます：</p>
                <ul>
                    <li>ウェブサイト、ブログ、SNSでの使用</li>
                    <li>印刷物（チラシ、パンフレット、名刺など）</li>
                    <li>商品パッケージ、広告・宣伝物</li>
                    <li>動画、プレゼンテーション資料</li>
                    <li>アプリケーション、ゲーム</li>
                    <li>その他の商業目的での使用</li>
                </ul>

                <h3>1.3 利用範囲</h3>
                <p>以下の範囲内でご利用ください：</p>
                <ul>
                    <li>素材の加工・編集は自由に行っていただけます</li>
                    <li>色の変更、サイズの調整、トリミングなど</li>
                    <li>他の素材との組み合わせ</li>
                    <li>複数の素材を組み合わせた新しい作品の制作</li>
                </ul>

                <h2>2. 禁止事項</h2>
                
                <p>以下の行為は禁止いたします：</p>

                <h3>2.1 素材の再配布・販売</h3>
                <ul>
                    <li>素材そのものを第三者に配布、販売すること</li>
                    <li>素材集やテンプレート集として販売すること</li>
                    <li>他の素材サイトへの転載</li>
                    <li>素材をメインコンテンツとして販売すること</li>
                </ul>

                <h3>2.2 権利の主張</h3>
                <ul>
                    <li>素材の著作権を自身のものと主張すること</li>
                    <li>商標登録、意匠登録などの知的財産権を取得すること</li>
                    <li>素材を作成者として発表すること</li>
                </ul>

                <h3>2.3 不適切な利用</h3>
                <ul>
                    <li>公序良俗に反する内容での使用</li>
                    <li>違法行為に関連する使用</li>
                    <li>他者の権利を侵害する使用</li>
                    <li>当サイトや作者の名誉を毀損する使用</li>
                    <li>政治的・宗教的な主張を目的とした使用</li>
                    <li>差別やヘイトスピーチに関連する使用</li>
                </ul>

                <h3>2.4 技術的制限の回避</h3>
                <ul>
                    <li>サイトへの過度なアクセスやスクレイピング</li>
                    <li>大量ダウンロードによるサーバーへの負荷をかける行為</li>
                </ul>

                <h2>3. 著作権について</h2>
                
                <h3>3.1 著作権の帰属</h3>
                <p>すべての素材の著作権は、たかせさとるに帰属します。</p>

                <h3>3.2 ライセンス</h3>
                <p>当サイトの素材は、本利用規約に従って利用する限り、著作権者の許諾を得たものとして取り扱います。</p>

                <h2>4. クレジット表記について</h2>
                
                <p>クレジット表記は<strong>任意</strong>です。ただし、以下の場合は表記をお願いいたします：</p>
                <ul>
                    <li>可能な範囲で「maruttoart」の表記をお願いします</li>
                    <li>表記例：「イラスト：maruttoart」「素材提供：maruttoart」</li>
                    <li>ウェブサイトURL（https://maruttoart.com）の記載も歓迎します</li>
                </ul>

                <div class="text-important">
                    <p><strong>注意：</strong>クレジット表記は義務ではありませんが、表記していただけると作者の励みになります。</p>
                </div>

                <h2>5. 免責事項</h2>
                
                <h3>5.1 品質保証</h3>
                <p>当サイトは素材の品質について可能な限り注意を払っておりますが、以下について保証いたしません：</p>
                <ul>
                    <li>素材の完全性、正確性</li>
                    <li>特定の目的への適合性</li>
                    <li>商業的価値</li>
                    <li>エラーや不具合がないこと</li>
                </ul>

                <h3>5.2 損害責任</h3>
                <p>素材の利用により生じた以下の損害について、当サイトは一切の責任を負いません：</p>
                <ul>
                    <li>直接的、間接的な損害</li>
                    <li>データの損失</li>
                    <li>営業上の損失</li>
                    <li>第三者からのクレーム</li>
                    <li>その他あらゆる損害</li>
                </ul>

                <h3>5.3 サービスの停止</h3>
                <p>当サイトは、事前の通知なく以下を行う場合があります：</p>
                <ul>
                    <li>サービスの一時停止・終了</li>
                    <li>素材の削除・変更</li>
                    <li>利用規約の変更</li>
                </ul>

                <h2>6. 第三者の権利</h2>
                
                <p>当サイトの素材には、以下が含まれる場合があります：</p>
                <ul>
                    <li>一般的なモチーフ（動物、植物、風景など）</li>
                    <li>幾何学模様、抽象的なデザイン</li>
                    <li>伝統的な文様やパターン</li>
                </ul>

                <div class="text-warning">
                    <p><strong>重要：</strong>素材に特定のキャラクター、ロゴ、商標などが含まれていないことを確認してからご利用ください。第三者の権利侵害については、利用者の責任となります。</p>
                </div>

                <h2>7. 利用規約の変更</h2>
                
                <p>当サイトは、必要に応じて本利用規約を変更する場合があります：</p>
                <ul>
                    <li>変更時は当サイト上で告知いたします</li>
                    <li>重要な変更の場合は、事前に告知期間を設けます</li>
                    <li>変更後も素材を利用された場合、新しい利用規約に同意したものとみなします</li>
                </ul>

                <h2>8. 準拠法・管轄</h2>
                
                <p>本利用規約は日本法に準拠し、当サイトに関する一切の紛争は、日本の裁判所を専属的管轄裁判所といたします。</p>

                <h2>9. お問い合わせ</h2>
                
                <p>本利用規約に関するご質問、素材の利用に関するお問い合わせは、以下の方法でご連絡ください：</p>
                <ul>
                    <li>ウェブサイト：<a href="/">maruttoart</a></li>
                    <li>メールアドレス：<a href="mailto:contact@marutto.art">contact@marutto.art</a></li>
                    <li>利用に関する不明点がございましたら、お気軽にお問い合わせください</li>
                </ul>

                <div class="text-important">
                    <h3>まとめ</h3>
                    <p>当サイトの素材は、<strong>無料</strong>で<strong>商用利用可能</strong>です。素材そのものの再配布や販売、権利の主張は禁止されていますが、加工や編集は自由に行っていただけます。ご不明な点がございましたら、お気軽にお問い合わせください。</p>
                </div>

                <p class="mt-4"><small>最終更新日：2025年8月29日</small></p>
            </div>
        </div>

    <?php include 'includes/footer.php'; ?>

</body>
</html>
