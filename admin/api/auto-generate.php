<?php
require_once '../../config.php';
require_once '../../includes/openai.php';
startAdminSession();

header('Content-Type: application/json');

// ログイン状態を確認
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'ログインが必要です']);
    exit;
}

// 管理者権限の追加確認（必要に応じて）
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => '管理者権限が必要です']);
    exit;
}

// リファラーチェック（同一ドメインからのリクエストのみ許可）
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if (empty($referer) || strpos($referer, $host) === false) {
    http_response_code(403);
    echo json_encode(['error' => '不正なリクエストです']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // 入力データの取得
    $title = trim($_POST['title'] ?? '');
    $artMaterials = [];
    
    // 画材情報の取得
    if (!empty($_POST['art_materials'])) {
        $artMaterialsData = json_decode($_POST['art_materials'], true);
        if (is_array($artMaterialsData)) {
            $artMaterials = $artMaterialsData;
        }
    }
    
    if (empty($title)) {
        throw new Exception('タイトルが入力されていません');
    }
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('画像ファイルがアップロードされていません');
    }
    
    // 一時的に画像を保存
    $tempImagePath = $_FILES['image']['tmp_name'];
    
    // OpenAI APIで素材情報を生成
    $materialInfo = generateMaterialInfo($title, $tempImagePath, $artMaterials);
    
    // カテゴリIDを取得
    $pdo = getDB();
    $categoryId = null;
    if (!empty($materialInfo['category_slug'])) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$materialInfo['category_slug']]);
        $category = $stmt->fetch();
        if ($category) {
            $categoryId = $category['id'];
        }
    }
    
    // タグIDを取得または作成
    $tagIds = [];
    if (!empty($materialInfo['tags']) && is_array($materialInfo['tags'])) {
        foreach ($materialInfo['tags'] as $tagData) {
            // 新しい形式（name + slug オブジェクト）と旧形式（文字列）の両方に対応
            if (is_array($tagData) && isset($tagData['name']) && isset($tagData['slug'])) {
                $tagName = trim($tagData['name']);
                $tagSlug = trim($tagData['slug']);
            } else if (is_string($tagData)) {
                // 旧形式対応
                $tagName = trim($tagData);
                $tagSlug = createSlug($tagName);
            } else {
                continue;
            }
            
            if (empty($tagName)) continue;
            
            // 既存タグを検索（名前またはスラッグで）
            $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ? OR slug = ?");
            $stmt->execute([$tagName, $tagSlug]);
            $tag = $stmt->fetch();
            
            if ($tag) {
                // 既存のタグを使用
                $tagIds[] = $tag['id'];
            } else {
                // 新しいタグを作成（重複回避のためスラッグをユニークにする）
                $originalSlug = $tagSlug;
                $counter = 1;
                
                // スラッグが重複していないかチェック
                while (true) {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tags WHERE slug = ?");
                    $checkStmt->execute([$tagSlug]);
                    
                    if ($checkStmt->fetchColumn() == 0) {
                        // 重複なし、このスラッグを使用
                        break;
                    }
                    
                    // 重複している場合は番号を追加
                    $tagSlug = $originalSlug . '-' . $counter;
                    $counter++;
                }
                
                $stmt = $pdo->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
                if ($stmt->execute([$tagName, $tagSlug])) {
                    $tagIds[] = $pdo->lastInsertId();
                }
            }
        }
    }
    
    // 構造化検索キーワードを生成
    $structuredKeywords = [];
    
    // 多言語タイトルを構造化キーワードに追加
    if (!empty($materialInfo['en_title'])) {
        $structuredKeywords[] = 'title_en=' . $materialInfo['en_title'];
    }
    if (!empty($materialInfo['es_title'])) {
        $structuredKeywords[] = 'title_es=' . $materialInfo['es_title'];
    }
    if (!empty($materialInfo['fr_title'])) {
        $structuredKeywords[] = 'title_fr=' . $materialInfo['fr_title'];
    }
    if (!empty($materialInfo['nl_title'])) {
        $structuredKeywords[] = 'title_nl=' . $materialInfo['nl_title'];
    }
    
    // 多言語説明を構造化キーワードに追加
    if (!empty($materialInfo['en_description'])) {
        $structuredKeywords[] = 'description_en=' . $materialInfo['en_description'];
    }
    if (!empty($materialInfo['es_description'])) {
        $structuredKeywords[] = 'description_es=' . $materialInfo['es_description'];
    }
    if (!empty($materialInfo['fr_description'])) {
        $structuredKeywords[] = 'description_fr=' . $materialInfo['fr_description'];
    }
    if (!empty($materialInfo['nl_description'])) {
        $structuredKeywords[] = 'description_nl=' . $materialInfo['nl_description'];
    }
    
    // 元の検索キーワードと構造化キーワードを結合
    $originalKeywords = $materialInfo['search_keywords'] ?? '';
    $combinedKeywords = implode(',', $structuredKeywords);
    if (!empty($originalKeywords)) {
        $combinedKeywords .= ',' . $originalKeywords;
    }

    // レスポンスデータを準備
    $response = [
        'success' => true,
        'data' => [
            'slug' => $materialInfo['slug'] ?? '',
            'description' => $materialInfo['description'] ?? '',
            'category_id' => $categoryId,
            'tag_ids' => $tagIds,
            'search_keywords' => $combinedKeywords,
            'multilingual' => [
                'en_title' => $materialInfo['en_title'] ?? '',
                'en_description' => $materialInfo['en_description'] ?? '',
                'es_title' => $materialInfo['es_title'] ?? '',
                'es_description' => $materialInfo['es_description'] ?? '',
                'fr_title' => $materialInfo['fr_title'] ?? '',
                'fr_description' => $materialInfo['fr_description'] ?? '',
                'nl_title' => $materialInfo['nl_title'] ?? '',
                'nl_description' => $materialInfo['nl_description'] ?? ''
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // エラーログを出力
    error_log("OpenAI Auto-Generate Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    error_log("POST data: " . json_encode($_POST));
    error_log("FILES data: " . json_encode($_FILES));
    error_log("OpenAI API Key status: " . (!empty($_ENV['OPENAI_API_KEY']) ? 'SET ('.strlen($_ENV['OPENAI_API_KEY']).' chars)' : 'NOT_SET'));
    
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'post_data' => $_POST,
            'files_data' => array_map(function($file) {
                return [
                    'name' => $file['name'] ?? '',
                    'type' => $file['type'] ?? '',
                    'size' => $file['size'] ?? 0,
                    'error' => $file['error'] ?? -1,
                    'tmp_name_exists' => isset($file['tmp_name']) && file_exists($file['tmp_name'])
                ];
            }, $_FILES),
            'openai_key_set' => !empty($_ENV['OPENAI_API_KEY']),
            'openai_key_length' => strlen($_ENV['OPENAI_API_KEY'] ?? ''),
            'php_version' => PHP_VERSION,
            'curl_available' => function_exists('curl_init'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ]
    ]);
}

/**
 * スラッグ生成ヘルパー関数
 */
function createSlug($text) {
    // 日本語を英語に変換（拡張されたマッピング）
    $japaneseToEnglish = [
        // 基本的な形容詞
        'かわいい' => 'cute',
        'シンプル' => 'simple',
        'カラフル' => 'colorful',
        'パステル' => 'pastel',
        '手描き風' => 'hand-drawn',
        '水彩' => 'watercolor',
        'ポップ' => 'pop',
        'ナチュラル' => 'natural',
        'きれい' => 'beautiful',
        'おしゃれ' => 'stylish',
        
        // カテゴリ関連
        '果物' => 'fruit',
        '動物' => 'animal',
        '自然' => 'nature',
        '植物' => 'plant',
        '花' => 'flower',
        '食べ物' => 'food',
        '飲み物' => 'drink',
        '建物' => 'building',
        '乗り物' => 'vehicle',
        '道具' => 'tool',
        '家具' => 'furniture',
        'スポーツ' => 'sport',
        '音楽' => 'music',
        'ファッション' => 'fashion',
        '季節' => 'season',
        'お祭り' => 'festival',
        
        // 色
        '赤' => 'red',
        '青' => 'blue',
        '緑' => 'green',
        '黄色' => 'yellow',
        'ピンク' => 'pink',
        '紫' => 'purple',
        'オレンジ' => 'orange',
        '白' => 'white',
        '黒' => 'black',
        'グレー' => 'gray',
        '茶色' => 'brown',
        
        // 具体的なアイテム
        'レモン' => 'lemon',
        'りんご' => 'apple',
        'もも' => 'peach',
        '桜' => 'cherry-blossom',
        'ねこ' => 'cat',
        '犬' => 'dog',
        '鳥' => 'bird',
        '魚' => 'fish',
        '蝶' => 'butterfly',
        '太陽' => 'sun',
        '月' => 'moon',
        '星' => 'star',
        '雲' => 'cloud',
        '雨' => 'rain',
        '雪' => 'snow',
    ];
    
    // 完全一致の変換を試行
    if (isset($japaneseToEnglish[$text])) {
        return $japaneseToEnglish[$text];
    }
    
    // 部分一致の変換を試行
    foreach ($japaneseToEnglish as $jp => $en) {
        if (strpos($text, $jp) !== false) {
            $text = str_replace($jp, $en, $text);
        }
    }
    
    // 英数字以外を削除してハイフン区切りに変換
    $text = preg_replace('/[^\x20-\x7E]/', '', $text); // ASCII文字以外を削除
    $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text); // 英数字とスペース以外を削除
    $text = preg_replace('/\s+/', '-', trim($text)); // スペースをハイフンに変換
    $text = strtolower($text); // 小文字に変換
    $text = preg_replace('/-+/', '-', $text); // 複数のハイフンを単一に
    $text = trim($text, '-'); // 前後のハイフンを削除
    
    // 空になった場合のフォールバック
    if (empty($text)) {
        $text = 'item-' . substr(md5($text . time()), 0, 6);
    }
    
    return $text;
}
?>
