<?php
/**
 * AI自動作成 - 組み合わせ生成API
 * OpenAI GPT-4を使用して素材の配置や色を提案
 */

require_once '../../config.php';
startAdminSession();
requireLogin();

header('Content-Type: application/json');

// JSONリクエストを取得
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['materials'])) {
    echo json_encode(['success' => false, 'error' => '素材データが不足しています']);
    exit;
}

$materials = $data['materials'];
$canvasWidth = $data['canvasWidth'] ?? 800;
$canvasHeight = $data['canvasHeight'] ?? 800;
$userPrompt = $data['userPrompt'] ?? '';

try {
    $composition = generateCompositionWithAI($materials, $canvasWidth, $canvasHeight, $userPrompt);
    echo json_encode(['success' => true, 'composition' => $composition]);
} catch (Exception $e) {
    error_log('AI Composition Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * OpenAI APIを使って組み合わせを生成
 */
function generateCompositionWithAI($materials, $width, $height, $userPrompt) {
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        throw new Exception('OPENAI_API_KEYが設定されていません');
    }

    // 素材情報をテキスト形式で整理
    $materialDescriptions = [];
    foreach ($materials as $index => $material) {
        $materialDescriptions[] = sprintf(
            "%d. ID:%d, タイトル:%s, 説明:%s",
            $index + 1,
            $material['id'],
            $material['title'] ?? '',
            $material['description'] ?? ''
        );
    }
    $materialsText = implode("\n", $materialDescriptions);

    // システムプロンプト
    $systemPrompt = <<<EOT
あなたはイラスト構成のデザイナーです。
与えられた素材を使って、美しくバランスの取れた配置を提案してください。

以下のJSON形式で出力してください（必ず有効なJSONとして）:
{
  "backgroundColor": "#ffffff",
  "layers": [
    {
      "materialId": 素材ID(数値),
      "x": X座標(キャンバスの中心からのオフセット),
      "y": Y座標(キャンバスの中心からのオフセット),
      "scale": 拡大率(0.5〜2.0程度),
      "rotation": 回転角度(0〜360),
      "colors": [
        {"index": 0, "fill": "#色コード", "stroke": "#色コード"}
      ]
    }
  ],
  "reasoning": "配置の意図や理由の説明"
}

配置のガイドライン:
- 素材は重なり合っても良いが、バランスを考慮
- 中心付近に主要な素材を配置
- 小さい素材は周辺に配置して賑やかさを出す
- 色は調和を考えて選択（パステルカラーや明るい色を推奨）
- 背景色は全体の雰囲気に合わせる
- 各素材のscaleは適切なサイズ感になるよう調整（通常0.8〜1.5程度）
EOT;

    // ユーザープロンプト
    $userMessage = <<<EOT
キャンバスサイズ: {$width}x{$height}
素材リスト:
{$materialsText}

EOT;

    if (!empty($userPrompt)) {
        $userMessage .= "\n追加要望: {$userPrompt}\n";
    } else {
        $userMessage .= "\n自由に美しい配置を提案してください。\n";
    }

    // OpenAI APIリクエスト
    $url = 'https://api.openai.com/v1/chat/completions';
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ];

    $requestData = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => $userMessage
            ]
        ],
        'temperature' => 0.8,
        'max_tokens' => 2000,
        'response_format' => ['type' => 'json_object']
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_error($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('OpenAI APIリクエストエラー: ' . $error);
    }

    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('OpenAI APIエラー (HTTP ' . $httpCode . '): ' . $response);
    }

    $result = json_decode($response, true);
    if (!$result || !isset($result['choices'][0]['message']['content'])) {
        throw new Exception('OpenAI APIレスポンスが不正です');
    }

    $aiResponse = $result['choices'][0]['message']['content'];
    $composition = json_decode($aiResponse, true);

    if (!$composition) {
        throw new Exception('AI生成結果のJSONパースに失敗しました: ' . $aiResponse);
    }

    // 座標をキャンバス座標系に変換（中心基準から絶対座標へ）
    if (isset($composition['layers'])) {
        foreach ($composition['layers'] as &$layer) {
            // 中心座標に変換
            if (isset($layer['x'])) {
                $layer['x'] = $width / 2 + ($layer['x'] ?? 0);
            } else {
                $layer['x'] = $width / 2;
            }
            
            if (isset($layer['y'])) {
                $layer['y'] = $height / 2 + ($layer['y'] ?? 0);
            } else {
                $layer['y'] = $height / 2;
            }

            // デフォルト値を設定
            $layer['scale'] = $layer['scale'] ?? 1.0;
            $layer['rotation'] = $layer['rotation'] ?? 0;
            $layer['colors'] = $layer['colors'] ?? [];
        }
    }

    return $composition;
}
