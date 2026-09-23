<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\ApiFormRequest;

class StoreStoreRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên gian hàng',
            'logo.image' => 'Logo phải là hình ảnh',
            'logo.max' => 'Logo không được vượt quá 5MB',
        ];
    }
}
