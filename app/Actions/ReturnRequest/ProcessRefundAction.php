<?php

namespace App\Actions\ReturnRequest;

use App\Models\Notification;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;

class ProcessRefundAction
{
    /**
     * Process refund for return.
     *
     * @return array{success: bool, message?: string}
     */
    public function execute(ReturnRequest $return, ?string $notesAdmin): array
    {
        if ($return->status !== 'barang_diterima_toko') {
            return ['success' => false, 'message' => 'Refund hanya dapat diproses setelah barang diterima.'];
        }

        if ($return->type !== 'refund') {
            return ['success' => false, 'message' => 'Aksi ini hanya untuk retur jenis pengembalian dana.'];
        }

        DB::transaction(function () use ($return, $notesAdmin) {
            $return->update([
                'status' => 'refund_diproses',
                'refunded_at' => now(),
                'notes_admin' => $notesAdmin ?? $return->notes_admin,
            ]);

            $return->transaction->update(['return_status' => 'refund_diproses']);

            try {
                $bankAccount = $return->user->customerBankAccounts()->where('is_primary', true)->first()
                    ?? $return->user->customerBankAccounts()->first();
                $bankInfo = $bankAccount
                    ? "ke rekening {$bankAccount->bank_name} {$bankAccount->account_number} a.n. {$bankAccount->account_name}"
                    : 'ke rekening yang telah Anda daftarkan';

                Notification::create([
                    'user_id' => $return->user_id,
                    'title' => 'Pengembalian Dana Diproses',
                    'message' => 'Pengembalian dana sebesar Rp '.number_format((float) $return->refund_amount, 0, ',', '.').' untuk retur #'.$return->return_number." telah diproses {$bankInfo}.",
                    'type' => 'return_refund_processed',
                    'url' => '/transactions/'.$return->transaction_id,
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                // Fail silently
            }
        });

        return ['success' => true];
    }
}
