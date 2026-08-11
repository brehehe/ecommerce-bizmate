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
            $table->string('price_type')->default('net')->after('condition'); // 'net' or 'nego'
            $table->string('usage_period')->nullable()->after('price_type'); // e.g. "3 Bulan", "Beli Jan 2024"
            $table->string('contact_name')->nullable()->after('usage_period'); // contact person name
            $table->string('contact_phone')->nullable()->after('contact_name'); // contact phone number
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_type', 'usage_period', 'contact_name', 'contact_phone']);
        });
    }
};
