<?php
/**
 * OpenAI API設定とヘルパー関数
 */

/**
 * OpenAI APIキーの設定
 */
function getOpenAIConfig() {
    return [
        'api_key' => $_ENV['OPENAI_API_KEY'] ?? '',
        'model' => 'gpt-4o-mini', // 画像解析対応モデル
        'max_tokens' => 2000,
        'temperature' => 0.3
    ];
}

/**
 * OpenAI Vision APIを使用して画像とタイトルから素材情報を自動生成
 */
function generateMaterialInfo($title, $imagePath, $artMaterials = []) {
    $config = getOpenAIConfig();
    
    if (empty($config['api_key'])) {
        error_log("OpenAI API Key not found. ENV vars: " . print_r($_ENV, true));
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
    
    // 画材情報の処理
    $artMaterialsText = '';
    $artMaterialsGuideline = '';
    if (!empty($artMaterials)) {
        $materialNames = array_map(function($material) {
            return $material['name'];
        }, $artMaterials);
        $artMaterialsText = "\n使用した画材: " . implode(', ', $materialNames);
        $artMaterialsGuideline = "\n重要: 説明文では「" . implode('、', $materialNames) . "」のみを使用し、他の画材は記載しないでください。";
    }

    // プロンプトの作成
    $prompt = "あなたはイラスト素材サイトのコンテンツ管理アシスタントです。

与えられた画像とタイトル「{$title}」{$artMaterialsText}から、以下のJSONフォーマットで素材情報を生成してください：{$artMaterialsGuideline}

{
  \"slug\": \"英数字とハイフンのみのURL用文字列（英語ベース）\",
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

ガイドライン：
- slugは小文字の英数字とハイフンのみ使用（英語ベース）
- タグは3-5個程度を選択し、日本語名と英語スラッグの両方を提供
- タグのスラッグは descriptive な英語（例：watercolor, cute, pastel-color など）
- カテゴリスラッグは提供されたリストから最適なものを選択
- 各言語の説明文は自然で分かりやすく
- 使用した画材が指定されている場合は、指定された画材のみを説明文に記載し、その画材の特徴を正確に反映する
- 指定されていない画材（例：パステルを選択した場合に水彩と記載）は含めない
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
