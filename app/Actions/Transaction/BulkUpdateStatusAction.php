<?php

namespace App\Actions\Transaction;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BulkUpdateStatusAction
{
    public function __construct(
        protected UpdateTransactionStatusAction $updateStatusAction
    ) {}

    /**
     * Bulk update transaction status.
     *
     * @param  array<int, string>  $ids
     */
    public function execute(array $ids, string $newStatus, ?string $cancelReason, ?User $user): int
    {
        return DB::transaction(function () use ($ids, $newStatus, $cancelReason, $user) {
            $updated = 0;
            $statusOrder = [
                'belum_bayar',
                'menunggu',
                'diproses',
                'dikemas',
                'out_for_pickup',
                'dikirim',
                'selesai',
            ];

            $transactions = Transaction::whereIn('id', $ids)->get();

            foreach ($transactions as $transaction) {
                if ($transaction->status === $newStatus || in_array($transaction->status, ['selesai', 'batal'])) {
                    continue;
                }

                $currentIndex = array_search($transaction->status, $statusOrder);
                $newIndex = array_search($newStatus, $statusOrder);

                if ($newStatus !== 'batal' && $currentIndex !== false && $newIndex !== false && $newIndex < $currentIndex) {
                    continue;
                }

                if ($newStatus === 'batal') {
                    $this->updateStatusAction->restoreStock($transaction, $user);
                    $transaction->update([
                        'status' => $newStatus,
                        'cancel_reason' => $cancelReason ?: 'Pembatalan massal oleh Admin',
                        'cancelled_at' => now(),
                    ]);
                } else {
                    $transaction->update(['status' => $newStatus]);
                }

                $updated++;
            }

            return $updated;
        });
    }
}
