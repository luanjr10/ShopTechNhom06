<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiFormRequest;

class LoginRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'login' => 'required|string', // username hoặc email
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Vui lòng nhập tên đăng nhập hoặc email',
            'password.required' => 'Vui lòng nhập mật khẩu',
        ];
    }
}
