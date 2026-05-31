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
            $table->string('video_path')->nullable()->after('image');
            $table->string('model_3d_path')->nullable()->after('video_path');
            $table->string('model_3d_usdz_path')->nullable()->after('model_3d_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['video_path', 'model_3d_path', 'model_3d_usdz_path']);
        });
    }
};
