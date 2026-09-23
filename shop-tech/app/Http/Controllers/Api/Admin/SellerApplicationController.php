<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use App\Models\SellerProfile;
use App\Models\SellerWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin xem và duyệt/từ chối đơn đăng ký người bán.
 */
class SellerApplicationController extends Controller
{
    // [GET] /api/admin/seller-applications?status=pending
    public function index(Request $request)
    {
        $query = SellerApplication::with('user:id,name,username,email')->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate((int) $request->input('per_page', 15)),
        ], 200);
    }

    // [GET] /api/admin/seller-applications/{application}
    public function show(SellerApplication $application)
    {
        return response()->json([
            'success' => true,
            'data' => $application->load('user:id,name,username,email', 'reviewer:id,name'),
        ], 200);
    }

    // [POST] /api/admin/seller-applications/{application}/approve
    public function approve(Request $request, SellerApplication $application)
    {
        if ($application->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Đơn này đã được xử lý',
            ], 422);
        }

        DB::transaction(function () use ($request, $application) {
            $application->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $user = $application->user;
            $user->update(['role' => 'seller']);

            // Tạo SellerProfile (1-1) + ví nếu chưa có.
            $profile = SellerProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['display_name' => $application->shop_name, 'phone' => $application->phone, 'status' => 'active'],
            );

            SellerWallet::firstOrCreate(['seller_profile_id' => $profile->id]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Đã duyệt đơn — người dùng trở thành người bán',
            'data' => $application->fresh('user'),
        ], 200);
    }

    // [POST] /api/admin/seller-applications/{application}/reject
    public function reject(Request $request, SellerApplication $application)
    {
        if ($application->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Đơn này đã được xử lý',
            ], 422);
        }

        $application->update([
            'status' => 'rejected',
            'reject_reason' => $request->input('reject_reason'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã từ chối đơn đăng ký',
            'data' => $application,
        ], 200);
    }
}
