<?php
/**
 * ミニストーリー自動生成スクリプト
 * OpenAI APIを使用して素材のミニストーリー（絵本風）を生成
 * 未生成の素材を1件ずつ処理（API制限対応）
 */

require_once dirname(__DIR__) . '/config.php';

// ログファイルのパス
$logFile = dirname(__DIR__) . '/logs/mini_story_generation.log';

// ログ出力関数
function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

/**
 * 画像をBase64エンコード
 */
function encodeImageToBase64($imagePath) {
    $fullPath = dirname(__DIR__) . '/' . $imagePath;
    
    if (!file_exists($fullPath)) {
        throw new Exception("画像ファイルが見つかりません: {$fullPath}");
    }
    
    $imageData = file_get_contents($fullPath);
    $base64 = base64_encode($imageData);
    
    // MIMEタイプを判定
    $mimeType = mime_content_type($fullPath);
    
    return "data:{$mimeType};base64,{$base64}";
}

/**
 * OpenAI Vision APIでミニストーリーを生成
 */
function generateMiniStory($materialTitle, $categoryName, $imagePath, $apiKey) {
    $prompt = "あなたは優しい絵本作家です。
添付したイラストの雰囲気に合わせて、
小学生低学年にもわかる言葉だけを使い、
やさしいミニストーリーを1つ作ってください。

素材名: {$materialTitle}
カテゴリ: {$categoryName}

【条件】
- 小学生低学年でも読める語彙で書く
- 文章はふんわり温かいトーン
- 2〜3文ごとに改行し、読みやすい段落構成にする
- 1ストーリーは120〜160文字程度
- 難しい比喩や専門語は使わない
- まるっとアートの「やわらかくて静かな世界観」を保つ
- 各段落は「。」で終わる

【出力例】
ブロッコリーくんは、朝のおさんぽへ出かけました。

空を見上げて「今日はいい日になりそう」とにっこり。
ほかの野菜たちも葉っぱをゆらしてあいさつしてくれます。

ゆっくり歩く時間が、ブロッコリーくんは大好きです。";

    // 画像をBase64エンコード
    $base64Image = encodeImageToBase64($imagePath);

    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'あなたは子供向けの絵本を書く優しい作家です。小学生低学年にもわかる言葉だけを使い、シンプルで心温まるストーリーを作ります。難しい言葉や抽象的な表現は使いません。'
            ],
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
                            'url' => $base64Image
                        ]
                    ]
                ]
            ]
        ],
        'max_tokens' => 250,
        'temperature' => 0.8
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
        throw new Exception("OpenAI API error: HTTP {$httpCode} - {$response}");
    }

    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception("Invalid API response: " . json_encode($result));
    }

    $story = trim($result['choices'][0]['message']['content']);
    
    // 引用符を削除
    $story = trim($story, '"\'');
    
    // Windows改行(\r\n)をUnix改行(\n)に統一
    $story = str_replace("\r\n", "\n", $story);
    $story = str_replace("\r", "\n", $story);
    
    return $story;
}

/**
 * メイン処理
 */
function main() {
    global $logFile;
    
    logMessage("=== ミニストーリー生成開始 ===", $logFile);
    
    // OpenAI APIキーの確認
    $apiKey = getenv('OPENAI_API_KEY');
    if (empty($apiKey)) {
        logMessage("エラー: OPENAI_API_KEYが設定されていません", $logFile);
        exit(1);
    }
    
    try {
        $pdo = getDB();
        
        // 未生成の素材を1件取得（IDの古い順）
        $stmt = $pdo->query("
            SELECT m.id, m.title, m.slug, c.title as category_name,
                   m.image_path, m.webp_medium_path, m.webp_small_path
            FROM materials m
            LEFT JOIN categories c ON m.category_id = c.id
            WHERE m.mini_story IS NULL
            ORDER BY m.id ASC
            LIMIT 1
        ");
        
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$material) {
            logMessage("未生成の素材はありません", $logFile);
            exit(0);
        }
        
        logMessage("処理対象: ID={$material['id']}, タイトル={$material['title']}", $logFile);
        
        // 画像パスの優先順位: webp_medium > webp_small > image_path
        $imagePath = null;
        if (!empty($material['webp_medium_path'])) {
            $imagePath = $material['webp_medium_path'];
        } elseif (!empty($material['webp_small_path'])) {
            $imagePath = $material['webp_small_path'];
        } elseif (!empty($material['image_path'])) {
            $imagePath = $material['image_path'];
        }
        
        if (empty($imagePath)) {
            logMessage("エラー: 画像パスが見つかりません", $logFile);
            exit(1);
        }
        
        logMessage("使用画像: {$imagePath}", $logFile);
        
        // 残りの未生成件数を確認
        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM materials WHERE mini_story IS NULL");
        $remaining = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        logMessage("残り未生成件数: {$remaining}件", $logFile);
        
        // ミニストーリー生成
        logMessage("ミニストーリーを生成中...", $logFile);
        $story = generateMiniStory(
            $material['title'],
            $material['category_name'] ?? 'その他',
            $imagePath,
            $apiKey
        );
        
        logMessage("生成されたストーリー: {$story}", $logFile);
        logMessage("文字数: " . mb_strlen($story) . "文字", $logFile);
        
        // データベースに保存
        $updateStmt = $pdo->prepare("
            UPDATE materials 
            SET mini_story = ?,
                mini_story_generated_at = NOW(),
                mini_story_model = 'gpt-4o-mini'
            WHERE id = ?
        ");
        
        $updateStmt->execute([$story, $material['id']]);
        
        logMessage("データベースに保存完了: ID={$material['id']}", $logFile);
        logMessage("=== 処理成功 ===", $logFile);
        
        exit(0);
        
    } catch (Exception $e) {
        logMessage("エラー: " . $e->getMessage(), $logFile);
        logMessage("スタックトレース: " . $e->getTraceAsString(), $logFile);
        exit(1);
    }
}

// スクリプト実行
main();
