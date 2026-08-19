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
        Schema::table('product_ads', function (Blueprint $table) {
            $table->boolean('show_badge')->default(false)->after('status');
            $table->json('placements')->nullable()->after('show_badge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_ads', function (Blueprint $table) {
            $table->dropColumn(['show_badge', 'placements']);
        });
    }
};
