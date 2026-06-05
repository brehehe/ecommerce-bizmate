<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialMedia extends Model
{
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
