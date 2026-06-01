<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnMedia extends Model
{
    use HasFactory;

    protected $table = 'return_media';

    protected $fillable = [
        'return_id',
        'file_path',
        'file_type',
        'disk',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    /**
     * Get the full URL for this media file.
     */
    public function getUrlAttribute(): string
    {
        if (str_starts_with($this->file_path, 'http')) {
            return $this->file_path;
        }

        return '/storage/'.$this->file_path;
    }
}
