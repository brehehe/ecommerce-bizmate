<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinHistory;
use App\Models\Notification;
use App\Models\ProductStock;
use App\Models\RefundRequest;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RefundController extends Controller
{
    /**
     * List all cancellation and refund requests.
     */
    public function index(Request $request): InertiaResponse
    {
        $query = RefundRequest::with([
            'user:id,name,email',
            'transaction:id,transaction_number,grand_total,status',
        ])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('refund_method')) {
            $query->where('refund_method', $request->refund_method);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('refund_number', 'ilike', "%{$search}%")
                    ->orWhereHas('user', fn ($uq) => $uq->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('transaction', fn ($tq) => $tq->where('transaction_number', 'ilike', "%{$search}%"));
            });
        }

        $refunds = $query->paginate(20)->withQueryString();

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Admin/Refunds/Index', [
            'refunds' => $refunds,
            'statusLabels' => RefundRequest::statusLabels(),
            'filters' => $request->only(['status', 'refund_method', 'search']),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Show a specific cancellation and refund request.
     */
    public function show(RefundRequest $refund): InertiaResponse
    {
        $refund->load([
            'user:id,name,email',
            'user.customerBankAccounts',
            'transaction.items.product',
            'processedByUser:id,name',
        ]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Admin/Refunds/Show', [
            'refund' => $refund,
            'statusLabels' => RefundRequest::statusLabels(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Approve a cancellation request, cancels transaction, restores stock, and processes coins if applicable.
     */
    public function approve(Request $request, RefundRequest $refund): RedirectResponse
    {
        if ($refund->status !== 'menunggu_konfirmasi') {
            return back()->with('error', 'Pengajuan ini tidak dapat disetujui pada status saat ini.');
        }

        $request->validate([
            'notes_admin' => 'nullable|string|max:500',
        ]);

        $transaction = $refund->transaction;

        if ($transaction->status === 'batal') {
            return back()->with('error', 'Transaksi untuk pengajuan ini sudah dibatalkan sebelumnya.');
        }

        DB::transaction(function () use ($request, $refund, $transaction) {
            // 1. Restore stock
            $this->restoreStock($transaction, $request->user());

            // 2. Cancel transaction
            $transaction->update([
                'status' => 'batal',
                'cancel_reason' => 'Pengajuan Pembatalan Disetujui: '.$refund->reason,
                'cancelled_at' => now(),
            ]);

            $refundStatus = 'disetujui';
            $refundedAt = null;

            // 3. Process refund based on method
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

                // Notify customer of points credit
                Notification::create([
                    'user_id' => $refund->user_id,
                    'title' => 'Refund Koin Berhasil dikreditkan',
                    'message' => 'Refund berupa koin untuk transaksi #'.$transaction->transaction_number.' sebesar '.number_format($coinsToCredit, 0, ',', '.').' koin telah berhasil dikreditkan ke saldo koin Anda.',
                    'type' => 'refund_completed',
                    'url' => '/refunds/'.$refund->id,
                    'is_read' => false,
                ]);
            } else {
                // Bank Transfer refund is approved and pending transfer completion by admin
                Notification::create([
                    'user_id' => $refund->user_id,
                    'title' => 'Pengajuan Pembatalan Disetujui',
                    'message' => 'Pengajuan pembatalan untuk transaksi #'.$transaction->transaction_number.' telah disetujui. Refund transfer bank sebesar Rp '.number_format($refund->refund_amount, 0, ',', '.').' sedang diproses.',
                    'type' => 'refund_approved',
                    'url' => '/refunds/'.$refund->id,
                    'is_read' => false,
                ]);
            }

            // 4. Update refund request
            $refund->update([
                'status' => $refundStatus,
                'notes_admin' => $request->notes_admin,
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
                'refunded_at' => $refundedAt,
            ]);
        });

        return back()->with('success', 'Pengajuan pembatalan berhasil disetujui.');
    }

    /**
     * Reject a cancellation/refund request.
     */
    public function reject(Request $request, RefundRequest $refund): RedirectResponse
    {
        if ($refund->status !== 'menunggu_konfirmasi') {
            return back()->with('error', 'Pengajuan ini tidak dapat ditolak pada status saat ini.');
        }

        $request->validate([
            'notes_admin' => 'required|string|max:500',
        ], [
            'notes_admin.required' => 'Catatan penolakan wajib diisi.',
        ]);

        $refund->update([
            'status' => 'ditolak',
            'notes_admin' => $request->notes_admin,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        // Notify customer
        Notification::create([
            'user_id' => $refund->user_id,
            'title' => 'Pengajuan Pembatalan Ditolak',
            'message' => 'Pengajuan pembatalan untuk transaksi #'.$refund->transaction->transaction_number.' ditolak. Catatan: '.$request->notes_admin,
            'type' => 'refund_rejected',
            'url' => '/refunds/'.$refund->id,
            'is_read' => false,
        ]);

        return back()->with('success', 'Pengajuan pembatalan ditolak.');
    }

    /**
     * Mark a bank transfer refund as completed.
     */
    public function completeRefund(Request $request, RefundRequest $refund): RedirectResponse
    {
        if ($refund->status !== 'disetujui' || $refund->refund_method !== 'transfer') {
            return back()->with('error', 'Status pengajuan tidak valid untuk diselesaikan.');
        }

        $refund->update([
            'status' => 'selesai',
            'refunded_at' => now(),
        ]);

        // Notify customer
        Notification::create([
            'user_id' => $refund->user_id,
            'title' => 'Refund Berhasil Ditransfer',
            'message' => 'Dana refund sebesar Rp '.number_format($refund->refund_amount, 0, ',', '.').' untuk transaksi #'.$refund->transaction->transaction_number.' telah berhasil ditransfer ke rekening Anda.',
            'type' => 'refund_completed',
            'url' => '/refunds/'.$refund->id,
            'is_read' => false,
        ]);

        return back()->with('success', 'Refund transfer bank berhasil diselesaikan.');
    }

    /**
     * Helper method to restore stock when transaction is cancelled.
     */
    private function restoreStock(Transaction $transaction, $adminUser): void
    {
        $transaction->load('items');

        foreach ($transaction->items as $item) {
            if ($item->is_gift_item) {
                continue;
            }

            $stockRecord = $item->product_variant_id
                ? ProductStock::where('product_variant_id', $item->product_variant_id)->first()
                : ProductStock::where('product_id', $item->product_id)->whereNull('product_variant_id')->first();

            if ($stockRecord && ! $stockRecord->is_unlimited) {
                $stockBefore = $stockRecord->stock;
                $stockAfter = $stockBefore + $item->quantity;
                $stockRecord->update(['stock' => $stockAfter]);

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'transaction_id' => $transaction->id,
                    'type' => 'retur',
                    'quantity' => $item->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'notes' => 'Pembatalan transaksi - '.$transaction->transaction_number,
                    'created_by' => $adminUser->id,
                ]);
            }
        }
    }
}
