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
        Schema::table('product_listing_payments', function (Blueprint $table) {
            $table->decimal('original_amount', 15, 2)->nullable()->after('amount');
            $table->string('promo_name')->nullable()->after('original_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_listing_payments', function (Blueprint $table) {
            $table->dropColumn(['original_amount', 'promo_name']);
        });
    }
};
