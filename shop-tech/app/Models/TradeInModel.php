<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeInModel extends Model
{
    public const CATEGORIES = [
        'phone' => 'Điện thoại',
        'laptop' => 'Laptop',
        'tablet' => 'Máy tính bảng',
        'watch' => 'Đồng hồ thông minh',
        'other' => 'Khác',
    ];

    protected $fillable = [
        'store_id',
        'category',
        'brand',
        'name',
        'base_price',
        'is_active',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
