FROM php:8.4-fpm

# 安装 PHP 扩展安装器
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# 安装 PHP 扩展
RUN install-php-extensions pdo_mysql

# 安装 nginx
RUN apt-get update && \
    apt-get install -y nginx && \
    rm -rf /var/lib/apt/lists/*

# 复制 nginx 配置
COPY nginx.conf /etc/nginx/nginx.conf

# 复制项目代码
COPY src /var/www/html/

# 设置权限（可选）
RUN chown -R www-data:www-data /var/www/html

# 启动脚本
COPY start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
