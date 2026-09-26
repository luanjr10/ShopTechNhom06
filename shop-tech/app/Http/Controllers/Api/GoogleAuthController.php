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
    public function redirect(Request $request)
    {
        $app = $request->query('app') === 'admin' ? 'admin' : 'client';

        return $this->googleDriver($app)
            ->with(['state' => $app])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $app = $request->query('state') === 'admin' ? 'admin' : 'client';
        $frontendBase = $app === 'admin' ? (string) config('app.admin_url') : null;

        try {
            $googleUser = $this->googleDriver($app)->user();
        } catch (Throwable $e) {
            Log::error('Google OAuth callback lỗi', ['exception' => $e->getMessage(), 'class' => get_class($e)]);

            return redirect($this->frontendUrl('/login?error=google', $frontendBase));
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user && $googleUser->getEmail()) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'google_avatar' => $googleUser->getAvatar(),
                ]);
            }
        }

        if ($app === 'admin') {
            if (! $user) {
                return redirect($this->frontendUrl('/login?error=google_not_registered', $frontendBase));
            }

            if (! in_array($user->role, ['admin', 'seller', 'employee'], true)) {
                return redirect($this->frontendUrl('/login?error=google_no_access', $frontendBase));
            }
        }

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

            $user->markEmailAsVerified();
        }

        $token = Auth::guard('api')->login($user);

        return redirect($this->frontendUrl('/', $frontendBase))
            ->withCookie(JwtCookie::make($token));
    }

    private function googleDriver(string $app)
    {
        $driver = Socialite::driver('google')->stateless();

        $adminRedirect = config('services.google.admin_redirect');
        if ($app === 'admin' && $adminRedirect) {
            $driver->redirectUrl($adminRedirect);
        }

        return $driver;
    }

    private function frontendUrl(string $path = '/', ?string $base = null): string
    {
        return rtrim($base ?? (string) config('app.frontend_url'), '/').$path;
    }
}
