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
            $table->boolean('is_digital')->default(false)->after('active');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->string('note', 500)->nullable()->after('promo_quantity_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_digital');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
