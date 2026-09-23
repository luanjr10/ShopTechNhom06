<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerApproved
{
    /**
     * Chỉ cho phép seller đã được duyệt và đang hoạt động
     * truy cập Seller Center.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa đăng nhập',
            ], 401);
        }

        $sellerProfile = $user->sellerProfile;

        if (! $sellerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa có hồ sơ người bán',
            ], 403);
        }

        if ($sellerProfile->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản người bán chưa được duyệt hoặc đang bị tạm ngưng',
            ], 403);
        }

        return $next($request);
    }
}
