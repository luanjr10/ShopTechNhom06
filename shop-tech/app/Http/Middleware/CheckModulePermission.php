<?php

namespace App\Http\Middleware;

use App\Support\AdminModules;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModulePermission
{
    public function handle(Request $request, Closure $next, string $module, string $ability): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Chưa đăng nhập'], 401);
        }

        if (! AdminModules::isValidModule($module) || ! AdminModules::isValidAbility($module, $ability)) {
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
