<?php

namespace App\Models;

use Database\Factories\CourierFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Courier extends Model
{
    /** @use HasFactory<CourierFactory> */
    use HasFactory;

    use HasUuids, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->order)) {
                $model->order = static::withTrashed()->max('order') + 1;
            }
        });
    }
}
