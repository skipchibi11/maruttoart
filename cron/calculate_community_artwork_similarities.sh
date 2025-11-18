#!/bin/bash

# コミュニティ作品類似度計算スクリプト
# crontabに登録して定期実行

cd "$(dirname "$0")"
/usr/local/php/8.4/bin/php calculate_community_artwork_similarities.php
