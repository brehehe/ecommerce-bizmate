<?php

namespace App\Actions\Product;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class BulkDeleteProductsAction
{
    public function __construct(
        protected DeleteProductAction $deleteProductAction
    ) {}

    /**
     * Bulk delete products.
     *
     * @param  array<int, string>  $ids
     */
    public function execute(array $ids): int
    {
        return DB::transaction(function () use ($ids) {
            $count = 0;
            $products = Product::whereIn('id', $ids)->get();
            foreach ($products as $product) {
                $this->deleteProductAction->execute($product);
                $count++;
            }

            return $count;
        });
    }
}
