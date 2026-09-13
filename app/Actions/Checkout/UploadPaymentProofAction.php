<?php

namespace App\Actions\Checkout;

use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UploadPaymentProofAction
{
    /**
     * Execute payment proof upload.
     */
    public function execute(Transaction $transaction, UploadedFile $file, User $user): Transaction
    {
        return DB::transaction(function () use ($transaction, $file, $user) {
            // Delete old proof if exists
            $payment = $transaction->payment;
            if ($payment && $payment->proof_image) {
                Storage::disk('public')->delete($payment->proof_image);
            }

            $path = $file->store('payment-proofs', 'public');

            if ($payment) {
                $payment->update([
                    'proof_image' => $path,
                    'proof_uploaded_at' => now(),
                    'status' => 'pending',
                ]);
            } else {
                TransactionPayment::create([
                    'transaction_id' => $transaction->id,
                    'payment_method_id' => $transaction->payment_method_id,
                    'amount' => $transaction->grand_total,
                    'status' => 'pending',
                    'proof_image' => $path,
                    'proof_uploaded_at' => now(),
                ]);
            }

            $transaction->update(['status' => 'menunggu']);

            $transaction->statusHistories()->create([
                'status' => 'menunggu',
                'description' => 'Bukti pembayaran berhasil diunggah. Menunggu konfirmasi admin.',
                'created_by' => $user->id,
            ]);

            return $transaction;
        });
    }
}
