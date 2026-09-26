<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class VietnamesePhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^0\d{9}$/', (string) $value)) {
            $fail('Số điện thoại phải gồm đúng 10 chữ số và bắt đầu bằng 0.');
        }
    }
}
