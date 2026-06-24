# Multi-stage build for production deployment
FROM node:20-alpine AS frontend-builder

# Build frontend
WORKDIR /app/frontend
COPY resources/js/package*.json ./
RUN npm ci
COPY resources/js/ ./
RUN npm run build

# PHP/Laravel backend with Node.js for SvelteKit
FROM node:20-alpine AS backend

# Install PHP and system dependencies
RUN apk add --no-cache \
    php84 \
    php84-fpm \
    php84-pdo \
    php84-pdo_pgsql \
    php84-mbstring \
    php84-exif \
    php84-pcntl \
    php84-bcmath \
    php84-gd \
    php84-session \
    php84-tokenizer \
    php84-xml \
    php84-ctype \
    php84-fileinfo \
    php84-openssl \
    php84-zip \
    php84-curl \
    php84-dom \
    php84-xmlreader \
    php84-xmlwriter \
    php84-simplexml \
    php84-phar \
    php84-iconv \
    php84-intl \
    php84-posix \
    git \
    curl \
    zip \
    unzip \
    postgresql-dev \
    nginx \
    supervisor \
    oniguruma-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libxpm-dev \
    net-tools \
    procps

# Create symlinks for PHP and set up PHP configuration
RUN ln -sf /usr/bin/php84 /usr/bin/php \
    && ln -sf /usr/sbin/php-fpm84 /usr/sbin/php-fpm

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application code first (needed for artisan)
COPY . .

# Create Laravel directories if they don't exist
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \
    && mkdir -p bootstrap/cache

# Create log directories for supervisord and services
RUN mkdir -p /var/log/supervisor /var/log/nginx /var/run

# Set PHP memory limit for Composer
ENV COMPOSER_MEMORY_LIMIT=-1

# Install PHP dependencies (skip scripts to avoid artisan issues)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Run Laravel post-install scripts now that everything is in place
RUN php artisan package:discover --ansi || true

# Copy built frontend assets (SvelteKit Node.js build)
COPY --from=frontend-builder /app/frontend/build ./frontend-build
COPY --from=frontend-builder /app/frontend/package*.json ./frontend-build/

# Install frontend production dependencies
WORKDIR /var/www/html/frontend-build
RUN npm ci --only=production

# Back to main directory
WORKDIR /var/www/html

# Set permissions (use nginx user which already exists)
RUN chown -R nginx:nginx /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy and set up startup script
COPY docker/startup.sh /usr/local/bin/start.sh
COPY docker/queue-worker.sh /usr/local/bin/queue-worker.sh
COPY docker/monitor-queue.sh /usr/local/bin/monitor-queue.sh
RUN chmod +x /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/queue-worker.sh \
    && chmod +x /usr/local/bin/monitor-queue.sh \
    && ls -la /usr/local/bin/start.sh \
    && ls -la /usr/local/bin/queue-worker.sh \
    && ls -la /usr/local/bin/monitor-queue.sh

# Set default port (can be overridden by environment)
ENV PORT=80

EXPOSE $PORT

# node:20-alpine sets an entrypoint that can break non-Node CMDs
ENTRYPOINT []

# Use exec form to avoid shell interpretation issues
CMD ["/usr/local/bin/start.sh"]
