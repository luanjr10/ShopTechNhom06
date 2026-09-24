# Image chạy backend ShopTech trên Railway / Render (hoặc bất kỳ nơi nào chạy Docker).
# Cần Docker vì code bắt buộc có ext-mongodb — runtime PHP mặc định của các PaaS không có.
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

# mongodb ghim đúng bản đang chạy ở máy dev (composer.lock yêu cầu ext-mongodb ^2.4)
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql mongodb-2.5.2 bcmath intl zip gd pcntl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Cài vendor trước để tận dụng cache layer khi chỉ sửa code
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

# Số tiến trình PHP xử lý request song song của `artisan serve`
ENV PHP_CLI_SERVER_WORKERS=4

CMD ["sh", "docker/start.sh"]
