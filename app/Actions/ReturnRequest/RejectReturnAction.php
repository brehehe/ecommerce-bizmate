<?php

namespace App\Actions\ReturnRequest;

use App\Models\Notification;
use App\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;

class RejectReturnAction
{
    /**
     * Reject return request.
     *
     * @return array{success: bool, message?: string}
     */
    public function execute(ReturnRequest $return, string $notesAdmin): array
    {
        if ($return->status !== 'menunggu_review') {
            return ['success' => false, 'message' => 'Retur tidak dapat ditolak pada status saat ini.'];
        }

        DB::transaction(function () use ($return, $notesAdmin) {
            $return->update([
                'status' => 'ditolak',
                'notes_admin' => $notesAdmin,
                'rejected_at' => now(),
            ]);

            $return->transaction->update(['return_status' => 'ditolak']);

            try {
                Notification::create([
                    'user_id' => $return->user_id,
                    'title' => 'Pengajuan Retur Ditolak',
                    'message' => 'Pengajuan retur Anda (#'.$return->return_number.') ditolak. Alasan: '.$notesAdmin,
                    'type' => 'return_rejected',
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
