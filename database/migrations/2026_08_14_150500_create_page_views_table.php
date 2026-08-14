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
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->nullable()->index();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('url');
            $table->string('path', 255)->index();
            $table->string('route_name', 100)->nullable()->index();
            $table->string('device', 20)->default('desktop');
            $table->text('referer')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'seller_id']);
            $table->index(['created_at', 'path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
