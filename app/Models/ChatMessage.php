<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'chat_id',
        'sender_type',
        'sender_id',
        'body',
        'attachment_type',
        'attachment_data',
        'is_read',
    ];

    protected $casts = [
        'attachment_data' => 'array',
        'is_read' => 'boolean',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }
}
