<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class SellerProductRequest extends ApiFormRequest
{
    public function isCreate(): bool
    {
        return $this->isMethod('post') && ! $this->route('product');
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'code' => [
                $this->isCreate() ? 'required' : 'sometimes',
                'string', 'max:150',
                Rule::unique('products', 'code')->ignore($productId),
            ],
            'name' => 'required|string|max:255',
            'price' => 'required|decimal:0,2|min:0',
            'discount_percent' => 'nullable|integer|min:0|max:100',
            'stock' => 'required|integer|min:0',
            'status' => 'required|integer|in:0,1',
            'category_id' => 'required|integer|exists:categories,id',
            'images' => [$this->isCreate() ? 'required' : 'nullable', 'array'],
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'existing_images' => 'nullable|json',
            'specifications' => 'nullable|json',
            'variants' => 'nullable|json',
            'use_case_ids' => 'nullable|json',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Vui lòng nhập mã sản phẩm',
            'code.unique' => 'Mã sản phẩm đã tồn tại',
            'name.required' => 'Vui lòng nhập tên sản phẩm',
            'price.required' => 'Vui lòng nhập giá',
            'stock.required' => 'Vui lòng nhập tồn kho',
            'category_id.required' => 'Vui lòng chọn danh mục',
            'category_id.exists' => 'Danh mục không tồn tại',
            'images.required' => 'Vui lòng chọn ít nhất một ảnh',
        ];
    }
}
