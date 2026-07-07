<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    //
    use HasUuids, SoftDeletes;

    protected $fillable = ['key', 'value', 'order'];

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->order)) {
                $model->order = static::withTrashed()->max('order') + 1;
            }
        });
    }
}
