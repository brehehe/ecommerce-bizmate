<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignUuid('transaction_item_id')->constrained('transaction_items')->cascadeOnDelete();
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->string('product_name', 255);
            $table->string('variant_name', 255)->nullable();
            $table->unsignedInteger('quantity_returned');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('refund_subtotal', 14, 2)->default(0);
            $table->timestamps();

            $table->index('return_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
