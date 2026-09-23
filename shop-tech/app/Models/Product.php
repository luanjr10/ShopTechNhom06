<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'code',
        'name',
        'slug',
        'price',
        'discount_percent',
        'is_featured',
        'is_flash_sale',
        'stock',
        'status',
        'category_id',
        'brand_id',
        'store_id',
        'weight',
        'length',
        'width',
        'height',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_flash_sale' => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brands::class, 'brand_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /** Chỉ bình luận GỐC (parent_id null) — trả lời nằm trong ProductComment::replies(). */
    public function comments(): HasMany
    {
        return $this->hasMany(ProductComment::class)->whereNull('parent_id');
    }
}
