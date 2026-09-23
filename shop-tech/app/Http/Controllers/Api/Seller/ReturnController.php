<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Notifications\ReturnRequestRespondedNotification;
use Illuminate\Http\Request;

/**
 * Seller xem + duyệt/từ chối yêu cầu hoàn trả/bảo hành của khách cho gian
 * hàng của mình. Phản hồi (seller_response) được gửi email về cho khách
 * ngay khi duyệt/từ chối (xem ReturnRequestRespondedNotification).
 */
class ReturnController extends Controller
{
    // [GET] /api/seller/stores/{store}/returns
    public function index(Request $request, Store $store)
    {
        $query = ReturnRequest::whereHas('sellerOrder', fn ($q) => $q->where('store_id', $store->id))
            ->with(['orderItem', 'user:id,name,email,phone,avatar,google_avatar'])
            ->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    // [GET] /api/seller/stores/{store}/returns/{returnRequest}
    public function show(Store $store, ReturnRequest $returnRequest)
    {
        $this->assertBelongsToStore($returnRequest, $store);

        return response()->json([
            'success' => true,
            'data' => $returnRequest->load(['orderItem', 'user:id,name,email,phone,avatar,google_avatar', 'sellerOrder']),
        ], 200);
    }

    // [PATCH] /api/seller/stores/{store}/returns/{returnRequest}/respond
    public function respond(Request $request, Store $store, ReturnRequest $returnRequest)
    {
        $this->assertBelongsToStore($returnRequest, $store);

        if ($returnRequest->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Yêu cầu này đã được xử lý rồi'], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'seller_response' => 'required|string|max:1000',
        ]);

        $returnRequest->update([
            'status' => $validated['status'],
            'seller_response' => $validated['seller_response'],
            'responded_at' => now(),
        ]);

        $returnRequest->user->notify(new ReturnRequestRespondedNotification($returnRequest));

        return response()->json([
            'success' => true,
            'message' => $validated['status'] === 'approved' ? 'Đã duyệt yêu cầu' : 'Đã từ chối yêu cầu',
            'data' => $returnRequest,
        ], 200);
    }

    private function assertBelongsToStore(ReturnRequest $returnRequest, Store $store): void
    {
        abort_unless($returnRequest->sellerOrder->store_id === $store->id, 404, 'Không tìm thấy yêu cầu');
    }
}
