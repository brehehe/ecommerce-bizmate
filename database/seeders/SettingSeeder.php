<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultSettings = [
            'store_name' => 'Bizmate Premium Store',
            'store_email' => 'admin@bizmate.com',
            'store_phone' => '081234567890',
            'store_whatsapp' => '6281234567890',
            'store_instagram' => '@bizmate.store',
            'store_tiktok' => '@bizmate.store',
            'store_description' => 'Platform e-commerce premium terbaik untuk kebutuhan belanja online Anda.',
            'primary_color' => '#0c4cb4',
            'secondary_color' => '#1500ffc4',
            'tax_enabled' => '0',
            'tax_percentage' => '10',
            'province_id' => '35',
            'province_name' => 'JAWA TIMUR',
            'regency_id' => '3578',
            'regency_name' => 'KOTA SURABAYA',
            'district_id' => '3578110',
            'district_name' => 'WONOKROMO',
            'village_id' => '3578110004',
            'village_name' => 'NGAGELREJO',
            'postal_code' => '60245',
            'address' => 'Jl. Raya Darmo No. 123, Surabaya',
            'latitude' => '-7.2891',
            'longitude' => '112.7424',
            'bank_name' => 'Bank Central Asia (BCA)',
            'bank_account' => '1234567890',
            'bank_holder' => 'PT Bizmate Sukses Makmur',
            'shipping_rate' => '10000',
            'enable_cod' => '1',
            'enable_qris' => '1',
            'enable_bank' => '1',
            'store_logo' => '/logos/default-logo.png',
        ];

        $order = 1;
        foreach ($defaultSettings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'order' => $order++,
                ]
            );
        }
    }
}
