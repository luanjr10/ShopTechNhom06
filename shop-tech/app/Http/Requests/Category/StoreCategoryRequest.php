<?php

namespace App\Http\Requests\Category;

use App\Http\Requests\ApiFormRequest;

class StoreCategoryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:categories,code',
            'name' => 'required|string|max:150|unique:categories,name',
            'parent_id' => 'nullable|integer|exists:categories,id',
            'description' => 'nullable|string|max:500',
            'display_type' => 'nullable|string|in:icon,image',
            'icon' => 'required_if:display_type,icon|nullable|string|max:150',
            'color' => 'required_if:display_type,icon|nullable|string|max:150',
            'status' => 'required|integer|in:0,1',
            'brand_ids' => 'nullable|array',
            'brand_ids.*' => 'integer|exists:brands,id',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Vui lòng nhập mã danh mục',
            'code.max' => 'Mã danh mục không được vượt quá :max ký tự',
            'code.unique' => 'Mã danh mục này đã tồn tại',
            'name.required' => 'Vui lòng nhập tên danh mục',
            'name.max' => 'Tên danh mục không được vượt quá :max ký tự',
            'name.unique' => 'Tên danh mục này đã tồn tại',
            'parent_id.integer' => 'Danh mục cha không hợp lệ',
            'parent_id.exists' => 'Danh mục cha được chọn không tồn tại',
            'description.max' => 'Mô tả không được vượt quá :max ký tự',
            'icon.required_if' => 'Vui lòng nhập tên icon',
            'icon.max' => 'Tên icon không được vượt quá :max ký tự',
            'color.required_if' => 'Vui lòng nhập màu icon',
            'color.max' => 'Màu icon không được vượt quá :max ký tự',
            'status.required' => 'Vui lòng chọn trạng thái',
            'status.in' => 'Trạng thái không hợp lệ',
            'brand_ids.array' => 'Danh sách thương hiệu không hợp lệ',
            'brand_ids.*.integer' => 'Thương hiệu không hợp lệ',
            'brand_ids.*.exists' => 'Thương hiệu được chọn không tồn tại',
        ];
    }
}
