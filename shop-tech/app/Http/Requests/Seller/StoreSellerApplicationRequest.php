<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\ApiFormRequest;

class StoreSellerApplicationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'shop_name' => 'required|string|max:150',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'integer|exists:categories,id',
        ];
    }

    public function messages(): array
    {
        return [
            'shop_name.required' => 'Vui lòng nhập tên gian hàng dự kiến',
            'category_ids.required' => 'Vui lòng chọn ít nhất một danh mục kinh doanh',
            'category_ids.min' => 'Vui lòng chọn ít nhất một danh mục kinh doanh',
            'category_ids.*.exists' => 'Danh mục được chọn không hợp lệ',
        ];
    }
}
