# ─── Build Stage ────────────────────────────────────────────
FROM php:8.2-apache

# Install system dependencies + PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
      pdo pdo_mysql mysqli \
      gd zip mbstring

# Enable Apache mod_rewrite (needed for .htaccess)
# Disable mpm_event — PHP requires mpm_prefork
RUN a2dismod mpm_event || true \
 && a2enmod mpm_prefork rewrite

# ─── App Files ──────────────────────────────────────────────
WORKDIR /var/www/html
COPY . .
RUN chown -R www-data:www-data /var/www/html

# ─── Apache VirtualHost ─────────────────────────────────────
RUN printf '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    DirectoryIndex index.php index.html\n\
    <Directory /var/www/html>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

# ─── Startup Script (patches PORT at runtime) ────────────────
RUN printf '#!/bin/sh\n\
PORT=${PORT:-80}\n\
echo "[boot] Binding Apache to port $PORT"\n\
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf\n\
sed -i "s/:80>/:$PORT>/" /etc/apache2/sites-available/000-default.conf\n\
sed -i "s/:80>/:$PORT>/" /etc/apache2/sites-enabled/000-default.conf\n\
exec apache2-foreground\n' > /boot.sh && chmod +x /boot.sh

EXPOSE 80
CMD ["/boot.sh"]
