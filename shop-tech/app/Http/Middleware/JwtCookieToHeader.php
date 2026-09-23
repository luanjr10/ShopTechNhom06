<?php

namespace App\Http\Middleware;

use App\Support\JwtCookie;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cầu nối cookie → JWT.
 *
 * Guard `auth:api` (driver jwt) chỉ đọc token từ header `Authorization: Bearer`.
 * Middleware này lấy JWT từ HttpOnly cookie và gắn vào header đó nếu request
 * chưa tự gửi Bearer. Nhờ vậy React không cần (và không thể) đọc token, nhưng
 * cookie vẫn xác thực được. Header Bearer gửi tay vẫn được ưu tiên → không phá
 * client hiện có và không đổi guard/JWT config.
 */
class JwtCookieToHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken() && $request->hasCookie(JwtCookie::NAME)) {
            $token = $request->cookie(JwtCookie::NAME);

            if (is_string($token) && $token !== '') {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
