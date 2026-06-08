<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'label',
    'receiver_name',
    'phone_number',
    'full_address',
    'province_id',
    'province_name',
    'regency_id',
    'regency_name',
    'district_id',
    'district_name',
    'village_id',
    'village_name',
    'postal_code',
    'latitude',
    'longitude',
    'note',
    'is_primary',
])]
class CustomerAddress extends Model
{
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Get the user that owns the address.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
