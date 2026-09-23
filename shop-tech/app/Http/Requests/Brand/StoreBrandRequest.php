<?php

namespace App\Http\Requests\Brand;

use App\Http\Requests\ApiFormRequest;

class StoreBrandRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:150|unique:brands,code',
            'name' => 'required|string|max:255|unique:brands,name',
            'description' => 'required|string|max:200',
            'images' => 'required|array|size:1',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'status' => 'required|integer|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Vui lòng nhập mã thương hiệu',
            'code.max' => 'Mã thương hiệu không được vượt quá :max ký tự',
            'code.unique' => 'Mã thương hiệu này đã tồn tại',
            'name.required' => 'Vui lòng nhập tên thương hiệu',
            'name.max' => 'Tên thương hiệu không được vượt quá :max ký tự',
            'name.unique' => 'Tên thương hiệu này đã tồn tại',
            'images.required' => 'Vui lòng chọn ít nhất một ảnh thương hiệu',
            'images.array' => 'Dữ liệu ảnh không hợp lệ',
            'images.min' => 'Vui lòng chọn ít nhất một ảnh thương hiệu',
            'images.*.image' => 'File tải lên phải là hình ảnh',
            'images.*.mimes' => 'Ảnh phải có định dạng jpg, jpeg, png hoặc webp',
            'images.*.max' => 'Dung lượng mỗi ảnh không được vượt quá 5MB',
            'status.required' => 'Vui lòng chọn trạng thái',
            'status.in' => 'Trạng thái không hợp lệ',
        ];
    }
}
