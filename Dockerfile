FROM php:8.4-fpm

# 安装 PHP 扩展安装器
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql

# 安装 Nginx 和 Supervisor，并清理缓存（稳定步骤）
RUN apt-get update && \
    apt-get install -y --no-install-recommends nginx supervisor && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /var/log/* \
           /usr/share/doc/* /usr/share/man/* /usr/share/info/* /usr/share/lintian/* /usr/share/linda/* \
           /usr/share/locale/*

# 清理目录
RUN rm -rf /var/www/html/* && rm -rf /etc/nginx/sites-enabled/*

# 复制 Nginx 和 Supervisor 配置
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# 最后复制项目代码
COPY src /var/www/html/
RUN chown -R www-data:www-data /var/www/html


# Let supervisord start nginx & php-fpm
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
