# ----------------------------------------------------------------
# Stage 1: The Builder (Heavy Lifting)
# ----------------------------------------------------------------
# We use a dedicated Composer image to handle dependencies.
# This keeps our final image clean of git/unzip/cache artifacts.
FROM composer:latest as builder

WORKDIR /app
COPY composer.json composer.lock ./

# Install dependencies optimized for production
# --no-dev: Skip testing packages
# --optimize-autoloader: Speeds up class loading
# --no-scripts: Security precaution
RUN composer install --no-dev --optimize-autoloader --no-scripts --ignore-platform-reqs

# ----------------------------------------------------------------
# Stage 2: The Production Server (Lean & Fast)
# ----------------------------------------------------------------
FROM php:8.2-apache

# 1. Install System Dependencies & Cleanup in ONE Layer
# -qq: Quiet mode (reduces log spam)
# --no-install-recommends: Skips useless extras
# rm -rf /var/lib/apt/lists/*: Deletes the apt cache (Saves disk space)
RUN apt-get update -qq && apt-get install -y -qq --no-install-recommends \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-install -j$(nproc) pdo_mysql zip mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 2. Apache Configuration
RUN a2enmod rewrite

# 3. PHP Configuration for large file uploads
RUN echo "upload_max_filesize = 2048M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 2048M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 600" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time = 600" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini

# 4. Set Working Directory
WORKDIR /var/www/html

# 4. Copy Only Necessary Files
# We grab the 'vendor' folder from the 'builder' stage above
COPY --from=builder /app/vendor ./vendor
COPY . .

# 5. Set Permissions (Standard Apache User)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 6. Launch
CMD ["apache2-foreground"]