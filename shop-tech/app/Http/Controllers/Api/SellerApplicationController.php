<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreSellerApplicationRequest;
use App\Models\SellerApplication;
use Illuminate\Http\Request;

/**
 * Customer đăng ký trở thành người bán. Quản lý/duyệt nằm ở Admin\SellerApplicationController.
 */
class SellerApplicationController extends Controller
{
    // [POST] /api/seller-applications
    public function store(StoreSellerApplicationRequest $request)
    {
        $user = $request->user();

        if ($user->isSeller()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã là người bán',
            ], 422);
        }

        $hasPending = SellerApplication::where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn đã có đơn đăng ký đang chờ duyệt',
            ], 422);
        }

        $application = SellerApplication::create([
            'user_id' => $user->id,
            'shop_name' => $request->shop_name,
            'phone' => $request->phone,
            'address' => $request->address,
            'category_ids' => $request->input('category_ids', []),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi đơn đăng ký người bán',
            'data' => $application,
        ], 201);
    }

    // [GET] /api/seller-applications/mine
    public function mine(Request $request)
    {
        $applications = SellerApplication::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $applications,
        ], 200);
    }
}
