<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Models\Transaction;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:sync-shipment-status')->everyFiveMinutes();
Schedule::call(function () {
    Transaction::processAutoStatusUpdates();
})->everyMinute();

// Membership: issue birthday bonus vouchers daily at 8am
Schedule::command('membership:birthday-bonus')->dailyAt('08:00');
