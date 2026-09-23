<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerApplication extends Model
{
    protected $fillable = [
        'user_id',
        'shop_name',
        'phone',
        'address',
        'category_ids',
        'status',
        'reject_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'category_ids' => 'array',
    ];

    protected $appends = ['category_names'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Tên các danh mục kinh doanh đã chọn (để hiển thị ở admin).
     *
     * @return array<int, string>
     */
    public function getCategoryNamesAttribute(): array
    {
        $ids = $this->category_ids ?? [];

        if (empty($ids)) {
            return [];
        }

        return Category::whereIn('id', $ids)->pluck('name')->all();
    }
}
