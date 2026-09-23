<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đang chuyển đến SePay...</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f6f8fa;
            color: #1f2937;
        }
        .box { text-align: center; }
        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid #e2e6ea;
            border-top-color: #0f8a5f;
            border-radius: 50%;
            margin: 0 auto 16px;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        a { color: #0f8a5f; }
    </style>
</head>
<body>
    {{-- Endpoint /v1/checkout/init của SePay CHỈ nhận SUBMIT FORM (POST), không
    phải link GET để redirect thẳng — trang này auto-submit form ẩn ngay khi
    tải xong. Xem PaymentController::sepayRedirect / SePayService::buildCheckoutForm. --}}
    <div class="box">
        <div class="spinner"></div>
        <p>Đang chuyển đến cổng thanh toán SePay...</p>
        <form id="sepay-checkout-form" method="POST" action="{{ $action }}">
            @foreach ($fields as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <noscript>
                <button type="submit">Tiếp tục thanh toán</button>
            </noscript>
        </form>
    </div>

    <script>
        document.getElementById('sepay-checkout-form').submit();
    </script>
</body>
</html>
