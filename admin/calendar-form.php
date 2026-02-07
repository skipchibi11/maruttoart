<?php
require_once '../config.php';
requireLogin();
setNoCache();

// OpenAI設定ファイルを読み込み
if (file_exists(__DIR__ . '/../includes/openai.php')) {
    require_once __DIR__ . '/../includes/openai.php';
}

$pdo = getDB();
$isEdit = isset($_GET['id']);
$item = null;

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM calendar_items WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $item = $stmt->fetch();
    
    if (!$item) {
        header('Location: calendar.php?error=item_not_found');
        exit;
    }
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'CSRFトークンが無効です。';
    } else {
        // 日付ピッカーの値を年月日に分割
        $calendarDate = $_POST['calendar_date'] ?? '';
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $calendarDate, $matches)) {
            $inputYear = (int)$matches[1];
            $inputMonth = (int)$matches[2];
            $inputDay = (int)$matches[3];
        } else {
            $error = '日付の形式が正しくありません。';
        }
        
        if (!isset($error)) {
            $data = [
                'year' => $inputYear,
                'month' => $inputMonth,
                'day' => $inputDay,
                'title' => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'is_published' => isset($_POST['is_published']) ? 1 : 0,
                'date_reason' => trim($_POST['date_reason'] ?? '') ?: null
            ];
            
            // 日付の重複チェック（編集時は自分自身を除外）
            $duplicateCheckSql = "SELECT COUNT(*) FROM calendar_items WHERE year = ? AND month = ? AND day = ?";
            if ($isEdit) {
                $duplicateCheckSql .= " AND id != ?";
                $duplicateStmt = $pdo->prepare($duplicateCheckSql);
                $duplicateStmt->execute([$data['year'], $data['month'], $data['day'], $item['id']]);
            } else {
                $duplicateStmt = $pdo->prepare($duplicateCheckSql);
                $duplicateStmt->execute([$data['year'], $data['month'], $data['day']]);
            }
            
            if ($duplicateStmt->fetchColumn() > 0) {
                $error = sprintf('%d年%d月%d日は既に登録されています。別の日付を選択してください。', $data['year'], $data['month'], $data['day']);
            }
            
            // 重複エラーがある場合は画像アップロード処理をスキップ
            if (!isset($error)) {
                // AI自動設定フラグ
                $autoDate = isset($_POST['auto_date']);
            
                // 画像アップロード処理
                $uploadedImagePath = $isEdit ? $item['image_path'] : null;
                $uploadedThumbnailPath = $isEdit ? $item['thumbnail_path'] : null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            try {
                $fileInfo = pathinfo($_FILES['image']['name']);
                $extension = strtolower($fileInfo['extension']);
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (!in_array($extension, $allowedExtensions)) {
                    throw new Exception('画像ファイル（JPG、PNG、WebP）のみアップロード可能です。');
                }
                
                // 一時的に画像を保存してAI分析用に使用
                $tempPath = '../uploads/calendar/temp_' . time() . '.' . $extension;
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $tempPath)) {
                    throw new Exception('ファイルのアップロードに失敗しました。');
                }
                
                // 新規作成でAI自動設定がONの場合、AIで適切な月日を提案
                if (!$isEdit && $autoDate && function_exists('suggestCalendarDate')) {
                    try {
                        $dateInfo = suggestCalendarDate($tempPath);
                        
                        // 1年先までの空き日付を取得し、AIに最適な日付を選ばせる
                        if (function_exists('findAvailableDateWithAI')) {
                            $selectedDate = findAvailableDateWithAI($pdo, $dateInfo['month'], $dateInfo['day'], $dateInfo['reason']);
                            $data['year'] = $selectedDate['year'];
                            $data['month'] = $selectedDate['month'];
                            $data['day'] = $selectedDate['day'];
                            // フォームで入力されていない場合のみAIの理由を使用
                            if (empty($data['date_reason'])) {
                                $data['date_reason'] = $selectedDate['reason'] ?? $dateInfo['reason'];
                            }
                            
                            // AI選択後の日付を再度重複チェック
                            $duplicateStmt = $pdo->prepare("SELECT COUNT(*) FROM calendar_items WHERE year = ? AND month = ? AND day = ?");
                            $duplicateStmt->execute([$data['year'], $data['month'], $data['day']]);
                            if ($duplicateStmt->fetchColumn() > 0) {
                                // 一時ファイルを削除
                                if (file_exists($tempPath)) {
                                    unlink($tempPath);
                                }
                                throw new Exception(sprintf('%d年%d月%d日は既に登録されています。空きがないため自動設定に失敗しました。', $data['year'], $data['month'], $data['day']));
                            }
                        }
                    } catch (Exception $e) {
                        error_log('AI日付提案エラー: ' . $e->getMessage());
                        $error = $e->getMessage();
                        // 一時ファイルを削除
                        if (isset($tempPath) && file_exists($tempPath)) {
                            unlink($tempPath);
                        }
                    }
                }
                
                // 年月フォルダ構造を作成（AI提案後の日付を使用）
                $yearMonthDir = sprintf('%04d/%02d', $data['year'], $data['month']);
                $uploadDir = '../uploads/calendar/' . $yearMonthDir . '/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // 古い画像を削除
                if ($isEdit && $item['image_path'] && file_exists('../' . $item['image_path'])) {
                    unlink('../' . $item['image_path']);
                    if ($item['thumbnail_path'] && file_exists('../' . $item['thumbnail_path'])) {
                        unlink('../' . $item['thumbnail_path']);
                    }
                }
                
                $dateSlug = sprintf('%04d-%02d-%02d', $data['year'], $data['month'], $data['day']);
                $fileName = $dateSlug . '_' . time() . '.' . $extension;
                $filePath = $uploadDir . $fileName;
                
                // 一時ファイルを最終的な場所に移動
                if (rename($tempPath, $filePath)) {
                    $uploadedImagePath = 'uploads/calendar/' . $yearMonthDir . '/' . $fileName;
                    
                    // サムネイル生成（300x300px）- PNGの場合はWebPに変換
                    $thumbnailExtension = ($extension === 'png') ? 'webp' : $extension;
                    $thumbnailFileName = $dateSlug . '_thumb_' . time() . '.' . $thumbnailExtension;
                    $thumbnailPath = $uploadDir . $thumbnailFileName;
                    if (createThumbnail($filePath, $thumbnailPath, 300, 300)) {
                        $uploadedThumbnailPath = 'uploads/calendar/' . $yearMonthDir . '/' . $thumbnailFileName;
                    }
                    
                    // 新規作成で画像がアップロードされた場合、AIでタイトルと説明文を生成
                    if (!$isEdit && function_exists('generateCalendarContent')) {
                        try {
                            $userHint = !empty($data['title']) ? $data['title'] : '';
                            $generatedContent = generateCalendarContent($filePath, $userHint);
                            
                            // AIが生成したタイトルと説明文で上書き
                            $data['title'] = $generatedContent['title'];
                            $data['description'] = $generatedContent['description'];
                        } catch (Exception $e) {
                            error_log('AIコンテンツ生成エラー: ' . $e->getMessage());
                            // エラーが発生してもユーザーが入力した内容は保持
                        }
                    }
                } else {
                    // 一時ファイルを削除
                    if (file_exists($tempPath)) {
                        unlink($tempPath);
                    }
                    throw new Exception('ファイルの移動に失敗しました。');
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
            // GIFアップロード処理
            $uploadedGifPath = $isEdit ? $item['gif_path'] : null;
            if (isset($_FILES['gif']) && $_FILES['gif']['error'] === UPLOAD_ERR_OK) {
            try {
                // 年月フォルダ構造を作成
                $yearMonthDir = sprintf('%04d/%02d', $data['year'], $data['month']);
                $uploadDir = '../uploads/calendar/' . $yearMonthDir . '/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileInfo = pathinfo($_FILES['gif']['name']);
                $extension = strtolower($fileInfo['extension']);
                
                if ($extension !== 'gif') {
                    throw new Exception('GIFファイルのみアップロード可能です。');
                }
                
                // 古いGIFを削除
                if ($isEdit && $item['gif_path'] && file_exists('../' . $item['gif_path'])) {
                    unlink('../' . $item['gif_path']);
                }
                
                $dateSlug = sprintf('%04d-%02d-%02d', $data['year'], $data['month'], $data['day']);
                $fileName = $dateSlug . '_anim_' . time() . '.gif';
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['gif']['tmp_name'], $filePath)) {
                    $uploadedGifPath = 'uploads/calendar/' . $yearMonthDir . '/' . $fileName;
                } else {
                    throw new Exception('GIFのアップロードに失敗しました。');
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
            }  // 重複エラーチェックの閉じ括弧
        
            $data['image_path'] = $uploadedImagePath;
            $data['thumbnail_path'] = $uploadedThumbnailPath;
            $data['gif_path'] = $uploadedGifPath;
        
            if (!isset($error)) {
                try {
                    if ($isEdit) {
                        $sql = "UPDATE calendar_items SET 
                                year = ?, month = ?, day = ?, title = ?, description = ?, date_reason = ?,
                                image_path = ?, thumbnail_path = ?, gif_path = ?, is_published = ?
                                WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            $data['year'], $data['month'], $data['day'], $data['title'], $data['description'], $data['date_reason'],
                            $data['image_path'], $data['thumbnail_path'], $data['gif_path'], $data['is_published'],
                            $item['id']
                        ]);
                        header('Location: calendar.php?success=updated');
                        exit;
                    } else {
                        $sql = "INSERT INTO calendar_items (year, month, day, title, description, date_reason, image_path, thumbnail_path, gif_path, is_published) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            $data['year'], $data['month'], $data['day'], $data['title'], $data['description'], $data['date_reason'],
                            $data['image_path'], $data['thumbnail_path'], $data['gif_path'], $data['is_published']
                        ]);
                        header('Location: calendar.php?success=created');
                        exit;
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// デフォルト値設定
$currentYear = $item['year'] ?? date('Y');
$currentMonth = $item['month'] ?? date('n');
$currentDay = $item['day'] ?? date('j');
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'カレンダー編集' : 'カレンダー作成' ?> - maruttoart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .preview-image {
            max-width: 300px;
            max-height: 300px;
            border-radius: 8px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">maruttoart 管理画面</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">ダッシュボード</a>
                <a class="nav-link" href="categories.php">カテゴリ</a>
                <a class="nav-link" href="tags.php">タグ</a>
                <a class="nav-link" href="artworks.php">みんなのアトリエ</a>
                <a class="nav-link" href="calendar.php">カレンダー</a>
                <a class="nav-link" href="logout.php">ログアウト</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="bi bi-calendar3"></i> <?= $isEdit ? 'カレンダー編集' : 'カレンダー作成' ?></h1>
                    <a href="calendar.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> 一覧に戻る
                    </a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= h($error) ?></div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                            <div class="mb-3">
                                <label class="form-label">
                                    日付 <span class="text-danger">*</span>
                </label>
                <input type="date" name="calendar_date" id="calendar_date" class="form-control" 
                       value="<?= sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $currentDay) ?>" 
                       required min="1980-06-15" max="<?= date('Y') + 1 ?>-12-31">
                <small class="form-text text-muted">1980年6月15日から<?= date('Y') + 1 ?>年末までの範囲で選択できます</small>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" name="auto_date" id="auto_date" class="form-check-input">
                <label class="form-check-label" for="auto_date">
                    AIに任せる（画像から季節を判定して適切な空いている日付を自動設定）
                            <div class="mb-3">
                                <label class="form-label">タイトル（簡単な説明）</label>
                                <input type="text" name="title" class="form-control" 
                                       value="<?= h($item['title'] ?? '') ?>"
                                       placeholder="例：りんごを持つペンギン">
                                <small class="form-text text-muted">
                                    新規作成時：ここに簡単な説明を入力してください。画像と合わせてAIが正式なタイトルと説明文を生成します
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">説明</label>
                                <textarea name="description" id="description" class="form-control" rows="8"><?= h($item['description'] ?? '') ?></textarea>
                                <small class="form-text text-muted">
                                    新規作成時：画像アップロード後、自動生成されます
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">季節の選定理由</label>
                                <textarea name="date_reason" id="date_reason" class="form-control" rows="3"><?= h($item['date_reason'] ?? '') ?></textarea>
                                <small class="form-text text-muted">
                                    「なぜ、このイラストはその月日なの？」に表示される内容です。AI自動設定を使用すると自動生成されますが、手動で編集できます。
                                </small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">画像</label>
                                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <?php if ($isEdit && $item['image_path']): ?>
                                    <img src="/<?= h($item['image_path']) ?>" class="preview-image" alt="現在の画像">
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">GIFアニメーション</label>
                                <input type="file" name="gif" class="form-control" accept="image/gif">
                                <?php if ($isEdit && $item['gif_path']): ?>
                                    <img src="/<?= h($item['gif_path']) ?>" class="preview-image" alt="現在のGIF">
                                <?php endif; ?>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" name="is_published" class="form-check-input" id="is_published" 
                                       <?= ($item['is_published'] ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_published">公開する</label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-lg"></i> <?= $isEdit ? '更新する' : '作成する' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
