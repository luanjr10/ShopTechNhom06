<?php

namespace App\Http\Controllers\Api\Shipping;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\SellerOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Nhận callback trạng thái đơn từ GHN (Cấu hình webhook trên GHN Developer
 * Portal — xem app/Services/SellerOrderService, config/services.php['ghn']).
 * GHN không ký payload mặc định — xác thực bằng header tuỳ chỉnh tự đăng ký
 * (GHN echo lại ở mọi request), so khớp bằng hash_equals.
 *
 * Payload PascalCase theo tài liệu chính thức (OrderCode, Type, Status, ...).
 * Idempotent: GHN có thể gửi lại cùng sự kiện — chỉ update nếu shipment tồn tại,
 * không tạo mới, không lỗi nếu gọi trùng.
 */
class GhnWebhookController extends Controller
{
    /**
     * Trạng thái GHN đánh dấu "Cuối" (terminal) NGOÀI delivered — coi là giao
     * thất bại vĩnh viễn, tự hủy seller_order + hoàn kho/ví. Các trạng thái
     * tạm thời như delivery_fail/waiting_to_return/return/return_transporting/
     * return_sorting/returning KHÔNG nằm trong danh sách này — GHN có thể vẫn
     * đang xử lý/giao lại, chỉ lưu vào shipment.status để theo dõi.
     */
    private const TERMINAL_FAILURE_STATUSES = ['returned', 'cancel', 'exception', 'lost', 'damage', 'scrap'];

    public function __construct(private SellerOrderService $sellerOrderService) {}

    // [POST] /api/shipping/ghn/webhook
    public function handle(Request $request)
    {
        $headerName = (string) config('services.ghn.webhook_header_name');
        $expected = config('services.ghn.webhook_secret');
        $received = $request->header($headerName);

        if (! $expected || ! $received || ! hash_equals((string) $expected, (string) $received)) {
            Log::warning('GHN webhook auth không hợp lệ', ['header' => $headerName]);

            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $orderCode = $payload['OrderCode'] ?? null;

        if (! $orderCode) {
            // Payload thiếu OrderCode — không xử lý được, nhưng vẫn ack 200 để GHN
            // không retry vô ích (không phải lỗi tạm thời).
            return response()->json(['success' => true], 200);
        }

        $shipment = Shipment::where('tracking_number', $orderCode)->first();
        if (! $shipment) {
            Log::warning('GHN webhook không khớp shipment nào', ['order_code' => $orderCode]);

            return response()->json(['success' => true], 200);
        }

        if (($payload['Type'] ?? null) === 'switch_status' && ($status = $payload['Status'] ?? null)) {
            $shipment->update([
                'status' => $status,
                'delivered_at' => $status === 'delivered' ? ($shipment->delivered_at ?? now()) : $shipment->delivered_at,
            ]);

            // Chỉ khi GHN báo delivered mới đẩy seller_order sang 'delivered' — các
            // trạng thái vận chuyển khác (picking/storing/transporting...) chỉ lưu
            // vào shipment, KHÔNG đụng seller_orders.status.
            if ($status === 'delivered' && $shipment->sellerOrder->status === 'shipping') {
                try {
                    $this->sellerOrderService->markDelivered($shipment->sellerOrder);
                } catch (RuntimeException $e) {
                    Log::warning('GHN webhook markDelivered lỗi: '.$e->getMessage(), ['order_code' => $orderCode]);
                }
            }

            // Giao thất bại vĩnh viễn (mất hàng/hư hỏng/trả về hẳn/huỷ/sự cố) —
            // tự hủy seller_order, hoàn kho + hoàn ví (xem markDeliveryFailed()).
            if (in_array($status, self::TERMINAL_FAILURE_STATUSES, true) && $shipment->sellerOrder->status === 'shipping') {
                try {
                    $this->sellerOrderService->markDeliveryFailed($shipment->sellerOrder, $status);
                } catch (RuntimeException $e) {
                    Log::warning('GHN webhook markDeliveryFailed lỗi: '.$e->getMessage(), ['order_code' => $orderCode]);
                }
            }
        }

        // Type='cod': GHN đã chuyển khoản tiền thu hộ về cho platform — chỉ để
        // đối soát (đánh dấu đã nhận), KHÔNG tự động chuyển tiếp cho seller ở
        // đây (nằm ngoài phạm vi tích hợp GHN, xem WalletService/SettlementService).
        if (($payload['Type'] ?? null) === 'cod' && ! $shipment->cod_transferred_at) {
            $shipment->update(['cod_transferred_at' => now()]);
        }

        return response()->json(['success' => true], 200);
    }
}
