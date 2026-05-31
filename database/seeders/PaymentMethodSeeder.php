<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Midtrans Gateway
        PaymentMethod::updateOrCreate(
            ['name' => 'Midtrans'],
            [
                'type' => 'gateway',
                'api_key' => 'SB-Mid-server-YvWfBSvBdRvLwqzUc_TmKCHH', // Server Key
                'api_secret' => 'SB-Mid-client-15KqQ2A7XYrBc5cL', // Client Key
                'admin_fee' => 0,
                'is_active' => true,
                'settings' => [
                    'url' => 'https://app.sandbox.midtrans.com',
                ],
            ]
        );

        // 2. Xendit Gateway
        // PaymentMethod::updateOrCreate(
        //     ['name' => 'Xendit'],
        //     [
        //         'type' => 'gateway',
        //         'api_key' => config('app.xendit.private_key') ?: 'xnd_development_your_private_key',
        //         'api_secret' => config('app.xendit.public_key') ?: 'xnd_development_your_public_key',
        //         'admin_fee' => 0,
        //         'is_active' => true,
        //         'settings' => [
        //             'url' => 'https://api.xendit.co',
        //             'webhook_token' => config('app.xendit.webhook_token') ?: 'xendit_webhook_token',
        //         ],
        //     ]
        // );

        // 3. Manual BCA Transfer (example)
        PaymentMethod::updateOrCreate(
            ['name' => 'Transfer Bank BCA (Manual)'],
            [
                'type' => 'manual',
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_name' => 'PT Toko Kita Utama',
                'admin_fee' => 0,
                'is_active' => true,
            ]
        );

        // 4. Manual Mandiri Transfer (example)
        PaymentMethod::updateOrCreate(
            ['name' => 'Transfer Bank Mandiri (Manual)'],
            [
                'type' => 'manual',
                'bank_name' => 'Mandiri',
                'account_number' => '0987654321',
                'account_name' => 'PT Toko Kita Utama',
                'admin_fee' => 0,
                'is_active' => true,
            ]
        );

        // 5. Flip Gateway
        // PaymentMethod::updateOrCreate(
        //     ['name' => 'Flip'],
        //     [
        //         'type' => 'gateway',
        //         'api_key' => 'JDJ5JDEzJG5rSXlyTnlINlgzOVk2emxzOUVtNk9PTS9iUnpIcVRTdGlOS0RTSUxzTC83RXNGcGliclhh', // Secret Key
        //         'api_secret' => '$2y$13$bJAwMLvSexLawNRLvHAeP.8BT7mJorBYqBASfx1FjMN.NsYlZ7LXu', // Validation Token
        //         'admin_fee' => 0,
        //         'is_active' => true,
        //         'settings' => [
        //             'url' => 'https://bigflip.id/big_sandbox_api',
        //             'webhook_token' => '$2y$13$bJAwMLvSexLawNRLvHAeP.8BT7mJorBYqBASfx1FjMN.NsYlZ7LXu',
        //         ],
        //     ]
        // );
    }
}
