<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductStock extends Model
{
    use HasUuids;

    protected $fillable = ['product_id', 'product_variant_id', 'stock', 'min_stock', 'min_purchase', 'is_unlimited'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    protected $casts = [
        'is_unlimited' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (ProductStock $productStock) {
            if ($productStock->is_unlimited) {
                return;
            }

            $product = $productStock->product;
            if (! $product) {
                return;
            }

            $variantName = '';
            if ($productStock->variant && $productStock->variant->options->count() > 0) {
                $variantName = ' ('.$productStock->variant->options->pluck('name')->implode(', ').')';
            }

            $productName = $product->name.$variantName;

            // If stock is 0 or less
            if ($productStock->stock <= 0) {
                $operator = DB::connection()->getDriverName() === 'sqlite' ? 'like' : 'ilike';
                $exists = Notification::where('type', 'out_of_stock')
                    ->where('url', '/admin/store/stocks')
                    ->where('message', $operator, '%'.$productName.'%')
                    ->where('is_read', false)
                    ->exists();

                if (! $exists) {
                    Notification::create([
                        'user_id' => null,
                        'title' => 'Stok Produk Habis',
                        'message' => 'Stok untuk produk '.$productName.' telah habis (0).',
                        'type' => 'out_of_stock',
                        'url' => '/admin/store/stocks',
                        'is_read' => false,
                    ]);
                }
            }
            // If stock is below or equal to min_stock
            elseif ($productStock->stock <= $productStock->min_stock) {
                $operator = DB::connection()->getDriverName() === 'sqlite' ? 'like' : 'ilike';
                $exists = Notification::where('type', 'low_stock')
                    ->where('url', '/admin/store/stocks')
                    ->where('message', $operator, '%'.$productName.'%')
                    ->where('is_read', false)
                    ->exists();

                if (! $exists) {
                    Notification::create([
                        'user_id' => null,
                        'title' => 'Stok Produk Menipis',
                        'message' => 'Stok untuk produk '.$productName.' menipis (sisa '.$productStock->stock.').',
                        'type' => 'low_stock',
                        'url' => '/admin/store/stocks',
                        'is_read' => false,
                    ]);
                }
            }
        });
    }
}
