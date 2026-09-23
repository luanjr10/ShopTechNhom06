<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brands extends Model
{
    protected $table = 'brands';

    protected $fillable = [
        'code',
        'name',
        'slug',
        'logo',
        'description',
        'status',
    ];

    public function products(): HasMany
    {
        return $this->hashMany(Product::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_brand', 'brand_id', 'category_id');
    }
}
