<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        // Callback riêng cho luồng Dashboard (admin/seller). Cần khi FE deploy
        // trên domain khác nhau không chung gốc (vd 2 project Vercel proxy /api):
        // callback phải quay về đúng domain admin để cookie JWT đặt trên domain
        // đó. Để trống = dùng chung GOOGLE_REDIRECT_URI (như chạy local).
        'admin_redirect' => env('GOOGLE_ADMIN_REDIRECT_URI'),
    ],

    // Chatbot AI tư vấn sản phẩm (storefront) — OpenAI Chat Completions + function calling.
    // Hiện KHÔNG dùng (tài khoản hết credit) — giữ lại config phòng khi đổi lại.
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    // Chatbot AI tư vấn sản phẩm (storefront) — Google Gemini generateContent + function calling.
    // Hiện KHÔNG dùng (key cần "Set up billing" mới gọi được, dù free tier) — giữ lại phòng khi đổi lại.
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    // Chatbot AI tư vấn sản phẩm (storefront) — Groq (OpenAI-compatible Chat Completions,
    // miễn phí, KHÔNG cần thẻ) + function calling. Đang dùng chính cho AiChatService.
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    ],

    // MoMo sandbox (test-payment.momo.vn) — thay bằng thông tin merchant thật khi lên production.
    'momo' => [
        'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
        'partner_code' => env('MOMO_PARTNER_CODE', 'MOMO'),
        'access_key' => env('MOMO_ACCESS_KEY', 'F8BBA842ECF85'),
        'secret_key' => env('MOMO_SECRET_KEY', 'K951B6PE1waDMi640xX08PD3vg6EkVlz'),
    ],

    // VNPay sandbox (sandbox.vnpayment.vn) — thay bằng thông tin merchant thật khi lên production.
    'vnpay' => [
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'tmn_code' => env('VNPAY_TMN_CODE', '64DFOLZV'),
        'hash_secret' => env('VNPAY_HASH_SECRET', 'O6J4Z89F24EL7WDPFXJEJBX47AGBLQVO'),
    ],

    // OnePay sandbox (mtf.onepay.vn) — hỗ trợ 2 cổng:
    //   - domestic: thẻ ATM nội địa (Napas)
    //   - international: Visa / MasterCard / JCB
    // Lấy access_code / hash_code / merchant_id khi đăng ký tài khoản merchant.
    'onepay' => [
        'domestic_url' => env('ONEPAY_DOMESTIC_URL', 'https://mtf.onepay.vn/onecomm-pay/vpc.op'),
        'international_url' => env('ONEPAY_INTERNATIONAL_URL', 'https://mtf.onepay.vn/vpcpay/vpcpay.op'),
        'merchant_id' => env('ONEPAY_MERCHANT_ID', 'ONEPAY_MERCHANT_ID'),
        'access_code' => env('ONEPAY_ACCESS_CODE', 'ONEPAY_ACCESS_CODE'),
        'hash_code' => env('ONEPAY_HASH_CODE', 'ONEPAY_HASH_CODE'),
        // URL return đăng ký với OnePay merchant portal — trỏ về route backend.
        'return_url' => env('ONEPAY_RETURN_URL'),
        // 'again_link' nếu muốn khách bấm "Thử lại" quay về FE thay vì OnePay mặc định.
        'again_link' => env('ONEPAY_AGAIN_LINK'),
    ],

    // SePay Payment Gateway (my.sepay.vn/pg) — KHÔNG phải REST API JSON, mà là
    // form HTML submit (POST) tới host checkout, ký bằng HMAC-SHA256 + base64.
    // Lấy merchant_id/secret_key tại my.sepay.vn/pg/merchant > Thông tin đơn vị.
    'sepay' => [
        'merchant_id' => env('SEPAY_MERCHANT_ID'),
        'secret_key' => env('SEPAY_SECRET_KEY'),
        // Host submit form (POST /v1/checkout/init) — sandbox: pay-sandbox.sepay.vn,
        // production: pay.sepay.vn (đổi khi merchant chuyển sang Production).
        'checkout_url' => env('SEPAY_CHECKOUT_URL', 'https://pay-sandbox.sepay.vn/v1/checkout/init'),
        // IPN PHẢI cấu hình thủ công trên dashboard (Cổng thanh toán > Cấu hình >
        // IPN) với URL HTTPS công khai — SePay từ chối lưu URL localhost/nội bộ.
        // Auth Type = "Secret Key" trên dashboard dùng secret này để merchant verify.
        'ipn_secret' => env('SEPAY_IPN_SECRET'),
        // Bật stub khi CHƯA có tài khoản SePay merchant (test local).
        // Routes /sepay-stub/checkout và webhook stub chỉ hoạt động khi flag này true.
        'stub' => env('SEPAY_STUB', false) === 'true' || env('SEPAY_STUB', false) === true,
    ],

    // GHN (Giao Hàng Nhanh) — hệ địa chỉ CŨ (province/district/ward, có quận/huyện)
    // vì API Tính phí (/v2/shipping-order/fee) chỉ nhận district_id/ward_code kiểu
    // cũ, không hỗ trợ hệ mới (is_new_to_address) như API Tạo đơn. Toàn bộ
    // LocationService/LocationPicker của app dùng chung nguồn dữ liệu này.
    'ghn' => [
        // Mặc định STAGING nếu quên khai báo — KHÔNG mặc định production, để lỡ
        // thiếu .env cũng không vô tình gọi vào hệ thống thật.
        'api_url' => env('GHN_API_URL', 'https://dev-online-gateway.ghn.vn'),
        'token' => env('GHN_TOKEN'),
        'shop_id' => env('GHN_SHOP_ID'),
        // Header tuỳ chỉnh đăng ký trên GHN Developer Portal (Cấu hình webhook) để
        // GHN echo lại ở mọi callback — dùng verify request đến từ GHN thật.
        'webhook_header_name' => env('GHN_WEBHOOK_HEADER_NAME', 'X-ShopTech-Webhook-Secret'),
        'webhook_secret' => env('GHN_WEBHOOK_SECRET'),
        // Chỉ true khi thật sự go-live production — xem GhnClient (chặn cứng
        // gọi nhầm production lúc dev/test).
        'allow_production' => env('GHN_ALLOW_PRODUCTION', false),
    ],

];
