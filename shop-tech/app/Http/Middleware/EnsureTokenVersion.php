<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vô hiệu hoá các JWT đã cấp trước khi "đăng xuất tất cả thiết bị".
 *
 * So khớp claim `tv` trong token với `users.token_version`. Khi người dùng bấm
 * đăng xuất tất cả, token_version tăng lên → mọi token cũ có `tv` không khớp
 * sẽ bị từ chối. Chạy sau `auth:api`.
 */
class EnsureTokenVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('api')->user();

        if ($user) {
            $tokenVersion = (int) Auth::guard('api')->payload()->get('tv');

            if ($tokenVersion !== (int) $user->token_version) {
                Auth::guard('api')->invalidate();

                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đăng nhập đã hết hiệu lực',
                ], 401);
            }
        }

        return $next($request);
    }
}
