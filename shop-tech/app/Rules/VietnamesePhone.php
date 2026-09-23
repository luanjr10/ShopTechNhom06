<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Số điện thoại Việt Nam: đúng 10 chữ số, bắt đầu bằng 0 (VD 0912345678).
 * Dùng chung cho profile, địa chỉ giao hàng, địa chỉ lấy hàng gian hàng,
 * đơn hàng (receiver_phone), nhân viên, đơn đăng ký mở gian hàng.
 */
class VietnamesePhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^0\d{9}$/', (string) $value)) {
            $fail('Số điện thoại phải gồm đúng 10 chữ số và bắt đầu bằng 0.');
        }
    }
}
