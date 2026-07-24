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
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_tx_status_created');
            $table->index(['user_id', 'created_at'], 'idx_tx_user_created');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['type', 'created_at'], 'idx_stock_type_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_tx_status_created');
            $table->dropIndex('idx_tx_user_created');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_stock_type_created');
        });
    }
};
