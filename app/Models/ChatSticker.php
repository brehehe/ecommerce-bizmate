<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChatSticker extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'category',
        'image_path',
        'order',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /** @var list<string> */
    protected $appends = ['image_url', 'url'];

    /**
     * Get the full public URL for the sticker image.
     */
    public function getImageUrlAttribute(): string
    {
        return asset('storage/'.$this->image_path);
    }

    /**
     * Alias of image_url for frontend compatibility.
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->image_path);
    }
}
