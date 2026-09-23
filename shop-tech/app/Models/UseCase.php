<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Quick Link (nhu cầu sử dụng) gắn với một danh mục MySQL qua `categoryId`.
 * Lưu trên MongoDB giống các dữ liệu bổ trợ khác (ProductImage, ProductSpecification).
 */
class UseCase extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'use_cases';

    protected $fillable = [
        'categoryId',
        'name',
        'slug',
        'image',
        'sortOrder',
        'status',
    ];

    protected $casts = [
        'categoryId' => 'integer',
        'sortOrder' => 'integer',
        'status' => 'boolean',
    ];
}
