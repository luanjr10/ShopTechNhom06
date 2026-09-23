<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetCodeRequest;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    /**
     * [POST] /api/forgot-password — gửi mã OTP 6 số về email (hết hạn 60 giây).
     * Luôn trả về thông báo chung để không lộ email nào đã đăng ký.
     */
    public function forgot(ForgotPasswordRequest $request)
    {
        $email = (string) $request->input('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $code = $this->generateCode();

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($code), 'created_at' => now()],
            );

            $user->notify(new ResetPasswordNotification($code));
        }

        return response()->json([
            'success' => true,
            'message' => 'Nếu email tồn tại, chúng tôi đã gửi mã xác minh.',
            'ttl' => (int) config('auth.reset_code.ttl', 60),
        ]);
    }

    /**
     * [POST] /api/verify-reset-code — kiểm tra mã hợp lệ (KHÔNG tiêu thụ mã).
     * Dùng cho bước xác minh trước khi cho người dùng nhập mật khẩu mới.
     */
    public function verify(VerifyResetCodeRequest $request)
    {
        $error = $this->codeError(
            (string) $request->input('email'),
            (string) $request->input('code'),
        );

        if ($error) {
            return response()->json([
                'success' => false,
                'message' => $error,
                'errors' => ['code' => [$error]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mã hợp lệ',
        ]);
    }

    /**
     * [POST] /api/reset-password — xác minh mã OTP và đặt mật khẩu mới.
     */
    public function reset(ResetPasswordRequest $request)
    {
        $email = (string) $request->input('email');
        $code = (string) $request->input('code');

        $error = $this->codeError($email, $code);

        if ($error) {
            return response()->json([
                'success' => false,
                'message' => $error,
                'errors' => ['code' => [$error]],
            ], 422);
        }

        $user = User::where('email', $email)->first();

        $user->password = $request->input('password');
        $user->save();
        // Đổi mật khẩu → vô hiệu mọi phiên đã đăng nhập trước đó.
        $user->increment('token_version');

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập lại.',
        ]);
    }

    /**
     * Trả về thông báo lỗi nếu mã không hợp lệ/hết hạn, hoặc null nếu hợp lệ.
     * Mã hết hạn sẽ bị xoá khỏi bảng.
     */
    private function codeError(string $email, string $code): ?string
    {
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $row || ! Hash::check($code, $row->token)) {
            return 'Mã xác minh không đúng';
        }

        $ttl = (int) config('auth.reset_code.ttl', 60);

        if (Carbon::parse($row->created_at)->addSeconds($ttl)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return 'Mã xác minh đã hết hạn. Vui lòng yêu cầu mã mới.';
        }

        if (! User::where('email', $email)->exists()) {
            return 'Không tìm thấy tài khoản với email này.';
        }

        return null;
    }

    /**
     * Sinh mã OTP gồm N chữ số (không lộ ra ngoài, chỉ lưu bản băm).
     */
    private function generateCode(): string
    {
        $length = (int) config('auth.reset_code.length', 6);
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
