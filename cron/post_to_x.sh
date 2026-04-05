#!/bin/bash

# スクリプトのディレクトリに移動
cd "$(dirname "$0")"

# PHPスクリプトを実行
php post_to_x.php

# 終了ステータスを返す
exit $?
