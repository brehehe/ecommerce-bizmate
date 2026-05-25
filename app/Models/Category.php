<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    //
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'image',
        'parent_id',
        'order',
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('order');
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Model $model) {
            if (empty($model->order)) {
                $model->order = static::withTrashed()->max('order') + 1;
            }
        });
    }
}
