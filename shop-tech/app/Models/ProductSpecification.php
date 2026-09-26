<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ProductSpecification extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'product_specifications';

    protected $fillable = [
        'productId',
        'specifications',
        'variants',
    ];

    protected $casts = [
        'productId' => 'integer',
        'specifications' => 'array',
        'variants' => 'array',
    ];
}
