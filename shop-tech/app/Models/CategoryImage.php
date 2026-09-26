<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

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
