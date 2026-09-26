<?php

return [

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
        'admin_redirect' => env('GOOGLE_ADMIN_REDIRECT_URI'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
    ],

    'momo' => [
        'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
        'partner_code' => env('MOMO_PARTNER_CODE', 'MOMO'),
        'access_key' => env('MOMO_ACCESS_KEY', 'F8BBA842ECF85'),
        'secret_key' => env('MOMO_SECRET_KEY', 'K951B6PE1waDMi640xX08PD3vg6EkVlz'),
    ],

    'vnpay' => [
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'tmn_code' => env('VNPAY_TMN_CODE', '64DFOLZV'),
        'hash_secret' => env('VNPAY_HASH_SECRET', 'O6J4Z89F24EL7WDPFXJEJBX47AGBLQVO'),
    ],

    'onepay' => [
        'domestic_url' => env('ONEPAY_DOMESTIC_URL', 'https://mtf.onepay.vn/onecomm-pay/vpc.op'),
        'international_url' => env('ONEPAY_INTERNATIONAL_URL', 'https://mtf.onepay.vn/vpcpay/vpcpay.op'),
        'merchant_id' => env('ONEPAY_MERCHANT_ID', 'ONEPAY_MERCHANT_ID'),
        'access_code' => env('ONEPAY_ACCESS_CODE', 'ONEPAY_ACCESS_CODE'),
        'hash_code' => env('ONEPAY_HASH_CODE', 'ONEPAY_HASH_CODE'),
        'return_url' => env('ONEPAY_RETURN_URL'),
        'again_link' => env('ONEPAY_AGAIN_LINK'),
    ],

    'sepay' => [
        'merchant_id' => env('SEPAY_MERCHANT_ID'),
        'secret_key' => env('SEPAY_SECRET_KEY'),
        'checkout_url' => env('SEPAY_CHECKOUT_URL', 'https://pay-sandbox.sepay.vn/v1/checkout/init'),
        'ipn_secret' => env('SEPAY_IPN_SECRET'),
        'stub' => env('SEPAY_STUB', false) === 'true' || env('SEPAY_STUB', false) === true,
    ],

    'ghn' => [
        'api_url' => env('GHN_API_URL', 'https://dev-online-gateway.ghn.vn'),
        'token' => env('GHN_TOKEN'),
        'shop_id' => env('GHN_SHOP_ID'),
        'webhook_header_name' => env('GHN_WEBHOOK_HEADER_NAME', 'X-ShopTech-Webhook-Secret'),
        'webhook_secret' => env('GHN_WEBHOOK_SECRET'),
        'allow_production' => env('GHN_ALLOW_PRODUCTION', false),
    ],

];
