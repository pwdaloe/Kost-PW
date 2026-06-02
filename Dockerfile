FROM php:8.1-apache

# Install ekstensi PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Enable mod_rewrite untuk .htaccess
RUN a2enmod rewrite

# Copy konfigurasi Apache custom (AllowOverride All)
COPY docker/apache.conf /etc/apache2/conf-enabled/kostpw.conf

EXPOSE 80
