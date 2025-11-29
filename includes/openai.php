<?php
/**
 * OpenAI API設定とヘルパー関数
 */

// OpenAI APIキーを定数として定義
if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', $_ENV['OPENAI_API_KEY'] ?? '');
}

/**
 * OpenAI APIキーの設定
 */
function getOpenAIConfig() {
    return [
        'api_key' => OPENAI_API_KEY,
        'model' => 'gpt-4o-mini', // 画像解析対応モデル
        'max_tokens' => 2000,
        'temperature' => 0.3
    ];
}

/**
 * OpenAI Vision APIを使用して画像とタイトルから素材情報を自動生成
 */
function generateMaterialInfo($title, $imagePath) {
    $config = getOpenAIConfig();
    
    if (empty($config['api_key'])) {
        throw new Exception('OpenAI APIキーが設定されていません');
    }
    
    // 画像をBase64エンコード
    if (!file_exists($imagePath)) {
        throw new Exception('画像ファイルが見つかりません: ' . $imagePath);
    }
    
    $imageSize = filesize($imagePath);
    if ($imageSize > 20 * 1024 * 1024) { // 20MB制限
        throw new Exception('画像ファイルが大きすぎます (制限: 20MB)');
    }
    
    $imageData = base64_encode(file_get_contents($imagePath));
    $mimeType = mime_content_type($imagePath);

    // プロンプトの作成
    $prompt = "あなたはイラスト素材サイトのコンテンツ管理アシスタントです。

与えられた画像とタイトル「{$title}」から、以下のJSONフォーマットで素材情報を生成してください：

{
  \"slug\": \"英数字とハイフンのみのURL用文字列（適切な英語翻訳ベース）\",
  \"description\": \"日本語の簡潔な説明文（150-200文字程度）\",
  \"category_slug\": \"最適なカテゴリのスラッグ（fruits, nature, animals, vehicles, space, weather, buildings, plants, food, drinks, tools, furniture, sports, music, fashion, seasons, festivals のいずれか）\",
  \"tags\": [
    {
      \"name\": \"日本語タグ名\",
      \"slug\": \"英語スラッグ（小文字、ハイフン区切り）\"
    },
    {
      \"name\": \"日本語タグ名2\", 
      \"slug\": \"英語スラッグ2\"
    }
  ],
  \"search_keywords\": \"日本語キーワード,検索用語,関連語\",
  \"en_title\": \"英語タイトル\",
  \"en_description\": \"英語説明文\",
  \"es_title\": \"スペイン語タイトル\",
  \"es_description\": \"スペイン語説明文\",
  \"fr_title\": \"フランス語タイトル\",
  \"fr_description\": \"フランス語説明文\",
  \"nl_title\": \"オランダ語タイトル\",
  \"nl_description\": \"オランダ語説明文\"
}
}

重要なslug生成ルール：
- slugは日本語を適切な英語に翻訳してから作成（ローマ字読みは禁止）
- 例：「さやえんどう」→「snow-pea」（sayaendoは不適切）
- 例：「りんご」→「apple」（ringoは不適切）  
- 例：「ねこ」→「cat」（nekoは不適切）
- 例：「桜」→「cherry-blossom」（sakuraは不適切）
- 食べ物、動物、植物などは必ず適切な英語名を使用
- 複数の単語はハイフンで繋ぐ
- 小文字のみ使用

ガイドライン：
- slugは適切な英語翻訳を使用（ローマ字読みではなく、実際の英語名）
- タグは3-5個程度を選択し、日本語名と英語スラッグの両方を提供
- タグのスラッグも適切な英語翻訳を使用（例：cute, colorful, simple など）
- カテゴリスラッグは提供されたリストから最適なものを選択
- 各言語の説明文は自然で分かりやすく
- イラスト素材として適切な内容に";

    $data = [
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
        'max_tokens' => $config['max_tokens'],
        'temperature' => $config['temperature']
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['api_key']
        ],
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_VERBOSE => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // 詳細なエラーログ
    error_log("OpenAI API Request - HTTP Code: $httpCode");
    if ($error) {
        error_log("OpenAI API cURL Error: $error");
    }
    if ($response) {
        error_log("OpenAI API Response (first 500 chars): " . substr($response, 0, 500));
    }

    if ($error) {
        throw new Exception("OpenAI API通信エラー: {$error}");
    }

    if ($httpCode !== 200) {
        $errorDetails = '';
        if ($response) {
            $errorResponse = json_decode($response, true);
            if ($errorResponse && isset($errorResponse['error'])) {
                $errorDetails = ': ' . ($errorResponse['error']['message'] ?? 'Unknown error');
            }
        }
        throw new Exception("OpenAI APIエラー: HTTP {$httpCode}{$errorDetails}");
    }

    $result = json_decode($response, true);
    
    if (!$result || !isset($result['choices'][0]['message']['content'])) {
        throw new Exception('OpenAI APIから無効な応答を受信しました');
    }

    $content = $result['choices'][0]['message']['content'];
    
    // JSONデータを抽出（マークダウンのコードブロックを除去）
    $content = preg_replace('/```json\s*|\s*```/', '', $content);
    $content = trim($content);
    
    $materialInfo = json_decode($content, true);
    
    if (!$materialInfo) {
        throw new Exception('生成されたJSONが無効です: ' . json_last_error_msg());
    }

    return $materialInfo;
}
?>
