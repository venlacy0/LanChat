FROM php:8.2-apache

# 安装必要的 PHP 扩展
RUN docker-php-ext-install mysqli pdo pdo_mysql

# 启用 Apache mod_rewrite
RUN a2enmod rewrite

# 设置工作目录
WORKDIR /var/www/html

# 复制项目文件
COPY . /var/www/html/

# 设置目录权限
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/data \
    && chmod -R 777 /var/www/html/uploads \
    && chmod -R 777 /var/www/html/avatars \
    && chmod -R 777 /var/www/html/logs

# 暴露端口
EXPOSE 80

# 启动 Apache
CMD ["apache2-foreground"]
