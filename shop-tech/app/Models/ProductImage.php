<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ProductImage extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'product_images';

    protected $fillable = [
        'productId',
        'images',
    ];

    protected $casts = [
        'productId' => 'integer',
    ];
}
