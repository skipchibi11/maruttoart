<?php
/**
 * 画像ベクトル化 + 構造化データ用画像生成スクリプト
 * 素材に対して構造化データ用画像（未生成の場合）とベクトル化を一括処理
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/openai.php';

// ログファイルの設定
$logFile = __DIR__ . '/../logs/image_embedding.log';

/**
 * ログ出力関数
 */
function logMessage($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry;
}

// ===== 構造化データ用画像生成 =====

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

function getDominantColor($imagePath) {
    $imageInfo = @getimagesize($imagePath);
    if (!$imageInfo) return null;

    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($imagePath); break;
        case IMAGETYPE_PNG:  $image = imagecreatefrompng($imagePath);  break;
        case IMAGETYPE_WEBP: $image = imagecreatefromwebp($imagePath); break;
        default: return null;
    }
    if (!$image) return null;

    $sample = imagecreatetruecolor(50, 50);
    imagecopyresampled($sample, $image, 0, 0, 0, 0, 50, 50, imagesx($image), imagesy($image));

    $colors = [];
    for ($x = 0; $x < 50; $x++) {
        for ($y = 0; $y < 50; $y++) {
            $rgb = imagecolorat($sample, $x, $y);
            if ((($rgb >> 24) & 0x7F) > 100) continue;
            $key = sprintf('%02x%02x%02x', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
            $colors[$key] = ($colors[$key] ?? 0) + 1;
        }
    }
    imagedestroy($image);
    imagedestroy($sample);

    if (empty($colors)) return null;
    arsort($colors);
    return hexToRgb('#' . key($colors));
}

function getLuminance($rgb) {
    $r = $rgb['r'] / 255; $g = $rgb['g'] / 255; $b = $rgb['b'] / 255;
    $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
    $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
    $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

function getContrastRatio($color1, $color2) {
    $l1 = getLuminance($color1); $l2 = getLuminance($color2);
    return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
}

function getSafeBackgroundColor($dominantColor) {
    return getLuminance($dominantColor) > 0.5
        ? ['r' => 240, 'g' => 245, 'b' => 250]
        : ['r' => 255, 'g' => 253, 'b' => 245];
}

function getBackgroundColorFromAI($imagePath) {
    $config = getOpenAIConfig();
    if (empty($config['api_key'])) {
        throw new Exception('OpenAI APIキーが設定されていません');
    }

    $isRemoteUrl = (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0);
    if ($isRemoteUrl) {
        $imageUrlData = ['url' => $imagePath];
    } else {
        if (!file_exists($imagePath)) {
            throw new Exception('画像ファイルが見つかりません: ' . $imagePath);
        }
        $mimeType = mime_content_type($imagePath);
        $imageUrlData = ['url' => "data:{$mimeType};base64," . base64_encode(file_get_contents($imagePath))];
    }

    $prompt = "この画像を分析して、構造化データ用の背景として最適なペールトーン（薄い色調）の背景色を1つ提案してください。\n\n要件：\n- 画像の主要な色調と十分なコントラストを持つ色（同化を避ける）\n- ペールトーン（明度80%以上）で、かつ画像の主要色と明確に区別できる色\n- 画像が背景に埋もれず、輪郭がはっきり見える色\n- 構造化データやソーシャルメディアでの表示に適した色\n\n回答は以下のJSONフォーマットのみで返してください：\n{\n  \"background_color\": \"#RRGGBB\",\n  \"color_name\": \"色の名前（日本語）\",\n  \"reasoning\": \"選択理由\"\n}";

    $postData = [
        'model' => $config['model'],
        'messages' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => $imageUrlData]
            ]
        ]],
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("OpenAI API エラー: HTTP {$httpCode}");
    }

    $result = json_decode($response, true);
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('OpenAI APIから有効な応答が得られませんでした');
    }

    $content = trim($result['choices'][0]['message']['content']);
    $content = preg_replace('/```json\s*\n?(.*?)\n?```/s', '$1', $content);
    $content = preg_replace('/```\s*\n?(.*?)\n?```/s', '$1', $content);
    $colorData = json_decode(trim($content), true);

    if (!$colorData || !isset($colorData['background_color'])) {
        throw new Exception('OpenAI APIから有効な色情報が得られませんでした: ' . $content);
    }
    return $colorData;
}

function getOptimizedBackgroundColor($imagePath) {
    try {
        $aiResult = getBackgroundColorFromAI($imagePath);
        $dominantColor = getDominantColor($imagePath);
        if ($dominantColor) {
            $contrastRatio = getContrastRatio(hexToRgb($aiResult['background_color']), $dominantColor);
            if ($contrastRatio < 2.0) {
                $safe = getSafeBackgroundColor($dominantColor);
                $aiResult['background_color'] = sprintf('#%02x%02x%02x', $safe['r'], $safe['g'], $safe['b']);
                $aiResult['color_name'] = 'セーフカラー（コントラスト調整済み）';
            }
        }
        return $aiResult;
    } catch (Exception $e) {
        logMessage("背景色AI分析エラー: " . $e->getMessage(), 'WARN');
        return ['background_color' => '#f8f9fa', 'color_name' => 'ライトグレー（フォールバック）', 'reasoning' => 'AI分析エラーのためデフォルト色を使用'];
    }
}

function generateStructuredDataImage($inputPath, $outputPath, $backgroundColor) {
    if (!extension_loaded('gd')) throw new Exception('GD拡張が必要です');
    $imageInfo = getimagesize($inputPath);
    if (!$imageInfo) throw new Exception('画像情報を取得できません: ' . $inputPath);

    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG: $srcImage = imagecreatefromjpeg($inputPath); break;
        case IMAGETYPE_PNG:  $srcImage = imagecreatefrompng($inputPath);  break;
        case IMAGETYPE_WEBP: $srcImage = imagecreatefromwebp($inputPath); break;
        default: throw new Exception('サポートされていない画像形式です');
    }
    if (!$srcImage) throw new Exception('画像の読み込みに失敗しました');

    $destImage = imagecreatetruecolor(1200, 1200);
    $bgColor = hexToRgb($backgroundColor);
    imagefill($destImage, 0, 0, imagecolorallocate($destImage, $bgColor['r'], $bgColor['g'], $bgColor['b']));

    $srcAspect = $imageInfo[0] / $imageInfo[1];
    if ($srcAspect > 1) {
        $newWidth = min(1200, $imageInfo[0]); $newHeight = $newWidth / $srcAspect;
    } else {
        $newHeight = min(1200, $imageInfo[1]); $newWidth = $newHeight * $srcAspect;
    }

    imagecopyresampled($destImage, $srcImage, (1200 - $newWidth) / 2, (1200 - $newHeight) / 2, 0, 0, $newWidth, $newHeight, $imageInfo[0], $imageInfo[1]);

    if (!imagepng($destImage, $outputPath, 6)) throw new Exception('画像の保存に失敗しました: ' . $outputPath);
    imagedestroy($srcImage);
    imagedestroy($destImage);
    return true;
}

/**
 * 素材1件の構造化データ用画像を生成してDBを更新
 */
function generateStructuredImageForMaterial($pdo, $material) {
    $isRemoteUrl = (strpos($material['image_path'], 'http://') === 0 || strpos($material['image_path'], 'https://') === 0);

    if ($isRemoteUrl) {
        $tempFile = sys_get_temp_dir() . '/material_' . $material['id'] . '_' . time() . '.png';
        $imageContent = file_get_contents($material['image_path']);
        if ($imageContent === false) throw new Exception("R2から画像をダウンロードできません: {$material['image_path']}");
        file_put_contents($tempFile, $imageContent);
        $inputPath = $tempFile;
        $needsCleanup = true;
    } else {
        $inputPath = dirname(__DIR__) . '/' . $material['image_path'];
        $needsCleanup = false;
        if (!file_exists($inputPath)) throw new Exception("元画像が見つかりません: {$inputPath}");
    }

    try {
        logMessage("Analyzing background color for material ID: {$material['id']}");
        $colorData = getOptimizedBackgroundColor($isRemoteUrl ? $material['image_path'] : $inputPath);
        logMessage("Background color: {$colorData['background_color']} ({$colorData['color_name']})");

        $createdDate = new DateTime($material['created_at'] ?? 'now');
        $slug = $material['slug'] ?? pathinfo($material['image_path'], PATHINFO_FILENAME);
        $outputDir = dirname(__DIR__) . '/uploads/' . $createdDate->format('Y') . '/' . $createdDate->format('m');
        $outputFilename = $slug . '-structured.png';
        $outputPath = $outputDir . '/' . $outputFilename;
        $relativeOutputPath = 'uploads/' . $createdDate->format('Y') . '/' . $createdDate->format('m') . '/' . $outputFilename;

        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);

        logMessage("Generating 1200x1200 structured image for material ID: {$material['id']}");
        generateStructuredDataImage($inputPath, $outputPath, $colorData['background_color']);

        $pdo->prepare("UPDATE materials SET structured_image_path = ?, structured_bg_color = ? WHERE id = ?")
            ->execute([$relativeOutputPath, $colorData['background_color'], $material['id']]);

        logMessage("Structured image saved: {$relativeOutputPath}");

        // 呼び出し元が最新の structured_image_path を使えるよう返す
        return $relativeOutputPath;
    } finally {
        if (!empty($needsCleanup) && !empty($tempFile) && file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}

// ===== 画像ベクトル化 =====

/**
 * OpenAI Vision APIを使用して画像のベクトル化を実行
 */
function getImageEmbedding($imagePath, $title = '') {
    $openaiApiKey = getenv('OPENAI_API_KEY');
    if (!$openaiApiKey) {
        throw new Exception('OPENAI_API_KEY environment variable is not set');
    }

    // R2 URLかローカルパスかを判定
    $isRemoteUrl = (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0);
    
    if ($isRemoteUrl) {
        // R2 URLの場合は直接URLを使用
        $imageUrlData = ['url' => $imagePath];
    } else {
        // ローカルファイルの場合
        $fullImagePath = __DIR__ . '/../' . $imagePath;
        if (!file_exists($fullImagePath)) {
            throw new Exception("Image file not found: {$fullImagePath}");
        }

        // 画像をbase64エンコード
        $imageData = file_get_contents($fullImagePath);
        $base64Image = base64_encode($imageData);
        $mimeType = mime_content_type($fullImagePath);
        $imageUrlData = ['url' => "data:{$mimeType};base64,{$base64Image}"];
    }

    // OpenAI APIリクエストの準備
    $url = 'https://api.openai.com/v1/embeddings';
    $headers = [
        'Authorization: Bearer ' . $openaiApiKey,
        'Content-Type: application/json',
    ];

    // タイトル情報を含めたプロンプトを作成
    $promptText = 'この画像の内容を簡潔に英語で説明してください。色、形、オブジェクト、スタイルを含めてください。';
    if (!empty($title)) {
        $promptText = "この画像は「{$title}」というタイトルのミニマルイラストです。このタイトルを参考にして、画像の内容を簡潔に英語で説明してください。色、形、オブジェクト、スタイルを含めてください。";
    }

    // 画像の特徴を文字列で記述してからベクトル化
    // まず画像を解析してテキスト記述を取得
    $visionUrl = 'https://api.openai.com/v1/chat/completions';
    $visionData = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $promptText
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => $imageUrlData
                    ]
                ]
            ]
        ],
        'max_tokens' => 300
    ];

    // Vision APIで画像を解析
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $visionUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($visionData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $visionResponse = curl_exec($ch);
    $visionHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        curl_close($ch);
        throw new Exception('Vision API CURL error: ' . curl_error($ch));
    }
    curl_close($ch);

    if ($visionHttpCode !== 200) {
        throw new Exception("Vision API HTTP error: {$visionHttpCode}, Response: {$visionResponse}");
    }

    $visionResult = json_decode($visionResponse, true);
    if (!isset($visionResult['choices'][0]['message']['content'])) {
        throw new Exception('Invalid Vision API response: ' . $visionResponse);
    }

    $imageDescription = $visionResult['choices'][0]['message']['content'];
    logMessage("Image description generated for '{$title}': {$imageDescription}");

    // 画像説明をベクトル化
    $embeddingData = [
        'model' => 'text-embedding-3-small',
        'input' => $imageDescription,
        'encoding_format' => 'float'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($embeddingData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        curl_close($ch);
        throw new Exception('Embedding API CURL error: ' . curl_error($ch));
    }
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Embedding API HTTP error: {$httpCode}, Response: {$response}");
    }

    $result = json_decode($response, true);
    if (!isset($result['data'][0]['embedding'])) {
        throw new Exception('Invalid embedding API response: ' . $response);
    }

    return [
        'embedding' => $result['data'][0]['embedding'],
        'model' => $embeddingData['model'],
        'description' => $imageDescription
    ];
}

/**
 * メイン処理
 */
function main() {
    logMessage('Starting image embedding process');

    try {
        $pdo = getDB();
        
        // ベクトル化未実施の素材を1件取得
        $stmt = $pdo->prepare("
            SELECT id, title, slug, image_path, webp_path, structured_image_path, created_at
            FROM materials
            WHERE image_embedding IS NULL
            ORDER BY created_at ASC
            LIMIT 1
        ");
        $stmt->execute();
        $material = $stmt->fetch();

        if (!$material) {
            logMessage('No materials found that need embedding processing');
            return;
        }

        logMessage("Processing material ID: {$material['id']}, Title: {$material['title']}");

        // 構造化データ用画像が未生成の場合は先に生成する
        if (empty($material['structured_image_path'])) {
            logMessage("structured_image_path not found, generating first");
            $structuredPath = generateStructuredImageForMaterial($pdo, $material);
            $material['structured_image_path'] = $structuredPath;
        }

        // 使用する画像パスを決定（優先順位: structured_image_path > webp_path > image_path）
        if (!empty($material['structured_image_path'])) {
            $imagePath = $material['structured_image_path'];
        } elseif (!empty($material['webp_path'])) {
            $imagePath = $material['webp_path'];
        } else {
            $imagePath = $material['image_path'];
        }

        if (!$imagePath) {
            throw new Exception("No valid image path found for material ID: {$material['id']}");
        }

        logMessage("Using image path: {$imagePath}");

        // OpenAI APIでベクトル化を実行（タイトル情報を含める）
        $embeddingResult = getImageEmbedding($imagePath, $material['title']);
        
        // データベースに保存
        $updateStmt = $pdo->prepare("
            UPDATE materials 
            SET image_embedding = ?, 
                embedding_model = ?, 
                embedding_created_at = NOW() 
            WHERE id = ?
        ");
        
        $embeddingJson = json_encode($embeddingResult['embedding']);
        $updateStmt->execute([
            $embeddingJson,
            $embeddingResult['model'],
            $material['id']
        ]);

        logMessage("Successfully processed material ID: {$material['id']}");
        logMessage("Embedding model: {$embeddingResult['model']}");
        logMessage("Embedding vector length: " . count($embeddingResult['embedding']));
        
    } catch (Exception $e) {
        logMessage("Error: " . $e->getMessage(), 'ERROR');
        exit(1);
    }
}

// スクリプト実行
main();
logMessage('Image embedding process completed');