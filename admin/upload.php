<?php
require_once '../config.php';
startAdminSession(); // 管理画面専用セッション開始
requireLogin();

// 管理画面はキャッシュ無効化
setNoCache();

$error = '';
$success = '';

// タグデータを取得
$pdo = getDB();
$tags = getAllTags($pdo);
$categories = getAllCategories($pdo);

// 画材データを取得
$stmt = $pdo->prepare("SELECT * FROM art_materials WHERE is_active = 1 ORDER BY sort_order, name");
$stmt->execute();
$art_materials = $stmt->fetchAll();

if ($_POST) {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $youtube_url = trim($_POST['youtube_url'] ?? '');
    $search_keywords = trim($_POST['search_keywords'] ?? '');
    $tag_ids = $_POST['tag_ids'] ?? [];
    $art_material_ids = $_POST['art_material_ids'] ?? [];
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    
    // バリデーション
    if (empty($title) || empty($slug)) {
        $error = 'タイトルとスラッグは必須です。';
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = '画像ファイルをアップロードしてください。';
    } else {
        // スラッグの重複チェック
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'そのスラッグは既に使用されています。';
        } else {
            // 画像アップロード
            $uploadResult = uploadImage($_FILES['image'], $slug);
            if ($uploadResult) {
                // データベースに保存
                $stmt = $pdo->prepare("
                    INSERT INTO materials (title, slug, description, youtube_url, search_keywords, image_path, webp_small_path, webp_medium_path, upload_date, category_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([
                    $title,
                    $slug,
                    $description,
                    $youtube_url,
                    $search_keywords,
                    $uploadResult['original'],
                    $uploadResult['webp_small'],
                    $uploadResult['webp_medium'],
                    date('Y-m-d'),
                    $category_id
                ])) {
                    // 登録した素材のIDを取得
                    $materialId = $pdo->lastInsertId();
                    
                    // タグを関連付け
                    if (!empty($tag_ids)) {
                        addMaterialTags($materialId, $tag_ids, $pdo);
                    }
                    
                    // 画材を関連付け
                    if (!empty($art_material_ids)) {
                        foreach ($art_material_ids as $art_material_id) {
                            $stmt = $pdo->prepare("INSERT INTO material_art_materials (material_id, art_material_id) VALUES (?, ?)");
                            $stmt->execute([$materialId, $art_material_id]);
                        }
                    }
                    
                    $success = '素材が正常にアップロードされました。';
                    // フォームをリセット
                    $_POST = [];
                } else {
                    $error = 'データベースの保存に失敗しました。';
                }
            } else {
                $error = '画像のアップロードに失敗しました。';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>素材アップロード - maruttoart 管理画面</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #ffffff;
        }
        .sidebar {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .main-content {
            background-color: #ffffff;
        }
        .preview-container {
            max-width: 300px;
            margin-top: 10px;
        }
        .preview-image {
            max-width: 100%;
            height: auto;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- サイドバー -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4>maruttoart</h4>
                        <small class="text-muted">管理画面</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/">
                                <i class="bi bi-house-door"></i> ダッシュボード
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="/admin/upload.php">
                                <i class="bi bi-plus-circle"></i> 素材アップロード
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/categories.php">
                                <i class="bi bi-folder"></i> カテゴリ管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/tags.php">
                                <i class="bi bi-tags"></i> タグ管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/">
                                <i class="bi bi-globe"></i> 公式サイト
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/logout.php">
                                <i class="bi bi-box-arrow-right"></i> ログアウト
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- メインコンテンツ -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">素材アップロード</h1>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= h($error) ?>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <?= h($success) ?>
                    <a href="/admin/" class="btn btn-sm btn-outline-success ms-2">ダッシュボードに戻る</a>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="mb-0">基本情報</h5>
                                <div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="testConnectionBtn">
                                        <i class="bi bi-wifi"></i> 接続テスト
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" id="autoGenerateBtn">
                                        <i class="bi bi-magic"></i> 自動設定
                                    </button>
                                </div>
                            </div>
                            
                            <!-- 自動設定の説明 -->
                            <div class="alert alert-info" id="autoGenerateInfo" style="display: none;">
                                <div class="d-flex align-items-center">
                                    <div class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></div>
                                    <span>OpenAI APIで画像を解析し、フォーム内容を自動設定しています...</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="title" class="form-label">タイトル *</label>
                                <input type="text" class="form-control" id="title" name="title" required value="<?= h($_POST['title'] ?? '') ?>">
                                <div class="form-text">タイトルと画像を入力後、「自動設定」ボタンで他の項目を自動入力できます</div>
                            </div>

                            <div class="mb-3">
                                <label for="image" class="form-label">画像ファイル *</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*" required onchange="previewImage(this)">
                                <div class="form-text">PNG, JPEG, GIF対応。WebPが自動生成されます。</div>
                                
                                <div class="preview-container">
                                    <img id="imagePreview" class="preview-image" style="display: none;" alt="プレビュー">
                                </div>
                            </div>

                            <hr>
                            
                            <div class="mb-3">
                                <label class="form-label">使用画材 <span class="text-danger">*</span></label>
                                <div class="row" id="artMaterialsContainer">
                                    <?php foreach ($art_materials as $material): ?>
                                        <div class="col-md-4 col-sm-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="art_material_ids[]" 
                                                       id="art_material_<?= $material['id'] ?>" value="<?= $material['id'] ?>"
                                                       <?= in_array($material['id'], $_POST['art_material_ids'] ?? []) ? 'checked' : '' ?>>
                                                <label class="form-check-label d-flex align-items-center" for="art_material_<?= $material['id'] ?>">
                                                    <?php if ($material['color_code']): ?>
                                                        <span class="badge me-2" style="background-color: <?= h($material['color_code']) ?>; width: 12px; height: 12px; border-radius: 50%;"></span>
                                                    <?php endif; ?>
                                                    <?= h($material['name']) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">使用した画材を選択してください。自動設定時の説明文生成に使用されます。</div>
                            </div>

                            <hr>

                            <div class="mb-3">
                                <label for="slug" class="form-label">スラッグ *</label>
                                <input type="text" class="form-control" id="slug" name="slug" required value="<?= h($_POST['slug'] ?? '') ?>" placeholder="例: peach-illustration">
                                <div class="form-text">URLで使用される識別子です。英数字とハイフンのみ使用可能です。</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">説明</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?= h($_POST['description'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="category_id" class="form-label">カテゴリ</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">カテゴリを選択してください</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                                <?= isset($_POST['category_id']) && $_POST['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                            <?= h($category['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">素材を分類するカテゴリを1つ選択してください</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">タグ選択</label>
                                <div class="row" id="tagsContainer">
                                    <?php foreach ($tags as $tag): ?>
                                        <div class="col-md-4 col-sm-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="tag_ids[]" 
                                                       id="tag_<?= $tag['id'] ?>" value="<?= $tag['id'] ?>"
                                                       <?= in_array($tag['id'], $_POST['tag_ids'] ?? []) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="tag_<?= $tag['id'] ?>">
                                                    <?= h($tag['name']) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">複数のタグを選択できます</div>
                            </div>

                            <div class="mb-3">
                                <label for="search_keywords" class="form-label">検索キーワード・SEO設定</label>
                                <textarea class="form-control" id="search_keywords" name="search_keywords" rows="4" placeholder='title_en=lemon,title_fr=citron,title_es=limón,title_nl=citroen,description_en=Fresh lemon watercolor illustration,description_fr=Illustration aquarelle de citron frais,fruit,yellow,citrus,水彩,果物,黄色,無料,素材,かわいい,手描き,イラスト,商用利用OK'><?= h($_POST['search_keywords'] ?? '') ?></textarea>
                                <div class="form-text">
                                    <strong>SEO推奨キーワード:</strong> 無料、素材、かわいい、手描き、水彩、イラスト、商用利用OK<br>
                                    構造化キーワード（title_en=英語タイトル、description_en=英語説明など）と通常のキーワードをカンマ区切りで入力
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="youtube_url" class="form-label">YouTube URL</label>
                                <input type="url" class="form-control" id="youtube_url" name="youtube_url" value="<?= h($_POST['youtube_url'] ?? 'https://www.youtube.com/embed/') ?>" placeholder="https://www.youtube.com/watch?v=...">
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between">
                                <a href="/admin/" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> 戻る
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-upload"></i> アップロード
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // タイトルからスラッグを自動生成
        document.getElementById('title').addEventListener('input', function() {
            const title = this.value;
            const slug = title.toLowerCase()
                .replace(/[^\w\s-]/g, '') // 特殊文字を除去
                .replace(/\s+/g, '-') // スペースをハイフンに
                .replace(/-+/g, '-') // 連続するハイフンを一つに
                .replace(/^-|-$/g, ''); // 前後のハイフンを除去
            document.getElementById('slug').value = slug;
        });

        // 接続テスト機能
        document.getElementById('testConnectionBtn').addEventListener('click', function() {
            const btn = this;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> テスト中...';
            
            fetch('/admin/api/test-connection.php')
            .then(response => {
                if (response.status === 401) {
                    throw new Error('ログインセッションが切れています。再ログインしてください。');
                }
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    document.getElementById('autoGenerateInfo').innerHTML = 
                        '<div class="d-flex align-items-center text-success">' +
                        '<i class="bi bi-check-circle me-2"></i>' +
                        '<span>接続テスト成功 - OpenAI: ' + data.openai_key_status + ', cURL: ' + data.curl_available + '</span>' +
                        '</div>';
                    document.getElementById('autoGenerateInfo').style.display = 'block';
                    setTimeout(() => {
                        document.getElementById('autoGenerateInfo').style.display = 'none';
                    }, 3000);
                } else {
                    throw new Error('接続テスト失敗');
                }
            })
            .catch(error => {
                console.error('Connection test error:', error);
                document.getElementById('autoGenerateInfo').innerHTML = 
                    '<div class="d-flex align-items-center text-danger">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    '<span>接続テスト失敗: ' + error.message + '</span>' +
                    '</div>';
                document.getElementById('autoGenerateInfo').style.display = 'block';
                setTimeout(() => {
                    document.getElementById('autoGenerateInfo').style.display = 'none';
                }, 5000);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });

        // 自動設定機能
        document.getElementById('autoGenerateBtn').addEventListener('click', function() {
            const title = document.getElementById('title').value.trim();
            const imageFile = document.getElementById('image').files[0];
            
            if (!title) {
                alert('タイトルを入力してください。');
                return;
            }
            
            if (!imageFile) {
                alert('画像ファイルを選択してください。');
                return;
            }
            
            // ボタンを無効化し、ローディング表示
            const btn = this;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> 生成中...';
            
            // 情報パネルを表示
            document.getElementById('autoGenerateInfo').style.display = 'block';
            
            // FormDataを作成
            const formData = new FormData();
            formData.append('title', title);
            formData.append('image', imageFile);
            
            // 選択された画材情報を追加
            const selectedArtMaterials = [];
            const artMaterialCheckboxes = document.querySelectorAll('input[name="art_material_ids[]"]:checked');
            artMaterialCheckboxes.forEach(checkbox => {
                selectedArtMaterials.push({
                    id: checkbox.value,
                    name: checkbox.nextElementSibling.textContent.trim()
                });
            });
            formData.append('art_materials', JSON.stringify(selectedArtMaterials));
            
            // OpenAI APIを呼び出し
            fetch('/admin/api/auto-generate.php', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const responseText = await response.text();
                let responseData;
                
                try {
                    responseData = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response text:', responseText);
                    throw new Error(`レスポンスの解析に失敗しました。Status: ${response.status}, Content: ${responseText.substring(0, 200)}...`);
                }
                
                if (response.status === 401) {
                    throw new Error('ログインセッションが切れています。再ログインしてください。');
                }
                
                if (!response.ok) {
                    // エラーレスポンスでもJSONデータを返す
                    const error = new Error(`HTTP error! status: ${response.status}`);
                    error.responseData = responseData;
                    throw error;
                }
                
                return responseData;
            })
            .then(data => {
                if (data.success) {
                    // フォームに生成されたデータを設定
                    if (data.data.slug) {
                        document.getElementById('slug').value = data.data.slug;
                    }
                    
                    if (data.data.description) {
                        document.getElementById('description').value = data.data.description;
                    }
                    
                    if (data.data.category_id) {
                        document.getElementById('category_id').value = data.data.category_id;
                    }
                    
                    if (data.data.search_keywords) {
                        document.getElementById('search_keywords').value = data.data.search_keywords;
                    }
                    
                    // タグの設定
                    if (data.data.tag_ids && Array.isArray(data.data.tag_ids)) {
                        // 既存のタグチェックを全て解除
                        const tagCheckboxes = document.querySelectorAll('input[name="tag_ids[]"]');
                        tagCheckboxes.forEach(checkbox => {
                            checkbox.checked = false;
                        });
                        
                        // 生成されたタグをチェック
                        data.data.tag_ids.forEach(tagId => {
                            const checkbox = document.getElementById('tag_' + tagId);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                    }
                    
                    // 検索キーワードに構造化データを設定
                    if (data.data.search_keywords) {
                        let keywords = data.data.search_keywords;
                        
                        // 多言語データがある場合は構造化キーワードを追加
                        if (data.data.multilingual) {
                            const ml = data.data.multilingual;
                            const structuredKeywords = [];
                            
                            if (ml.en_title) structuredKeywords.push(`title_en=${ml.en_title}`);
                            if (ml.en_description) structuredKeywords.push(`description_en=${ml.en_description}`);
                            if (ml.es_title) structuredKeywords.push(`title_es=${ml.es_title}`);
                            if (ml.es_description) structuredKeywords.push(`description_es=${ml.es_description}`);
                            if (ml.fr_title) structuredKeywords.push(`title_fr=${ml.fr_title}`);
                            if (ml.fr_description) structuredKeywords.push(`description_fr=${ml.fr_description}`);
                            if (ml.nl_title) structuredKeywords.push(`title_nl=${ml.nl_title}`);
                            if (ml.nl_description) structuredKeywords.push(`description_nl=${ml.nl_description}`);
                            
                            if (structuredKeywords.length > 0) {
                                keywords = structuredKeywords.join(',') + ',' + keywords;
                            }
                        }
                        
                        document.getElementById('search_keywords').value = keywords;
                    }
                    
                    // 成功メッセージを表示
                    document.getElementById('autoGenerateInfo').innerHTML = 
                        '<div class="d-flex align-items-center text-success">' +
                        '<i class="bi bi-check-circle me-2"></i>' +
                        '<span>自動設定が完了しました！内容を確認して必要に応じて調整してください。</span>' +
                        '</div>';
                    
                    // 3秒後に情報パネルを非表示
                    setTimeout(() => {
                        document.getElementById('autoGenerateInfo').style.display = 'none';
                    }, 3000);
                    
                } else {
                    // エラーメッセージを表示
                    let errorMsg = data.error || '不明なエラーが発生しました';
                    
                    document.getElementById('autoGenerateInfo').innerHTML = 
                        '<div class="alert alert-danger">' +
                        '<div class="d-flex align-items-center">' +
                        '<i class="bi bi-exclamation-triangle me-2"></i>' +
                        '<span>エラー: ' + errorMsg + '</span>' +
                        '</div>' +
                        (data.debug ? 
                            '<details class="mt-2"><summary>詳細情報</summary>' +
                            '<pre style="font-size: 0.8em; margin-top: 10px;">' + JSON.stringify(data.debug, null, 2) + '</pre>' +
                            '</details>' : '') +
                        '</div>';
                        
                    // 15秒後に情報パネルを非表示
                    setTimeout(() => {
                        document.getElementById('autoGenerateInfo').style.display = 'none';
                    }, 15000);
                }
            })
            .catch(error => {
                console.error('Error details:', error);
                let errorMessage = '通信エラーが発生しました';
                let debugInfo = {};
                
                if (error.responseData) {
                    // サーバーからエラーレスポンスを受け取った場合
                    errorMessage = error.responseData.error || 'サーバーエラーが発生しました';
                    debugInfo = error.responseData.debug || {};
                } else if (error.name === 'TypeError' && error.message.includes('fetch')) {
                    errorMessage = 'ネットワークエラー: サーバーに接続できませんでした';
                } else if (error.message.includes('HTTP error')) {
                    errorMessage = 'サーバーエラー: ' + error.message;
                } else {
                    errorMessage = 'エラー: ' + error.message;
                }
                
                document.getElementById('autoGenerateInfo').innerHTML = 
                    '<div class="alert alert-danger">' +
                    '<div class="d-flex align-items-center">' +
                    '<i class="bi bi-exclamation-triangle me-2"></i>' +
                    '<span>' + errorMessage + '</span>' +
                    '</div>' +
                    '<details class="mt-2"><summary>詳細情報</summary>' +
                    '<pre style="font-size: 0.8em; margin-top: 10px;">' + JSON.stringify(debugInfo, null, 2) + '</pre>' +
                    '</details>' +
                    '</div>';
                    
                // 15秒後に情報パネルを非表示
                setTimeout(() => {
                    document.getElementById('autoGenerateInfo').style.display = 'none';
                }, 15000);
            })
            .finally(() => {
                // ボタンを再度有効化
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        });
    </script>
</body>
</html>
</body>
</html>
