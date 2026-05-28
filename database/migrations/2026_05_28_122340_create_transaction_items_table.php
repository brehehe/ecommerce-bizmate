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
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // Snapshot saat transaksi dibuat
            $table->string('product_name');
            $table->string('product_sku');
            $table->string('variant_name')->nullable();
            $table->string('product_image')->nullable();
            $table->integer('quantity');

            // Pricing snapshot
            $table->decimal('hpp', 15, 2)->default(0); // Harga Pokok Penjualan
            $table->decimal('harga_jual', 15, 2)->default(0); // harga jual sebelum diskon item
            $table->decimal('diskon_item', 15, 2)->default(0); // diskon dari promo per item
            $table->decimal('harga_akhir', 15, 2)->default(0); // harga setelah diskon item
            $table->decimal('subtotal', 15, 2)->default(0); // harga_akhir × quantity

            // Bundling gift
            $table->boolean('is_gift_item')->default(false);
            $table->string('gift_from_promotion')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
