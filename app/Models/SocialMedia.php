<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SocialMedia extends Model
{
    use HasUuids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'platform',
        'label',
        'url',
        'icon',
        'order',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];
}
