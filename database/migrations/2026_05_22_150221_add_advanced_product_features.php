<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('category_id');
            $table->decimal('cost', 15, 2)->nullable()->after('price');
            $table->string('stock_status')->default('Tersedia (In Stock)')->after('min_stock');
            $table->string('summary')->nullable()->after('stock_status');
            $table->text('description')->nullable()->after('summary');
            $table->integer('weight')->default(0)->after('description');
            $table->integer('length')->default(0)->after('weight');
            $table->integer('width')->default(0)->after('length');
            $table->integer('height')->default(0)->after('width');
            $table->boolean('tax_enabled')->default(false)->after('height');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_enabled');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->boolean('is_main')->default(false);
            $table->timestamps();
        });

        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. Warna, Ukuran
            $table->timestamps();
        });

        Schema::create('product_variation_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variation_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // e.g. Merah, L
            $table->string('description')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->decimal('price', 15, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->string('image')->nullable();
            $table->timestamps();
        });

        Schema::create('product_variant_option_combinations', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('product_variation_option_id')->constrained('product_variation_options')->cascadeOnDelete();
            $table->primary(['product_variant_id', 'product_variation_option_id'], 'pv_pvo_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_combinations');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_variation_options');
        Schema::dropIfExists('product_variations');
        Schema::dropIfExists('product_images');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'brand', 'cost', 'stock_status', 'summary', 'description',
                'weight', 'length', 'width', 'height', 'tax_enabled', 'tax_rate'
            ]);
        });
    }
};
