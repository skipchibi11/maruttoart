<?php
/**
 * 構造化データ用の画像生成スクリプト
 * 背景色はOpenAIに画像を分析してもらい、適切なペールトーンを決定
 * サイズはスクエア1200px
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/openai.php';

// コマンドライン実行専用
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('このスクリプトはコマンドラインからのみ実行可能です。');
}

/**
 * OpenAIに画像を分析してもらい、適切なペールトーンの背景色を取得
 */
function getBackgroundColorFromAI($imagePath) {
    $config = getOpenAIConfig();
    
    if (empty($config['api_key'])) {
        throw new Exception('OpenAI APIキーが設定されていません');
    }
    
    // 画像をBase64エンコード
    if (!file_exists($imagePath)) {
        throw new Exception('画像ファイルが見つかりません: ' . $imagePath);
    }
    
    $imageData = base64_encode(file_get_contents($imagePath));
    $mimeType = mime_content_type($imagePath);

    $prompt = "この画像を分析して、構造化データ用の背景として最適なペールトーン（薄い色調）の背景色を1つ提案してください。

要件：
- 画像の主要な色調と調和する色
- ペールトーン（明度80%以上）
- 構造化データやソーシャルメディアでの表示に適した色
- 画像が見やすくなる色

回答は以下のJSONフォーマットのみで返してください。コードブロック（```）は使用せず、直接JSONを返してください：
{
  \"background_color\": \"#RRGGBB\",
  \"color_name\": \"色の名前（日本語）\",
  \"reasoning\": \"選択理由\"
}";

    $postData = [
        'model' => $config['model'],
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:{$mimeType};base64,{$imageData}"
                        ]
                    ]
                ]
            ]
        ],
        'max_tokens' => 300,
        'temperature' => 0.3
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $config['api_key'],
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("OpenAI API エラー: HTTP {$httpCode} - {$response}");
    }

    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('OpenAI APIから有効な応答が得られませんでした');
    }

    $content = trim($result['choices'][0]['message']['content']);
    
    // デバッグ用：元のレスポンスを表示
    echo "  OpenAI生レスポンス: " . $content . "\n";
    
    // JSONブロックが```json```で囲まれている場合は抽出
    if (preg_match('/```json\s*\n(.*?)\n```/s', $content, $matches)) {
        $content = trim($matches[1]);
        echo "  JSONブロックを抽出しました\n";
    } elseif (preg_match('/```\s*\n(.*?)\n```/s', $content, $matches)) {
        $content = trim($matches[1]);
        echo "  コードブロックを抽出しました\n";
    }
    
    // JSONレスポンスをパース
    $colorData = json_decode($content, true);
    if (!$colorData || !isset($colorData['background_color'])) {
        $jsonError = json_last_error_msg();
        throw new Exception("OpenAI APIから有効な色情報が得られませんでした。JSONエラー: {$jsonError}, 内容: {$content}");
    }

    return $colorData;
}

/**
 * 画像を1200x1200pxにリサイズし、背景色を適用（PNG形式で出力）
 */
function generateStructuredDataImage($inputPath, $outputPath, $backgroundColor) {
    if (!extension_loaded('gd')) {
        throw new Exception('GD拡張が必要です');
    }

    // 元画像を読み込み
    $imageInfo = getimagesize($inputPath);
    if (!$imageInfo) {
        throw new Exception('画像情報を取得できません: ' . $inputPath);
    }

    $srcWidth = $imageInfo[0];
    $srcHeight = $imageInfo[1];
    $imageType = $imageInfo[2];

    // 元画像リソースを作成
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $srcImage = imagecreatefromjpeg($inputPath);
            break;
        case IMAGETYPE_PNG:
            $srcImage = imagecreatefrompng($inputPath);
            break;
        case IMAGETYPE_WEBP:
            $srcImage = imagecreatefromwebp($inputPath);
            break;
        default:
            throw new Exception('サポートされていない画像形式です');
    }

    if (!$srcImage) {
        throw new Exception('画像の読み込みに失敗しました');
    }

    // 1200x1200の新しい画像を作成
    $destImage = imagecreatetruecolor(1200, 1200);
    
    // 背景色を設定
    $bgColor = hexToRgb($backgroundColor);
    $bgColorResource = imagecolorallocate($destImage, $bgColor['r'], $bgColor['g'], $bgColor['b']);
    imagefill($destImage, 0, 0, $bgColorResource);

    // 元画像のアスペクト比を保持してリサイズ
    $srcAspect = $srcWidth / $srcHeight;
    
    if ($srcAspect > 1) {
        // 横長の場合
        $newWidth = min(1200, $srcWidth);
        $newHeight = $newWidth / $srcAspect;
    } else {
        // 縦長または正方形の場合
        $newHeight = min(1200, $srcHeight);
        $newWidth = $newHeight * $srcAspect;
    }

    // 中央に配置するための座標計算
    $destX = (1200 - $newWidth) / 2;
    $destY = (1200 - $newHeight) / 2;

    // リサイズして描画
    imagecopyresampled(
        $destImage, $srcImage,
        $destX, $destY, 0, 0,
        $newWidth, $newHeight, $srcWidth, $srcHeight
    );

    // PNG形式で保存（イラストに最適・高品質）
    if (!imagepng($destImage, $outputPath, 6)) {
        throw new Exception('画像の保存に失敗しました: ' . $outputPath);
    }

    // メモリを解放
    imagedestroy($srcImage);
    imagedestroy($destImage);

    return true;
}

/**
 * HEXカラーをRGBに変換
 */
function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    
    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}

/**
 * メイン処理
 */
function main($materialId = null) {
    try {
        $pdo = getDB();
        
        // 処理対象の素材を取得
        if ($materialId) {
            $stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
            $stmt->execute([$materialId]);
            $materials = $stmt->fetchAll();
        } else {
            // 構造化データ用画像が未生成の素材を1件のみ取得（15分間隔実行に最適化）
            $stmt = $pdo->prepare("SELECT * FROM materials WHERE (structured_image_path IS NULL OR structured_image_path = '') ORDER BY created_at DESC LIMIT 1");
            $stmt->execute();
            $materials = $stmt->fetchAll();
        }

        if (empty($materials)) {
            echo "処理対象の素材がありません。全ての素材に構造化データ用画像が生成済みです。\n";
            return;
        }

        $processed = 0;
        $errors = 0;

        foreach ($materials as $material) {
            echo "処理中: ID {$material['id']} - {$material['title']}\n";
            
            try {
                $inputPath = dirname(__DIR__) . '/' . $material['image_path'];
                
                if (!file_exists($inputPath)) {
                    echo "  エラー: 元画像が見つかりません - {$inputPath}\n";
                    $errors++;
                    continue;
                }

                // OpenAIで背景色を決定
                echo "  OpenAIで背景色を分析中...\n";
                $colorData = getBackgroundColorFromAI($inputPath);
                echo "  背景色: {$colorData['background_color']} ({$colorData['color_name']})\n";
                echo "  理由: {$colorData['reasoning']}\n";

                // 構造化データ用画像を生成（uploads/year/month/slug形式）
                $createdDate = new DateTime($material['created_at']);
                $year = $createdDate->format('Y');
                $month = $createdDate->format('m');
                $slug = $material['slug'] ?? pathinfo($material['image_path'], PATHINFO_FILENAME);
                
                $outputDir = dirname(__DIR__) . "/uploads/structured/{$year}/{$month}";
                $outputFilename = $slug . 'structured.png';
                $outputPath = $outputDir . '/' . $outputFilename;
                $relativeOutputPath = "uploads/structured/{$year}/{$month}/{$outputFilename}";

                // 出力ディレクトリを作成
                if (!is_dir($outputDir)) {
                    if (!mkdir($outputDir, 0755, true)) {
                        throw new Exception('出力ディレクトリの作成に失敗しました: ' . $outputDir);
                    }
                }

                echo "  1200x1200画像を生成中...\n";
                generateStructuredDataImage($inputPath, $outputPath, $colorData['background_color']);

                // データベースを更新
                $updateStmt = $pdo->prepare("UPDATE materials SET structured_image_path = ?, structured_bg_color = ? WHERE id = ?");
                $updateStmt->execute([$relativeOutputPath, $colorData['background_color'], $material['id']]);

                echo "  完了: {$relativeOutputPath}\n";
                $processed++;

            } catch (Exception $e) {
                echo "  エラー: " . $e->getMessage() . "\n";
                $errors++;
                
                // OpenAI APIのレート制限エラーの場合は処理を中断
                if (strpos($e->getMessage(), 'rate limit') !== false || strpos($e->getMessage(), '429') !== false) {
                    echo "  OpenAI APIレート制限に達しました。次回実行時に再試行します。\n";
                    break;
                }
            }

            echo "\n";
        }

        echo "処理完了: {$processed}件成功, {$errors}件エラー\n";
        
        // 残りの未処理件数を表示
        $remainingStmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE (structured_image_path IS NULL OR structured_image_path = '')");
        $remainingStmt->execute();
        $remainingCount = $remainingStmt->fetchColumn();
        
        if ($remainingCount > 0) {
            echo "残り未処理件数: {$remainingCount}件\n";
        } else {
            echo "全ての素材の構造化データ用画像生成が完了しました。\n";
        }

    } catch (Exception $e) {
        echo "致命的エラー: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// スクリプト実行
if (isset($argv[1])) {
    // 特定の素材IDを指定
    main((int)$argv[1]);
} else {
    // 全ての未処理素材を処理
    main();
}