<?php

namespace App\Actions\Transaction;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class BulkUpdateTrackingAction
{
    /**
     * Bulk update tracking numbers for multiple transactions.
     *
     * @param  array<int, array{id: string, tracking_number: string, courier_name?: ?string}>  $trackingData
     */
    public function execute(array $trackingData): int
    {
        return DB::transaction(function () use ($trackingData) {
            $updated = 0;

            foreach ($trackingData as $data) {
                if (empty($data['id']) || empty($data['tracking_number'])) {
                    continue;
                }

                $transaction = Transaction::find($data['id']);
                if ($transaction && ! in_array($transaction->status, ['selesai', 'batal'])) {
                    $updateFields = [
                        'tracking_number' => $data['tracking_number'],
                    ];

                    if (! empty($data['courier_name'])) {
                        $updateFields['courier_name'] = $data['courier_name'];
                    }

                    if (! in_array($transaction->status, ['dikirim', 'selesai'])) {
                        $updateFields['status'] = 'dikirim';
                    }

                    $transaction->update($updateFields);
                    $updated++;
                }
            }

            return $updated;
        });
    }
}
