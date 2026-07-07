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
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('courier_id')->nullable()->constrained('couriers')->nullOnDelete();
        });

        Schema::create('transaction_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->string('status');
            $table->string('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->json('media')->nullable(); // array of URLs/paths for photos/videos
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('transaction_status_histories');

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['courier_id']);
            $table->dropColumn('courier_id');
        });
    }
};
