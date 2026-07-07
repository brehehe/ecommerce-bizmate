<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('return_id')->constrained('returns')->cascadeOnDelete();
            $table->string('file_path', 500);
            $table->string('file_type', 10)->default('image'); // image or video
            $table->string('disk', 30)->default('public');
            $table->timestamps();

            $table->index('return_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_media');
    }
};
