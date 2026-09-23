<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\JwtCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Chuyển người dùng sang trang đăng nhập Google.
     */
    public function redirect()
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    /**
     * Google callback về Laravel: tạo/tìm user → cấp JWT → set HttpOnly cookie
     * → redirect về React. KHÔNG trả token trong JSON hay trên URL.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();
        } catch (Throwable $e) {
            Log::error('Google OAuth callback lỗi', ['exception' => $e->getMessage(), 'class' => get_class($e)]);

            return redirect($this->frontendUrl('/login?error=google'));
        }

        // 1. Tìm theo Google ID
        $user = User::where('google_id', $googleUser->getId())->first();

        // 2. Nếu chưa có Google ID, thử tìm theo email
        if (! $user && $googleUser->getEmail()) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Liên kết tài khoản Google với tài khoản hiện tại
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'google_avatar' => $googleUser->getAvatar(),
                ]);
            }
        }

        // 3. Nếu hoàn toàn chưa có tài khoản → tạo customer
        if (! $user) {
            $email = $googleUser->getEmail();

            $username = 'google_'.Str::lower(Str::random(12));

            $user = User::create([
                'name' => $googleUser->getName() ?: 'Google User',
                'username' => $username,
                'email' => $email,
                'password' => null,
                'role' => 'customer',
                'google_id' => $googleUser->getId(),
                'google_avatar' => $googleUser->getAvatar(),
            ]);

            // Email từ Google đã được Google xác thực sẵn.
            $user->markEmailAsVerified();
        }

        // 4. Cấp JWT giống login thông thường
        $token = Auth::guard('api')->login($user);

        // 5. Lưu JWT vào HttpOnly cookie rồi redirect về React (không đưa token
        //    lên URL). React sẽ tự gọi /api/me để biết trạng thái đăng nhập.
        return redirect($this->frontendUrl('/'))
            ->withCookie(JwtCookie::make($token));
    }

    /**
     * Ghép đường dẫn vào URL của client React (config app.frontend_url).
     */
    private function frontendUrl(string $path = '/'): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
