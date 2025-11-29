<?php
/**
 * 子供の作品用タイトルとストーリーを生成するCronスクリプト
 * タイトルまたはストーリーがNULLの作品に対してAI生成を実行
 */

error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/kids_story_generation.log');

require_once __DIR__ . '/../config.php';

// コマンドラインからの実行のみ許可
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("This script can only be run from command line.\n");
}

echo "[" . date('Y-m-d H:i:s') . "] Starting kids artwork story generation...\n";

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
    
    // タイトルとストーリーが両方ともデフォルト値（待機メッセージ）の作品を取得
    $defaultTitle = 'おはなしを つくっているよ';
    $defaultStory = "いま あなたの えから、すてきな おはなしを つくっています。\nすこし まっててね！";
    
    $stmt = $pdo->prepare("
        SELECT id, image_path, webp_path, created_at, title, ai_story
        FROM kids_artworks
        WHERE title = :default_title AND ai_story = :default_story
        ORDER BY created_at ASC
        LIMIT 1
    ");
    $stmt->execute([
        ':default_title' => $defaultTitle,
        ':default_story' => $defaultStory
    ]);
    
    $artworks = $stmt->fetchAll();
    $processedCount = 0;
    $errorCount = 0;
    
    if (empty($artworks)) {
        echo "No artworks to process (no default values found).\n";
        error_log("Kids artwork stories cron: No artworks with default values found");
        exit(0);
    }
    
    echo "Found " . count($artworks) . " artworks to process.\n";
    
    foreach ($artworks as $artwork) {
        $artworkId = $artwork['id'];
        $imagePath = __DIR__ . '/../' . ltrim($artwork['image_path'], '/');
        
        echo "\nProcessing artwork ID: {$artworkId}\n";
        
        // 画像ファイルの存在確認
        if (!file_exists($imagePath)) {
            error_log("Image file not found for artwork ID {$artworkId}: {$imagePath}");
            echo "  ERROR: Image file not found\n";
            $errorCount++;
            continue;
        }
        
        // AI生成を実行
        $aiContent = generateAIContent($imagePath);
        
        if ($aiContent === null) {
            error_log("AI generation failed for artwork ID {$artworkId}");
            echo "  ERROR: AI generation failed\n";
            $errorCount++;
            continue;
        }
        
        // データベースを更新
        try {
            $updateStmt = $pdo->prepare("
                UPDATE kids_artworks
                SET title = :title, ai_story = :ai_story
                WHERE id = :id
            ");
            
            $updateStmt->execute([
                ':title' => $aiContent['title'],
                ':ai_story' => $aiContent['story'],
                ':id' => $artworkId
            ]);
            
            echo "  SUCCESS: Generated title and story\n";
            echo "  Title: {$aiContent['title']}\n";
            echo "  Story: " . substr($aiContent['story'], 0, 50) . "...\n";
            
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
    error_log("Fatal error in kids story generation: " . $e->getMessage());
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * AIでタイトルとストーリーを生成する関数
 */
function generateAIContent($imagePath) {
    try {
        // 画像を読み込んでbase64エンコード
        $imageData = file_get_contents($imagePath);
        $base64Image = base64_encode($imageData);
        $mimeType = mime_content_type($imagePath);
        
        $prompt = "この子供の絵を見て、以下の2つを生成してください：

1. タイトル: 絵の内容を表す短いタイトル（10文字以内、ひらがな多め）

2. ストーリー: 絵から想像できる優しくて楽しい短いお話（100文字程度、ひらがな多め）
   - ストーリーは2〜3文に分けて、各文の間に改行（\\n）を入れてください
   - 読みやすく、リズム感のある文章にしてください

以下のJSON形式で返してください（storyの中には\\nで改行を入れてください）：
{
  \"title\": \"タイトル\",
  \"story\": \"1文目。\\n2文目。\\n3文目。\"
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
            'max_tokens' => 300
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
            }
            
            if ($aiData && isset($aiData['title'], $aiData['story'])) {
                return [
                    'title' => $aiData['title'],
                    'story' => $aiData['story']
                ];
            }
            
            // JSON形式でない場合は、テキストをストーリーとして扱う
            error_log("AI response not in JSON format: " . $content);
            return [
                'title' => 'こどもの さくひん',
                'story' => $content
            ];
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("AI content generation error: " . $e->getMessage());
        return null;
    }
}
