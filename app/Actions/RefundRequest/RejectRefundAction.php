<?php

namespace App\Actions\RefundRequest;

use App\Models\Notification;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectRefundAction
{
    /**
     * Reject refund / cancellation request.
     *
     * @return array{success: bool, message?: string}
     */
    public function execute(RefundRequest $refund, string $notesAdmin, User $adminUser): array
    {
        if ($refund->status !== 'menunggu_konfirmasi') {
            return ['success' => false, 'message' => 'Pengajuan ini tidak dapat ditolak pada status saat ini.'];
        }

        DB::transaction(function () use ($refund, $notesAdmin, $adminUser) {
            $refund->update([
                'status' => 'ditolak',
                'notes_admin' => $notesAdmin,
                'processed_by' => $adminUser->id,
                'processed_at' => now(),
            ]);

            try {
                Notification::create([
                    'user_id' => $refund->user_id,
                    'title' => 'Pengajuan Pembatalan Ditolak',
                    'message' => 'Pengajuan pembatalan untuk transaksi #'.$refund->transaction->transaction_number.' ditolak. Catatan: '.$notesAdmin,
                    'type' => 'refund_rejected',
                    'url' => '/refunds/'.$refund->id,
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                // Fail silently
            }
        });

        return ['success' => true];
    }
}
