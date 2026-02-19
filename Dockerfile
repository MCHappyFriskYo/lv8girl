FROM php:8.4-fpm-alpine

# 安装 Nginx 和 Supervisor
RUN apk add --no-cache supervisor openssl curl ca-certificates


# 添加稳定版 Nginx 仓库
RUN printf "%s%s%s%s\n" \
    "@nginx " \
    "https://nginx.org/packages/alpine/v" \
    `egrep -o '^[0-9]+\.[0-9]+' /etc/alpine-release` \
    "/main" \
    >> /etc/apk/repositories

# 导入官方签名密钥
RUN curl -o /tmp/nginx_signing.rsa.pub https://nginx.org/keys/nginx_signing.rsa.pub \
    && openssl rsa -pubin -in /tmp/nginx_signing.rsa.pub -text -noout \
    && mv /tmp/nginx_signing.rsa.pub /etc/apk/keys/

# 安装 Nginx 及常用模块
RUN apk add --no-cache nginx@nginx

# 安装 PHP 扩展安装器
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql


# 复制 nginx 配置
COPY nginx.conf /etc/nginx/conf.d/default.conf
#COPY nginx.conf /etc/nginx/http.d/default.conf

# 复制 Supervisor 配置
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 复制项目代码
COPY src /var/www/html/

# 设置权限（可选）
RUN chown -R www-data:www-data /var/www/html


# Let supervisord start nginx & php-fpm
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]