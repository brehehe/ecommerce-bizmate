<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'bank_name',
        'account_number',
        'account_name',
        'api_key',
        'api_secret',
        'admin_fee',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'admin_fee' => 'decimal:2',
    ];

    // protected static function booted(): void
    // {
    //     static::creating(function (Model $model) {
    //         if (empty($model->order)) {
    //             $model->order = static::withTrashed()->max('order') + 1;
    //         }
    //     });
    // }
}
