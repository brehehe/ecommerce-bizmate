<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    /**
     * Display list of all transactions.
     */
    public function index(Request $request)
    {
        $query = Transaction::with([
            'user:id,name,email',
            'paymentMethod:id,name,type',
            'payment',
            'items.product:id,is_digital,name',
        ])->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%");
                    });
            });
        }

        $transactions = $query->paginate(20)->withQueryString();

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Admin/Transactions/Index', [
            'transactions' => $transactions,
            'statusLabels' => Transaction::statusLabels(),
            'filters' => $request->only(['status', 'date_from', 'date_to', 'search']),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display a single transaction detail.
     */
    public function show(Transaction $transaction)
    {
        $transaction->load([
            'user:id,name,email',
            'customerAddress',
            'paymentMethod',
            'items.product.images',
            'payments.confirmedByUser:id,name',
            'stockMovements.product:id,name',
            'courierUser',
        ]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Admin/Transactions/Show', [
            'transaction' => $transaction,
            'statusLabels' => Transaction::statusLabels(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Find transaction by transaction number, booking code, or tracking number.
     */
    public function findByNumber($number)
    {
        $transaction = Transaction::where('transaction_number', $number)
            ->orWhere('booking_code', $number)
            ->orWhere('tracking_number', $number)
            ->first();

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'id' => $transaction->id,
            'redirect_url' => "/admin/transactions/{$transaction->id}",
        ]);
    }

    /**
     * Update the status of a transaction.
     */
    public function updateStatus(Request $request, Transaction $transaction)
    {
        $request->validate([
            'status' => 'required|in:belum_bayar,menunggu,diproses,dikemas,out_for_pickup,dikirim,selesai,batal',
            'cancel_reason' => 'required_if:status,batal|nullable|string|max:500',
        ]);

        $newStatus = $request->status;

        // If cancelling, restore stock
        if ($newStatus === 'batal' && $transaction->status !== 'batal') {
            $this->restoreStock($transaction, $request->user());
            $transaction->update([
                'status' => $newStatus,
                'cancel_reason' => $request->cancel_reason,
                'cancelled_at' => now(),
            ]);
        } else {
            $transaction->update(['status' => $newStatus]);
        }

        return back()->with('success', 'Status transaksi berhasil diperbarui.');
    }

    /**
     * Confirm customer's payment proof.
     */
    public function confirmPayment(Request $request, Transaction $transaction)
    {
        $payment = $transaction->payment;

        if (! $payment) {
            return back()->with('error', 'Data pembayaran tidak ditemukan.');
        }

        $payment->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_by' => $request->user()->id,
            'notes' => $request->notes,
        ]);

        $transaction->update(['status' => 'diproses']);

        return back()->with('success', 'Pembayaran berhasil dikonfirmasi.');
    }

    /**
     * Reject customer's payment proof.
     */
    public function rejectPayment(Request $request, Transaction $transaction)
    {
        $request->validate([
            'notes' => 'required|string|max:500',
        ]);

        $payment = $transaction->payment;

        if (! $payment) {
            return back()->with('error', 'Data pembayaran tidak ditemukan.');
        }

        $payment->update([
            'status' => 'rejected',
            'confirmed_at' => now(),
            'confirmed_by' => $request->user()->id,
            'notes' => $request->notes,
        ]);

        $transaction->update(['status' => 'belum_bayar']);

        return back()->with('success', 'Pembayaran ditolak. Customer perlu upload ulang bukti bayar.');
    }

    /**
     * Update tracking number (resi) and automatically set status to 'dikirim'.
     */
    public function updateTracking(Request $request, Transaction $transaction)
    {
        if (in_array($transaction->status, ['selesai', 'batal'])) {
            return back()->with('error', 'Tidak dapat memperbarui resi untuk transaksi yang sudah selesai atau batal.');
        }

        $request->validate([
            'tracking_number' => 'nullable|string|max:100',
            'courier_name' => 'nullable|string|max:100',
            'booking_code' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:50',
        ]);

        $updateData = [];
        if ($request->has('tracking_number')) {
            $updateData['tracking_number'] = $request->tracking_number;
        }
        if ($request->has('courier_name')) {
            $updateData['courier_name'] = $request->courier_name;
        }
        if ($request->has('booking_code')) {
            $updateData['booking_code'] = $request->booking_code;
        }

        // If tracking number is set and status is not already shipped/completed, default to dikirim
        if (! empty($request->tracking_number) && $transaction->shipping_courier !== 'store_courier' && ! in_array($transaction->status, ['dikirim', 'selesai'])) {
            $updateData['status'] = 'dikirim';
        } elseif ($request->filled('status')) {
            $updateData['status'] = $request->status;
        }

        $transaction->update($updateData);

        return back()->with('success', 'Informasi pengiriman berhasil diperbarui.');
    }

    /**
     * Add custom delivery log history for store courier.
     */
    public function addDeliveryHistory(Request $request, Transaction $transaction)
    {
        $request->validate([
            'description' => 'required|string|max:500',
        ]);

        $transaction->statusHistories()->create([
            'status' => $transaction->status,
            'description' => $request->description,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Riwayat pengiriman berhasil ditambahkan.');
    }

    /**
     * Bulk update status of multiple transactions.
     */
    public function bulkStatus(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:transactions,id',
            'status' => 'required|in:belum_bayar,menunggu,diproses,dikemas,out_for_pickup,dikirim,selesai,batal',
            'cancel_reason' => 'nullable|string|max:500',
        ]);

        $ids = $request->ids;
        $newStatus = $request->status;

        \DB::transaction(function () use ($ids, $newStatus, $request) {
            $transactions = Transaction::whereIn('id', $ids)->get();

            foreach ($transactions as $transaction) {
                // Skip if status is already the same
                if ($transaction->status === $newStatus) {
                    continue;
                }

                // If cancelling, restore stock
                if ($newStatus === 'batal') {
                    $this->restoreStock($transaction, $request->user());
                    $transaction->update([
                        'status' => $newStatus,
                        'cancel_reason' => $request->cancel_reason ?: 'Pembatalan massal oleh Admin',
                        'cancelled_at' => now(),
                    ]);
                } else {
                    $transaction->update(['status' => $newStatus]);
                }
            }
        });

        return back()->with('success', count($ids).' transaksi berhasil diperbarui.');
    }

    /**
     * Bulk update tracking numbers and set status to 'dikirim'.
     */
    public function bulkTracking(Request $request)
    {
        $request->validate([
            'tracking_data' => 'required|array',
            'tracking_data.*.id' => 'required|exists:transactions,id',
            'tracking_data.*.tracking_number' => 'required|string|max:100',
            'tracking_data.*.courier_name' => 'nullable|string|max:100',
        ]);

        \DB::transaction(function () use ($request) {
            foreach ($request->tracking_data as $data) {
                $transaction = Transaction::findOrFail($data['id']);

                if (in_array($transaction->status, ['selesai', 'batal'])) {
                    continue;
                }

                $transaction->update([
                    'tracking_number' => $data['tracking_number'],
                    'courier_name' => $data['courier_name'] ?? null,
                    'status' => 'dikirim',
                ]);
            }
        });

        return back()->with('success', 'Nomor resi untuk '.count($request->tracking_data).' transaksi berhasil disimpan.');
    }

    /**
     * Print transaction invoice.
     */
    public function printInvoice(Transaction $transaction)
    {
        $transaction->load([
            'user:id,name,email',
            'customerAddress',
            'paymentMethod',
            'items',
        ]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');

        return view('print.invoice', compact('transaction', 'storeName'));
    }

    /**
     * Print transaction shipping label.
     */
    public function printShippingLabel($transactionId)
    {
        if (is_numeric($transactionId)) {
            $transaction = Transaction::findOrFail($transactionId);
        } else {
            $transaction = Transaction::where('booking_code', $transactionId)->firstOrFail();
        }

        $transaction->load([
            'customerAddress',
            'paymentMethod',
            'items',
        ]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storePhone = Setting::where('key', 'store_phone')->value('value') ?? '-';
        $storeAddress = Setting::where('key', 'address')->value('value') ?? 'Gudang Utama BIZMATE';
        $storeCity = Setting::where('key', 'regency_name')->value('value') ?? 'DKI Jakarta';

        return view('print.shipping-label', compact('transaction', 'storeName', 'storePhone', 'storeAddress', 'storeCity'));
    }

    /**
     * Display stock movements report.
     */
    public function stockMovements(Request $request)
    {
        $query = StockMovement::with([
            'product:id,name,sku',
            'productVariant:id,sku',
            'transaction:id,transaction_number',
            'createdByUser:id,name',
        ])->latest();

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $movements = $query->paginate(25)->withQueryString();

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Admin/StockMovements/Index', [
            'movements' => $movements,
            'filters' => $request->only(['type', 'product_id', 'date_from', 'date_to']),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Restore stock when a transaction is cancelled.
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
