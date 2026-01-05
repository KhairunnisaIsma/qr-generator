# Gunakan PHP 8.2
FROM php:8.2-apache

# 1. Install alat-alat yang dibutuhkan
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    unzip \
    git

# 2. Aktifkan Extension Gambar (GD) & XML
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd xml

# 3. Aktifkan URL Rewrite (Supaya .htaccess jalan)
RUN a2enmod rewrite

# 4. Install Composer (Otomatis)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Copy semua file project kamu ke server
COPY . /var/www/html/

# 6. Setting agar folder Public jadi halaman utama
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# 7. Install Library Vendor (Otomatis!)
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader

# 8. Beri izin akses file
RUN chown -R www-data:www-data /var/www/html