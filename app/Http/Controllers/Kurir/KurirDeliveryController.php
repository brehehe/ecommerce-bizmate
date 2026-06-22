<?php

namespace App\Http\Controllers\Kurir;

use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Mail\DeliveryArrived;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class KurirDeliveryController extends Controller
{
    /**
     * Advance the delivery status for a store_courier transaction.
     *
     * Allowed transitions:
     *  dikemas / menunggu / diproses → out_for_pickup  (Ambil Paket)
     *  out_for_pickup                → dikirim          (Mulai Antar — auto-generates resi)
     *  dikirim                       → arrived          (Paket Tiba — sends email, sets delivery_arrived_at)
     */
    public function updateStatus(Request $request, Transaction $transaction): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:pickup,delivering,arrived',
            'delivery_photos' => 'nullable|array',
            'delivery_photos.*' => 'image|max:2048',
        ]);

        // Guard: only store_courier transactions
        if ($transaction->shipping_courier !== 'store_courier') {
            return back()->with('error', 'Transaksi ini bukan kurir toko.');
        }

        // Guard: cannot modify finished transactions
        if (in_array($transaction->status, ['selesai', 'batal'])) {
            return back()->with('error', 'Transaksi sudah selesai atau dibatalkan.');
        }

        $action = $request->action;

        // Guard: check booking_code and tracking_number for pickup action
        if ($action === 'pickup' && (empty($transaction->booking_code) || empty($transaction->tracking_number))) {
            return back()->with('error', 'Pesanan belum siap untuk dipickup (Kode booking dan nomor resi belum dibuat).');
        }

        match ($action) {
            'pickup' => $this->doPickup($transaction),
            'delivering' => $this->doDelivering($transaction),
            'arrived' => $this->doArrived($transaction, $request),
        };

        return back()->with('success', $this->successMessage($action));
    }

    /**
     * Mark as "Sudah dipick" → status out_for_pickup.
     */
    private function doPickup(Transaction $transaction): void
    {
        $transaction->update([
            'status' => 'out_for_pickup',
            'courier_user_id' => auth()->id(),
        ]);
    }

    /**
     * Mark as "Dalam Pengantaran" → status dikirim + auto-generate resi.
     */
    private function doDelivering(Transaction $transaction): void
    {
        $updateData = ['status' => 'dikirim'];

        // Auto-generate resi number if not yet set
        if (empty($transaction->tracking_number)) {
            $updateData['tracking_number'] = $this->generateResiNumber($transaction);
        }

        $transaction->update($updateData);
    }

    /**
     * Mark as "Pesanan Tiba" → set delivery_arrived_at, add history, send email & notification.
     */
    private function doArrived(Transaction $transaction, Request $request): void
    {
        $updateData = ['delivery_arrived_at' => now()];

        if ($request->hasFile('delivery_photos')) {
            $photos = [];
            foreach ($request->file('delivery_photos') as $file) {
                $photos[] = ImageHelper::compressAndStore($file, 'delivery_photos', 'public');
            }
            $updateData['delivery_photos'] = $photos;
        }

        $transaction->update($updateData);

        // Manual status history entry (not relying on model observer for this custom one)
        $transaction->statusHistories()->create([
            'status' => $transaction->status,
            'description' => 'Pesanan Telah Tiba di Tujuan. Segera lakukan konfirmasi Pesanan Selesai.',
            'created_by' => auth()->id(),
        ]);

        // Notify customer via in-app notification
        try {
            Notification::create([
                'user_id' => $transaction->user_id,
                'title' => 'Paket Anda Telah Tiba!',
                'message' => "Paket untuk pesanan #{$transaction->transaction_number} telah tiba. Segera konfirmasi Pesanan Selesai.",
                'type' => 'transaction_status',
                'url' => '/transactions/'.$transaction->id,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('Arrival notification failed: '.$e->getMessage());
        }

        // Send email to customer
        try {
            $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
            $storeLogo = Setting::where('key', 'store_logo')->value('value');

            $transaction->loadMissing(['user', 'items.product', 'customerAddress']);

            if ($transaction->user && $transaction->user->email) {
                Mail::to($transaction->user->email)
                    ->queue(new DeliveryArrived($transaction, $storeName, $storeLogo));
            }
        } catch (\Throwable $e) {
            Log::error('Delivery arrived email failed for transaction '.$transaction->transaction_number.': '.$e->getMessage());
        }
    }

    /**
     * Auto-generate a resi number for store courier deliveries.
     */
    private function generateResiNumber(Transaction $transaction): string
    {
        return 'RSI-'.str_replace('TRX-', '', $transaction->transaction_number).'-'.now()->format('Ymd');
    }

    /**
     * Return a success message for the given action.
     */
    private function successMessage(string $action): string
    {
        return match ($action) {
            'pickup' => 'Paket berhasil dipick up. Status diubah ke Out for Pickup.',
            'delivering' => 'Pengantaran dimulai. Nomor resi otomatis dibuat. Status diubah ke Dikirim.',
            'arrived' => 'Paket telah ditandai tiba. Email notifikasi dikirim ke pelanggan.',
            default => 'Status berhasil diperbarui.',
        };
    }
}
