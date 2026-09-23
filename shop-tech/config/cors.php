<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Cấu hình CORS cho SPA dùng session (Sanctum stateful). Cần
    | supports_credentials = true để trình duyệt gửi cookie session, nên KHÔNG
    | dùng '*' được. Dùng pattern để chấp nhận mọi port localhost/127.0.0.1
    | (Vite hay tự đổi port), tránh phải liệt kê từng port.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    // Origin cụ thể có thể khai báo thêm qua env FRONTEND_URLS (phân tách bằng dấu phẩy).
    'allowed_origins' => array_filter(explode(',', (string) env('FRONTEND_URLS', ''))),

    // Chấp nhận mọi cổng của localhost / 127.0.0.1 (client, admin, seller-center...).
    'allowed_origins_patterns' => [
        '#^http://(localhost|127\.0\.0\.1)(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
