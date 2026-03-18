<?php
require_once '../config.php';
require_once '../includes/r2-utils.php'; // R2アップロード用
requireLogin();
setNoCache();

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/calendar_upload_errors.log');

// OpenAI設定ファイルを読み込み
if (file_exists(__DIR__ . '/../includes/openai.php')) {
    require_once __DIR__ . '/../includes/openai.php';
}

$pdo = getDB();
$isEdit = isset($_GET['id']);
$item = null;
$notice = null;

function findNearestAvailableDate(PDO $pdo, int $year, int $month, int $day, ?int $excludeId = null, int $rangeDays = 365): ?array {
    $baseDate = DateTime::createFromFormat('Y-n-j', sprintf('%d-%d-%d', $year, $month, $day));
    if (!$baseDate) {
        return null;
    }

    $checkSql = "SELECT COUNT(*) FROM calendar_items WHERE year = ? AND month = ? AND day = ?";
    if ($excludeId !== null) {
        $checkSql .= " AND id != ?";
    }
    $checkStmt = $pdo->prepare($checkSql);

    for ($offset = 1; $offset <= $rangeDays; $offset++) {
        foreach ([+$offset, -$offset] as $diff) {
            $candidate = clone $baseDate;
            $candidate->modify(($diff > 0 ? '+' : '') . $diff . ' day');
            $cYear = (int)$candidate->format('Y');
            $cMonth = (int)$candidate->format('n');
            $cDay = (int)$candidate->format('j');

            $params = [$cYear, $cMonth, $cDay];
            if ($excludeId !== null) {
                $params[] = $excludeId;
            }
            $checkStmt->execute($params);
            if ($checkStmt->fetchColumn() == 0) {
                return ['year' => $cYear, 'month' => $cMonth, 'day' => $cDay];
            }
        }
    }

    return null;
}

function findNearestAvailableDateInYear(PDO $pdo, int $year, int $month, int $day, int $rangeDays = 10, ?int $excludeId = null, string $minDate = '1980-06-15'): ?array {
    if (!checkdate($month, $day, $year)) {
        $day = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
    }

    $baseDate = DateTime::createFromFormat('Y-n-j', sprintf('%d-%d-%d', $year, $month, $day));
    if (!$baseDate) {
        return null;
    }

    $checkSql = "SELECT COUNT(*) FROM calendar_items WHERE year = ? AND month = ? AND day = ?";
    if ($excludeId !== null) {
        $checkSql .= " AND id != ?";
    }
    $checkStmt = $pdo->prepare($checkSql);

    for ($offset = 0; $offset <= $rangeDays; $offset++) {
        foreach ($offset === 0 ? [0] : [+$offset, -$offset] as $diff) {
            $candidate = clone $baseDate;
            $candidate->modify(($diff > 0 ? '+' : '') . $diff . ' day');

            if ((int)$candidate->format('Y') !== $year) {
                continue;
            }

            $targetDate = $candidate->format('Y-m-d');
            if ($targetDate < $minDate) {
                continue;
            }

            $cYear = (int)$candidate->format('Y');
            $cMonth = (int)$candidate->format('n');
            $cDay = (int)$candidate->format('j');

            $params = [$cYear, $cMonth, $cDay];
            if ($excludeId !== null) {
                $params[] = $excludeId;
            }
            $checkStmt->execute($params);
            if ($checkStmt->fetchColumn() == 0) {
                return ['year' => $cYear, 'month' => $cMonth, 'day' => $cDay];
            }
        }
    }

    return null;
}

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
            // AI自動設定フラグ（先に取得）
            $autoDate = isset($_POST['auto_date']);
            
            $data = [
                'year' => $inputYear,
                'month' => $inputMonth,
                'day' => $inputDay,
                'title' => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'is_published' => isset($_POST['is_published']) ? 1 : 0,
                'date_reason' => trim($_POST['date_reason'] ?? '') ?: null
            ];
            
            // AI自動設定がOFFの場合のみ、フォーム入力日付の重複チェック
            // （AI自動設定がONの場合は、AIが選んだ日付で後でチェック）
            if (!$autoDate || $isEdit) {
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
                    $nearest = findNearestAvailableDate($pdo, $data['year'], $data['month'], $data['day'], $isEdit ? (int)$item['id'] : null);
                    if ($nearest) {
                        $data['year'] = $nearest['year'];
                        $data['month'] = $nearest['month'];
                        $data['day'] = $nearest['day'];
                        $notice = sprintf('指定日が埋まっていたため、最寄りの空き日付（%d年%d月%d日）に自動調整しました。', $data['year'], $data['month'], $data['day']);
                    } else {
                        $error = sprintf('%d年%d月%d日は既に登録されています。空き日付が見つからないため登録できません。', $data['year'], $data['month'], $data['day']);
                    }
                }
            }
            
            // 重複エラーがある場合は画像アップロード処理をスキップ
            if (!isset($error)) {
            
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
                
                // 編集時は古い画像を削除（R2対応）
                if ($isEdit && !empty($item['image_path'])) {
                    deleteFile($item['image_path'], '../');
                    if (!empty($item['thumbnail_path'])) {
                        deleteFile($item['thumbnail_path'], '../');
                    }
                }
                
                // 新規作成でAI自動設定がONの場合、AIで適切な月日を提案
                if (!$isEdit && $autoDate && function_exists('suggestCalendarDate')) {
                    try {
                        $dateInfo = suggestCalendarDate($tempPath);

                        $currentYear = (int)date('Y');
                        $suggestedMonth = (int)$dateInfo['month'];
                        $suggestedDay = (int)$dateInfo['day'];

                        // 当年の前後10日以内で最も近い空き日付を探す
                        $selectedDate = findNearestAvailableDateInYear($pdo, $currentYear, $suggestedMonth, $suggestedDay, 10);

                        // 当年に空きがない場合、過去に遡って前後10日以内を探す
                        if (!$selectedDate) {
                            for ($year = $currentYear - 1; $year >= 1980; $year--) {
                                $selectedDate = findNearestAvailableDateInYear($pdo, $year, $suggestedMonth, $suggestedDay, 10);
                                if ($selectedDate) {
                                    break;
                                }
                            }
                        }

                        if (!$selectedDate) {
                            // 一時ファイルを削除
                            if (file_exists($tempPath)) {
                                unlink($tempPath);
                            }
                            throw new Exception('空き日付が見つからないため自動設定に失敗しました。');
                        }

                        $data['year'] = $selectedDate['year'];
                        $data['month'] = $selectedDate['month'];
                        $data['day'] = $selectedDate['day'];
                        // フォームで入力されていない場合のみAIの理由を使用
                        if (empty($data['date_reason'])) {
                            $data['date_reason'] = $dateInfo['reason'] ?? null;
                        }

                        if ($data['year'] !== $currentYear || $data['month'] !== $suggestedMonth || $data['day'] !== $suggestedDay) {
                            $notice = sprintf('AI提案日が埋まっていたため、最寄りの空き日付（%d年%d月%d日）に自動調整しました。', $data['year'], $data['month'], $data['day']);
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
                
                // R2にアップロード（年月ディレクトリなし）
                $dateSlug = sprintf('%04d-%02d-%02d', $data['year'], $data['month'], $data['day']);
                
                $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $fileName = $dateSlug . '_' . time() . '.' . $extension;
                $r2Key = 'calendar/' . $fileName;
                
                // MIMEタイプを決定
                $mimeTypes = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp'
                ];
                $contentType = $mimeTypes[$extension] ?? 'image/jpeg';
                
                // R2にアップロード
                $uploadResult = uploadFileToR2($tempPath, $r2Key, $contentType);
                
                if (!$uploadResult) {
                    throw new Exception('R2へのアップロードに失敗しました。');
                }
                
                $uploadedImagePath = $uploadResult['url'];
                error_log('calendar upload to R2: ' . $uploadedImagePath);
                
                // サムネイル生成（300x300px）- PNGの場合はWebPに変換
                $thumbnailExtension = ($extension === 'png') ? 'webp' : $extension;
                $thumbnailFileName = $dateSlug . '_thumb_' . time() . '.' . $thumbnailExtension;
                $thumbnailTempPath = sys_get_temp_dir() . '/thumb_' . time() . '.' . $thumbnailExtension;
                
                if (createThumbnail($tempPath, $thumbnailTempPath, 300, 300)) {
                    $thumbnailR2Key = 'calendar/' . $thumbnailFileName;
                    $thumbnailContentType = ($thumbnailExtension === 'webp') ? 'image/webp' : $contentType;
                    
                    $thumbnailUploadResult = uploadFileToR2($thumbnailTempPath, $thumbnailR2Key, $thumbnailContentType);
                    
                    if ($thumbnailUploadResult) {
                        $uploadedThumbnailPath = $thumbnailUploadResult['url'];
                    }
                    
                    // サムネイルの一時ファイルを削除
                    if (file_exists($thumbnailTempPath)) {
                        unlink($thumbnailTempPath);
                    }
                }
                
                // 新規作成で画像がアップロードされた場合、AIでタイトルと説明文を生成
                if (!$isEdit && function_exists('generateCalendarContent')) {
                    try {
                        $userHint = !empty($data['title']) ? $data['title'] : '';
                        $generatedContent = generateCalendarContent($tempPath, $userHint);
                        
                        // AIが生成したタイトルと説明文で上書き
                        $data['title'] = $generatedContent['title'];
                        $data['description'] = $generatedContent['description'];
                    } catch (Exception $e) {
                        error_log('AIコンテンツ生成エラー: ' . $e->getMessage());
                        // エラーが発生してもユーザーが入力した内容は保持
                    }
                }
                
                // 一時ファイルを削除
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
                
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
            // GIFアップロード処理
            $uploadedGifPath = $isEdit ? $item['gif_path'] : null;
            if (isset($_FILES['gif']) && $_FILES['gif']['error'] === UPLOAD_ERR_OK) {
            try {
                $fileInfo = pathinfo($_FILES['gif']['name']);
                $extension = strtolower($fileInfo['extension']);
                
                if ($extension !== 'gif') {
                    throw new Exception('GIFファイルのみアップロード可能です。');
                }
                
                // 一時ファイルに保存
                $tempGifPath = '../uploads/calendar/temp_gif_' . time() . '.gif';
                if (!move_uploaded_file($_FILES['gif']['tmp_name'], $tempGifPath)) {
                    throw new Exception('GIFファイルのアップロードに失敗しました。');
                }
                
                // 編集時は古いGIFを削除（R2対応）
                if ($isEdit && !empty($item['gif_path'])) {
                    deleteFile($item['gif_path'], '../');
                }
                
                // R2にアップロード（年月ディレクトリなし）
                $dateSlug = sprintf('%04d-%02d-%02d', $data['year'], $data['month'], $data['day']);
                $fileName = $dateSlug . '_anim_' . time() . '.gif';
                $r2Key = 'calendar/' . $fileName;
                
                $gifUploadResult = uploadFileToR2($tempGifPath, $r2Key, 'image/gif');
                
                if (!$gifUploadResult) {
                    throw new Exception('GIFのR2へのアップロードに失敗しました。');
                }
                
                $uploadedGifPath = $gifUploadResult['url'];
                
                // 一時ファイルを削除
                if (file_exists($tempGifPath)) {
                    unlink($tempGifPath);
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
                <?php if (!empty($notice)): ?>
                    <div class="alert alert-info"><?= h($notice) ?></div>
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
