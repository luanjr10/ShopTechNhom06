<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    protected $fillable = [
        'user_id',
        'store_id',
        'last_message_at',
        'last_message_preview',
        'customer_unread',
        'store_unread',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'customer_unread' => 'integer',
        'store_unread' => 'integer',
    ];

    public function isSupport(): bool
    {
        return $this->store_id === null;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }
}
