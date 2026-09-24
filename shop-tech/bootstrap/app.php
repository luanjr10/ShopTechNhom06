<?php

use App\Http\Middleware\CheckModulePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureSellerApproved;
use App\Http\Middleware\EnsureStoreOwner;
use App\Http\Middleware\EnsureTokenVersion;
use App\Http\Middleware\JwtCookieToHeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Chạy sau proxy HTTPS của Railway/Render/Nginx — tin header X-Forwarded-*
        // để route()/url() sinh đúng https:// (URL trả về cổng thanh toán, ảnh…).
        $middleware->trustProxies(at: '*');

        // Đọc JWT từ HttpOnly cookie và gắn vào header Bearer trước khi guard
        // auth:api chạy. Nhờ vậy React xác thực bằng cookie mà không cần đọc token.
        $middleware->api(prepend: [
            JwtCookieToHeader::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'store.owner' => EnsureStoreOwner::class,
            'seller.approved' => EnsureSellerApproved::class,
            'token.version' => EnsureTokenVersion::class,
            'permission' => CheckModulePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
