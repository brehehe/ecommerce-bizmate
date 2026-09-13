<?php

namespace App\Actions\Transaction;

use App\Models\Transaction;
use Illuminate\Support\Str;

class SyncShipmentTrackingStatusAction
{
    /**
     * Apply the status represented by the newest courier tracking event.
     */
    public function execute(Transaction $transaction, array $history): ?string
    {
        $latestEvent = collect($history)
            ->filter(fn (mixed $event): bool => is_array($event))
            ->sortByDesc(fn (array $event): string => (string) ($event['date'] ?? ''))
            ->first();

        if (! is_array($latestEvent)) {
            return null;
        }

        return $this->updateFromDescription(
            $transaction,
            (string) ($latestEvent['desc'] ?? $latestEvent['status'] ?? ''),
        );
    }

    /**
     * Apply a status represented by a Biteship webhook or tracking description.
     */
    public function updateFromDescription(Transaction $transaction, string $description): ?string
    {
        $description = Str::lower($description);

        if ($this->isDelivered($description)) {
            if (! in_array($transaction->status, ['selesai', 'batal'])) {
                $transaction->update(['status' => 'selesai']);

                return 'selesai';
            }

            return null;
        }

        if ($this->isInDelivery($description) && in_array($transaction->status, ['diproses', 'dikemas', 'out_for_pickup'])) {
            $transaction->update(['status' => 'dikirim']);

            return 'dikirim';
        }

        return null;
    }

    private function isDelivered(string $description): bool
    {
        return Str::contains($description, ['diterima', 'delivered', 'sampai', 'selesai']);
    }

    private function isInDelivery(string $description): bool
    {
        return Str::contains($description, [
            'jalan',
            'transit',
            'kurir',
            'kirim',
            'pickup',
            'hub',
            'picked',
            'dropping',
            'intransit',
            'in transit',
            'way',
            'delivering',
            'shipping',
        ]);
    }
}
