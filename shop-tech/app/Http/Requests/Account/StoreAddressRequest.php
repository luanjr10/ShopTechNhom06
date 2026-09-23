<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\ApiFormRequest;
use App\Rules\VietnamesePhone;

class StoreAddressRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => 'required|string|max:150',
            'phone' => ['required', 'string', new VietnamesePhone],
            // Mã GHN (hệ cũ, có quận/huyện) — bắt buộc để tính phí/tạo vận đơn thật.
            'province_id' => 'required|integer',
            'district_id' => 'required|integer',
            'ward_code' => 'required|string',
            'address_line' => 'required|string|max:255',
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
