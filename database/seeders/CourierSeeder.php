<?php

namespace Database\Seeders;

use App\Models\Courier;
use Illuminate\Database\Seeder;

class CourierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $couriers = [
            'gojek' => 'GoSend / Gojek',
            'grab' => 'GrabExpress / Grab',
            'deliveree' => 'Deliveree',
            'jne' => 'JNE',
            'tiki' => 'TIKI',
            'ninja' => 'Ninja Xpress',
            'lion' => 'Lion Parcel',
            'rara' => 'Rara Delivery',
            'sicepat' => 'SiCepat Express',
            'jnt' => 'J&T Express',
            'idexpress' => 'ID Express',
            'rpx' => 'RPX Holding',
            'jdl' => 'JDL Express',
            'wahana' => 'Wahana Prestasi Logistik',
            'pos' => 'POS Indonesia',
            'anteraja' => 'Anteraja',
            'sap' => 'SAP Express',
            'paxel' => 'Paxel',
            'borzo' => 'Borzo',
            'lalamove' => 'Lalamove',
            'sentralcargo' => 'Sentral Cargo',
            'dash_express' => 'Dash Express',
        ];

        // Delete any other couriers from database except the new list
        Courier::whereNotIn('code', array_keys($couriers))->forceDelete();

        $order = 1;
        foreach ($couriers as $code => $name) {
            Courier::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'is_active' => true,
                    'order' => $order++,
                ]
            );
        }
    }
}
