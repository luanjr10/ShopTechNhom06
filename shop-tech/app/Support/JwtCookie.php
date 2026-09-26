<?php

namespace App\Support;

use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class JwtCookie
{
    public const NAME = 'access_token';

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

    public static function forget(): SymfonyCookie
    {
        return Cookie::forget(self::NAME, '/');
    }
}
