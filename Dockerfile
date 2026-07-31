FROM php:8.2-apache

# Instalar extensoes PHP necessarias
RUN docker-php-ext-install pdo pdo_mysql mysqli opcache

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Copiar projeto
COPY . /var/www/html/

# Configurar Apache para aceitar .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /var/www/apache2.conf

# Permissoes
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage

EXPOSE 80
