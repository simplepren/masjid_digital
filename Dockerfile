FROM dunglas/frankenphp:latest
WORKDIR /app
ENV SERVER_NAME=":80"
RUN install-php-extensions \
    pdo_mysql \
    gd \
    intl \
    zip \
    opcache \
    dom
RUN apt-get update && apt-get install -y curl
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
RUN apt-get install -y nodejs
RUN apt-get install nano -y
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction
RUN npm install && npm run build

RUN mkdir -p database storage bootstrap/cache \
    && touch database/database.sqlite

# Berikan izin ke user www-data (user default FrankenPHP)
RUN chown -R www-data:www-data /app \
    && chmod -R 775 /app/storage /app/bootstrap/cache /app/database