<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    use HasUuids;

    protected $fillable = ['product_id', 'name', 'sort_order'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function options()
    {
        return $this->hasMany(ProductVariationOption::class)->orderBy('sort_order', 'asc');
    }
}
