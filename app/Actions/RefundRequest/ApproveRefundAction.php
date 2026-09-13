<?php

namespace App\Actions\RefundRequest;

use App\Actions\Transaction\UpdateTransactionStatusAction;
use App\Models\CoinHistory;
use App\Models\Notification;
use App\Models\RefundRequest;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveRefundAction
{
    public function __construct(
        protected UpdateTransactionStatusAction $updateTransactionStatusAction
    ) {}

    /**
     * Approve refund / cancellation request.
     *
     * @return array{success: bool, message?: string}
     */
    public function execute(RefundRequest $refund, ?string $notesAdmin, User $adminUser): array
    {
        if ($refund->status !== 'menunggu_konfirmasi') {
            return ['success' => false, 'message' => 'Pengajuan ini tidak dapat disetujui pada status saat ini.'];
        }

        $transaction = $refund->transaction;
        if ($transaction->status === 'batal') {
            return ['success' => false, 'message' => 'Transaksi untuk pengajuan ini sudah dibatalkan sebelumnya.'];
        }

        DB::transaction(function () use ($refund, $transaction, $notesAdmin, $adminUser) {
            $this->updateTransactionStatusAction->restoreStock($transaction, $adminUser);

            $transaction->update([
                'status' => 'batal',
                'cancel_reason' => 'Pengajuan Pembatalan Disetujui: '.$refund->reason,
                'cancelled_at' => now(),
            ]);

            $refundStatus = 'disetujui';
            $refundedAt = null;

            if ($refund->refund_method === 'poin') {
                $coinConversionRate = (float) (Setting::where('key', 'coin_conversion_rate')->value('value') ?? 1);
                $coinsToCredit = (int) ($refund->refund_amount / $coinConversionRate);

                $user = $refund->user;
                if ($user) {
                    $user->increment('coins_balance', $coinsToCredit);
                    CoinHistory::create([
                        'user_id' => $user->id,
                        'transaction_id' => $transaction->id,
                        'amount' => $coinsToCredit,
                        'type' => 'refund',
                        'description' => 'Refund pembatalan transaksi #'.$transaction->transaction_number.' ke Koin Toko',
                    ]);
                }

                $refundStatus = 'selesai';
                $refundedAt = now();

                try {
                    Notification::create([
                        'user_id' => $refund->user_id,
                        'title' => 'Refund Koin Berhasil dikreditkan',
                        'message' => 'Refund berupa koin untuk transaksi #'.$transaction->transaction_number.' sebesar '.number_format($coinsToCredit, 0, ',', '.').' koin telah berhasil dikreditkan ke saldo koin Anda.',
                        'type' => 'refund_completed',
                        'url' => '/refunds/'.$refund->id,
                        'is_read' => false,
                    ]);
                } catch (\Throwable $e) {
                    // Fail silently
                }
            } else {
                try {
                    Notification::create([
                        'user_id' => $refund->user_id,
                        'title' => 'Pengajuan Pembatalan Disetujui',
                        'message' => 'Pengajuan pembatalan untuk transaksi #'.$transaction->transaction_number.' telah disetujui. Refund transfer bank sebesar Rp '.number_format((float) $refund->refund_amount, 0, ',', '.').' sedang diproses.',
                        'type' => 'refund_approved',
                        'url' => '/refunds/'.$refund->id,
                        'is_read' => false,
                    ]);
                } catch (\Throwable $e) {
                    // Fail silently
                }
            }

            $refund->update([
                'status' => $refundStatus,
                'notes_admin' => $notesAdmin,
                'processed_by' => $adminUser->id,
                'processed_at' => now(),
                'refunded_at' => $refundedAt,
            ]);
        });

        return ['success' => true];
    }
}
