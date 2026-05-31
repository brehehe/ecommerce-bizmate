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
            'jne' => 'JNE',
            'sicepat' => 'SiCepat Express',
            'ide' => 'ID Express',
            'sap' => 'SAP Express',
            'jnt' => 'J&T Express',
            'ninja' => 'Ninja Xpress',
            'tiki' => 'TIKI',
            'lion' => 'Lion Parcel',
            'anteraja' => 'AnterAja',
            'pos' => 'POS Indonesia',
            'ncs' => 'NCS',
            'rex' => 'REX Express',
            'rpx' => 'RPX',
            'sentral' => 'Sentral Cargo',
            'star' => 'Star Cargo',
            'wahana' => 'Wahana Express',
            'dse' => '21 Express (DSE)',
            'gojek' => 'GoSend (Instant)',
            'grab' => 'GrabExpress (Instant)',
        ];

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
