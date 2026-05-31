<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unique(['product_id', 'category_id']);
        });

        Schema::create('product_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('brand_id')->constrained('brands')->cascadeOnDelete();
            $table->unique(['product_id', 'brand_id']);
        });

        // Copy existing categories
        $products = DB::table('products')->whereNotNull('category_id')->get();
        foreach ($products as $product) {
            DB::table('product_categories')->insert([
                'product_id' => $product->id,
                'category_id' => $product->category_id,
            ]);
        }

        // Copy existing brands
        $productsWithBrands = DB::table('products')->whereNotNull('brand_id')->get();
        foreach ($productsWithBrands as $product) {
            DB::table('product_brands')->insert([
                'product_id' => $product->id,
                'brand_id' => $product->brand_id,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('product_brands');
    }
};
