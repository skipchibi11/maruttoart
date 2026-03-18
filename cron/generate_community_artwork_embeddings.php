<?php
/**
 * コミュニティ作品画像ベクトル化処理スクリプト
 * OpenAI Vision APIで画像の特徴を文字列化 → Embedding APIでベクトル化
 * 
 * 実行方法: php generate_community_artwork_embeddings.php
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

require_once __DIR__ . '/../config.php';

// ログファイルの設定
$logFile = __DIR__ . '/../logs/community_artwork_embeddings.log';

// ログディレクトリを作成
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * ログ出力関数
 */
function logMessage($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

/**
 * 画像をベクトル化する
 * @param string $imagePath 画像パス
 * @param string $title 作品タイトル
 * @return array ['embedding' => array, 'model' => string]
 */
function getImageEmbedding($imagePathOrUrl, $title = '') {
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        throw new Exception('OPENAI_API_KEY environment variable not set');
    }

    $url = 'https://api.openai.com/v1/embeddings';
    
    // R2 URL か相対パスかを判定
    $isRemoteUrl = (strpos($imagePathOrUrl, 'http://') === 0 || strpos($imagePathOrUrl, 'https://') === 0);
    
    if ($isRemoteUrl) {
        // R2 などのリモート URL の場合、そのまま使用
        $imageUrl = $imagePathOrUrl;
        logMessage("Processing remote image: {$imageUrl}");
    } else {
        // ローカルファイルの場合、base64 エンコード
        $absolutePath = dirname(__DIR__) . '/' . ltrim($imagePathOrUrl, '/');
        if (!file_exists($absolutePath)) {
            throw new Exception("Image file not found: {$absolutePath}");
        }
        logMessage("Processing local image: {$absolutePath}");
        
        // 画像の MIME タイプを判定
        $imageInfo = getimagesize($absolutePath);
        $mimeType = $imageInfo['mime'] ?? 'image/png';
        
        // base64 エンコード
        $imageData = file_get_contents($absolutePath);
        $base64Image = base64_encode($imageData);
        $imageUrl = "data:{$mimeType};base64,{$base64Image}";
    }
    
    // プロンプトテキスト（タイトルがあれば含める）
    $promptText = 'この画像の内容を簡潔に英語で説明してください。色、形、オブジェクト、スタイルを含めてください。';
    if (!empty($title)) {
        $promptText = "この画像は「{$title}」というタイトルの作品です。このタイトルを参考にして、画像の内容を簡潔に英語で説明してください。色、形、オブジェクト、スタイルを含めてください。";
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
                            'url' => $imageUrl
                        ]
                    ]
                ]
            ]
        ],
        'max_tokens' => 300
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $visionUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($visionData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);

    $visionResponse = curl_exec($ch);
    $visionHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
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
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        curl_close($ch);
        throw new Exception('Embedding API CURL error: ' . curl_error($ch));
    }
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Embedding API HTTP error: {$httpCode}, Response: {$response}");
    }

    $result = json_decode($response, true);
    
    if (!isset($result['data'][0]['embedding'])) {
        throw new Exception('Invalid embedding response: ' . $response);
    }

    return [
        'embedding' => $result['data'][0]['embedding'],
        'model' => $result['model']
    ];
}

/**
 * メイン処理
 */
function main() {
    logMessage('Starting community artwork embedding process');

    try {
        $pdo = getDB();
        
        // ベクトル化未実施の承認済み作品を1件取得
        $stmt = $pdo->prepare("
            SELECT id, title, pen_name, file_path, webp_path
            FROM community_artworks 
            WHERE image_embedding IS NULL 
              AND status = 'approved'
            ORDER BY created_at ASC 
            LIMIT 1
        ");
        $stmt->execute();
        $artwork = $stmt->fetch();

        if (!$artwork) {
            logMessage('No community artworks found that need embedding processing');
            return;
        }

        logMessage("Processing artwork ID: {$artwork['id']}, Title: {$artwork['title']} by {$artwork['pen_name']}");

        // 使用する画像パスを決定（優先順位: webp_path > file_path）
        $imagePath = null;
        if (!empty($artwork['webp_path'])) {
            $imagePath = $artwork['webp_path'];
        } elseif (!empty($artwork['file_path'])) {
            $imagePath = $artwork['file_path'];
        }

        if (!$imagePath) {
            throw new Exception("No valid image path found for artwork ID: {$artwork['id']}");
        }

        logMessage("Using image path: {$imagePath}");

        // OpenAI APIでベクトル化を実行（タイトル情報を含める）
        $embeddingResult = getImageEmbedding($imagePath, $artwork['title']);
        
        // データベースに保存
        $updateStmt = $pdo->prepare("
            UPDATE community_artworks 
            SET image_embedding = ?, 
                embedding_model = ?, 
                embedding_created_at = NOW() 
            WHERE id = ?
        ");
        
        $embeddingJson = json_encode($embeddingResult['embedding']);
        $updateStmt->execute([
            $embeddingJson,
            $embeddingResult['model'],
            $artwork['id']
        ]);

        logMessage("Successfully processed artwork ID: {$artwork['id']}");
        logMessage("Embedding vector dimension: " . count($embeddingResult['embedding']));

    } catch (Exception $e) {
        logMessage("Error processing artwork: " . $e->getMessage(), 'ERROR');
        exit(1);
    }
}

// スクリプト実行
main();
logMessage('Community artwork embedding process completed');
