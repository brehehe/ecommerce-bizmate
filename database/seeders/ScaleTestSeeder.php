<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ScaleTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Running scale dataset seeder (100,000 transactions)...');

        $this->command->call('db:seed-scale', [
            '--count' => 100000,
        ]);
    }
}
