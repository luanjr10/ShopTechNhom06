<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * [GET] /api/email/verify/{id}/{hash} — link signed từ email.
     * Chữ ký đã được middleware `signed` kiểm tra; ở đây đối chiếu hash email
     * rồi đánh dấu đã xác thực và redirect về client.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect($this->frontendUrl('/tai-khoan?verified=invalid'));
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect($this->frontendUrl('/tai-khoan?verified=1'));
    }

    /**
     * [POST] /api/email/verification-notification — gửi lại email xác thực.
     */
    public function resend(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email đã được xác thực.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi lại email xác thực.',
        ]);
    }

    private function frontendUrl(string $path = '/'): string
    {
        return rtrim((string) config('app.frontend_url'), '/').$path;
    }
}
