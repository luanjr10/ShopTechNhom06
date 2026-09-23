<?php

namespace App\Http\Requests\Category;

use App\Http\Requests\ApiFormRequest;
use App\Models\Category;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('categories', 'name')->ignore($this->route('id')),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                Rule::notIn([$this->route('id')]),
            ],
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
            'name.required' => 'Vui lòng nhập tên danh mục',
            'name.max' => 'Tên danh mục không được vượt quá :max ký tự',
            'name.unique' => 'Tên danh mục này đã tồn tại',
            'parent_id.integer' => 'Danh mục cha không hợp lệ',
            'parent_id.exists' => 'Danh mục cha được chọn không tồn tại',
            'parent_id.not_in' => 'Danh mục không thể là danh mục cha của chính nó',
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

    /**
     * Cây danh mục không giới hạn số cấp (danh mục con vẫn có thể có danh mục
     * con riêng). Chỉ cần chặn VÒNG LẶP: không được chọn chính danh mục này,
     * hoặc bất kỳ danh mục con/cháu nào của nó, làm danh mục cha của nó.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $parentId = $this->input('parent_id');
            $currentId = (int) $this->route('id');

            if (! $parentId) {
                return;
            }

            // Đi ngược từ danh mục cha vừa chọn lên tổ tiên — nếu gặp lại
            // chính danh mục đang sửa thì đây là vòng lặp (không hợp lệ).
            $ancestor = Category::find((int) $parentId);
            $guard = 0;

            while ($ancestor && $guard < 50) {
                if ($ancestor->id === $currentId) {
                    $validator->errors()->add(
                        'parent_id',
                        'Không thể chọn danh mục con/cháu của chính nó làm danh mục cha (tạo vòng lặp)'
                    );

                    return;
                }

                $ancestor = $ancestor->parent_id ? Category::find($ancestor->parent_id) : null;
                $guard++;
            }
        });
    }
}
