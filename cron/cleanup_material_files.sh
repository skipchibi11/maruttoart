#!/bin/bash

# 素材ファイル整理定期処理実行スクリプト
# 使用方法: ./cleanup_material_files.sh

# スクリプトのディレクトリに移動
cd "$(dirname "$0")"

# ログファイルのパス
LOG_FILE="../logs/cleanup_material_files.log"

# ログディレクトリが存在しない場合は作成
mkdir -p "$(dirname "$LOG_FILE")"

# タイムスタンプ付きでログに記録
echo "=== $(date '+%Y-%m-%d %H:%M:%S') - 素材ファイル整理処理開始 ===" >> "$LOG_FILE"

# PHPスクリプトを実行
/usr/local/php/8.4/bin/php cleanup_material_files.php >> "$LOG_FILE" 2>&1

# 終了コードを確認
if [ $? -eq 0 ]; then
    echo "=== $(date '+%Y-%m-%d %H:%M:%S') - 素材ファイル整理処理完了 ===" >> "$LOG_FILE"
else
    echo "=== $(date '+%Y-%m-%d %H:%M:%S') - 素材ファイル整理処理エラー ===" >> "$LOG_FILE"
    exit 1
fi

echo "" >> "$LOG_FILE"