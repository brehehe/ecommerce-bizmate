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
            $table->timestamp('payment_expires_at')->nullable()->after('cancelled_at');
            $table->timestamp('auto_complete_at')->nullable()->after('payment_expires_at');
            $table->boolean('is_extended')->default(false)->after('auto_complete_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['payment_expires_at', 'auto_complete_at', 'is_extended']);
        });
    }
};
