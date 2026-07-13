<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Exclusive product — only visible to members at or above a given level order
            $table->boolean('is_exclusive')->default(false)->after('is_active');
            $table->integer('exclusive_min_level_order')->nullable()->after('is_exclusive')
                ->comment('Minimum membership level order required to view/buy this product');

            // Early access — visible to higher-tier members before general release
            $table->boolean('is_early_access')->default(false)->after('exclusive_min_level_order');
            $table->timestamp('early_access_until')->nullable()->after('is_early_access')
                ->comment('Product is in early-access until this datetime; after that it is visible to all');
            $table->integer('early_access_min_level_order')->nullable()->after('early_access_until');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'is_exclusive',
                'exclusive_min_level_order',
                'is_early_access',
                'early_access_until',
                'early_access_min_level_order',
            ]);
        });
    }
};
