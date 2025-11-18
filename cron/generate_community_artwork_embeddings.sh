#!/bin/bash

# コミュニティ作品画像ベクトル化処理用cronスクリプト 
# 毎分実行して、未処理の作品を1件ずつベクトル化

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
LOCK_FILE="$LOG_DIR/community_artwork_embedding.lock"
LOG_FILE="$LOG_DIR/community_artwork_embedding_cron.log"

# ログ出力関数
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# ロックファイル確認
if [ -f "$LOCK_FILE" ]; then
    log_message "Previous community artwork embedding process still running (lock file exists)"
    exit 1
fi

# ロックファイル作成
echo $$ > "$LOCK_FILE"

# trap で異常終了時にロックファイルを削除
trap 'rm -f "$LOCK_FILE"; exit' INT TERM EXIT

log_message "Starting community artwork embedding cron job"

# PHPスクリプト実行
cd "$PROJECT_DIR"
/usr/local/php/8.4/bin/php "$SCRIPT_DIR/generate_community_artwork_embeddings.php"
EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    log_message "Community artwork embedding cron job completed successfully"
else
    log_message "Community artwork embedding cron job failed with exit code: $EXIT_CODE"
fi

# ロックファイル削除
rm -f "$LOCK_FILE"

log_message "Community artwork embedding cron job finished"bash

# コミュニティ作品画像ベクトル化スクリプト
# crontabに登録して定期実行

cd "$(dirname "$0")"
/usr/local/php/8.4/bin/php generate_community_artwork_embeddings.php
