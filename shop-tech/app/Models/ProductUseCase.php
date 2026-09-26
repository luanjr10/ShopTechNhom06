<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ProductUseCase extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'product_use_cases';

    protected $fillable = [
        'productId',
        'useCaseIds',
    ];

    protected $casts = [
        'productId' => 'integer',
    ];

}
