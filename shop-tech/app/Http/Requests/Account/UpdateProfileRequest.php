<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;
use App\Rules\VietnamesePhone;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        // Chỉ tra DNS (kiểm tra domain có thật, MX/A record tồn tại) khi email
        // THAY ĐỔI so với hiện tại — tránh chặn việc lưu hồ sơ của user cũ nếu
        // email cũ trót không còn resolve được; email mới nhập luôn bị soi kỹ.
        $emailChanged = $this->input('email') !== $this->user()->email;

        return [
            'name' => 'required|string|max:150',
            'username' => [
                'required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'email' => [
                'required', $emailChanged ? 'email:rfc,dns' : 'email:rfc', 'max:190',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['nullable', 'string', new VietnamesePhone],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ tên',
            'username.required' => 'Vui lòng nhập tên đăng nhập',
            'username.alpha_dash' => 'Tên đăng nhập chỉ gồm chữ, số, gạch ngang/dưới',
            'username.unique' => 'Tên đăng nhập đã tồn tại',
            'email.required' => 'Vui lòng nhập email',
            'email.email' => 'Email không hợp lệ hoặc tên miền không tồn tại',
            'email.unique' => 'Email đã tồn tại',
        ];
    }
}
