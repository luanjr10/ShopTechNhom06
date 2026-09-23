<?php

namespace App\Http\Middleware;

use App\Support\AdminModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn theo TỪNG module + hành động cụ thể (view/create/edit/delete) — dùng
 * SAU middleware 'role:admin,employee' (đảm bảo $user tồn tại + đúng role cơ
 * bản). Admin thật (role=admin) luôn qua được (xem User::hasModulePermission).
 *
 * Dùng: ->middleware('permission:products,view')
 *
 * Chặn CẢ khi gọi thẳng API (Postman...) — không chỉ ẩn UI ở FE.
 */
class CheckModulePermission
{
    public function handle(Request $request, Closure $next, string $module, string $ability): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập'], 401);
        }

        if (! AdminModules::isValidModule($module) || ! AdminModules::isValidAbility($module, $ability)) {
            // Cấu hình route sai (module/ability không tồn tại) — lỗi lập trình,
            // không phải lỗi phân quyền của user.
            abort(500, 'Cấu hình quyền không hợp lệ');
        }

        if (! $user->hasModulePermission($module, $ability)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập mục này',
            ], 403);
        }

        return $next($request);
    }
}
