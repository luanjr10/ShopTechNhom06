<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'display_type',
        'status',
        'status_order',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brands::class, 'category_brand', 'category_id', 'brand_id');
    }

    /** Danh mục cha (null nếu đây đã là danh mục cấp cao nhất). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** Danh mục con — hệ thống chỉ hỗ trợ tối đa 2 cấp (cha - con). */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
