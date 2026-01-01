#!/bin/bash

# 再紹介アイテム追加スクリプト
cd "$(dirname "$0")/.."
/usr/local/php/8.4/bin/php cron/add_reintroduction_item.php
