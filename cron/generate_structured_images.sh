#!/bin/bash
# 構造化データ用画像生成のcronジョブ

# スクリプトのディレクトリに移動
cd "$(dirname "$0")"

# ログファイルのパス
LOG_FILE="../logs/structured_images.log"

# ログディレクトリが存在しない場合は作成
mkdir -p "$(dirname "$LOG_FILE")"

# タイムスタンプ付きでログに記録
echo "=== $(date '+%Y-%m-%d %H:%M:%S') - 構造化データ用画像生成開始 ===" >> "$LOG_FILE"

# PHP スクリプトを実行
/usr/local/php/8.4/bin/php generate_structured_images.php >> "$LOG_FILE" 2>&1

echo "=== $(date '+%Y-%m-%d %H:%M:%S') - 構造化データ用画像生成完了 ===" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

# ログファイルが大きくなりすぎないように古いログを削除（30日より古いものを削除）
find "$(dirname "$LOG_FILE")" -name "*.log" -type f -mtime +30 -delete 2>/dev/null