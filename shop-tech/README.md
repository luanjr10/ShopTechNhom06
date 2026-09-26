# ShopTech · REST API

Backend Laravel 13 (PHP 8.3) cho sàn ShopTech: xác thực JWT/Google OAuth, đơn hàng nhiều gian hàng, hoa hồng, ví người bán, thanh toán MoMo/VNPay.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan serve   # http://localhost:8000
php artisan test
```

Xem tổng quan dự án tại [README gốc](../README.md).
