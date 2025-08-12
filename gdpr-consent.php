<?php
require_once 'config.php';

// CORS対応
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['consent'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$consent = $input['consent'];
$expires = time() + (365 * 24 * 60 * 60); // 1年

if ($consent === true || $consent === 'true') {
    // 同意した場合
    setcookie('gdpr_consent', 'accepted', $expires, '/');
    echo json_encode([
        'status' => 'success', 
        'message' => 'Consent accepted'
    ]);
} else {
    // 拒否した場合
    setcookie('gdpr_consent', 'rejected', $expires, '/');
    echo json_encode([
        'status' => 'success', 
        'message' => 'Consent rejected'
    ]);
}
?>
