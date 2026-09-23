<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Services\CloudinaryService;
use App\Services\WarrantyService;
use Illuminate\Http\Request;

/**
 * Khách gửi yêu cầu hoàn trả/bảo hành cho 1 dòng sản phẩm ĐÃ NHẬN HÀNG
 * (seller_order.status = completed) — bắt buộc kèm ảnh minh chứng. Seller
 * phản hồi (duyệt/từ chối) ở App\Http\Controllers\Api\Seller\ReturnController.
 */
class CustomerReturnController extends Controller
{
    public function __construct(
        private CloudinaryService $cloudinaryService,
        private WarrantyService $warrantyService,
    ) {}

    // [GET] /api/returns/mine
    public function index(Request $request)
    {
        $requests = ReturnRequest::where('user_id', $request->user()->id)
            ->with(['orderItem', 'sellerOrder.store:id,name,slug'])
            ->latest()
            ->paginate((int) $request->input('per_page', 15));

        return response()->json(['success' => true, 'data' => $requests], 200);
    }

    // [GET] /api/returns/{returnRequest}
    public function show(Request $request, ReturnRequest $returnRequest)
    {
        if ($returnRequest->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy yêu cầu'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $returnRequest->load(['orderItem', 'sellerOrder.store:id,name,slug']),
        ], 200);
    }

    // [POST] /api/order-items/{orderItem}/returns
    public function store(Request $request, OrderItem $orderItem)
    {
        $sellerOrder = $orderItem->sellerOrder()->with('order')->first();

        if (! $sellerOrder || $sellerOrder->order->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy sản phẩm trong đơn hàng của bạn'], 404);
        }

        if (! $this->warrantyService->canRequestReturn($sellerOrder)) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể gửi yêu cầu hoàn trả/bảo hành sau khi đơn đã hoàn tất (đã nhận hàng).',
            ], 422);
        }

        $alreadyPending = ReturnRequest::where('order_item_id', $orderItem->id)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm này đang có 1 yêu cầu chờ xử lý — vui lòng đợi seller phản hồi.',
            ], 422);
        }

        $validated = $request->validate([
            'type' => 'required|in:return,warranty',
            'reason' => 'required|string|max:1000',
            'images' => 'required|array|min:1|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $imageUrls = $this->cloudinaryService->uploadMultipleImages($request->file('images'), 'returns-shoptech');

        $returnRequest = ReturnRequest::create([
            'order_item_id' => $orderItem->id,
            'seller_order_id' => $sellerOrder->id,
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'images' => $imageUrls,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi yêu cầu — seller sẽ phản hồi sớm.',
            'data' => $returnRequest,
        ], 201);
    }
}
