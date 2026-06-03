<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\KomerceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('app:sync-shipment-status')]
#[Description('Sync shipment status from Komerce and update transaction status accordingly')]
class SyncShipmentStatus extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting shipment status sync...');

        // Fetch active transactions that have tracking numbers and are not completed/cancelled
        $transactions = Transaction::whereIn('status', ['diproses', 'dikemas', 'out_for_pickup', 'dikirim'])
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('No transactions found to sync.');

            return;
        }

        foreach ($transactions as $transaction) {
            $this->info("Syncing transaction: {$transaction->transaction_number} (Resi: {$transaction->tracking_number})");

            $response = KomerceService::getShipmentHistory($transaction->tracking_number, $transaction->shipping_courier);

            if (isset($response['success']) && $response['success'] && ! empty($response['history'])) {
                $history = $response['history'];
                $latestStatus = end($history);
                $desc = strtolower($latestStatus['desc'] ?? '');

                $this->line("Latest status: {$latestStatus['desc']}");

                // Determine transaction status updates
                if (str_contains($desc, 'diterima') || str_contains($desc, 'delivered') || str_contains($desc, 'sampai') || str_contains($desc, 'selesai')) {
                    if ($transaction->status !== 'selesai') {
                        $transaction->update(['status' => 'selesai']);
                        $this->info("Transaction {$transaction->transaction_number} status updated to [selesai].");
                        Log::info("Auto-sync: Transaction {$transaction->transaction_number} completed based on courier delivery.");
                    }
                } elseif (str_contains($desc, 'jalan') || str_contains($desc, 'transit') || str_contains($desc, 'kurir') || str_contains($desc, 'kirim') || str_contains($desc, 'pickup') || str_contains($desc, 'hub')) {
                    if (in_array($transaction->status, ['diproses', 'dikemas', 'out_for_pickup'])) {
                        $transaction->update(['status' => 'dikirim']);
                        $this->info("Transaction {$transaction->transaction_number} status updated to [dikirim].");
                        Log::info("Auto-sync: Transaction {$transaction->transaction_number} shipped based on courier pickup.");
                    }
                }
            } else {
                $this->warn("Failed to get tracking history for {$transaction->transaction_number} or history is empty.");
            }
        }

        $this->info('Shipment status sync completed.');
    }
}
