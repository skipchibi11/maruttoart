#!/bin/bash

# ミニストーリー生成シェルスクリプト
# cron実行用のラッパー

# スクリプトディレクトリ
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/../logs"
LOCK_FILE="$LOG_DIR/mini_story_generation.lock"
CRON_LOG="$LOG_DIR/mini_story_generation_cron.log"

# ログディレクトリ作成
mkdir -p "$LOG_DIR"

# ログ出力関数
log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$CRON_LOG"
}

# ロックファイルチェック
if [ -f "$LOCK_FILE" ]; then
    log "既に処理が実行中です (ロックファイル: $LOCK_FILE)"
    exit 1
fi

# ロックファイル作成
touch "$LOCK_FILE"

# 終了時にロックファイルを削除
trap 'rm -f "$LOCK_FILE"; exit' INT TERM EXIT

log "=== ミニストーリー生成開始 ==="

# PHP実行
PHP_OUTPUT=$(/usr/local/php/8.4/bin/php "$SCRIPT_DIR/generate_mini_stories.php" 2>&1)
PHP_EXIT_CODE=$?

# 結果をログに出力
echo "$PHP_OUTPUT" >> "$CRON_LOG"

if [ $PHP_EXIT_CODE -eq 0 ]; then
    log "=== 処理成功 ==="
else
    log "=== 処理失敗 (終了コード: $PHP_EXIT_CODE) ==="
fi

# ロックファイル削除
rm -f "$LOCK_FILE"

exit $PHP_EXIT_CODE
