<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\ReturnItem;
use App\Models\ReturnMedia;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReturnController extends Controller
{
    /**
     * Display a listing of the customer's return requests.
     */
    public function index(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        $returns = ReturnRequest::where('user_id', $user->id)
            ->with(['transaction', 'items', 'media', 'replacementTransaction'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Returns', [
            'returns' => $returns,
            'returnStatusLabels' => Transaction::returnStatusLabels(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Submit a new return request for a completed transaction.
     */
    public function store(Request $request, Transaction $transaction): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Only allow the transaction owner
        if ($transaction->user_id !== $user->id) {
            abort(403);
        }

        // Only completed transactions can be returned
        if ($transaction->status !== 'selesai') {
            return back()->with('error', 'Retur hanya dapat diajukan untuk pesanan yang sudah selesai.');
        }

        // Check if there's already an active return
        $existing = ReturnRequest::where('transaction_id', $transaction->id)
            ->whereNotIn('status', ['ditolak'])
            ->exists();

        if ($existing) {
            return back()->with('error', 'Pesanan ini sudah memiliki pengajuan retur yang aktif.');
        }

        $request->validate([
            'type' => 'required|in:refund,penggantian_barang',
            'reason' => 'required|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.transaction_item_id' => 'required|integer|exists:transaction_items,id',
            'items.*.quantity_returned' => 'required|integer|min:1',
            'media' => 'required|array|min:1',
            'media.*' => 'required|file|mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi|max:51200',
        ], [
            'type.required' => 'Pilih jenis retur.',
            'reason.required' => 'Alasan retur wajib diisi.',
            'items.required' => 'Pilih minimal satu produk untuk diretur.',
            'media.required' => 'Upload minimal satu foto/video bukti retur.',
            'media.*.mimes' => 'File harus berupa gambar (JPG, PNG, WEBP) atau video (MP4, MOV, AVI).',
            'media.*.max' => 'Ukuran file maksimal 50MB.',
        ]);

        $transaction->load('items');

        DB::transaction(function () use ($request, $transaction, $user) {
            $totalRefund = 0;
            $returnItems = [];

            foreach ($request->items as $itemData) {
                $txItem = $transaction->items->firstWhere('id', $itemData['transaction_item_id']);

                if (! $txItem) {
                    continue;
                }

                $qtyReturned = min((int) $itemData['quantity_returned'], $txItem->quantity);
                $unitPrice = $txItem->harga_akhir;
                $refundSubtotal = $unitPrice * $qtyReturned;
                $totalRefund += $refundSubtotal;

                $returnItems[] = [
                    'transaction_item_id' => $txItem->id,
                    'product_id' => $txItem->product_id,
                    'product_variant_id' => $txItem->product_variant_id,
                    'product_name' => $txItem->product_name,
                    'variant_name' => $txItem->variant_name,
                    'quantity_returned' => $qtyReturned,
                    'unit_price' => $unitPrice,
                    'refund_subtotal' => $refundSubtotal,
                ];
            }

            // Create the return record
            $returnRequest = ReturnRequest::create([
                'return_number' => ReturnRequest::generateNumber(),
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'status' => 'menunggu_review',
                'type' => $request->type,
                'reason' => $request->reason,
                'refund_amount' => $totalRefund,
            ]);

            // Create return items
            foreach ($returnItems as $item) {
                ReturnItem::create(array_merge($item, ['return_id' => $returnRequest->id]));
            }

            // Upload media files
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $fileType = str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image';
                    $path = $file->store('returns/'.$returnRequest->id, 'public');

                    ReturnMedia::create([
                        'return_id' => $returnRequest->id,
                        'file_path' => $path,
                        'file_type' => $fileType,
                        'disk' => 'public',
                    ]);
                }
            }

            // Update transaction return_status
            $transaction->update(['return_status' => 'menunggu_review']);

            // Notify admin
            try {
                Notification::create([
                    'user_id' => null, // Admin global
                    'title' => 'Pengajuan Retur Baru',
                    'message' => 'Ada pengajuan retur dari '.$user->name.' untuk transaksi #'.$transaction->transaction_number.'.',
                    'type' => 'return_request',
                    'url' => '/admin/returns/'.$returnRequest->id,
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                // Fail silently
            }
        });

        return back()->with('success', 'Pengajuan retur berhasil dikirim. Admin akan meninjaunya segera.');
    }

    /**
     * Customer submits the return shipping tracking number.
     */
    public function updateTracking(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($returnRequest->user_id !== $user->id) {
            abort(403);
        }

        if ($returnRequest->status !== 'disetujui') {
            return back()->with('error', 'Nomor resi hanya dapat diisi setelah retur disetujui.');
        }

        $request->validate([
            'return_tracking_number' => 'required|string|max:100',
            'return_courier_name' => 'nullable|string|max:100',
        ]);

        $returnRequest->update([
            'return_tracking_number' => $request->return_tracking_number,
            'return_courier_name' => $request->return_courier_name,
            'status' => 'barang_dikirim_customer',
        ]);

        // Update transaction return_status
        $returnRequest->transaction->update(['return_status' => 'barang_dikirim_customer']);

        // Notify admin
        try {
            Notification::create([
                'user_id' => null,
                'title' => 'Barang Retur Dikirim',
                'message' => 'Customer '.$user->name.' telah mengirim barang retur untuk transaksi #'.$returnRequest->transaction->transaction_number.'. Resi: '.$request->return_tracking_number,
                'type' => 'return_request',
                'url' => '/admin/returns/'.$returnRequest->id,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            // Fail silently
        }

        return back()->with('success', 'Nomor resi retur berhasil disimpan.');
    }
}
