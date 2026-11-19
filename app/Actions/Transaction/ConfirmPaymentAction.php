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
        if ($transaction->status === 'batal') {
            return ['success' => false, 'message' => 'Transaksi yang sudah batal tidak dapat menerima konfirmasi pembayaran.'];
        }

        $isCod = str_contains(strtolower($transaction->paymentMethod?->name ?? ''), 'cod')
            || str_contains(strtolower($transaction->paymentMethod?->name ?? ''), 'bayar di tempat');

        if ($transaction->status === 'selesai' && ! $isCod) {
            return ['success' => false, 'message' => 'Transaksi yang sudah selesai tidak dapat menerima konfirmasi pembayaran.'];
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

            $newStatus = $transaction->status;
            if ($transaction->status !== 'selesai') {
                $newStatus = 'diproses';
                $isRajaOngkir = ! in_array($transaction->shipping_courier, ['self_pickup', 'digital', 'store_courier']);
                if ($isRajaOngkir && ! KomerceService::isDeliveryEnabled()) {
                    $newStatus = 'dikemas';
                }
            }

            $transaction->update([
                'status' => $newStatus,
                'payment_status' => 'paid',
            ]);
        });

        return ['success' => true];
    }
}
