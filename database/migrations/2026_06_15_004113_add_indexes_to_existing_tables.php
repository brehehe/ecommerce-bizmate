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
            $table->index('is_active');
            $table->index('last_active_at');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index('slug');
            $table->index('parent_id');
            $table->index('order');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('category_id');
            $table->index('brand_id');
            $table->index('active');
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('is_main');
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->index('product_id');
        });

        Schema::table('product_variation_options', function (Blueprint $table) {
            $table->index('product_variation_id');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('product_id');
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('product_variant_id');
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('product_variant_id');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->index('type');
            $table->index('start_time');
            $table->index('end_time');
            $table->index('is_active');
        });

        Schema::table('promotion_items', function (Blueprint $table) {
            $table->index('promotion_id');
            $table->index('product_id');
            $table->index('product_variant_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('product_id');
            $table->index('product_variant_id');
            $table->index('is_checked');
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('type');
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('is_primary');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->index('sender_id');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->index('product_id');
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('customer_address_id');
            $table->index('payment_method_id');
            $table->index('courier_id');
            $table->index('courier_user_id');
            $table->index('original_transaction_id');
            $table->index('status');
            $table->index('return_status');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('product_variant_id');
            $table->index('transaction_id');
            $table->index('created_by');
            $table->index('type');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->index('transaction_id');
            $table->index('product_id');
            $table->index('product_variant_id');
            $table->index('applied_promotion_id');
        });

        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->index('transaction_id');
            $table->index('payment_method_id');
            $table->index('confirmed_by');
            $table->index('status');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->index('category_id');
        });

        Schema::table('product_brands', function (Blueprint $table) {
            $table->index('brand_id');
        });

        Schema::table('transaction_status_histories', function (Blueprint $table) {
            $table->index('transaction_id');
            $table->index('created_by');
            $table->index('status');
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('product_id');
            $table->index('product_variant_id');
            $table->index('transaction_id');
            $table->index('rating');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('is_read');
            $table->index('type');
        });

        Schema::table('customer_bank_accounts', function (Blueprint $table) {
            $table->index('is_primary');
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->index('replacement_transaction_id');
            $table->index('approved_by');
            $table->index('received_by');
            $table->index('status');
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->index('transaction_item_id');
            $table->index('product_id');
            $table->index('product_variant_id');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();
        });

        Schema::table('coin_histories', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('transaction_id');
            $table->index('type');
        });

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->index('transaction_id');
            $table->index('user_id');
            $table->index('processed_by');
            $table->index('status');
        });

        Schema::table('chat_stickers', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_stickers', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['category']);
        });

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['processed_by']);
            $table->dropIndex(['status']);
        });

        Schema::table('coin_histories', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['type']);
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['product_variant_id']);
            $table->dropIndex(['transaction_item_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['product_variant_id']);
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->dropIndex(['replacement_transaction_id']);
            $table->dropIndex(['approved_by']);
            $table->dropIndex(['received_by']);
            $table->dropIndex(['status']);
        });

        Schema::table('customer_bank_accounts', function (Blueprint $table) {
            $table->dropIndex(['is_primary']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_read']);
            $table->dropIndex(['type']);
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['product_variant_id']);
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['rating']);
        });

        Schema::table('transaction_status_histories', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['status']);
        });

        Schema::table('product_brands', function (Blueprint $table) {
            $table->dropIndex(['brand_id']);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
        });

        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['payment_method_id']);
            $table->dropIndex(['confirmed_by']);
            $table->dropIndex(['status']);
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['product_variant_id']);
            $table->dropIndex(['applied_promotion_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['product_variant_id']);
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['type']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['customer_address_id']);
            $table->dropIndex(['payment_method_id']);
            $table->dropIndex(['courier_id']);
            $table->dropIndex(['courier_user_id']);
            $table->dropIndex(['original_transaction_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['return_status']);
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['sender_id']);
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_primary']);
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['type']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['product_variant_id']);
            $table->dropIndex(['is_checked']);
        });

        Schema::table('promotion_items', function (Blueprint $table) {
            $table->dropIndex(['promotion_id']);
            $table->dropIndex(['product_id']);
            $table->dropIndex(['product_variant_id']);
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['start_time']);
            $table->dropIndex(['end_time']);
            $table->dropIndex(['is_active']);
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['product_variant_id']);
        });

        Schema::table('product_prices', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['product_variant_id']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });

        Schema::table('product_variation_options', function (Blueprint $table) {
            $table->dropIndex(['product_variation_id']);
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['is_main']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['brand_id']);
            $table->dropIndex(['active']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['slug']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['order']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['last_active_at']);
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropIndex(['product_id']);
        });
    }
};
