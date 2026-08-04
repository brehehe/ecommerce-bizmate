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
            $table->boolean('is_seller')->default(false)->after('is_active');
            $table->string('store_name')->nullable()->after('is_seller');
            $table->string('store_slug')->nullable()->after('store_name');
            $table->string('store_logo')->nullable()->after('store_slug');
            $table->text('store_description')->nullable()->after('store_logo');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignUuid('origin_address_id')->nullable()->after('user_id')->constrained('customer_addresses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropForeign(['origin_address_id']);
            $table->dropColumn('origin_address_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_seller', 'store_name', 'store_slug', 'store_logo', 'store_description']);
        });
    }
};
