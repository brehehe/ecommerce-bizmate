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
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->foreignUuid('applied_promotion_id')->nullable()->constrained('promotions')->nullOnDelete();
            $table->integer('promo_quantity_used')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('applied_promotion_id');
            $table->dropColumn('promo_quantity_used');
        });
    }
};
