#!/bin/sh
# Lệnh khởi động container backend (Railway/Render truyền cổng qua biến $PORT).
set -e

php artisan storage:link || true
php artisan config:cache
php artisan route:cache

# Chỉ chạy migration bổ sung (bảng gốc được nhập từ file SQL, không có migration tạo).
# Không để lỗi migrate làm sập server — xem log nếu có lỗi.
php artisan migrate --force || echo "!!! migrate that bai — kiem tra DB da import shoptech.sql chua"

# QUEUE_CONNECTION=database cần worker chạy nền
php artisan queue:work --sleep=3 --tries=3 &

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
