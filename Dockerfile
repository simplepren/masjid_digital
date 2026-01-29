FROM dunglas/frankenphp:latest

WORKDIR /app

ENV SERVER_NAME=":80"

# Install PHP extensions
RUN install-php-extensions \
    pdo_mysql \
    gd \
    intl \
    zip \
    opcache \
    dom

# install node + npm
RUN apt-get update && apt-get install -y curl
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
RUN apt-get install -y nodejs && npm install -g npm@latest

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN npm install && npm run build

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache
