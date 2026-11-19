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
        if (! Schema::hasTable('store_reviews')) {
            Schema::create('store_reviews', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('transaction_id')->constrained('transactions')->cascadeOnDelete();
                $table->foreignUuid('resto_profile_id')->nullable()->constrained('resto_profiles')->nullOnDelete();
                $table->foreignUuid('seller_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('customer_name')->default('Pelanggan');
                $table->string('customer_phone')->nullable();
                $table->unsignedTinyInteger('rating'); // 1 - 5
                $table->text('comment')->nullable();
                $table->text('reply')->nullable();
                $table->boolean('is_public')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['resto_profile_id', 'is_public']);
                $table->index(['seller_id', 'is_public']);
                $table->index('rating');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_reviews');
    }
};
