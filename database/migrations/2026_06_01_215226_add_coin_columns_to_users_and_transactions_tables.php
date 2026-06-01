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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('coins_balance')->default(0)->after('last_active_at');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('coins_redeemed')->default(0)->after('notes');
            $table->integer('coins_value')->default(0)->after('coins_redeemed');
            $table->integer('coins_earned')->default(0)->after('coins_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('coins_balance');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['coins_redeemed', 'coins_value', 'coins_earned']);
        });
    }
};
