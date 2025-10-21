#!/bin/bash

# 画像ベクトル化処理用cronスクリプト 
# 毎分実行して、未処理の素材を1件ずつベクトル化

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# ログディレクトリの作成
LOG_DIR="$PROJECT_DIR/logs"
mkdir -p "$LOG_DIR"

# 環境変数の設定（OpenAI API Key）
# 本番環境では適切に設定してください
# export OPENAI_API_KEY="your-openai-api-key-here"

# ロックファイルで重複実行を防止
LOCK_FILE="$LOG_DIR/image_embedding.lock"
LOG_FILE="$LOG_DIR/image_embedding_cron.log"

# ログ出力関数
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# ロックファイル確認
if [ -f "$LOCK_FILE" ]; then
    log_message "Previous embedding process still running (lock file exists)"
    exit 1
fi

# ロックファイル作成
echo $$ > "$LOCK_FILE"

# trap で異常終了時にロックファイルを削除
trap 'rm -f "$LOCK_FILE"; exit' INT TERM EXIT

log_message "Starting image embedding cron job"

# PHPスクリプト実行
cd "$PROJECT_DIR"
/usr/local/php/8.4/bin/php "$SCRIPT_DIR/generate_image_embeddings.php"
EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    log_message "Image embedding cron job completed successfully"
else
    log_message "Image embedding cron job failed with exit code: $EXIT_CODE"
fi

# ロックファイル削除
rm -f "$LOCK_FILE"

log_message "Image embedding cron job finished"