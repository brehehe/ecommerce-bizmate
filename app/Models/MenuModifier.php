<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuModifier extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'product_id',
        'group_name',
        'is_required',
        'is_multiple',
        'max_selectable',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_multiple' => 'boolean',
        'max_selectable' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuModifierItem::class);
    }
}
