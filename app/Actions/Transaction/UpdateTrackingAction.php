<?php

namespace App\Actions\Transaction;

use App\Models\Transaction;
use App\Models\User;

class UpdateTrackingAction
{
    /**
     * Update tracking number, booking code, courier name and status.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, message?: string}
     */
    public function execute(Transaction $transaction, array $data): array
    {
        if (in_array($transaction->status, ['selesai', 'batal'])) {
            return ['success' => false, 'message' => 'Tidak dapat memperbarui resi untuk transaksi yang sudah selesai atau batal.'];
        }

        $updateData = [];
        if (array_key_exists('tracking_number', $data)) {
            $updateData['tracking_number'] = $data['tracking_number'];
        }
        if (array_key_exists('courier_name', $data)) {
            $updateData['courier_name'] = $data['courier_name'];
        }
        if (array_key_exists('booking_code', $data)) {
            $updateData['booking_code'] = $data['booking_code'];
        }
        if (array_key_exists('courier_user_id', $data)) {
            $oldCourierId = $transaction->courier_user_id;
            $updateData['courier_user_id'] = $data['courier_user_id'];
            if (! empty($data['courier_user_id']) && $data['courier_user_id'] !== $oldCourierId) {
                $courier = User::find($data['courier_user_id']);
                if ($courier) {
                    $transaction->statusHistories()->create([
                        'status' => $transaction->status,
                        'description' => "Kurir Toko ditugaskan: {$courier->name}.",
                        'created_by' => auth()->id(),
                    ]);
                }
            }
        }

        if (! empty($data['tracking_number']) && $transaction->shipping_courier !== 'store_courier' && ! in_array($transaction->status, ['dikirim', 'selesai'])) {
            $updateData['status'] = 'dikirim';
        } elseif (! empty($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        $transaction->update($updateData);

        return ['success' => true];
    }
}
