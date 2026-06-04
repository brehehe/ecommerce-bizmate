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
            'lion' => 'Lion Parcel',
            'gojek' => 'GoSend (Instant)',
        ];

        // Delete any other couriers from database
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
