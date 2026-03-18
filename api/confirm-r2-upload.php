<?php
/**
 * R2アップロード完了確認API
 * アップロード後にDBへの登録とpost_limits更新を行う
 */

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/r2_confirm_errors.log');

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function sendError($message, $code = 400, $details = null) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'details' => $details,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendSuccess($data) {
    echo json_encode([
        'success' => true,
        'data' => $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('POSTメソッドが必要です', 405);
    }

    // リクエストデータ取得
    $input = json_decode(file_get_contents('php://input'), true);
    $pngKey = $input['pngKey'] ?? '';
    $webpKey = $input['webpKey'] ?? '';
    $uniqueId = $input['uniqueId'] ?? '';
    $usedMaterialIds = trim($input['usedMaterialIds'] ?? '');
    $svgData = $input['svgData'] ?? null;
    
    // svgData が配列の場合はJSON文字列に変換
    if (is_array($svgData)) {
        $svgData = json_encode($svgData, JSON_UNESCAPED_UNICODE);
    }

    // バリデーション
    if (empty($pngKey) || empty($webpKey) || empty($uniqueId)) {
        sendError('必須パラメータが不足しています');
    }

    // 使用素材IDの検証
    if (!empty($usedMaterialIds)) {
        if (!preg_match('/^[0-9,]+$/', $usedMaterialIds)) {
            sendError('使用素材IDの形式が正しくありません');
        }
        if (mb_strlen($usedMaterialIds) > 1000) {
            sendError('使用素材IDが長すぎます');
        }
    }

    // データベース接続
    $pdo = getDB();
    $userIP = $_SERVER['REMOTE_ADDR'];
    $today = date('Y-m-d');

    // 注：投稿制限チェックは presigned URL 取得時に実施済み
    // R2 アップロード後の重複チェックは省略（無駄なファイルが残るのを防ぐため）
    
    // post_limits レコードを取得（更新用）
    $checkStmt = $pdo->prepare("
        SELECT post_count 
        FROM post_limits 
        WHERE ip_address = ? AND post_date = ?
    ");
    $checkStmt->execute([$userIP, $today]);
    $limitRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // R2の公開URLを構築
    $publicUrl = R2_PUBLIC_URL;
    if (empty($publicUrl)) {
        // デフォルトURLを使用
        $publicUrl = "https://image.marutto.art";
    }
    $pngUrl = rtrim($publicUrl, '/') . '/' . $pngKey;
    $webpUrl = rtrim($publicUrl, '/') . '/' . $webpKey;

    // デフォルトタイトル生成
    $defaultTitle = 'カスタム作品 ' . date('Y/m/d H:i');

    // トランザクション開始
    $pdo->beginTransaction();

    try {
        // community_artworks テーブルに挿入
        $stmt = $pdo->prepare("
            INSERT INTO community_artworks (
                title,
                pen_name,
                file_path,
                webp_path,
                svg_data,
                used_material_ids,
                ip_address,
                status,
                original_filename,
                file_hash,
                file_size,
                mime_type,
                created_at,
                updated_at
            ) VALUES (
                :title,
                :pen_name,
                :file_path,
                :webp_path,
                :svg_data,
                :used_material_ids,
                :ip_address,
                'approved',
                :original_filename,
                :file_hash,
                :file_size,
                :mime_type,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ':title' => $defaultTitle,
            ':pen_name' => '匿名',
            ':file_path' => $pngUrl,
            ':webp_path' => $webpUrl,
            ':svg_data' => $svgData,
            ':used_material_ids' => $usedMaterialIds ?: null,
            ':ip_address' => $userIP,
            ':original_filename' => basename($pngKey),
            ':file_hash' => hash('sha256', $pngKey . time()), // 仮のハッシュ値
            ':file_size' => 0, // R2からのファイルサイズは取得しない
            ':mime_type' => 'image/png',
        ]);

        $artworkId = $pdo->lastInsertId();

        // post_limits の更新
        if ($limitRecord) {
            $updateStmt = $pdo->prepare("
                UPDATE post_limits 
                SET post_count = post_count + 1, updated_at = NOW() 
                WHERE ip_address = ? AND post_date = ?
            ");
            $updateStmt->execute([$userIP, $today]);
        } else {
            $insertStmt = $pdo->prepare("
                INSERT INTO post_limits (ip_address, post_date, post_count, created_at, updated_at)
                VALUES (?, ?, 1, NOW(), NOW())
            ");
            $insertStmt->execute([$userIP, $today]);
        }

        // 使用素材の関連付け（material_usage テーブルがある場合）
        if (!empty($usedMaterialIds)) {
            $materialIdsArray = array_map('intval', explode(',', $usedMaterialIds));
            foreach ($materialIdsArray as $materialId) {
                if ($materialId > 0) {
                    try {
                        $usageStmt = $pdo->prepare("
                            INSERT IGNORE INTO material_usage (artwork_id, material_id, created_at)
                            VALUES (?, ?, NOW())
                        ");
                        $usageStmt->execute([$artworkId, $materialId]);
                    } catch (Exception $e) {
                        // material_usage テーブルが存在しない場合はスキップ
                        error_log("Material usage insert skipped: " . $e->getMessage());
                    }
                }
            }
        }

        $pdo->commit();

        sendSuccess([
            'artworkId' => $artworkId,
            'pngUrl' => $pngUrl,
            'webpUrl' => $webpUrl,
            'message' => '作品を投稿しました!'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in confirm-r2-upload: " . $e->getMessage());
    sendError('投稿処理中にエラーが発生しました: ' . $e->getMessage(), 500);
}
