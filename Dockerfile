FROM php:8.4-fpm-alpine

# 安装 Nginx 和 Supervisor
RUN apk add --no-cache nginx supervisor


# 安装 PHP 扩展安装器
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql


# 复制 nginx 配置
COPY nginx.conf /etc/nginx/nginx.conf

# 复制 Supervisor 配置
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 清理网站目录
RUN rm -rf /var/www/html/*

# 复制项目代码
COPY src /var/www/html/

# 设置权限（可选）
RUN chown -R www-data:www-data /var/www/html


# Let supervisord start nginx & php-fpm
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
