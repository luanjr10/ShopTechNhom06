<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;

class ChangePasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed|different:current_password',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại',
            'password.required' => 'Vui lòng nhập mật khẩu mới',
            'password.min' => 'Mật khẩu tối thiểu :min ký tự',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp',
            'password.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại',
        ];
    }
}
