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
        Schema::create('social_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('platform');      // instagram, tiktok, facebook, twitter, youtube, whatsapp, telegram, etc.
            $table->string('label');         // Display name, e.g. "Instagram Toko"
            $table->string('url');           // Full URL or handle
            $table->string('icon')->default('ti-brand-instagram'); // Tabler icon class
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media');
    }
};
