<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreOwner
{
    /**
     * Đảm bảo store trên route {store} thuộc về seller đang đăng nhập.
     * Đặt sau middleware 'role:seller'.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $sellerProfile = $user?->sellerProfile;

        if (! $sellerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn chưa có hồ sơ người bán',
            ], 403);
        }

        $routeStore = $request->route('store');
        $store = $routeStore instanceof Store
            ? $routeStore
            : Store::find($routeStore);

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy gian hàng',
            ], 404);
        }

        if ($store->seller_profile_id !== $sellerProfile->id) {
            return response()->json([
                'success' => false,
                'message' => 'Gian hàng này không thuộc về bạn',
            ], 403);
        }

        return $next($request);
    }
}
