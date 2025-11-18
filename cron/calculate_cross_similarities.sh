#!/bin/bash

# コミュニティ作品と素材のクロス類似度計算処理用cronスクリプト 
# 定期実行して、未処理の作品と素材の類似度を1件ずつ計算

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# ログディレクトリの作成
LOG_DIR="$PROJECT_DIR/logs"
mkdir -p "$LOG_DIR"

# ロックファイルで重複実行を防止
LOCK_FILE="$LOG_DIR/cross_similarity_calculation.lock"
LOG_FILE="$LOG_DIR/cross_similarity_calculation_cron.log"

# ログ出力関数
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# ロックファイル確認
if [ -f "$LOCK_FILE" ]; then
    log_message "Previous cross similarity calculation process still running (lock file exists)"
    exit 1
fi

# ロックファイル作成
echo $$ > "$LOCK_FILE"

# trap で異常終了時にロックファイルを削除
trap 'rm -f "$LOCK_FILE"; exit' INT TERM EXIT

log_message "Starting cross similarity calculation cron job"

# PHPスクリプト実行
cd "$PROJECT_DIR"
PHP_OUTPUT=$(/usr/local/php/8.4/bin/php "$SCRIPT_DIR/calculate_cross_similarities.php" 2>&1)
EXIT_CODE=$?

# PHPの出力をログに記録
if [ ! -z "$PHP_OUTPUT" ]; then
    log_message "PHP Output: $PHP_OUTPUT"
fi

if [ $EXIT_CODE -eq 0 ]; then
    log_message "Cross similarity calculation cron job completed successfully"
else
    log_message "Cross similarity calculation cron job failed with exit code: $EXIT_CODE"
fi

# ロックファイル削除
rm -f "$LOCK_FILE"

log_message "Cross similarity calculation cron job finished"
