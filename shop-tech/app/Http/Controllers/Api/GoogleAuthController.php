<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\JwtCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Chuyển người dùng sang trang đăng nhập Google.
     *
     * `?app=admin` đánh dấu luồng bắt đầu từ Dashboard (admin/seller) thay vì
     * app khách hàng — truyền qua Google bằng tham số OAuth chuẩn `state` (được
     * Google echo lại y nguyên ở callback, kể cả khi dùng `stateless()`).
     */
    public function redirect(Request $request)
    {
        $app = $request->query('app') === 'admin' ? 'admin' : 'client';

        return $this->googleDriver($app)
            ->with(['state' => $app])
            ->redirect();
    }

    /**
     * Google callback về Laravel: tạo/tìm user → cấp JWT → set HttpOnly cookie
     * → redirect về React. KHÔNG trả token trong JSON hay trên URL.
     *
     * Luồng `app=admin`: KHÔNG tự tạo tài khoản mới (tránh tự leo thang thành
     * admin/seller qua Google) — chỉ đăng nhập vào tài khoản admin/seller/
     * employee đã tồn tại sẵn, khớp qua email.
     */
    public function callback(Request $request): RedirectResponse
    {
        $app = $request->query('state') === 'admin' ? 'admin' : 'client';
        $frontendBase = $app === 'admin' ? (string) config('app.admin_url') : null;

        try {
            // Đổi code lấy token phải dùng ĐÚNG redirect_uri đã gửi ở bước redirect().
            $googleUser = $this->googleDriver($app)->user();
        } catch (Throwable $e) {
            Log::error('Google OAuth callback lỗi', ['exception' => $e->getMessage(), 'class' => get_class($e)]);

            return redirect($this->frontendUrl('/login?error=google', $frontendBase));
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

        // 3a. Luồng Dashboard (admin/seller): KHÔNG tự tạo tài khoản — chỉ được
        // đăng nhập vào tài khoản admin/seller/employee có sẵn, tránh việc ai
        // đó tự "đăng ký" thành admin/seller chỉ bằng cách bấm nút Google.
        if ($app === 'admin') {
            if (! $user) {
                return redirect($this->frontendUrl('/login?error=google_not_registered', $frontendBase));
            }

            if (! in_array($user->role, ['admin', 'seller', 'employee'], true)) {
                return redirect($this->frontendUrl('/login?error=google_no_access', $frontendBase));
            }
        }

        // 3b. Luồng khách hàng: hoàn toàn chưa có tài khoản → tạo customer.
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
        return redirect($this->frontendUrl('/', $frontendBase))
            ->withCookie(JwtCookie::make($token));
    }

    /**
     * Driver Google với callback theo luồng: admin dùng GOOGLE_ADMIN_REDIRECT_URI
     * (nếu có) để Google quay về qua domain admin -> cookie JWT đặt đúng domain đó.
     */
    private function googleDriver(string $app)
    {
        $driver = Socialite::driver('google')->stateless();

        $adminRedirect = config('services.google.admin_redirect');
        if ($app === 'admin' && $adminRedirect) {
            $driver->redirectUrl($adminRedirect);
        }

        return $driver;
    }

    /**
     * Ghép đường dẫn vào URL React — mặc định app khách hàng (config
     * app.frontend_url), hoặc $base nếu luồng đến từ Dashboard (app.admin_url).
     */
    private function frontendUrl(string $path = '/', ?string $base = null): string
    {
        return rtrim($base ?? (string) config('app.frontend_url'), '/').$path;
    }
}
