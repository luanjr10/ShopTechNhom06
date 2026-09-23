<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Support\JwtCookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Xác thực bằng session (Sanctum SPA). Toàn bộ nghiệp vụ chỉ phụ thuộc
 * $request->user() + middleware 'role', nên sau này thay JWT chỉ cần sửa
 * controller này + guard, không đụng phần còn lại.
 */
class AuthController extends Controller
{
    // [POST] /api/register — luôn tạo tài khoản customer.
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => $request->password,
            'role' => 'customer',
        ]);

        // Gửi email xác thực (đăng ký bằng email/password).
        $user->sendEmailVerificationNotification();

        $token = Auth::guard('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công',
            'data' => [
                'user' => $user->load('sellerProfile.stores', 'permissions'),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201)->withCookie(JwtCookie::make($token));
    }

    // [POST] /api/login — đăng nhập bằng username HOẶC email.
    public function login(LoginRequest $request)
    {
        $login = $request->input('login');
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $field => $login,
            'password' => $request->input('password'),
        ];

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Thông tin đăng nhập không chính xác',
            ], 401);
        }

        $user = Auth::guard('api')->user();

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'data' => [
                'user' => $user->load('sellerProfile.stores', 'permissions'),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200)->withCookie(JwtCookie::make($token));
    }

    // [GET] /api/me — thông tin user hiện tại.
    public function me(Request $request)
    {
        $user = Auth::guard('api')->user();

        return response()->json([
            'success' => true,
            'data' => $user->load('sellerProfile.stores', 'permissions'),
        ], 200);
    }

    // [POST] /api/logout
    public function logout(Request $request)
    {
        Auth::guard('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Đã đăng xuất',
        ], 200)->withCookie(JwtCookie::forget());
    }
}
