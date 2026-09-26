<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

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
