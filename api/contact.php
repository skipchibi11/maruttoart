<?php
require_once '../config.php';

header('Content-Type: application/json');

// POSTリクエストのみ受け付け
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// レート制限（セッションベース）
session_start();
$currentTime = time();
$lastSubmitTime = $_SESSION['last_contact_submit'] ?? 0;
$minInterval = 60; // 60秒間隔

if ($currentTime - $lastSubmitTime < $minInterval) {
    http_response_code(429);
    echo json_encode([
        'success' => false, 
        'message' => '送信間隔が短すぎます。しばらくお待ちください。'
    ]);
    exit;
}

// 入力値の取得とバリデーション
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$honeypot = $_POST['website'] ?? '';

// ハニーポットチェック
if (!empty($honeypot)) {
    // ボットと判断（ログは残すが成功レスポンスを返す）
    error_log("Contact form honeypot triggered from IP: " . $_SERVER['REMOTE_ADDR']);
    echo json_encode(['success' => true]);
    exit;
}

// バリデーション
$errors = [];

if (empty($name) || mb_strlen($name) < 2 || mb_strlen($name) > 50) {
    $errors[] = 'お名前は2文字以上50文字以内で入力してください。';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '有効なメールアドレスを入力してください。';
}

if (empty($subject) || mb_strlen($subject) < 5 || mb_strlen($subject) > 100) {
    $errors[] = '件名は5文字以上100文字以内で入力してください。';
}

if (empty($message) || mb_strlen($message) < 10 || mb_strlen($message) > 2000) {
    $errors[] = 'お問い合わせ内容は10文字以上2000文字以内で入力してください。';
}

// スパムキーワードチェック
$spamKeywords = ['viagra', 'casino', 'porn', 'sex', 'loan', 'bitcoin', 'crypto'];
$combinedText = strtolower($subject . ' ' . $message);
foreach ($spamKeywords as $keyword) {
    if (strpos($combinedText, $keyword) !== false) {
        error_log("Contact form spam detected from IP: " . $_SERVER['REMOTE_ADDR']);
        echo json_encode(['success' => true]); // スパマーには成功と見せる
        exit;
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => implode("\n", $errors)
    ]);
    exit;
}

// メール送信
$to = 'takasesatoru6@gmail.com';
$emailSubject = '[maruttoart お問い合わせ] ' . $subject;
$emailBody = "お問い合わせがありました\n\n";
$emailBody .= "送信者名: " . $name . "\n";
$emailBody .= "メールアドレス: " . $email . "\n";
$emailBody .= "件名: " . $subject . "\n";
$emailBody .= "送信日時: " . date('Y-m-d H:i:s') . "\n";
$emailBody .= "IPアドレス: " . $_SERVER['REMOTE_ADDR'] . "\n\n";
$emailBody .= "お問い合わせ内容:\n";
$emailBody .= $message . "\n";

$headers = "From: noreply@marutto.art\r\n";
$headers .= "Reply-To: " . $email . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$mailSent = mail($to, $emailSubject, $emailBody, $headers);

if ($mailSent) {
    // 送信成功時にセッションを更新
    $_SESSION['last_contact_submit'] = $currentTime;
    
    // ログに記録
    error_log("Contact form submitted successfully from: " . $email);
    
    echo json_encode([
        'success' => true,
        'message' => 'お問い合わせを受け付けました。'
    ]);
} else {
    error_log("Contact form email failed to send from: " . $email);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'メール送信に失敗しました。時間をおいて再度お試しください。'
    ]);
}
