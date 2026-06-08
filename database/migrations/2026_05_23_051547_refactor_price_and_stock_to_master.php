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
        Schema::create('product_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('cost', 15, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('product_stocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('stock')->default(0);
            $table->integer('min_stock')->default(0);
            $table->integer('min_purchase')->default(1);
            $table->boolean('is_unlimited')->default(false);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price', 'cost', 'stock', 'min_stock']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['price', 'stock']);
            $table->integer('weight')->nullable()->after('sku');
            $table->integer('length')->nullable()->after('weight');
            $table->integer('width')->nullable()->after('length');
            $table->integer('height')->nullable()->after('width');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master', function (Blueprint $table) {
            //
        });
    }
};
