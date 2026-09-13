<?php

namespace App\Actions\ReturnRequest;

use App\Models\Notification;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveReturnAction
{
    /**
     * Approve return request.
     *
     * @return array{success: bool, message?: string}
     */
    public function execute(ReturnRequest $return, ?string $notesAdmin, User $adminUser): array
    {
        if ($return->status !== 'menunggu_review') {
            return ['success' => false, 'message' => 'Retur tidak dapat disetujui pada status saat ini.'];
        }

        DB::transaction(function () use ($return, $notesAdmin, $adminUser) {
            $return->update([
                'status' => 'disetujui',
                'approved_by' => $adminUser->id,
                'approved_at' => now(),
                'notes_admin' => $notesAdmin,
            ]);

            $return->transaction->update(['return_status' => 'disetujui']);

            try {
                $typeLabel = $return->type === 'refund' ? 'pengembalian dana' : 'penggantian barang';
                Notification::create([
                    'user_id' => $return->user_id,
                    'title' => 'Pengajuan Retur Disetujui',
                    'message' => 'Pengajuan retur Anda (#'.$return->return_number.') untuk '.$typeLabel.' telah disetujui. Silakan kirim barang retur ke alamat toko dan masukkan nomor resi pengiriman.',
                    'type' => 'return_approved',
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
