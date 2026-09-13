<?php

namespace App\Console\Commands;

use App\Actions\Transaction\SyncShipmentTrackingStatusAction;
use App\Models\Transaction;
use App\Services\BiteshipService;
use App\Services\KomerceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('app:sync-shipment-status')]
#[Description('Sync shipment status from Komerce and update transaction status accordingly')]
class SyncShipmentStatus extends Command
{
    public function __construct(private readonly SyncShipmentTrackingStatusAction $syncShipmentTrackingStatus)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting shipment status sync...');

        // Fetch active transactions that have tracking numbers and are not completed/cancelled
        $transactions = Transaction::whereIn('status', ['diproses', 'dikemas', 'out_for_pickup', 'dikirim'])
            ->whereNotNull('tracking_number')
            ->where('tracking_number', '!=', '')
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('No transactions found to sync.');

            return self::SUCCESS;
        }

        foreach ($transactions as $transaction) {
            $this->info("Syncing transaction: {$transaction->transaction_number} (Resi: {$transaction->tracking_number})");

            $isBiteship = BiteshipService::isEnabled() &&
                $transaction->booking_code &&
                ! str_starts_with(strtoupper($transaction->tracking_number ?? ''), 'KOMERKOM');

            if ($isBiteship) {
                $response = BiteshipService::getShipmentHistory($transaction->tracking_number, $transaction->shipping_courier);
            } else {
                $response = KomerceService::getShipmentHistory($transaction->tracking_number, $transaction->shipping_courier);
            }

            if (isset($response['success']) && $response['success'] && ! empty($response['history'])) {
                if ($isBiteship && ($response['simulated'] ?? false)) {
                    $this->warn("Skipping simulated Biteship tracking for {$transaction->transaction_number}.");

                    continue;
                }

                $newStatus = $this->syncShipmentTrackingStatus->execute($transaction, $response['history']);

                if ($newStatus) {
                    $this->info("Transaction {$transaction->transaction_number} status updated to [{$newStatus}].");
                    Log::info("Auto-sync: Transaction {$transaction->transaction_number} status updated from courier tracking.", [
                        'status' => $newStatus,
                    ]);
                }
            } else {
                $this->warn("Failed to get tracking history for {$transaction->transaction_number} or history is empty.");
            }
        }

        $this->info('Shipment status sync completed.');

        return self::SUCCESS;
    }
}
