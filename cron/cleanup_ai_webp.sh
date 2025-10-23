#!/bin/bash

# AI製品画像WebPファイル削除スクリプト
# 使用方法: ./cleanup_ai_webp.sh

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# ログファイルパス
LOG_FILE="$PROJECT_DIR/logs/cleanup_ai_webp.log"

# ログディレクトリが存在しない場合は作成
mkdir -p "$(dirname "$LOG_FILE")"

echo "$(date '+%Y-%m-%d %H:%M:%S') AI製品画像WebPファイル削除処理開始" >> "$LOG_FILE"

# PHPスクリプトを実行
/usr/local/php/8.4/bin/php "$SCRIPT_DIR/cleanup_ai_webp.php"

# 実行結果を確認
if [ $? -eq 0 ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') AI製品画像WebPファイル削除処理が正常に完了しました" >> "$LOG_FILE"
    exit 0
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') AI製品画像WebPファイル削除処理でエラーが発生しました" >> "$LOG_FILE"
    exit 1
fi