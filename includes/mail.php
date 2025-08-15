<?php
/**
 * メール送信機能
 */

class MailSender {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    
    public function __construct($config = []) {
        // 環境変数またはデフォルト値を設定
        $this->smtp_host = $config['smtp_host'] ?? $_ENV['SMTP_HOST'] ?? 'localhost';
        $this->smtp_port = $config['smtp_port'] ?? $_ENV['SMTP_PORT'] ?? 587;
        $this->smtp_username = $config['smtp_username'] ?? $_ENV['SMTP_USERNAME'] ?? '';
        $this->smtp_password = $config['smtp_password'] ?? $_ENV['SMTP_PASSWORD'] ?? '';
        $this->from_email = $config['from_email'] ?? $_ENV['FROM_EMAIL'] ?? 'noreply@marutto.art';
        $this->from_name = $config['from_name'] ?? $_ENV['FROM_NAME'] ?? 'マルットアート';
    }
    
    /**
     * パスワード再設定メールを送信
     */
    public function sendPasswordResetEmail($to_email, $reset_url) {
        $subject = '【マルットアート】パスワード再設定のご案内';
        $message = $this->buildPasswordResetMessage($reset_url);
        
        return $this->sendEmail($to_email, $subject, $message);
    }
    
    /**
     * メール送信（実際のSMTP送信またはログ出力）
     */
    private function sendEmail($to, $subject, $message) {
        $headers = [
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To: ' . $this->from_email,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit'
        ];
        
        $headers_string = implode("\r\n", $headers);
        
        // ログファイルに記録（デバッグ用）
        $log_message = "=== PASSWORD RESET EMAIL ===\n";
        $log_message .= "To: $to\n";
        $log_message .= "Subject: $subject\n";
        $log_message .= "Message: $message\n";
        $log_message .= "Sent at: " . date('Y-m-d H:i:s') . "\n\n";
        
        file_put_contents(__DIR__ . '/../logs/email.log', $log_message, FILE_APPEND | LOCK_EX);
        
        // 実際のメール送信を試行
        try {
            // SMTP設定がある場合は実際に送信
            if (!empty($this->smtp_host) && $this->smtp_host !== 'localhost') {
                return $this->sendSMTP($to, $subject, $message, $headers);
            } else {
                // PHPのmail()関数を使用
                $result = mail($to, $subject, $message, $headers_string);
                if (!$result) {
                    error_log("Mail function failed for: $to");
                }
                return $result;
            }
        } catch (Exception $e) {
            error_log("Mail sending error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * SMTP経由でメールを送信
     */
    private function sendSMTP($to, $subject, $message, $headers) {
        try {
            $socket = $this->connectSMTP();
            if (!$socket) return false;
            
            // SMTP認証とメール送信
            $this->smtpCommand($socket, "EHLO " . $_SERVER['SERVER_NAME'] ?? 'localhost');
            
            // STARTTLS (ポート587の場合)
            if ($this->smtp_port == 587) {
                $this->smtpCommand($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->smtpCommand($socket, "EHLO " . $_SERVER['SERVER_NAME'] ?? 'localhost');
            }
            
            // 認証
            $this->smtpCommand($socket, "AUTH LOGIN");
            $this->smtpCommand($socket, base64_encode($this->smtp_username));
            $this->smtpCommand($socket, base64_encode($this->smtp_password));
            
            // メール送信
            $this->smtpCommand($socket, "MAIL FROM: <{$this->from_email}>");
            $this->smtpCommand($socket, "RCPT TO: <$to>");
            $this->smtpCommand($socket, "DATA");
            
            $email_data = "Subject: $subject\r\n";
            foreach ($headers as $header) {
                $email_data .= "$header\r\n";
            }
            $email_data .= "\r\n$message\r\n.\r\n";
            
            fputs($socket, $email_data);
            $response = fgets($socket);
            
            $this->smtpCommand($socket, "QUIT");
            fclose($socket);
            
            return strpos($response, '250') === 0;
            
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * SMTP接続
     */
    private function connectSMTP() {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $address = ($this->smtp_port == 465) ? "ssl://{$this->smtp_host}" : $this->smtp_host;
        $socket = stream_socket_client(
            "$address:{$this->smtp_port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }
        
        $response = fgets($socket);
        if (strpos($response, '220') !== 0) {
            error_log("SMTP Server not ready: $response");
            fclose($socket);
            return false;
        }
        
        return $socket;
    }
    
    /**
     * SMTPコマンド送信
     */
    private function smtpCommand($socket, $command) {
        fputs($socket, "$command\r\n");
        $response = fgets($socket);
        
        if (!in_array(substr($response, 0, 3), ['220', '221', '235', '250', '334', '354'])) {
            throw new Exception("SMTP Error: $command -> $response");
        }
        
        return $response;
    }
    
    /**
     * パスワード再設定メールの本文を作成
     */
    private function buildPasswordResetMessage($reset_url) {
        $site_name = $_ENV['SITE_NAME'] ?? 'マルットアート';
        $site_url = $_ENV['SITE_URL'] ?? 'https://marutto.art';
        
        return "
        <!DOCTYPE html>
        <html lang='ja'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>パスワード再設定</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #007bff;'>パスワード再設定のご案内</h2>
                
                <p>いつも{$site_name}をご利用いただき、ありがとうございます。</p>
                
                <p>パスワードの再設定をご希望の旨、承りました。<br>
                以下のリンクをクリックして、新しいパスワードを設定してください。</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$reset_url}' 
                       style='display: inline-block; background-color: #007bff; color: white; 
                              padding: 12px 30px; text-decoration: none; border-radius: 5px;
                              font-weight: bold;'>パスワードを再設定する</a>
                </div>
                
                <p style='color: #666; font-size: 14px;'>
                    ※このリンクの有効期限は24時間です。<br>
                    ※もしボタンがクリックできない場合は、以下のURLをコピーしてブラウザのアドレスバーに貼り付けてください。<br>
                    <a href='{$reset_url}'>{$reset_url}</a>
                </p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
                
                <p style='color: #666; font-size: 12px;'>
                    このメールに心当たりがない場合は、無視していただいて構いません。<br>
                    お困りの際は、お気軽にお問い合わせください。
                </p>
                
                <p style='color: #666; font-size: 12px;'>
                    {$site_name}<br>
                    {$site_url}
                </p>
            </div>
        </body>
        </html>
        ";
    }
}
?>
