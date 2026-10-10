<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentPayment extends Model
{
    protected $fillable = [
        'plan_id',
        'number',
        'amount',
        'principal_part',
        'interest_part',
        'due_date',
        'status',
        'paid_at',
        'payment_ref',
        'reminded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'principal_part' => 'decimal:2',
        'interest_part' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'reminded_at' => 'datetime',
        'number' => 'integer',
    ];

    protected $appends = ['is_overdue'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'plan_id');
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending'
            && $this->due_date !== null
            && $this->due_date->toDateString() < now('Asia/Ho_Chi_Minh')->toDateString();
    }
}
