<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyCheckin extends Model
{
    protected $fillable = [
        'user_id',
        'checkin_date',
        'streak',
        'xu_earned',
    ];

    protected $casts = [
        'checkin_date' => 'date',
        'streak' => 'integer',
        'xu_earned' => 'integer',
    ];
}
