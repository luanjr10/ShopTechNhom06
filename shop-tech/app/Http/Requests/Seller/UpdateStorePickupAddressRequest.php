<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\ApiFormRequest;
use App\Rules\VietnamesePhone;

class UpdateStorePickupAddressRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'pickup_contact_name' => 'required|string|max:150',
            'pickup_phone' => ['required', 'string', new VietnamesePhone],
            // Mã GHN (hệ cũ, có quận/huyện) — dùng làm địa chỉ lấy hàng khi tạo vận đơn thật.
            'province_id' => 'required|integer',
            'district_id' => 'required|integer',
            'ward_code' => 'required|string',
            'address_line' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'pickup_contact_name.required' => 'Vui lòng nhập tên liên hệ lấy hàng',
            'pickup_phone.required' => 'Vui lòng nhập số điện thoại lấy hàng',
            'province_id.required' => 'Vui lòng chọn Tỉnh/Thành phố',
            'district_id.required' => 'Vui lòng chọn Quận/Huyện',
            'ward_code.required' => 'Vui lòng chọn Phường/Xã',
            'address_line.required' => 'Vui lòng nhập số nhà, tên đường',
        ];
    }
}
