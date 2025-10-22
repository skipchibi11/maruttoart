<?php
/**
 * 画像ベクトル化処理スクリプト
 * OpenAI Vision APIを使用して画像をベクトル化し、データベースに保存
 */

require_once __DIR__ . '/../config.php';

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

/**
 * OpenAI Vision APIを使用して画像のベクトル化を実行
 */
function getImageEmbedding($imagePath, $title = '') {
    $openaiApiKey = getenv('OPENAI_API_KEY');
    if (!$openaiApiKey) {
        throw new Exception('OPENAI_API_KEY environment variable is not set');
    }

    // 画像ファイルの存在確認
    $fullImagePath = __DIR__ . '/../' . $imagePath;
    if (!file_exists($fullImagePath)) {
        throw new Exception("Image file not found: {$fullImagePath}");
    }

    // 画像をbase64エンコード
    $imageData = file_get_contents($fullImagePath);
    $base64Image = base64_encode($imageData);
    $mimeType = mime_content_type($fullImagePath);

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
                        'image_url' => [
                            'url' => "data:{$mimeType};base64,{$base64Image}"
                        ]
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
            SELECT id, title, image_path, webp_path, structured_image_path 
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

        // 使用する画像パスを決定（優先順位: structured_image_path > webp_path > image_path）
        $imagePath = null;
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