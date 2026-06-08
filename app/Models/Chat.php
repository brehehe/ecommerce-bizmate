<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Chat extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'subject',
        'product_id',
        'status',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->orderBy('created_at', 'desc');
    }

    /** Count unread messages sent by the user (not yet read by admin). */
    public function unreadByAdmin(): int
    {
        return $this->messages()->where('sender_type', 'user')->where('is_read', false)->count();
    }
}
