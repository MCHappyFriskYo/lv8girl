#!/bin/bash
set -e

if [ -d /var/www/html/uploads ]; then
    chown -R root:www-data /var/www/html/uploads
    chmod -R g+w /var/www/html/uploads
fi

php-fpm -D
nginx

# 捕获停止信号，快速杀掉后台进程
trap "kill -TERM $(jobs -p); exit 0" SIGTERM SIGINT

# 挂起等待，保持脚本为 PID 1
tail -f /dev/null &
wait $!
