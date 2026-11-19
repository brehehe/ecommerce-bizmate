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
        Schema::table('cart_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('cart_items', 'selected_modifiers')) {
                $table->json('selected_modifiers')->nullable()->after('note');
            }
        });

        Schema::table('transaction_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('transaction_items', 'selected_modifiers')) {
                $table->json('selected_modifiers')->nullable()->after('note');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table): void {
            if (Schema::hasColumn('cart_items', 'selected_modifiers')) {
                $table->dropColumn('selected_modifiers');
            }
        });

        Schema::table('transaction_items', function (Blueprint $table): void {
            if (Schema::hasColumn('transaction_items', 'selected_modifiers')) {
                $table->dropColumn('selected_modifiers');
            }
        });
    }
};
