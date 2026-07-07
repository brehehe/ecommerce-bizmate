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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->enum('type', ['masuk', 'keluar', 'retur', 'penyesuaian']);
            $table->integer('quantity'); // positif = masuk, negatif = keluar
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->string('notes')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
