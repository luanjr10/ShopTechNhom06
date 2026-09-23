<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Ảnh đại diện của danh mục (thay cho icon) — lưu MongoDB như ProductImage,
 * vì `categories` (MySQL) chỉ có cột `icon` cho hiển thị dạng icon.
 */
class CategoryImage extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'category_images';

    protected $fillable = [
        'categoryId',
        'image',
    ];

    protected $casts = [
        'categoryId' => 'integer',
    ];
}
