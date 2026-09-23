<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice['invoice_no'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .muted { color: #6b7280; }
        .header { width: 100%; margin-bottom: 16px; }
        .header td { vertical-align: top; }
        .status {
            display: inline-block; padding: 3px 10px; border-radius: 12px;
            font-size: 11px; font-weight: bold; color: #fff; background: #6366f1;
        }
        .status-completed { background: #059669; }
        .status-cancelled { background: #dc2626; }
        .status-paid { background: #2563eb; }
        table.info { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        table.info td { padding: 4px 0; vertical-align: top; width: 50%; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.items th {
            background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 11px;
            border-bottom: 1px solid #d1d5db;
        }
        table.items td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .text-right { text-align: right; }
        .store-title { font-weight: bold; background: #eef2ff; padding: 5px 8px; margin-top: 12px; }
        table.totals { width: 100%; margin-top: 10px; }
        table.totals td { padding: 3px 0; }
        table.totals .label { color: #6b7280; }
        table.totals .grand td { font-size: 14px; font-weight: bold; border-top: 1px solid #d1d5db; padding-top: 6px; }
        .footer { margin-top: 24px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>ShopTech</h1>
                <div class="muted">Hóa đơn bán hàng</div>
            </td>
            <td class="text-right">
                <div><strong>Số hóa đơn:</strong> {{ $invoice['invoice_no'] }}</div>
                <div class="muted">Ngày xuất: {{ $invoice['issued_at']->format('d/m/Y H:i') }}</div>
                <div style="margin-top: 6px;">
                    <span class="status status-{{ $invoice['status'] }}">{{ $invoice['status_label'] }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="info">
        <tr>
            <td>
                <strong>Khách hàng</strong><br>
                {{ $invoice['customer']['name'] }}<br>
                {{ $invoice['customer']['email'] }}<br>
                {{ $invoice['customer']['phone'] ?? '—' }}
            </td>
            <td>
                <strong>Giao đến</strong><br>
                {{ $invoice['receiver']['name'] }} — {{ $invoice['receiver']['phone'] }}<br>
                {{ $invoice['receiver']['address'] }}
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding-top: 8px;">
                <strong>Phương thức thanh toán:</strong> {{ strtoupper($invoice['payment_method']) }}
                @if($invoice['paid_at'])
                    &nbsp;·&nbsp; <strong>Đã thanh toán:</strong> {{ $invoice['paid_at']->format('d/m/Y H:i') }}
                @endif
            </td>
        </tr>
    </table>

    @foreach($invoice['groups'] as $group)
        <div class="store-title">
            Gian hàng: {{ $group['store_name'] }} — {{ $group['status_label'] }}
        </div>
        <table class="items">
            <thead>
                <tr>
                    <th>Sản phẩm</th>
                    <th>SKU</th>
                    <th class="text-right">Đơn giá</th>
                    <th class="text-right">SL</th>
                    <th class="text-right">Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                @foreach($group['items'] as $item)
                    <tr>
                        <td>{{ $item['name'] }}</td>
                        <td>{{ $item['sku'] ?? '—' }}</td>
                        <td class="text-right">{{ number_format($item['unit_price'], 0, ',', '.') }}đ</td>
                        <td class="text-right">{{ $item['quantity'] }}</td>
                        <td class="text-right">{{ number_format($item['line_total'], 0, ',', '.') }}đ</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <table class="totals">
            <tr>
                <td class="label">Tạm tính gian hàng</td>
                <td class="text-right">{{ number_format($group['subtotal'], 0, ',', '.') }}đ</td>
            </tr>
            <tr>
                <td class="label">Phí vận chuyển</td>
                <td class="text-right">{{ number_format($group['shipping_fee'], 0, ',', '.') }}đ</td>
            </tr>
        </table>
    @endforeach

    <table class="totals" style="margin-top: 16px;">
        <tr>
            <td class="label">Tạm tính</td>
            <td class="text-right">{{ number_format($invoice['subtotal'], 0, ',', '.') }}đ</td>
        </tr>
        <tr>
            <td class="label">Phí vận chuyển</td>
            <td class="text-right">{{ number_format($invoice['shipping_fee'], 0, ',', '.') }}đ</td>
        </tr>
        @if($invoice['discount_amount'] > 0)
            <tr>
                <td class="label">Giảm giá {{ $invoice['discount_code'] ? "($invoice[discount_code])" : '' }}</td>
                <td class="text-right">-{{ number_format($invoice['discount_amount'], 0, ',', '.') }}đ</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Tổng cộng</td>
            <td class="text-right">{{ number_format($invoice['total'], 0, ',', '.') }}đ</td>
        </tr>
    </table>

    <div class="footer">
        Hóa đơn được tạo tự động từ ShopTech — phản ánh trạng thái đơn hàng tại thời điểm xuất ({{ $invoice['issued_at']->format('d/m/Y H:i') }}).
    </div>
</body>
</html>
