<?php

namespace App\Http\Middleware;

use App\Support\JwtCookie;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
