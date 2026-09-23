<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\ApiFormRequest;

class UpdateProductRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'price' => 'required|decimal:0,2|min:0',
            'discount_percent' => 'nullable|integer|min:0|max:100',
            'stock' => 'required|integer|min:0',
            'status' => 'required|integer|in:0,1',
            'category_id' => 'required|integer|exists:categories,id',
            'specifications' => 'nullable|json',
            'variants' => 'nullable|json',
            'use_case_ids' => 'nullable|json',
            'existing_images' => 'nullable|json',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên sản phẩm',
            'name.max' => 'Tên sản phẩm không được vượt quá :max ký tự',
            'price.required' => 'Vui lòng nhập giá sản phẩm',
            'price.decimal' => 'Giá sản phẩm không đúng định dạng số',
            'price.min' => 'Giá sản phẩm không được nhỏ hơn 0',
            'discount_percent.integer' => 'Giảm giá phải là số nguyên',
            'discount_percent.min' => 'Giảm giá không được nhỏ hơn 0%',
            'discount_percent.max' => 'Giảm giá không được vượt quá 100%',
            'stock.required' => 'Vui lòng nhập số lượng tồn kho',
            'stock.integer' => 'Số lượng tồn kho phải là số nguyên',
            'stock.min' => 'Số lượng tồn kho không được nhỏ hơn 0',
            'images.array' => 'Dữ liệu ảnh không hợp lệ',
            'images.*.image' => 'File tải lên phải là hình ảnh',
            'images.*.mimes' => 'Ảnh phải có định dạng jpg, jpeg, png hoặc webp',
            'images.*.max' => 'Dung lượng mỗi ảnh không được vượt quá 5MB',
            'status.required' => 'Vui lòng chọn trạng thái',
            'status.in' => 'Trạng thái không hợp lệ',
            'category_id.required' => 'Vui lòng chọn danh mục',
            'category_id.integer' => 'Danh mục không hợp lệ',
            'category_id.exists' => 'Danh mục đã chọn không tồn tại',
            'specifications.json' => 'Dữ liệu thông số kỹ thuật không hợp lệ',
            'existing_images.json' => 'Dữ liệu ảnh hiện tại không hợp lệ',
        ];
    }
}
