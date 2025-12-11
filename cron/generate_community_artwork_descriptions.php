<?php
/**
 * みんなのアトリエ作品用タイトルと説明を生成するCronスクリプト
 * 説明が空の作品に対してAI生成を実行
 */

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/community_description_generation.log');

require_once __DIR__ . '/../config.php';

// コマンドラインからの実行のみ許可
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This script can only be run from command line.\n");
}

echo "[" . date('Y-m-d H:i:s') . "] Starting community artwork description generation...\n";

try {
    $pdo = getDB();
    
    // OpenAI設定ファイルを読み込み
    if (!file_exists(__DIR__ . '/../includes/openai.php')) {
        error_log("OpenAI config file not found");
        die("ERROR: OpenAI config file not found\n");
    }
    require_once __DIR__ . '/../includes/openai.php';
    
    // APIキーの存在確認
    if (!defined('OPENAI_API_KEY') || empty(OPENAI_API_KEY)) {
        error_log("OpenAI API key not configured");
        die("ERROR: OpenAI API key not configured\n");
    }
    
    // 説明が空の作品を取得（最大10件を処理）
    $stmt = $pdo->prepare("
        SELECT id, file_path, webp_path, title, pen_name, created_at
        FROM community_artworks
        WHERE (description IS NULL OR description = '')
        AND status = 'approved'
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    
    $artworks = $stmt->fetchAll();
    $processedCount = 0;
    $errorCount = 0;
    
    if (empty($artworks)) {
        echo "No artworks to process (no empty descriptions found).\n";
        error_log("Community artwork descriptions cron: No artworks with empty descriptions found");
        exit(0);
    }
    
    echo "Found " . count($artworks) . " artworks to process.\n";
    
    foreach ($artworks as $artwork) {
        $artworkId = $artwork['id'];
        
        // WebP優先、なければ元画像
        $imageRelativePath = $artwork['webp_path'] ?: $artwork['file_path'];
        $imagePath = __DIR__ . '/../' . ltrim($imageRelativePath, '/');
        
        echo "\nProcessing artwork ID: {$artworkId}\n";
        echo "  Current title: {$artwork['title']}\n";
        echo "  Pen name: {$artwork['pen_name']}\n";
        
        // 画像ファイルの存在確認
        if (!file_exists($imagePath)) {
            error_log("Image file not found for artwork ID {$artworkId}: {$imagePath}");
            echo "  ERROR: Image file not found\n";
            $errorCount++;
            continue;
        }
        
        // AI生成を実行
        $aiContent = generateAIContent($imagePath, $artwork['title'], $artwork['pen_name']);
        
        if ($aiContent === null) {
            error_log("AI generation failed for artwork ID {$artworkId}");
            echo "  ERROR: AI generation failed\n";
            $errorCount++;
            continue;
        }
        
        // データベースを更新
        try {
            $updateStmt = $pdo->prepare("
                UPDATE community_artworks
                SET title = :title, description = :description
                WHERE id = :id
            ");
            
            $updateStmt->execute([
                ':title' => $aiContent['title'],
                ':description' => $aiContent['description'],
                ':id' => $artworkId
            ]);
            
            echo "  SUCCESS: Generated title and description\n";
            echo "  New title: {$aiContent['title']}\n";
            echo "  Description: " . substr($aiContent['description'], 0, 50) . "...\n";
            
            $processedCount++;
            
            // API制限を考慮して少し待機
            sleep(2);
            
        } catch (Exception $e) {
            error_log("Database update error for artwork ID {$artworkId}: " . $e->getMessage());
            echo "  ERROR: Database update failed\n";
            $errorCount++;
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Processing completed.\n";
    echo "Processed: {$processedCount}\n";
    echo "Errors: {$errorCount}\n";
    
} catch (Exception $e) {
    error_log("Fatal error in community description generation: " . $e->getMessage());
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * AIでタイトルと説明を生成する関数
 */
function generateAIContent($imagePath, $currentTitle, $penName) {
    try {
        // 画像を読み込んでbase64エンコード
        $imageData = file_get_contents($imagePath);
        $base64Image = base64_encode($imageData);
        $mimeType = mime_content_type($imagePath);
        
        $prompt = "この作品の画像を見て、以下の2つを生成してください：

1. タイトル: 作品の内容や雰囲気を表す魅力的なタイトル（20文字以内）
   - 現在のタイトルは「{$currentTitle}」ですが、より良いタイトルがあれば変更してください
   - 「カスタム作品」のようなデフォルトタイトルの場合は、必ず具体的な内容に変更してください

2. 説明: 作品の魅力や特徴を伝える説明文（80〜150文字程度）
   - 作品の内容、色使い、雰囲気、感じられる世界観などを含めてください
   - 親しみやすく、読みやすい文章にしてください

以下のJSON形式で返してください：
{
  \"title\": \"タイトル\",
  \"description\": \"説明文\"
}";
        
        $apiKey = OPENAI_API_KEY;
        
        $data = [
            'model' => 'gpt-4o-mini',
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
                                'url' => "data:{$mimeType};base64,{$base64Image}"
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => 400
        ];
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("OpenAI API error: HTTP {$httpCode}, Response: {$response}");
            return null;
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            $content = trim($result['choices'][0]['message']['content']);
            
            // JSON形式のレスポンスをパース
            // まずマークダウンのコードブロックを除去
            $content = preg_replace('/```json\s*|\s*```/', '', $content);
            $content = trim($content);
            
            $aiData = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error: " . json_last_error_msg());
                return null;
            }
            
            if ($aiData && isset($aiData['title'], $aiData['description'])) {
                return [
                    'title' => $aiData['title'],
                    'description' => $aiData['description']
                ];
            }
            
            error_log("AI response missing required fields: " . $content);
            return null;
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("AI content generation error: " . $e->getMessage());
        return null;
    }
}
