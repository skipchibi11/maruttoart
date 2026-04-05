#!/bin/bash

# スクリプトのディレクトリに移動
cd "$(dirname "$0")"

# PHPスクリプトを実行
/usr/local/php/8.4/bin/php post_to_x.php

# 終了ステータスを返す
exit $?
