FROM php:8.3-cli

# System deps + PHP extensions Laravel needs
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    default-mysql-client \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    bcmath \
    gd \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN if [ -f composer.json ]; then composer install --no-interaction --prefer-dist --optimize-autoloader; fi

EXPOSE 8000

CMD ["sh", "-c", "if [ -f artisan ]; then php artisan migrate --force; fi; php artisan serve --host=0.0.0.0 --port=8000"]