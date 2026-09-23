<?php

namespace App\Http\Requests\UseCase;

use App\Http\Requests\ApiFormRequest;

class StoreUseCaseRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp,svg|max:5120',
            'sortOrder' => 'nullable|integer|min:0',
            'status' => 'required|integer|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên Quick Link',
            'name.max' => 'Tên quá :max ký tự',
            'image.required' => 'Vui lòng chọn ảnh cho Q Quick Link không được vượtuick Link',
            'image.image' => 'File tải lên phải là hình ảnh',
            'image.mimes' => 'Ảnh phải có định dạng jpg, jpeg, png, webp hoặc svg',
            'image.max' => 'Dung lượng ảnh không được vượt quá 5MB',
            'sortOrder.integer' => 'Thứ tự phải là số nguyên',
            'sortOrder.min' => 'Thứ tự không được nhỏ hơn 0',
            'status.required' => 'Vui lòng chọn trạng thái',
            'status.in' => 'Trạng thái không hợp lệ',
        ];
    }
}
