<?php

namespace App\Http\Requests\Brand;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('brands', 'name')->ignore($this->route('id')),
            ],

            'description' => [
                'required',
                'string',
                'max:200',
            ],

            'status' => 'required|integer|in:0,1',

            'existing_images' => 'nullable|json',

            'images' => 'nullable|array|max:1',

            'images.*' => [
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên thương hiệu',
            'name.max' => 'Tên thương hiệu không được vượt quá :max ký tự',
            'name.unique' => 'Tên thương hiệu này đã tồn tại',

            'description.required' => 'Vui lòng nhập mô tả thương hiệu',
            'description.max' => 'Mô tả thương hiệu không được vượt quá :max ký tự',

            'images.array' => 'Dữ liệu ảnh không hợp lệ',
            'images.max' => 'Thương hiệu chỉ được phép có một ảnh',

            'images.*.image' => 'File tải lên phải là hình ảnh',
            'images.*.mimes' => 'Ảnh phải có định dạng jpg, jpeg, png hoặc webp',
            'images.*.max' => 'Dung lượng ảnh không được vượt quá 5MB',

            'status.required' => 'Vui lòng chọn trạng thái',
            'status.in' => 'Trạng thái không hợp lệ',

            'existing_images.json' => 'Dữ liệu ảnh hiện tại không hợp lệ',
        ];
    }
}
