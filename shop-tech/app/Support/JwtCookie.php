<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Đóng gói cookie chứa JWT dùng chung cho login/register/Google.
 *
 * Yêu cầu bảo mật:
 * - HttpOnly = true  → React KHÔNG đọc được token bằng JS (chống XSS).
 * - Secure   = true khi chạy production (chỉ gửi qua HTTPS).
 * - SameSite = Lax   → cookie vẫn được gửi khi redirect top-level từ Google về.
 * - Không lưu token vào localStorage/sessionStorage hay URL.
 */
class JwtCookie
{
    /** Tên cookie chứa JWT. */
    public const NAME = 'access_token';

    /**
     * Tạo cookie HttpOnly chứa JWT (TTL khớp với cấu hình jwt.ttl, đơn vị phút).
     */
    public static function make(string $token): SymfonyCookie
    {
        return Cookie::make(
            name: self::NAME,
            value: $token,
            minutes: (int) config('jwt.ttl', 60),
            path: '/',
            domain: null,
            secure: app()->environment('production'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    /**
     * Cookie xoá JWT (dùng khi logout).
     */
    public static function forget(): SymfonyCookie
    {
        return Cookie::forget(self::NAME, '/');
    }
}
