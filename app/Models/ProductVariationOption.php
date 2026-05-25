<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariationOption extends Model
{
    protected $fillable = ['product_variation_id', 'name', 'description', 'image'];

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    public function variants()
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_option_combinations');
    }
}
