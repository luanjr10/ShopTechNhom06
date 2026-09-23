<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;
use App\Rules\VietnamesePhone;

class UpdateAddressRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => 'sometimes|required|string|max:150',
            'phone' => ['sometimes', 'required', 'string', new VietnamesePhone],
            'province_id' => 'sometimes|required|integer',
            'district_id' => 'sometimes|required|integer',
            'ward_code' => 'sometimes|required|string',
            'address_line' => 'sometimes|required|string|max:255',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'recipient_name.required' => 'Vui lòng nhập tên người nhận',
            'phone.required' => 'Vui lòng nhập số điện thoại',
            'province_id.required' => 'Vui lòng chọn Tỉnh/Thành phố',
            'district_id.required' => 'Vui lòng chọn Quận/Huyện',
            'ward_code.required' => 'Vui lòng chọn Phường/Xã',
            'address_line.required' => 'Vui lòng nhập số nhà, tên đường',
        ];
    }
}
