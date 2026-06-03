<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\ProductStock;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
{
    /**
     * List all return requests.
     */
    public function index(Request $request): Response
    {
        $query = ReturnRequest::with([
            'user:id,name,email',
            'transaction:id,transaction_number,grand_total',
            'items',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'ilike', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('transaction', fn ($tq) => $tq->where('transaction_number', 'ilike', "%{$search}%"));
            });
        }

        $returns = $query->paginate(20)->withQueryString();

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Admin/Returns/Index', [
            'returns' => $returns,
            'statusLabels' => ReturnRequest::statusLabels(),
            'filters' => $request->only(['status', 'type', 'search']),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Show a single return request detail.
     */
    public function show(ReturnRequest $return): Response
    {
        $return->load([
            'user:id,name,email',
            'user.customerBankAccounts',
            'transaction:id,transaction_number,grand_total,shipping_fee,status,user_id',
            'transaction.items',
            'items.transactionItem',
            'media',
            'approvedByUser:id,name',
            'receivedByUser:id,name',
            'replacementTransaction:id,transaction_number,status',
        ]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Admin/Returns/Show', [
            'return' => $return,
            'statusLabels' => ReturnRequest::statusLabels(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Approve a return request.
     */
    public function approve(Request $request, ReturnRequest $return): RedirectResponse
    {
        if ($return->status !== 'menunggu_review') {
            return back()->with('error', 'Retur tidak dapat disetujui pada status saat ini.');
        }

        $request->validate([
            'notes_admin' => 'nullable|string|max:500',
        ]);

        $return->update([
            'status' => 'disetujui',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'notes_admin' => $request->notes_admin,
        ]);

        $return->transaction->update(['return_status' => 'disetujui']);

        // Notify customer
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

        return back()->with('success', 'Pengajuan retur berhasil disetujui.');
    }

    /**
     * Reject a return request.
     */
    public function reject(Request $request, ReturnRequest $return): RedirectResponse
    {
        if ($return->status !== 'menunggu_review') {
            return back()->with('error', 'Retur tidak dapat ditolak pada status saat ini.');
        }

        $request->validate([
            'notes_admin' => 'required|string|max:500',
        ]);

        $return->update([
            'status' => 'ditolak',
            'notes_admin' => $request->notes_admin,
            'rejected_at' => now(),
        ]);

        $return->transaction->update(['return_status' => 'ditolak']);

        // Notify customer
        try {
            Notification::create([
                'user_id' => $return->user_id,
                'title' => 'Pengajuan Retur Ditolak',
                'message' => 'Pengajuan retur Anda (#'.$return->return_number.') ditolak. Alasan: '.$request->notes_admin,
                'type' => 'return_rejected',
                'url' => '/transactions/'.$return->transaction_id,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            // Fail silently
        }

        return back()->with('success', 'Pengajuan retur berhasil ditolak.');
    }

    /**
     * Admin inputs the customer's return tracking number (if customer didn't).
     */
    public function updateCustomerTracking(Request $request, ReturnRequest $return): RedirectResponse
    {
        if (! in_array($return->status, ['disetujui', 'barang_dikirim_customer'])) {
            return back()->with('error', 'Nomor resi tidak dapat diubah pada status ini.');
        }

        $request->validate([
            'return_tracking_number' => 'required|string|max:100',
            'return_courier_name' => 'nullable|string|max:100',
        ]);

        $return->update([
            'return_tracking_number' => $request->return_tracking_number,
            'return_courier_name' => $request->return_courier_name,
            'status' => 'barang_dikirim_customer',
        ]);

        $return->transaction->update(['return_status' => 'barang_dikirim_customer']);

        return back()->with('success', 'Nomor resi retur customer berhasil disimpan.');
    }

    /**
     * Confirm receipt of returned goods by the store.
     */
    public function confirmReceipt(Request $request, ReturnRequest $return): RedirectResponse
    {
        if ($return->status !== 'barang_dikirim_customer') {
            return back()->with('error', 'Konfirmasi penerimaan hanya dapat dilakukan setelah barang dikirim customer.');
        }

        $return->update([
            'status' => 'barang_diterima_toko',
            'received_by' => $request->user()->id,
            'received_at' => now(),
        ]);

        $return->transaction->update(['return_status' => 'barang_diterima_toko']);

        $stockAction = $request->input('stock_action', 'active');

        // Restore stock for returned items
        $return->load('items');
        foreach ($return->items as $item) {
            $stockRecord = $item->product_variant_id
                ? ProductStock::where('product_variant_id', $item->product_variant_id)->first()
                : ProductStock::where('product_id', $item->product_id)->whereNull('product_variant_id')->first();

            if ($stockRecord && ! $stockRecord->is_unlimited) {
                $stockBefore = $stockRecord->stock;

                if ($stockAction === 'active') {
                    $stockAfter = $stockBefore + $item->quantity_returned;
                    $stockRecord->update(['stock' => $stockAfter]);
                    $notes = 'Retur barang (kembali ke stok aktif) - '.$return->return_number;
                } else {
                    $stockAfter = $stockBefore;
                    $notes = 'Retur barang (rusak/tidak dikembalikan ke stok) - '.$return->return_number;
                }

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'transaction_id' => $return->transaction_id,
                    'type' => 'retur',
                    'quantity' => $stockAction === 'active' ? $item->quantity_returned : 0,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'notes' => $notes,
                    'created_by' => $request->user()->id,
                ]);
            }
        }

        // Notify customer
        try {
            Notification::create([
                'user_id' => $return->user_id,
                'title' => 'Barang Retur Diterima',
                'message' => 'Barang retur Anda (#'.$return->return_number.') telah diterima oleh toko. Admin sedang memproses '.($return->type === 'refund' ? 'pengembalian dana' : 'pengiriman barang pengganti').'.',
                'type' => 'return_received',
                'url' => '/transactions/'.$return->transaction_id,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            // Fail silently
        }

        return back()->with('success', 'Penerimaan barang retur berhasil dikonfirmasi. Stok produk telah dikembalikan.');
    }

    /**
     * Process refund (for type = refund).
     */
    public function processRefund(Request $request, ReturnRequest $return): RedirectResponse
    {
        if ($return->status !== 'barang_diterima_toko') {
            return back()->with('error', 'Refund hanya dapat diproses setelah barang diterima.');
        }

        if ($return->type !== 'refund') {
            return back()->with('error', 'Aksi ini hanya untuk retur jenis pengembalian dana.');
        }

        $request->validate([
            'notes_admin' => 'nullable|string|max:500',
        ]);

        $return->update([
            'status' => 'refund_diproses',
            'refunded_at' => now(),
            'notes_admin' => $request->notes_admin ?? $return->notes_admin,
        ]);

        $return->transaction->update(['return_status' => 'refund_diproses']);

        // Get customer bank account info for notification
        $return->load('user.customerBankAccounts');
        $bankAccount = $return->user->customerBankAccounts->where('is_primary', true)->first()
            ?? $return->user->customerBankAccounts->first();

        $bankInfo = $bankAccount
            ? "Dana akan ditransfer ke {$bankAccount->bank_name} - {$bankAccount->account_number} a.n. {$bankAccount->account_name}."
            : 'Mohon hubungi admin untuk informasi rekening tujuan refund.';

        // Notify customer
        try {
            $amount = 'Rp '.number_format($return->refund_amount, 0, ',', '.');
            Notification::create([
                'user_id' => $return->user_id,
                'title' => 'Refund Sedang Diproses',
                'message' => "Refund sebesar {$amount} untuk retur #{$return->return_number} sedang diproses. {$bankInfo}",
                'type' => 'refund_processed',
                'url' => '/transactions/'.$return->transaction_id,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            // Fail silently
        }

        return back()->with('success', 'Refund berhasil diproses. Customer telah dinotifikasi.');
    }

    /**
     * Create a replacement transaction and mark return as selesai (for type = penggantian_barang).
     */
    public function processReplacement(Request $request, ReturnRequest $return): RedirectResponse
    {
        if ($return->status !== 'barang_diterima_toko') {
            return back()->with('error', 'Penggantian barang hanya dapat diproses setelah barang diterima.');
        }

        if ($return->type !== 'penggantian_barang') {
            return back()->with('error', 'Aksi ini hanya untuk retur jenis penggantian barang.');
        }

        $return->load(['transaction.items', 'items']);

        DB::transaction(function () use ($return) {
            $originalTx = $return->transaction;

            // Build items for replacement transaction (only returned items with their qty)
            $returnedItemIds = $return->items->pluck('transaction_item_id')->toArray();
            $returnedQtyMap = $return->items->keyBy('transaction_item_id');

            // Calculate new subtotal based on returned items
            $newSubtotal = 0;
            $itemsForNew = [];

            foreach ($originalTx->items as $item) {
                if (! in_array($item->id, $returnedItemIds)) {
                    continue;
                }

                $returnItem = $returnedQtyMap[$item->id];
                $qty = $returnItem->quantity_returned;
                $lineTotal = $returnItem->unit_price * $qty;
                $newSubtotal += $lineTotal;

                $itemsForNew[] = [
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'product_sku' => $item->product_sku,
                    'product_image' => $item->product_image,
                    'quantity' => $qty,
                    'hpp' => $item->hpp,
                    'harga_jual' => $returnItem->unit_price,
                    'diskon_item' => 0,
                    'harga_akhir' => $returnItem->unit_price,
                    'subtotal' => $lineTotal,
                    'is_gift_item' => false,
                ];
            }

            // Create the replacement transaction
            $replacementTx = Transaction::create([
                'transaction_number' => Transaction::generateNumber(),
                'user_id' => $originalTx->user_id,
                'customer_address_id' => $originalTx->customer_address_id,
                'payment_method_id' => $originalTx->payment_method_id,
                'courier_id' => $originalTx->courier_id,
                'status' => 'diproses',
                'subtotal' => $newSubtotal,
                'discount_amount' => 0,
                'shipping_fee' => 0,
                'shipping_discount' => 0,
                'admin_fee' => 0,
                'application_fee' => 0,
                'grand_total' => $newSubtotal,
                'shipping_courier' => $originalTx->shipping_courier,
                'shipping_service' => $originalTx->shipping_service,
                'notes' => 'Transaksi penggantian barang retur dari '.$originalTx->transaction_number,
                'return_status' => null,
                'is_replacement_transaction' => true,
                'original_transaction_id' => $originalTx->id,
            ]);

            // Create items for replacement transaction
            foreach ($itemsForNew as $itemData) {
                $replacementTx->items()->create($itemData);
            }

            // Update the return record
            $return->update([
                'status' => 'selesai',
                'replacement_transaction_id' => $replacementTx->id,
            ]);

            $return->transaction->update(['return_status' => 'selesai']);

            // Notify customer
            try {
                Notification::create([
                    'user_id' => $return->user_id,
                    'title' => 'Barang Pengganti Sedang Diproses',
                    'message' => 'Barang pengganti untuk retur #'.$return->return_number.' sedang diproses. Transaksi baru dibuat: #'.$replacementTx->transaction_number.'.',
                    'type' => 'replacement_created',
                    'url' => '/transactions/'.$replacementTx->id,
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                // Fail silently
            }
        });

        return back()->with('success', 'Transaksi penggantian barang berhasil dibuat dan customer telah dinotifikasi.');
    }

    /**
     * Admin inputs the replacement (barang pengganti) tracking number.
     */
    public function updateReplacementTracking(Request $request, ReturnRequest $return): RedirectResponse
    {
        $request->validate([
            'replacement_tracking_number' => 'required|string|max:100',
            'replacement_courier_name' => 'nullable|string|max:100',
        ]);

        $return->update([
            'replacement_tracking_number' => $request->replacement_tracking_number,
            'replacement_courier_name' => $request->replacement_courier_name,
        ]);

        // If there's a replacement transaction, also update its tracking
        if ($return->replacement_transaction_id) {
            $return->replacementTransaction->update([
                'tracking_number' => $request->replacement_tracking_number,
                'courier_name' => $request->replacement_courier_name,
                'status' => 'dikirim',
            ]);
        }

        // Notify customer
        try {
            Notification::create([
                'user_id' => $return->user_id,
                'title' => 'Barang Pengganti Dikirim',
                'message' => 'Barang pengganti untuk retur #'.$return->return_number.' telah dikirim. Resi: '.$request->replacement_tracking_number,
                'type' => 'replacement_shipped',
                'url' => '/transactions/'.($return->replacement_transaction_id ?? $return->transaction_id),
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            // Fail silently
        }

        return back()->with('success', 'Nomor resi barang pengganti berhasil disimpan.');
    }

    /**
     * Mark refund as fully completed (selesai) after refund_diproses.
     */
    public function completeRefund(Request $request, ReturnRequest $return): RedirectResponse
    {
        if ($return->status !== 'refund_diproses') {
            return back()->with('error', 'Status tidak valid untuk menyelesaikan refund.');
        }

        $return->update(['status' => 'selesai']);
        $return->transaction->update(['return_status' => 'selesai']);

        // Notify customer
        try {
            $amount = 'Rp '.number_format($return->refund_amount, 0, ',', '.');
            Notification::create([
                'user_id' => $return->user_id,
                'title' => 'Refund Selesai',
                'message' => "Refund sebesar {$amount} untuk retur #{$return->return_number} telah selesai diproses.",
                'type' => 'refund_completed',
                'url' => '/transactions/'.$return->transaction_id,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            // Fail silently
        }

        return back()->with('success', 'Retur berhasil diselesaikan.');
    }
}
