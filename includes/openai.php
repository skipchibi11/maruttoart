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

/**
 * OpenAI Vision APIを使用してカレンダーアイテムのタイトルと説明文を生成
 * @param string $imagePath 画像のパス
 * @param string $userHint ユーザーが入力した簡単な説明（オプション）
 */
function generateCalendarContent($imagePath, $userHint = '') {
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

    // ユーザーヒントがある場合はプロンプトに含める
    $hintText = '';
    if (!empty($userHint)) {
        $hintText = "\n【ユーザーからの簡単な説明】\n「{$userHint}」\n\nこの説明と画像をもとに、より魅力的なタイトルと詩的な説明文を作成してください。\n";
    }

    // プロンプトの作成
    $prompt = "あなたは優しくて詩的な文章を書くクリエイターです。
{$hintText}
与えられた画像から、タイトルと説明文を生成してください。

【タイトルの書き方】
- 15文字以内
- シンプルで優しい表現
- 例：「りんごとペンギン」「小さな達成感」「できたよの瞬間」

【説明文のスタイル例】
りんごの木のそばで、
ペンギンは赤いりんごをひとつ抱えています。

背伸びをして、
少しだけがんばって、
やっと手に届いた、ひとつのりんご。

たくさんではないけれど、
今日はこれで十分。
胸の前にそっと抱えるその姿は、
小さな達成感に満ちています。

大きな成功ではなく、
静かにうれしい出来事。

日常の中にある、
「できたよ」という瞬間を感じてもらえたら嬉しいです

【説明文の書き方のガイドライン】
- 短い行で区切り、詩的な雰囲気に
- 優しく、温かみのある表現を使う
- 小さな幸せや、日常の中の特別な瞬間を描く
- 最後は読者への優しいメッセージで締める
- 150〜250文字程度
- 子供から大人まで楽しめる内容

以下のJSON形式で出力してください：
{
  \"title\": \"タイトル\",
  \"description\": \"説明文\"
}";

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
        'max_tokens' => 600,
        'temperature' => 0.7 // より創造的に
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
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

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

    $content = trim($result['choices'][0]['message']['content']);
    
    // JSONデータを抽出（マークダウンのコードブロックを除去）
    $content = preg_replace('/```json\s*|\s*```/', '', $content);
    $content = trim($content);
    
    $calendarContent = json_decode($content, true);
    
    if (!$calendarContent || !isset($calendarContent['title']) || !isset($calendarContent['description'])) {
        throw new Exception('生成されたJSONが無効です');
    }
    
    // タイトルと説明文の前後の空白を削除
    $calendarContent['title'] = trim($calendarContent['title']);
    $calendarContent['description'] = trim($calendarContent['description']);
    
    return $calendarContent;
}

/**
 * 画像から適切な月日を提案
 */
function suggestCalendarDate($imagePath) {
    $config = getOpenAIConfig();
    
    if (empty($config['api_key'])) {
        throw new Exception('OpenAI APIキーが設定されていません');
    }
    
    if (!file_exists($imagePath)) {
        throw new Exception('画像ファイルが見つかりません: ' . $imagePath);
    }
    
    $imageData = base64_encode(file_get_contents($imagePath));
    $mimeType = mime_content_type($imagePath);

    $prompt = "この画像から、日本のカレンダーに最適な月と日を提案してください。

【重要な判断基準】
1. 背景色やグラデーションは無視してください
2. 画像の主要なモチーフ・被写体に注目してください
3. 描かれている具体的な要素（植物、動物、アイテムなど）から季節を判断してください

【日本の季節基準】
- 桜・お花見 → 3月下旬～4月上旬
- 新緑・若葉 → 4月～5月
- 梅雨・紫陽花 → 6月
- 夏・海・ひまわり・花火 → 7月～8月
- 秋・紅葉・落ち葉 → 10月～11月（重要：紅葉は秋です）
- 冬・雪・雪だるま → 12月～2月
- ハロウィン → 10月31日
- クリスマス → 12月25日
- お正月 → 1月1日

画像の主要な要素を丁寧に観察し、背景色ではなく描かれている内容から日本の四季に合わせた適切な月日を提案してください。

以下のJSON形式で出力してください：
{
  \"month\": 月（1-12の数字）,
  \"day\": 日（1-31の数字）,
  \"reason\": \"提案理由（簡潔に、その季節の特徴を説明）\"
}";

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
        'max_tokens' => 200,
        'temperature' => 0.5
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
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("OpenAI API通信エラー: {$error}");
    }

    if ($httpCode !== 200) {
        throw new Exception("OpenAI APIエラー: HTTP {$httpCode}");
    }

    $result = json_decode($response, true);
    
    if (!$result || !isset($result['choices'][0]['message']['content'])) {
        throw new Exception('OpenAI APIから無効な応答を受信しました');
    }

    $content = trim($result['choices'][0]['message']['content']);
    $content = preg_replace('/```json\s*|\s*```/', '', $content);
    $content = trim($content);
    
    $dateInfo = json_decode($content, true);
    
    if (!$dateInfo || !isset($dateInfo['month']) || !isset($dateInfo['day'])) {
        throw new Exception('生成されたJSONが無効です');
    }
    
    return $dateInfo;
}

/**
 * 空いている日付リストからAIに最適な日付を選ばせる
 */
function selectBestDateFromAvailable($suggestedMonth, $suggestedDay, $reason, $availableDates) {
    $config = getOpenAIConfig();
    
    if (empty($config['api_key'])) {
        throw new Exception('OpenAI APIキーが設定されていません');
    }
    
    // 利用可能な日付リストを整形
    $dateList = array_map(function($date) {
        return sprintf('%d年%d月%d日', $date['year'], $date['month'], $date['day']);
    }, $availableDates);
    
    $dateListStr = implode("\n", $dateList);

    $prompt = "画像から判断して、{$suggestedMonth}月{$suggestedDay}日が最適と判断しました。理由：{$reason}

しかし、以下の日付しか空いていません：
{$dateListStr}

この中から、{$suggestedMonth}月{$suggestedDay}日に最も近い、季節感が合う日付を1つ選んでください。

以下のJSON形式で出力してください：
{
  \"year\": 選んだ年,
  \"month\": 選んだ月,
  \"day\": 選んだ日,
  \"reason\": \"その日付を選んだ季節的な理由のみ（「最も近い」「近接性」などの表現は使わず、純粋にその日付が持つ季節感や特徴のみを簡潔に説明）\"
}";

    $data = [
        'model' => $config['model'],
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => 200,
        'temperature' => 0.3
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
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("OpenAI API通信エラー: {$error}");
    }

    if ($httpCode !== 200) {
        throw new Exception("OpenAI APIエラー: HTTP {$httpCode}");
    }

    $result = json_decode($response, true);
    
    if (!$result || !isset($result['choices'][0]['message']['content'])) {
        throw new Exception('OpenAI APIから無効な応答を受信しました');
    }

    $content = trim($result['choices'][0]['message']['content']);
    $content = preg_replace('/```json\s*|\s*```/', '', $content);
    $content = trim($content);
    
    $selectedDate = json_decode($content, true);
    
    if (!$selectedDate || !isset($selectedDate['year']) || !isset($selectedDate['month']) || !isset($selectedDate['day'])) {
        throw new Exception('生成されたJSONが無効です');
    }
    
    return $selectedDate;
}

/**
 * 1年先までの空いている日付を取得し、AIに最適な日付を選ばせる
 */
function findAvailableDateWithAI($pdo, $suggestedMonth, $suggestedDay, $reason) {
    $currentDate = date('Y-m-d');
    $oneYearLater = date('Y-m-d', strtotime('+1 year'));
    $minDate = '1980-06-15';
    
    $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM calendar_items WHERE year = ? AND month = ? AND day = ?');
    
    // まず本日から1年先までの空いている日付を全て取得
    $futureDates = [];
    $startTimestamp = strtotime($currentDate);
    $endTimestamp = strtotime($oneYearLater);
    
    for ($timestamp = $startTimestamp; $timestamp <= $endTimestamp; $timestamp += 86400) {
        $year = (int)date('Y', $timestamp);
        $month = (int)date('n', $timestamp);
        $day = (int)date('j', $timestamp);
        
        $checkStmt->execute([$year, $month, $day]);
        if ($checkStmt->fetchColumn() == 0) {
            $futureDates[] = [
                'year' => $year,
                'month' => $month,
                'day' => $day
            ];
        }
    }
    
    // 未来に空きがある場合、AIに最適な日付を選ばせる
    if (!empty($futureDates)) {
        try {
            $selectedDate = selectBestDateFromAvailable($suggestedMonth, $suggestedDay, $reason, $futureDates);
            return $selectedDate;
        } catch (Exception $e) {
            error_log('AI日付選択エラー: ' . $e->getMessage());
            // AIでの選択に失敗した場合、最初の空き日付を返す
            return [
                'year' => $futureDates[0]['year'],
                'month' => $futureDates[0]['month'],
                'day' => $futureDates[0]['day'],
                'reason' => $reason
            ];
        }
    }
    
    // 未来に空きがない場合、過去の空き日付を探す
    $pastDates = [];
    $minTimestamp = strtotime($minDate);
    
    for ($timestamp = $startTimestamp - 86400; $timestamp >= $minTimestamp; $timestamp -= 86400) {
        $year = (int)date('Y', $timestamp);
        $month = (int)date('n', $timestamp);
        $day = (int)date('j', $timestamp);
        
        $checkStmt->execute([$year, $month, $day]);
        if ($checkStmt->fetchColumn() == 0) {
            $pastDates[] = [
                'year' => $year,
                'month' => $month,
                'day' => $day
            ];
        }
    }
    
    // 過去に空きがある場合、AIに最適な日付を選ばせる
    if (!empty($pastDates)) {
        try {
            $selectedDate = selectBestDateFromAvailable($suggestedMonth, $suggestedDay, $reason, $pastDates);
            return $selectedDate;
        } catch (Exception $e) {
            error_log('AI日付選択エラー: ' . $e->getMessage());
            // AIでの選択に失敗した場合、最初の空き日付を返す
            return [
                'year' => $pastDates[0]['year'],
                'month' => $pastDates[0]['month'],
                'day' => $pastDates[0]['day'],
                'reason' => $reason
            ];
        }
    }
    
    // 完全に空きがない場合はエラーを返す
    throw new Exception('登録可能な日付が見つかりませんでした。全ての日付が使用されています。');
}

/**
 * 提案された月日から利用可能な最も近い日付を検索
 * 1980年6月15日から未来1年後の範囲で検索
 */
function findAvailableDate($pdo, $suggestedMonth, $suggestedDay, $currentYear = null) {
    if ($currentYear === null) {
        $currentYear = (int)date('Y');
    }
    
    // 検索範囲の設定（1980年6月15日から未来1年後）
    $minYear = 1980;
    $minMonth = 6;
    $minDay = 15;
    $maxYear = $currentYear + 1;
    $minDate = '1980-06-15';
    
    // 提案された日付が有効かチェック
    if (!checkdate($suggestedMonth, $suggestedDay, $currentYear)) {
        // 無効な日付の場合（例：2月30日）、その月の最終日に調整
        $suggestedDay = (int)date('t', mktime(0, 0, 0, $suggestedMonth, 1, $currentYear));
    }
    
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM calendar_items WHERE year = ? AND month = ? AND day = ?');
    
    // 1. まず提案された月日で、今年から近い年を検索（今年→来年→去年→一昨年...）
    $yearOffsets = [0]; // 今年
    for ($i = 1; $i <= ($currentYear - $minYear + 1); $i++) {
        if ($currentYear + $i <= $maxYear) {
            $yearOffsets[] = $i;  // 未来方向
        }
        if ($currentYear - $i >= $minYear) {
            $yearOffsets[] = -$i; // 過去方向
        }
    }
    
    foreach ($yearOffsets as $offset) {
        $targetYear = $currentYear + $offset;
        
        // 日付の妥当性チェック（うるう年対応）
        if (!checkdate($suggestedMonth, $suggestedDay, $targetYear)) {
            continue;
        }
        
        // 1980年6月15日より前の日付は除外
        $targetDate = sprintf('%04d-%02d-%02d', $targetYear, $suggestedMonth, $suggestedDay);
        if ($targetDate < $minDate) {
            continue;
        }
        
        $stmt->execute([$targetYear, $suggestedMonth, $suggestedDay]);
        if ($stmt->fetchColumn() == 0) {
            return [
                'year' => $targetYear,
                'month' => $suggestedMonth,
                'day' => $suggestedDay
            ];
        }
    }
    
    // 2. 提案された月日で空きがない場合、前後30日間を検索
    for ($dayOffset = 1; $dayOffset <= 30; $dayOffset++) {
        // 後の日付をチェック
        $afterTimestamp = mktime(0, 0, 0, $suggestedMonth, $suggestedDay + $dayOffset, $currentYear);
        $afterMonth = (int)date('n', $afterTimestamp);
        $afterDay = (int)date('j', $afterTimestamp);
        
        foreach ($yearOffsets as $offset) {
            $targetYear = $currentYear + $offset;
            if ($targetYear < $minYear || $targetYear > $maxYear) continue;
            
            // 1980年6月15日より前の日付は除外
            $targetDate = sprintf('%04d-%02d-%02d', $targetYear, $afterMonth, $afterDay);
            if ($targetDate < $minDate) continue;
            
            $stmt->execute([$targetYear, $afterMonth, $afterDay]);
            if ($stmt->fetchColumn() == 0) {
                return [
                    'year' => $targetYear,
                    'month' => $afterMonth,
                    'day' => $afterDay
                ];
            }
        }
        
        // 前の日付をチェック
        $beforeTimestamp = mktime(0, 0, 0, $suggestedMonth, $suggestedDay - $dayOffset, $currentYear);
        $beforeMonth = (int)date('n', $beforeTimestamp);
        $beforeDay = (int)date('j', $beforeTimestamp);
        
        foreach ($yearOffsets as $offset) {
            $targetYear = $currentYear + $offset;
            if ($targetYear < $minYear || $targetYear > $maxYear) continue;
            
            // 1980年6月15日より前の日付は除外
            $targetDate = sprintf('%04d-%02d-%02d', $targetYear, $beforeMonth, $beforeDay);
            if ($targetDate < $minDate) continue;
            
            $stmt->execute([$targetYear, $beforeMonth, $beforeDay]);
            if ($stmt->fetchColumn() == 0) {
                return [
                    'year' => $targetYear,
                    'month' => $beforeMonth,
                    'day' => $beforeDay
                ];
            }
        }
    }
    
    // 3. それでも見つからない場合、範囲内の全ての日付から空きを探す
    for ($year = $currentYear; $year <= $maxYear; $year++) {
        for ($month = 1; $month <= 12; $month++) {
            $daysInMonth = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
            for ($day = 1; $day <= $daysInMonth; $day++) {
                // 1980年6月15日より前の日付は除外
                $checkDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                if ($checkDate < $minDate) continue;
                
                $stmt->execute([$year, $month, $day]);
                if ($stmt->fetchColumn() == 0) {
                    return [
                        'year' => $year,
                        'month' => $month,
                        'day' => $day
                    ];
                }
            }
        }
    }
    
    // 最終手段：提案された日付をそのまま返す（重複エラーが発生する可能性あり）
    return [
        'year' => $currentYear,
        'month' => $suggestedMonth,
        'day' => $suggestedDay
    ];
}

/**
 * OpenAI Vision APIを使用してカレンダーアイテムの説明文を生成（後方互換性のため残す）
 */
function generateCalendarDescription($title, $imagePath) {
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
    $prompt = "あなたは優しくて詩的な文章を書くクリエイターです。

与えられた画像とタイトル「{$title}」から、心温まる説明文を生成してください。

【スタイルの例】
りんごの木のそばで、
ペンギンは赤いりんごをひとつ抱えています。

背伸びをして、
少しだけがんばって、
やっと手に届いた、ひとつのりんご。

たくさんではないけれど、
今日はこれで十分。
胸の前にそっと抱えるその姿は、
小さな達成感に満ちています。

大きな成功ではなく、
静かにうれしい出来事。

日常の中にある、
「できたよ」という瞬間を感じてもらえたら嬉しいです

【書き方のガイドライン】
- 短い行で区切り、詩的な雰囲気に
- 優しく、温かみのある表現を使う
- 小さな幸せや、日常の中の特別な瞬間を描く
- 最後は読者への優しいメッセージで締める
- 150〜250文字程度
- 子供から大人まで楽しめる内容

説明文のみを出力してください（JSON形式は不要）。";

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
        'max_tokens' => 500,
        'temperature' => 0.7 // より創造的に
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
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

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

    $description = trim($result['choices'][0]['message']['content']);
    
    return $description;
}
?>
