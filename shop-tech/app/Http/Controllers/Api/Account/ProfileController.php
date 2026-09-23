<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\UpdateAvatarRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Support\JwtCookie;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * [PATCH] /api/profile — cập nhật hồ sơ. Đổi email sẽ reset trạng thái
     * xác thực và gửi lại mail xác thực.
     */
    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $emailChanged = $user->email !== $data['email'];

        $user->fill($data);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật hồ sơ thành công'.($emailChanged ? '. Vui lòng xác thực email mới.' : ''),
            'data' => $user->fresh()->load('sellerProfile.stores'),
        ]);
    }

    /**
     * [POST] /api/profile/avatar — upload ảnh đại diện.
     */
    public function updateAvatar(UpdateAvatarRequest $request)
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật ảnh đại diện thành công',
            'data' => $user->fresh()->load('sellerProfile.stores'),
        ]);
    }

    /**
     * [POST] /api/change-password — đổi mật khẩu (nhập mật khẩu hiện tại).
     * Đăng xuất các thiết bị khác, cấp lại cookie cho thiết bị hiện tại.
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mật khẩu hiện tại không đúng',
                'errors' => ['current_password' => ['Mật khẩu hiện tại không đúng']],
            ], 422);
        }

        $user->password = $request->input('password');
        $user->save();
        $user->increment('token_version');

        $token = Auth::guard('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Đổi mật khẩu thành công',
        ])->withCookie(JwtCookie::make($token));
    }
}
