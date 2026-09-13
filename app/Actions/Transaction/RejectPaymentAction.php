<?php

namespace App\Actions\Transaction;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectPaymentAction
{
    /**
     * Reject transaction payment proof.
     *
     * @return array{success: bool, message?: string}
     */
    public function execute(Transaction $transaction, string $notes, User $adminUser): array
    {
        if (in_array($transaction->status, ['selesai', 'batal'])) {
            return ['success' => false, 'message' => 'Transaksi yang sudah selesai atau batal tidak dapat menerima penolakan pembayaran.'];
        }

        $payment = $transaction->payment;
        if (! $payment) {
            return ['success' => false, 'message' => 'Data pembayaran tidak ditemukan.'];
        }

        DB::transaction(function () use ($transaction, $payment, $notes, $adminUser) {
            $payment->update([
                'status' => 'rejected',
                'confirmed_at' => now(),
                'confirmed_by' => $adminUser->id,
                'notes' => $notes,
            ]);

            $transaction->update([
                'status' => 'belum_bayar',
                'payment_status' => 'unpaid',
            ]);
        });

        return ['success' => true];
    }
}
