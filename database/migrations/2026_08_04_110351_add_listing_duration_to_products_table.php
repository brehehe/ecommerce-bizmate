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
            $table->timestamp('listing_expires_at')->nullable()->after('active');
            $table->decimal('listing_fee', 15, 2)->default(0)->after('listing_expires_at');
            $table->integer('listing_days')->default(0)->after('listing_fee');
            $table->index('listing_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['listing_expires_at']);
            $table->dropColumn(['listing_expires_at', 'listing_fee', 'listing_days']);
        });
    }
};
