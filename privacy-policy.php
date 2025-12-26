<?php
require_once 'config.php';

// プライバシーポリシーは変更頻度が低いので長期キャッシュ
setPublicCache(86400, 172800); // 24時間 / CDN 48時間
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8053468089362860"
     crossorigin="anonymous"></script>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プライバシーポリシー - maruttoart</title>
    <link rel="icon" href="/favicon.ico">
    
    <!-- Canonical tag -->
    <link rel="canonical" href="https://marutto.art/privacy-policy.php">
    
    <!-- Alternate language tags -->
    <link rel="alternate" hreflang="ja" href="https://marutto.art/privacy-policy.php" />
    <link rel="alternate" hreflang="en" href="https://marutto.art/en/privacy-policy.php" />
    <link rel="alternate" hreflang="es" href="https://marutto.art/es/privacy-policy.php" />
    <link rel="alternate" hreflang="fr" href="https://marutto.art/fr/privacy-policy.php" />
    <link rel="alternate" hreflang="nl" href="https://marutto.art/nl/privacy-policy.php" />
    <link rel="alternate" hreflang="x-default" href="https://marutto.art/privacy-policy.php" />
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #ffffff;
        }
        .policy-section {
            margin-bottom: 2rem;
        }
        .policy-section h3 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        
        /* お問い合わせフォーム */
        .contact-form-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
        }
        
        .contact-form-section h3 {
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .contact-form-section .form-label {
            font-weight: 600;
            color: #495057;
        }
        
        .contact-form-section .btn-primary {
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .alert-custom {
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'privacy-policy';
    include 'includes/header.php'; 
    ?>

    <div class="container mt-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h1 class="mb-4">プライバシーポリシー</h1>
                <p class="text-muted">最終更新日: <?= date('Y-m-d') ?></p>

                <div class="policy-section">
                    <h3>1. 基本方針</h3>
                    <p>maruttoart（以下「当サイト」）は、ユーザーの個人情報およびプライバシーの保護を重要視し、以下の方針に従って個人情報を取り扱います。</p>
                </div>

                <div class="policy-section">
                    <h3>2. 収集する情報</h3>
                    <p>当サイトでは、以下の情報を収集する場合があります：</p>
                    
                    <h4>自動的に収集される情報</h4>
                    <ul>
                        <li><strong>アクセスログ情報</strong>: IPアドレス、ブラウザ情報、アクセス日時、参照元URL等</li>
                        <li><strong>利用状況情報</strong>: ページビュー、セッション時間、クリック行動、スクロール状況等</li>
                        <li><strong>デバイス情報</strong>: 画面解像度、OS、ブラウザの種類・バージョン等</li>
                        <li><strong>地理的情報</strong>: 国、地域レベルの位置情報（IPアドレスベース）</li>
                    </ul>
                    
                    <h4>Cookieおよび類似技術による情報</h4>
                    <ul>
                        <li><strong>Google AdSense Cookie</strong>: _gads, _gac_*, __gpi, IDE, test_cookie等</li>
                    </ul>
                    
                    <h4>検索エンジン経由の情報</h4>
                    <ul>
                        <li><strong>検索クエリ</strong>: Google Search Console経由の検索キーワード（集計データ）</li>
                        <li><strong>検索パフォーマンス</strong>: 表示回数、クリック数、平均掲載順位等</li>
                    </ul>
                    
                    <h4>お問い合わせフォーム経由で収集する情報</h4>
                    <p>お問い合わせフォームを通じて、ユーザーが任意で提供する以下の情報を収集します：</p>
                    <ul>
                        <li><strong>お名前</strong>: お問い合わせへの対応のため</li>
                        <li><strong>メールアドレス</strong>: 返信・連絡のため</li>
                        <li><strong>件名</strong>: お問い合わせ内容の分類のため</li>
                        <li><strong>お問い合わせ内容</strong>: ユーザーからのご質問・ご意見への対応のため</li>
                        <li><strong>IPアドレス、送信日時</strong>: セキュリティ目的（スパム・不正利用の防止）</li>
                    </ul>
                    <p><strong>注意：</strong>お問い合わせフォーム以外では、直接的に個人を特定できる情報（氏名、メールアドレス等）は収集しません。お問い合わせフォームから送信された情報は、ユーザーの任意の提供によるものです。</p>
                </div>

                <div class="policy-section">
                    <h3>3. 情報の利用目的</h3>
                    <p>収集した情報は、以下の目的で利用します：</p>
                    <ul>
                        <li><strong>サービスの提供・改善</strong>: ウェブサイトの基本機能、コンテンツ配信</li>
                        <li><strong>検索エンジン最適化</strong>: Google Search Consoleを使用した検索パフォーマンス分析</li>
                        <li><strong>広告配信・最適化</strong>: Google AdSenseを使用した関連性の高い広告配信、広告効果の測定</li>
                        <li><strong>お問い合わせ対応</strong>: お問い合わせフォームから送信された情報を用いた、ユーザーへの返信・対応</li>
                        <li><strong>セキュリティの維持・向上</strong>: 不正アクセスの検知・防止、スパム対策</li>
                        <li><strong>法的要件への対応</strong>: 法令に基づく情報開示等</li>
                    </ul>
                    <p><strong>データの処理根拠：</strong>当サイトは、正当な利益（サイト運営・改善・収益化・ユーザーサポート）およびユーザーの同意に基づいて情報を処理します。お問い合わせフォームで提供された個人情報は、ユーザーの明示的な同意に基づいて処理されます。</p>
                </div>

                <div class="policy-section">
                    <h3>4. Cookieについて</h3>
                    <p>当サイトでは、以下の種類のCookieを使用します：</p>
                    
                    <h4>必須Cookie</h4>
                    <ul>
                        <li>サイトの基本機能（ナビゲーション、セキュリティ等）に必要</li>
                        <li>これらのCookieは無効にできません</li>
                    </ul>
                    
                    <h4>広告Cookie</h4>
                    <ul>
                        <li><strong>Google AdSense</strong>: 興味に基づく広告配信、広告効果測定</li>
                        <li>これらのCookieにより、ユーザーの興味に関連する広告が表示されます</li>
                    </ul>
                    
                    <p><strong>Cookieの管理：</strong></p>
                    <ul>
                        <li>ブラウザの設定でCookieを管理できます</li>
                        <li>Cookieを無効にした場合、一部の機能が制限される場合があります</li>
                    </ul>
                </div>

                <div class="policy-section">
                    <h3>5. 第三者サービスについて</h3>
                    <p>当サイトでは、以下の第三者サービスを利用しています：</p>
                    
                    <h4>Google Search Console</h4>
                    <ul>
                        <li><strong>提供会社</strong>: Google LLC</li>
                        <li><strong>利用目的</strong>: サイトの検索パフォーマンス分析、検索エンジンでの表示改善</li>
                        <li><strong>収集される情報</strong>: 検索クエリ、クリック数、表示回数、検索順位等</li>
                        <li><strong>データの性質</strong>: 個人を特定しない集計データのみを使用</li>
                    </ul>
                    
                    <h4>Google AdSense</h4>
                    <ul>
                        <li><strong>提供会社</strong>: Google LLC</li>
                        <li><strong>利用目的</strong>: 興味に基づく広告配信サービス</li>
                        <li><strong>制御方法</strong>: ブラウザの設定でCookieを無効にできます</li>
                    </ul>
                    </ul>
                    
                    <h4>Google AdSense について</h4>
                    <ul>
                        <li><strong>提供会社</strong>: Google LLC</li>
                        <li><strong>利用目的</strong>: 当サイトの運営費をサポートするための広告配信</li>
                        <li><strong>広告の仕組み</strong>: ユーザーの興味や関心、ウェブ閲覧履歴に基づいた関連性の高い広告を表示</li>
                        <li><strong>収集される情報</strong>: 
                            <ul>
                                <li>ブラウザ情報、デバイス情報</li>
                                <li>サイト訪問履歴、閲覧パターン</li>
                                <li>地理的位置情報（国、地域レベル）</li>
                                <li>興味・関心カテゴリー</li>
                            </ul>
                        </li>
                        <li><strong>Cookie情報</strong>: 
                            <ul>
                                <li>Google AdSense Cookie: _gads, _gac_*, __gpi等</li>
                                <li>DoubleClick Cookie: IDE, test_cookie等</li>
                                <li>これらのCookieは広告配信の最適化と頻度制御に使用されます</li>
                            </ul>
                        </li>
                        <li><strong>広告のパーソナライゼーション</strong>: ユーザーの興味に基づいた関連性の高い広告が表示されます</li>
                        <li><strong>広告設定の変更</strong>: 
                            <ul>
                                <li><a href="https://www.google.com/settings/ads" target="_blank" rel="noopener">Google広告設定</a>でパーソナライズ広告をオフにできます</li>
                                <li><a href="https://optout.aboutads.info/" target="_blank" rel="noopener">Digital Advertising Alliance</a>でオプトアウト可能です</li>
                                <li>当サイトのCookie設定でAdSense Cookieを拒否できます</li>
                            </ul>
                    
                    <p><strong>重要な注意事項：</strong></p>
                    <ul>
                        <li>これらのサービスは、それぞれ独自のプライバシーポリシーに従って運営されています</li>
                        <li>詳細については<a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Googleプライバシーポリシー</a>をご確認ください</li>
                    </ul>
                </div>

                <div class="policy-section">
                    <h3>6. 個人情報の保護</h3>
                    <p>当サイトは、収集した情報について適切なセキュリティ対策を講じ、不正アクセス、紛失、破壊、改ざん、漏洩などを防止するよう努めます。</p>
                    
                    <h4>お問い合わせ情報の管理</h4>
                    <p>お問い合わせフォームから送信された個人情報は、以下のように管理されます：</p>
                    <ul>
                        <li><strong>保存期間</strong>: お問い合わせ対応完了後、最長1年間保存し、その後削除します</li>
                        <li><strong>アクセス制限</strong>: サイト管理者のみがアクセス可能です</li>
                        <li><strong>利用目的の制限</strong>: お問い合わせへの対応以外の目的では使用しません</li>
                        <li><strong>第三者提供の禁止</strong>: 法令に基づく場合を除き、第三者に提供することはありません</li>
                    </ul>
                </div>

                <div class="policy-section">
                    <h3>7. 個人情報の第三者提供</h3>
                    <p>当サイトは、法令に基づく場合を除き、ユーザーの同意なく個人情報を第三者に提供することはありません。お問い合わせフォームで提供された個人情報は、お問い合わせ対応の目的のみに使用し、マーケティングや広告配信等の目的では使用しません。</p>
                    
                    <h4>Google サービスでのデータ処理</h4>
                    <p>当サイトでは、以下のGoogleサービスで情報が処理されます：</p></p>
                    <ul>
                        <li><strong>Google AdSense</strong>: 広告配信最適化のための匿名化されたデータ</li>
                        <li><strong>Google Search Console</strong>: 検索パフォーマンスの集計データ</li>
                    </ul>
                    
                    <p><strong>データ転送について：</strong></p>
                    <ul>
                        <li>これらのサービスでは、日本国外（主に米国）でデータが処理される場合があります</li>
                        <li>Googleは適切なデータ保護措置を講じており、<a href="https://policies.google.com/privacy/frameworks" target="_blank" rel="noopener">プライバシーフレームワーク</a>に準拠しています</li>
                        <li>データは匿名化され、個人を特定することはできません</li>
                    </ul>
                </div>

                <div class="policy-section">
                    <h3>8. ユーザーの権利</h3>
                    <p>お問い合わせフォームを通じて個人情報を提供されたユーザーは、以下の権利を有します：</p>
                    <ul>
                        <li><strong>アクセス権</strong>: 提供した個人情報の開示を請求できます</li>
                        <li><strong>訂正権</strong>: 提供した個人情報が不正確な場合、訂正を請求できます</li>
                        <li><strong>削除権（忘れられる権利）</strong>: 提供した個人情報の削除を請求できます</li>
                        <li><strong>利用停止権</strong>: 提供した個人情報の利用停止を請求できます</li>
                    </ul>
                    <p>これらの権利を行使される場合は、下記のお問い合わせフォームからご連絡ください。ただし、法令に基づく保存義務がある場合や、お問い合わせ対応中の情報については、権利の行使が制限される場合があります。</p>
                </div>

                <div class="policy-section">
                    <h3>9. プライバシーポリシーの変更</h3>
                    <p>当サイトは、必要に応じてプライバシーポリシーを変更する場合があります。重要な変更については、サイト上で通知いたします。最終更新日は本ページ上部に記載しています。</p>
                </div>

                <div class="policy-section">
                    <h3>10. お問い合わせ</h3>
                    <p>プライバシーポリシーやサイトに関するお問い合わせ、個人情報の開示・訂正・削除等のご請求は、以下のフォームからご連絡ください。</p>
                </div>

                <!-- お問い合わせフォーム -->
                <div class="contact-form-section mt-5">
                    <h3 class="text-center mb-4">お問い合わせフォーム</h3>
                    
                    <div id="contactFormMessages"></div>
                    
                    <form id="contactForm" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="contactName" class="form-label">お名前 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contactName" name="name" required minlength="2" maxlength="50">
                            <div class="invalid-feedback">お名前を入力してください（2文字以上）</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contactEmail" class="form-label">メールアドレス <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="contactEmail" name="email" required>
                            <div class="invalid-feedback">有効なメールアドレスを入力してください</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contactSubject" class="form-label">件名 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contactSubject" name="subject" required minlength="5" maxlength="100">
                            <div class="invalid-feedback">件名を入力してください（5文字以上）</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contactMessage" class="form-label">お問い合わせ内容 <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="contactMessage" name="message" rows="6" required minlength="10" maxlength="2000"></textarea>
                            <div class="invalid-feedback">お問い合わせ内容を入力してください（10文字以上）</div>
                            <div class="form-text"><span id="charCount">0</span> / 2000 文字</div>
                        </div>
                        
                        <!-- ハニーポット（ボット対策） -->
                        <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off">
                        
                        <!-- 簡単な算数問題（ボット対策） -->
                        <div class="mb-3">
                            <label for="mathAnswer" class="form-label">確認: <span id="mathQuestion"></span> = ? <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="mathAnswer" name="math_answer" required style="max-width: 150px;">
                            <div class="invalid-feedback">正しい答えを入力してください</div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitContactBtn">
                                <i class="bi bi-send"></i> 送信する
                            </button>
                        </div>
                    </form>
                </div>

                <div class="text-center mt-5">
                    <a href="/" class="btn btn-outline-primary">ホームに戻る</a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- お問い合わせフォームのJavaScript -->
    <script>
    (function() {
        let mathAnswer = 0;
        
        // 算数問題を生成
        function generateMathQuestion() {
            const num1 = Math.floor(Math.random() * 10) + 1;
            const num2 = Math.floor(Math.random() * 10) + 1;
            mathAnswer = num1 + num2;
            document.getElementById('mathQuestion').textContent = num1 + ' + ' + num2;
        }
        
        // 文字数カウント
        const messageField = document.getElementById('contactMessage');
        const charCount = document.getElementById('charCount');
        
        if (messageField && charCount) {
            messageField.addEventListener('input', function() {
                charCount.textContent = this.value.length;
            });
        }
        
        // フォーム送信
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Bootstrap validation
                if (!contactForm.checkValidity()) {
                    e.stopPropagation();
                    contactForm.classList.add('was-validated');
                    return;
                }
                
                // 算数問題の確認
                const userAnswer = parseInt(document.getElementById('mathAnswer').value);
                if (userAnswer !== mathAnswer) {
                    showMessage('算数問題の答えが正しくありません。', 'danger');
                    return;
                }
                
                // ハニーポット確認（ボットは通常このフィールドを埋める）
                const honeypot = document.querySelector('input[name="website"]').value;
                if (honeypot) {
                    // ボットと判断されたが、エラーメッセージは出さない
                    showMessage('送信に失敗しました。', 'danger');
                    return;
                }
                
                const submitBtn = document.getElementById('submitContactBtn');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>送信中...';
                
                try {
                    const formData = new FormData(contactForm);
                    const response = await fetch('/api/contact.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showMessage('お問い合わせを送信しました。ご連絡ありがとうございます。', 'success');
                        contactForm.reset();
                        contactForm.classList.remove('was-validated');
                        charCount.textContent = '0';
                        generateMathQuestion(); // 新しい問題を生成
                    } else {
                        showMessage(result.message || '送信に失敗しました。時間をおいて再度お試しください。', 'danger');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showMessage('送信エラーが発生しました。時間をおいて再度お試しください。', 'danger');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            });
        }
        
        function showMessage(message, type) {
            const messagesDiv = document.getElementById('contactFormMessages');
            messagesDiv.innerHTML = `
                <div class="alert alert-${type} alert-custom alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            messagesDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        // 初期化
        generateMathQuestion();
    })();
    </script>
</body>
</html>
