<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuModifierItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'menu_modifier_id',
        'name',
        'additional_price',
        'is_default',
        'is_available',
    ];

    protected $casts = [
        'additional_price' => 'decimal:2',
        'is_default' => 'boolean',
        'is_available' => 'boolean',
    ];

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(MenuModifier::class, 'menu_modifier_id');
    }
}
