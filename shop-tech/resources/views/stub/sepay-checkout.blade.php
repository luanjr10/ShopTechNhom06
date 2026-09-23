<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>SePay Stub — Thanh toán giả lập</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary: #0f8a5f;
            --primary-light: #33c481;
            --bg: #f6f8fa;
            --border: #e2e6ea;
            --text: #1f2937;
            --muted: #6b7280;
            --danger: #dc2626;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }
        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 24px 16px;
            text-align: center;
        }
        .header h1 { margin: 0 0 4px 0; font-size: 18px; font-weight: 700; }
        .header p { margin: 0; font-size: 13px; opacity: 0.9; }
        .container {
            max-width: 480px;
            margin: 20px auto;
            padding: 0 16px;
        }
        .card {
            background: white;
            border-radius: 14px;
            border: 1px solid var(--border);
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .warning {
            background: #fff7ed;
            border: 1px solid #fdba74;
            color: #9a3412;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 12.5px;
            margin-bottom: 16px;
        }
        .qr-box {
            text-align: center;
            padding: 20px 12px;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            background: #fafbfc;
            margin-bottom: 16px;
        }
        .qr-placeholder {
            font-family: 'Courier New', monospace;
            font-size: 8px;
            line-height: 1;
            white-space: pre;
            color: #1f2937;
            letter-spacing: -1px;
            user-select: all;
            margin: 12px auto;
            display: inline-block;
            text-align: left;
        }
        .qr-meta {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: var(--muted);
            margin-top: 8px;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            border-bottom: 1px solid var(--border);
        }
        .meta-row:last-child { border-bottom: none; }
        .meta-row .label { color: var(--muted); }
        .meta-row .value { font-weight: 600; }
        .amount {
            text-align: center;
            padding: 16px 0;
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            flex: 1;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: 2px solid transparent;
            transition: opacity 0.15s, transform 0.05s;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }
        .btn-primary:hover { opacity: 0.93; }
        .btn-secondary {
            background: white;
            color: var(--danger);
            border-color: #fecaca;
        }
        .btn-secondary:hover { background: #fef2f2; }
        .footer {
            text-align: center;
            margin-top: 16px;
            font-size: 12px;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏦 SePay (Stub — Dev Mode)</h1>
        <p>Giả lập cổng thanh toán SePay cho môi trường dev</p>
    </div>

    <div class="container">
        <div class="card">
            <div class="warning">
                ⚠️ <strong>Stub mode:</strong> đây KHÔNG phải trang SePay thật.
                Bấm "Đã thanh toán" sẽ mô phỏng webhook SePay bắn về hệ thống
                và đánh dấu đơn <code>#{{ $order->id }}</code> đã thanh toán.
                Bật/tắt bằng <code>SEPAY_STUB</code> trong <code>.env</code>.
            </div>

            <div class="qr-box">
                <div class="qr-placeholder">
{{-- Tạo ASCII art giả QR --}}
██████  ████  ██████      ████  ██████
██  ██  ██  ██  ██  ██  ██  ██  ██  ██
██  ██  ██████  ██  ██      ██████  ██
██████  ██  ██    ████  ██  ██  ██  ██
                </div>
                <div class="qr-meta">QR giả — không quét được</div>
            </div>

            <div class="amount">{{ number_format($amount, 0, ',', '.') }}đ</div>

            <div class="meta-row">
                <span class="label">Mã đơn</span>
                <span class="value">#{{ $order->id }}</span>
            </div>
            <div class="meta-row">
                <span class="label">Mã tham chiếu</span>
                <span class="value">{{ $ref }}</span>
            </div>
            <div class="meta-row">
                <span class="label">Nội dung CK</span>
                <span class="value">SEPAY ORDER{{ $order->id }}</span>
            </div>

            <div class="actions">
                <form method="POST" action="{{ route('sepay.stub.confirm') }}" style="flex:1;">
                    @csrf
                    <input type="hidden" name="order_id" value="{{ $order->id }}">
                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        ✅ Tôi đã thanh toán
                    </button>
                </form>
                <form method="POST" action="{{ route('sepay.stub.cancel') }}" style="flex:1;">
                    @csrf
                    <input type="hidden" name="order_id" value="{{ $order->id }}">
                    <button type="submit" class="btn btn-secondary" style="width:100%;">
                        ❌ Hủy
                    </button>
                </form>
            </div>
        </div>

        <div class="footer">
            SePay Stub — ShopTech local dev — {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>
