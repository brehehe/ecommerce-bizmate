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
        Schema::table('product_images', function (Blueprint $table) {
            $table->integer('sort_order')->default(0);
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->integer('sort_order')->default(0);
        });

        Schema::table('product_variation_options', function (Blueprint $table) {
            $table->integer('sort_order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('product_variation_options', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
