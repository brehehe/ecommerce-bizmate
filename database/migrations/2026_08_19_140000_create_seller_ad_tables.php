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
        // 1. Dompet Saldo Iklan Seller
        Schema::create('seller_ad_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->onDelete('cascade');
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->decimal('total_topup', 15, 2)->default(0);
            $table->timestamps();
        });

        // 2. Riwayat Mutasi Transaksi Saldo Iklan (Top Up & Pemotongan Biaya Iklan)
        Schema::create('seller_ad_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_id')->nullable()->unique();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type')->default('topup'); // topup, click_cost, daily_cost, refund
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->string('description');
            $table->string('payment_method')->nullable()->default('qris');
            $table->string('status')->default('paid'); // pending, paid, failed, cancelled
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'status']);
        });

        // 3. Kampanye Promosi / Iklan Produk Seller
        Schema::create('product_ads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('product_id')->constrained('products')->onDelete('cascade');
            $table->string('ad_type')->default('cpc'); // cpc, daily
            $table->decimal('bid_per_click', 12, 2)->default(300);
            $table->decimal('daily_budget', 12, 2)->default(10000);
            $table->decimal('spent_today', 12, 2)->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->unsignedBigInteger('impressions_count')->default(0);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->string('status')->default('active'); // active, paused, depleted, ended
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('last_spent_reset_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        // 4. Log Audit Klik Pengunjung Iklan (Anti-Spam Click & Billing Trace)
        Schema::create('product_ad_clicks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_ad_id')->constrained('product_ads')->onDelete('cascade');
            $table->foreignUuid('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->decimal('cost', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['product_ad_id', 'created_at']);
            $table->index(['ip_address', 'product_ad_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_ad_clicks');
        Schema::dropIfExists('product_ads');
        Schema::dropIfExists('seller_ad_transactions');
        Schema::dropIfExists('seller_ad_wallets');
    }
};
