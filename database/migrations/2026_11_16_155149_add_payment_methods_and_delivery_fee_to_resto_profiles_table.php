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
        Schema::table('resto_profiles', function (Blueprint $table) {
            $table->json('payment_methods')->nullable()->after('operating_hours');
            $table->string('delivery_fee_type', 30)->default('flat')->after('max_delivery_radius_km'); // 'flat' | 'per_km'
            $table->decimal('delivery_fee_base', 12, 2)->default(10000.00)->after('delivery_fee_type');
            $table->decimal('delivery_free_min_order', 12, 2)->default(0.00)->after('delivery_fee_base');
            $table->string('qris_image_path')->nullable()->after('banner_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resto_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'payment_methods',
                'delivery_fee_type',
                'delivery_fee_base',
                'delivery_free_min_order',
                'qris_image_path',
            ]);
        });
    }
};
