<?php

namespace Database\Seeders;

use App\Models\MembershipLevel;
use Illuminate\Database\Seeder;

class MembershipLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            [
                'name' => 'Member',
                'slug' => 'member',
                'icon' => 'ti-user',
                'badge_color' => '#64748b',
                'description' => 'Level dasar untuk semua pelanggan yang baru bergabung.',
                'order' => 0,
                'is_active' => true,
                'min_total_purchase' => 0,
                'min_total_transactions' => 0,
                'min_total_products' => 0,
                'period_type' => 'lifetime',
                'auto_upgrade' => true,
                'auto_downgrade' => false,
                'validity_months' => null,
                'benefits' => [
                    ['type' => 'point_multiplier',    'label' => 'Poin 1x untuk setiap transaksi',  'value' => 1,  'icon' => 'ti-star',          'order' => 1],
                    ['type' => 'early_access',        'label' => 'Akses promo member',               'value' => 0,  'icon' => 'ti-clock',         'order' => 2],
                ],
            ],
            [
                'name' => 'Silver',
                'slug' => 'silver',
                'icon' => 'ti-medal',
                'badge_color' => '#94a3b8',
                'description' => 'Level Silver untuk pelanggan setia dengan total belanja Rp1 juta.',
                'order' => 1,
                'is_active' => true,
                'min_total_purchase' => 1000000,
                'min_total_transactions' => 3,
                'min_total_products' => 0,
                'period_type' => 'lifetime',
                'auto_upgrade' => true,
                'auto_downgrade' => false,
                'validity_months' => null,
                'benefits' => [
                    ['type' => 'discount_percentage', 'label' => 'Diskon 3% untuk semua produk',      'value' => 3,  'icon' => 'ti-discount',     'order' => 1],
                    ['type' => 'point_multiplier',    'label' => 'Poin 1.5x untuk setiap transaksi', 'value' => 1.5, 'icon' => 'ti-star',          'order' => 2],
                    ['type' => 'cashback_percentage', 'label' => 'Cashback 1% per transaksi',         'value' => 1,  'icon' => 'ti-cash-banknote', 'order' => 3],
                ],
            ],
            [
                'name' => 'Gold',
                'slug' => 'gold',
                'icon' => 'ti-award',
                'badge_color' => '#f59e0b',
                'description' => 'Level Gold untuk pelanggan premium dengan total belanja Rp5 juta.',
                'order' => 2,
                'is_active' => true,
                'min_total_purchase' => 5000000,
                'min_total_transactions' => 10,
                'min_total_products' => 0,
                'period_type' => 'lifetime',
                'auto_upgrade' => true,
                'auto_downgrade' => false,
                'validity_months' => null,
                'benefits' => [
                    ['type' => 'discount_percentage', 'label' => 'Diskon 5% untuk semua produk',      'value' => 5,  'icon' => 'ti-discount',      'order' => 1],
                    ['type' => 'point_multiplier',    'label' => 'Poin 2x untuk setiap transaksi',   'value' => 2,  'icon' => 'ti-star',           'order' => 2],
                    ['type' => 'cashback_percentage', 'label' => 'Cashback 2% per transaksi',         'value' => 2,  'icon' => 'ti-cash-banknote',  'order' => 3],
                    ['type' => 'free_shipping',       'label' => 'Gratis ongkir min. Rp100rb',        'value' => 0,  'icon' => 'ti-truck',          'order' => 4],
                    ['type' => 'auto_voucher',        'label' => 'Voucher diskon 10% saat naik level', 'value' => 10, 'icon' => 'ti-ticket',         'order' => 5],
                ],
            ],
            [
                'name' => 'Platinum',
                'slug' => 'platinum',
                'icon' => 'ti-crown',
                'badge_color' => '#8b5cf6',
                'description' => 'Level Platinum eksklusif untuk pelanggan VIP dengan total belanja Rp20 juta.',
                'order' => 3,
                'is_active' => true,
                'min_total_purchase' => 20000000,
                'min_total_transactions' => 30,
                'min_total_products' => 0,
                'period_type' => 'lifetime',
                'auto_upgrade' => true,
                'auto_downgrade' => false,
                'validity_months' => null,
                'benefits' => [
                    ['type' => 'discount_percentage', 'label' => 'Diskon 8% untuk semua produk',       'value' => 8,  'icon' => 'ti-discount',      'order' => 1],
                    ['type' => 'point_multiplier',    'label' => 'Poin 3x untuk setiap transaksi',    'value' => 3,  'icon' => 'ti-star',           'order' => 2],
                    ['type' => 'cashback_percentage', 'label' => 'Cashback 3% per transaksi',          'value' => 3,  'icon' => 'ti-cash-banknote',  'order' => 3],
                    ['type' => 'free_shipping',       'label' => 'Gratis ongkir tanpa minimum',        'value' => 0,  'icon' => 'ti-truck',          'order' => 4],
                    ['type' => 'flash_sale_access',   'label' => 'Akses Flash Sale 1 jam lebih awal', 'value' => 60, 'icon' => 'ti-bolt',           'order' => 5],
                    ['type' => 'birthday_bonus',      'label' => 'Bonus diskon 15% di hari ulang tahun', 'value' => 15, 'icon' => 'ti-cake',           'order' => 6],
                    ['type' => 'auto_voucher',        'label' => 'Voucher diskon 15% saat naik level', 'value' => 15, 'icon' => 'ti-ticket',         'order' => 7],
                    ['type' => 'priority_cs',         'label' => 'Prioritas Customer Service',         'value' => 0,  'icon' => 'ti-headset',        'order' => 8],
                ],
            ],
            [
                'name' => 'Diamond',
                'slug' => 'diamond',
                'icon' => 'ti-diamond',
                'badge_color' => '#06b6d4',
                'description' => 'Level tertinggi untuk pelanggan loyal terbaik dengan total belanja Rp50 juta.',
                'order' => 4,
                'is_active' => true,
                'min_total_purchase' => 50000000,
                'min_total_transactions' => 75,
                'min_total_products' => 0,
                'period_type' => 'lifetime',
                'auto_upgrade' => true,
                'auto_downgrade' => false,
                'validity_months' => null,
                'benefits' => [
                    ['type' => 'discount_percentage', 'label' => 'Diskon 12% untuk semua produk',      'value' => 12, 'icon' => 'ti-discount',      'order' => 1],
                    ['type' => 'point_multiplier',    'label' => 'Poin 5x untuk setiap transaksi',    'value' => 5,  'icon' => 'ti-star',           'order' => 2],
                    ['type' => 'cashback_percentage', 'label' => 'Cashback 5% per transaksi',          'value' => 5,  'icon' => 'ti-cash-banknote',  'order' => 3],
                    ['type' => 'free_shipping',       'label' => 'Gratis ongkir tanpa minimum',        'value' => 0,  'icon' => 'ti-truck',          'order' => 4],
                    ['type' => 'flash_sale_access',   'label' => 'Akses Flash Sale 2 jam lebih awal', 'value' => 120, 'icon' => 'ti-bolt',           'order' => 5],
                    ['type' => 'birthday_bonus',      'label' => 'Bonus diskon 25% di hari ulang tahun', 'value' => 25, 'icon' => 'ti-cake',           'order' => 6],
                    ['type' => 'auto_voucher',        'label' => 'Voucher diskon 20% saat naik level', 'value' => 20, 'icon' => 'ti-ticket',         'order' => 7],
                    ['type' => 'priority_cs',         'label' => 'Prioritas CS & dedicated account',  'value' => 0,  'icon' => 'ti-headset',        'order' => 8],
                    ['type' => 'exclusive_product',   'label' => 'Akses produk eksklusif Diamond',    'value' => 0,  'icon' => 'ti-lock-open',      'order' => 9],
                    ['type' => 'early_access',        'label' => 'Early access produk baru',           'value' => 0,  'icon' => 'ti-eye',            'order' => 10],
                ],
            ],
        ];

        foreach ($levels as $levelData) {
            $benefits = $levelData['benefits'];
            unset($levelData['benefits']);

            $level = MembershipLevel::updateOrCreate(
                ['slug' => $levelData['slug']],
                $levelData
            );

            // Sync benefits: delete old and recreate
            $level->benefits()->delete();

            foreach ($benefits as $benefit) {
                $level->benefits()->create([
                    'type' => $benefit['type'],
                    'label' => $benefit['label'],
                    'description' => null,
                    'value' => $benefit['value'],
                    'icon' => $benefit['icon'],
                    'is_active' => true,
                    'order' => $benefit['order'],
                ]);
            }
        }

        $this->command->info('✅ 5 membership levels seeded: Member, Silver, Gold, Platinum, Diamond');
    }
}
