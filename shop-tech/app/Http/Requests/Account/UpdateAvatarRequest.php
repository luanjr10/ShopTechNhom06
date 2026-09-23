<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;

class UpdateAvatarRequest extends ApiFormRequest
{
    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'avatar' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Vui lòng chọn ảnh',
            'avatar.image' => 'Tệp phải là ảnh',
            'avatar.mimes' => 'Chỉ chấp nhận JPG, PNG hoặc WEBP',
            'avatar.max' => 'Ảnh tối đa 2MB',
        ];
    }
}
