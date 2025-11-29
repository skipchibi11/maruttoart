#!/bin/bash

# 子供の作品用タイトルとストーリー生成スクリプト
# 5分ごとに実行することを推奨

# スクリプトのディレクトリに移動
cd "$(dirname "$0")"

# PHPスクリプトを実行
/usr/local/php/8.4/bin/php generate_kids_artwork_stories.php >> ../logs/kids_story_generation.log 2>&1

# 終了ステータスを確認
if [ $? -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Kids story generation completed successfully" >> ../logs/kids_story_generation.log
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Kids story generation failed with error code $?" >> ../logs/kids_story_generation.log
fi
