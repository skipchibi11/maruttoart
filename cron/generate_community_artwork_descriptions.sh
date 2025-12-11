#!/bin/bash

# みんなのアトリエ作品の説明生成スクリプト
# crontabに追加: */10 * * * * /path/to/generate_community_artwork_descriptions.sh

# スクリプトのディレクトリに移動
cd "$(dirname "$0")"

# PHP実行
/usr/local/php/8.4/bin/php generate_community_artwork_descriptions.php >> ../logs/community_description_generation.log 2>&1
