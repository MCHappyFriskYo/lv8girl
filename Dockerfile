FROM php:8.4-fpm

ARG S6_OVERLAY_VERSION=3.2.2.0
ARG arch

# 安装 PHP 扩展安装器
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql

# 安装 nginx
RUN apt-get update && \
    apt-get install -y nginx && \
    rm -rf /var/lib/apt/lists/*

# 清空默认目录
RUN rm -rf /var/www/html/* && rm -rf /etc/nginx/sites-enabled/*

# 复制 nginx 配置
COPY nginx.conf /etc/nginx/conf.d/default.conf

# 复制项目代码
COPY src /var/www/html/

# 设置权限（可选）
RUN chown -R www-data:www-data /var/www/html

# 安装 s6-overlay（noarch + 架构相关）
ADD https://github.com/just-containers/s6-overlay/releases/download/v${S6_OVERLAY_VERSION}/s6-overlay-noarch.tar.xz /tmp
RUN tar -C / -Jxpf /tmp/s6-overlay-noarch.tar.xz && rm /tmp/s6-overlay-noarch.tar.xz
ADD https://github.com/just-containers/s6-overlay/releases/download/v${S6_OVERLAY_VERSION}/s6-overlay-${arch}.tar.xz /tmp
RUN tar -C / -Jxpf /tmp/s6-overlay-${arch}.tar.xz && rm /tmp/s6-overlay-${arch}.tar.xz

# 复制 s6 服务配置到 /etc/services.d/
COPY s6-overlay/ /etc/services.d/

ENTRYPOINT ["/init"]
