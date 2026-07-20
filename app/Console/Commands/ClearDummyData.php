<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('db:clear-dummy')]
#[Description('Clear all dummy products, transactions, refunds, returns, stock movements, and cart items.')]
class ClearDummyData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->confirm('This will PERMANENTLY delete all products, transactions, refunds, returns, stock movements, and cart items. Are you sure you want to proceed?', false)) {
            $this->warn('Operation cancelled.');

            return Command::FAILURE;
        }

        $this->info('Clearing database tables...');

        $tables = [
            'refund_requests',
            'return_media',
            'return_items',
            'returns',
            'transaction_status_histories',
            'transaction_payments',
            'transaction_items',
            'transactions',
            'cart_items',
            'stock_movements',
            'coin_histories',
            'product_reviews',
            'product_images',
            'product_prices',
            'product_stocks',
            'product_tier_prices',
            'product_variant_option_combinations',
            'product_variants',
            'product_variation_options',
            'product_variations',
            'product_brands',
            'product_categories',
            'promotion_items',
            'products',
        ];

        DB::transaction(function () use ($tables) {
            // PostgreSQL supports truncating multiple tables in one command to ignore FK constraint check order.
            $tablesList = implode(', ', array_map(fn ($t) => "\"$t\"", $tables));
            DB::statement("TRUNCATE TABLE $tablesList RESTART IDENTITY CASCADE");

            // Reset membership history as it is dependent on levels/transactions
            DB::table('membership_histories')->truncate();

            // Reset customer memberships to starting level and zeroed accumulated stats
            $firstLevel = DB::table('membership_levels')->orderBy('order')->first();
            if ($firstLevel) {
                DB::table('customer_memberships')->update([
                    'membership_level_id' => $firstLevel->id,
                    'total_purchase' => 0,
                    'total_transactions' => 0,
                    'total_products' => 0,
                    'total_points' => 0,
                    'total_cashback' => 0,
                ]);
            }
        });

        $this->info('Successfully cleared all products, transactions, refunds, returns, stock movements, and cart items.');

        return Command::SUCCESS;
    }
}
