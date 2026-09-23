<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Models\SellerWallet;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;

/**
 * "Sàn đang giữ bao nhiêu tiền" — tổng quan + chi tiết dòng tiền của sellers
 * mà platform đang tạm giữ hộ (đã thu tiền/giữ chỗ từ lúc seller bàn giao,
 * xem SellerOrderService::handover(), CHƯA trả cho seller tới khi khách xác
 * nhận nhận hàng — complete()).
 */
class PlatformFundsController extends Controller
{
    // [GET] /api/admin/platform-funds/summary
    public function summary()
    {
        return response()->json([
            'success' => true,
            'data' => [
                // Tổng tiền các seller_order đã bàn giao nhưng khách CHƯA xác nhận
                // nhận hàng — sàn đang giữ hộ, có thể phải hoàn nếu giao thất bại.
                'total_held' => (float) SellerWallet::sum('pending_balance'),
                // Tổng tiền đã chốt cho seller (khách đã nhận hàng) nhưng seller
                // CHƯA tạo yêu cầu rút hoặc yêu cầu chưa được duyệt.
                'total_withdrawable' => (float) SellerWallet::sum('withdrawable_balance'),
                // Tổng tiền ĐÃ THỰC SỰ rời khỏi sàn (withdrawal đã duyệt).
                'total_paid_out' => (float) WithdrawalRequest::where('status', 'approved')->sum('amount'),
            ],
        ], 200);
    }

    // [GET] /api/admin/platform-funds/held — đơn đã bàn giao (shipping/delivered),
    // tiền đang ở pending_balance, chưa chắc chắn đã về tay seller.
    public function held(Request $request)
    {
        $query = SellerOrder::whereIn('status', ['shipping', 'delivered'])
            ->with([
                'store:id,name',
                'sellerProfile.user:id,name,username',
                'items:id,seller_order_id,product_name,sku,unit_price,quantity,line_total',
                'order:id,receiver_name,receiver_phone,shipping_address,payment_method',
                'shipment',
            ])
            ->latest();

        $paginator = $query->paginate((int) $request->input('per_page', 15));

        $paginator->getCollection()->transform(function (SellerOrder $so) {
            $rate = (float) $so->commission_rate;
            $so->setAttribute('held_amount', round((float) $so->subtotal * (1 - $rate / 100), 2));
            // status='shipping': seller đang giao, khách chưa nhận. status=
            // 'delivered': seller báo đã giao (khách CÓ THỂ đã cầm hàng) nhưng
            // CHƯA tự xác nhận trên app — tiền vẫn giữ tới khi khách bấm xác nhận.
            $so->setAttribute('customer_received', $so->status === 'delivered');

            return $so;
        });

        return response()->json(['success' => true, 'data' => $paginator], 200);
    }

    // [GET] /api/admin/platform-funds/settlements — đơn đã hoàn tất (khách xác
    // nhận nhận hàng), tiền đã chuyển pending -> withdrawable cho seller.
    public function settlements(Request $request)
    {
        $query = SellerOrder::where('status', 'completed')
            ->with([
                'store:id,name',
                'sellerProfile.user:id,name,username',
                'items:id,seller_order_id,product_name,sku,unit_price,quantity,line_total',
                'order:id,receiver_name,receiver_phone,shipping_address,payment_method',
            ])
            ->orderByDesc('completed_at');

        $paginator = $query->paginate((int) $request->input('per_page', 15));

        $paginator->getCollection()->transform(function (SellerOrder $so) {
            $so->setAttribute('settled_amount', (float) $so->seller_amount);
            $so->setAttribute('customer_received', true);

            return $so;
        });

        return response()->json(['success' => true, 'data' => $paginator], 200);
    }
}
