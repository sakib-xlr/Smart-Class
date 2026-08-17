# ─── Smart Class — PHP Built-in Server ──────────────────────
FROM php:8.2-cli

# Install system libs + PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
      pdo pdo_mysql mysqli \
      gd zip mbstring

# ─── App Files ───────────────────────────────────────────────
WORKDIR /var/www/html
COPY . .

# ─── Start PHP built-in server on Railway's PORT ─────────────
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-80} -t /var/www/html"]
