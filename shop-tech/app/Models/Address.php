<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'recipient_name',
    'phone',
    'province_code',
    'province_name',
    'ward_code',
    'ward_name',
    'address_line',
    'is_default',
    // Mã GHN (hệ cũ, có quận/huyện) — nguồn DUY NHẤT dùng để tính phí/tạo vận đơn
    // từ nay trở đi. province_code/ward_code phía trên (provinces.open-api.vn) giữ
    // lại cho dữ liệu cũ, không còn được ghi mới.
    'province_id_ghn',
    'province_name_ghn',
    'district_id',
    'district_name',
    'ward_code_ghn',
    'ward_name_ghn',
])]
class Address extends Model
{
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
