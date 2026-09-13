<?php

namespace App\Actions\Transaction;

use App\Models\Transaction;
use App\Models\User;
use App\Services\KomerceService;
use Illuminate\Support\Facades\DB;

class ConfirmPaymentAction
{
    /**
     * Confirm transaction payment proof.
     *
     * @return array{success: bool, message?: string}
     */
    public function execute(Transaction $transaction, ?string $notes, User $adminUser): array
    {
        if (in_array($transaction->status, ['selesai', 'batal'])) {
            return ['success' => false, 'message' => 'Transaksi yang sudah selesai atau batal tidak dapat menerima konfirmasi pembayaran.'];
        }

        $payment = $transaction->payment;
        if (! $payment) {
            return ['success' => false, 'message' => 'Data pembayaran tidak ditemukan.'];
        }

        DB::transaction(function () use ($transaction, $payment, $notes, $adminUser) {
            $payment->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'confirmed_by' => $adminUser->id,
                'notes' => $notes,
            ]);

            $newStatus = 'diproses';
            $isRajaOngkir = ! in_array($transaction->shipping_courier, ['self_pickup', 'digital', 'store_courier']);
            if ($isRajaOngkir && ! KomerceService::isDeliveryEnabled()) {
                $newStatus = 'dikemas';
            }

            $transaction->update([
                'status' => $newStatus,
                'payment_status' => 'paid',
            ]);
        });

        return ['success' => true];
    }
}
