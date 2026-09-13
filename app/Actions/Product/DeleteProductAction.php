<?php

namespace App\Actions\Product;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteProductAction
{
    /**
     * Delete product and cleanup media assets.
     */
    public function execute(Product $product): bool
    {
        return DB::transaction(function () use ($product) {
            // Delete product images
            foreach ($product->images as $image) {
                $rawPath = str_replace('storage/', '', $image->path);
                Storage::disk('public')->delete($rawPath);
            }

            // Delete video/models if stored locally
            if ($product->video_path && str_starts_with($product->video_path, 'storage/')) {
                Storage::disk('public')->delete(str_replace('storage/', '', $product->video_path));
            }
            if ($product->model_3d_path && str_starts_with($product->model_3d_path, 'storage/')) {
                Storage::disk('public')->delete(str_replace('storage/', '', $product->model_3d_path));
            }
            if ($product->model_3d_usdz_path && str_starts_with($product->model_3d_usdz_path, 'storage/')) {
                Storage::disk('public')->delete(str_replace('storage/', '', $product->model_3d_usdz_path));
            }

            return $product->delete();
        });
    }
}
