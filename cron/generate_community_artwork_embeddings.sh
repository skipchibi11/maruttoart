#!/bin/bash

# コミュニティ作品画像ベクトル化スクリプト
# crontabに登録して定期実行

cd "$(dirname "$0")"
/usr/local/php/8.4/bin/php generate_community_artwork_embeddings.php
