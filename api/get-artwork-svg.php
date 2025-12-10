<?php
/**
 * 作品のSVGデータ取得API
 */

require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendSuccess($data) {
    echo json_encode([
        'success' => true,
        'svg_data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendError('GETメソッドが必要です', 405);
    }

    $artworkId = $_GET['id'] ?? null;
    
    if (!$artworkId || !is_numeric($artworkId)) {
        sendError('有効なartwork IDが必要です');
    }

    $pdo = getDB();

    // svg_dataカラムの存在確認
    $hasSvgData = false;
    try {
        $pdo->query("SELECT svg_data FROM community_artworks LIMIT 1");
        $hasSvgData = true;
    } catch (Exception $e) {
        sendError('SVGデータ機能が利用できません', 500);
    }

    // SVGデータを取得
    $stmt = $pdo->prepare("SELECT svg_data FROM community_artworks WHERE id = ? AND status = 'approved'");
    $stmt->execute([$artworkId]);
    $result = $stmt->fetch();

    if (!$result) {
        sendError('作品が見つかりません', 404);
    }

    if (empty($result['svg_data'])) {
        sendError('この作品にはSVGデータがありません', 404);
    }

    // JSON文字列をデコード
    $svgData = json_decode($result['svg_data'], true);
    
    if ($svgData === null) {
        sendError('SVGデータの解析に失敗しました', 500);
    }

    sendSuccess($svgData);

} catch (Exception $e) {
    error_log("Get SVG error: " . $e->getMessage());
    sendError('システムエラーが発生しました', 500);
}
