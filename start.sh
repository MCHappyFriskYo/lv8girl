#!/bin/bash
set -e

# 只调整 uploads 目录及其子文件
if [ -d /var/www/html/uploads ]; then
    chown -R root:www-data /var/www/html/uploads
    chmod -R g+w /var/www/html/uploads
fi

# 捕获停止信号，快速杀掉后台进程
trap "kill -TERM $(jobs -p); exit 0" SIGTERM SIGINT

# 启动 PHP-FPM 后台模式
php-fpm

# 启动 Nginx 后台模式
nginx

# 挂起等待，保持脚本为 PID 1
wait
