<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Support\JwtCookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SessionController extends Controller
{
    public function refresh(Request $request)
    {
        try {
            $token = Auth::guard('api')->refresh();
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể gia hạn phiên. Vui lòng đăng nhập lại.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã gia hạn phiên',
        ])->withCookie(JwtCookie::make($token));
    }

    public function logoutOthers(Request $request)
    {
        $user = $request->user();
        $user->increment('token_version');

        $token = Auth::guard('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Đã đăng xuất khỏi tất cả thiết bị khác',
        ])->withCookie(JwtCookie::make($token));
    }
}
