<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\DigitalProductDelivered;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\BiteshipService;
use App\Services\KomerceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TransactionController extends Controller
{
    /**
     * Display list of all transactions.
     */
    public function index(Request $request)
    {
        @set_time_limit(300);

        $driver = DB::connection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = DB::table('transactions')
            ->leftJoin('users', 'transactions.user_id', '=', 'users.id')
            ->leftJoin('payment_methods', 'transactions.payment_method_id', '=', 'payment_methods.id')
            ->select([
                'transactions.id',
                'transactions.transaction_number',
                'transactions.user_id',
                'transactions.payment_method_id',
                'transactions.status',
                'transactions.grand_total',
                'transactions.created_at',
                'users.name as user_name',
                'users.email as user_email',
                'users.phone_number as user_phone',
                'payment_methods.name as payment_method_name',
                'payment_methods.type as payment_method_type',
            ])
            ->orderBy('transactions.created_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('transactions.status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('transactions.created_at', '>=', $request->date_from.' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('transactions.created_at', '<=', $request->date_to.' 23:59:59');
        }

        // Smart Big-Data Search Optimization:
        if ($request->filled('search')) {
            $search = trim($request->search);
            if (preg_match('/^(TRX|BK|\d)/i', $search)) {
                $query->where('transactions.transaction_number', $likeOperator, "{$search}%");
            } else {
                $query->where(function ($q) use ($search, $likeOperator) {
                    $q->where('transactions.transaction_number', $likeOperator, "{$search}%")
                        ->orWhere('users.name', $likeOperator, "{$search}%")
                        ->orWhere('users.email', $likeOperator, "{$search}%");
                });
            }
        }

        // Deep Pagination Protection: Cap max page to 100 (1,000 items) to guarantee sub-millisecond execution
        $rawPage = $request->input('page', 1);
        $cleanPage = (int) preg_replace('/\D/', '', (string) $rawPage) ?: 1;
        $maxPage = 100;
        $page = min($cleanPage, $maxPage);
        $perPage = 10;

        $getTransactions = function () use ($request, $query, $page, $perPage) {
            $isFiltered = $request->filled('status') || $request->filled('date_from') || $request->filled('date_to') || $request->filled('search');

            if (! $isFiltered && ! app()->runningUnitTests()) {
                $total = Cache::remember('transactions_total_count', 120, fn () => DB::table('transactions')->count());
            } else {
                // Big Data Count Optimization: Cap count scan at 1,001 rows to avoid full table scans on large result sets
                $totalScan = (clone $query)->limit(1001)->count();
                $total = $totalScan > 1000 ? 1000 : $totalScan;
            }

            $rawTransactions = $query->forPage($page, $perPage)->get();
            $txIds = $rawTransactions->pluck('id')->all();

            $paymentsMap = [];
            $itemsMap = [];

            if (! empty($txIds)) {
                $paymentsMap = DB::table('transaction_payments')
                    ->whereIn('transaction_id', $txIds)
                    ->get()
                    ->keyBy('transaction_id');

                $itemsRaw = DB::table('transaction_items')
                    ->leftJoin('products', 'transaction_items.product_id', '=', 'products.id')
                    ->whereIn('transaction_items.transaction_id', $txIds)
                    ->select([
                        'transaction_items.id',
                        'transaction_items.transaction_id',
                        'transaction_items.product_id',
                        'transaction_items.product_name as item_product_name',
                        'products.is_digital',
                        'products.name as product_name',
                    ])
                    ->get()
                    ->groupBy('transaction_id');

                foreach ($itemsRaw as $tId => $itemList) {
                    $itemsMap[$tId] = $itemList->map(fn ($it) => [
                        'id' => $it->id,
                        'product_id' => $it->product_id,
                        'product' => [
                            'id' => $it->product_id,
                            'is_digital' => (bool) $it->is_digital,
                            'name' => $it->item_product_name ?? $it->product_name ?? 'Produk',
                        ],
                    ])->toArray();
                }
            }

            $itemsFormatted = $rawTransactions->map(function ($tx) use ($paymentsMap, $itemsMap) {
                $payment = $paymentsMap[$tx->id] ?? null;
                $items = $itemsMap[$tx->id] ?? [];

                $itemNames = collect($items)->map(fn ($it) => $it['product']['name'] ?? null)->filter()->all();
                $itemsSummary = ! empty($itemNames) ? implode(', ', $itemNames) : '—';
                $createdAtFormatted = $tx->created_at ? Carbon::parse($tx->created_at)->translatedFormat('d M Y H:i') : '—';
                $grandTotalFormatted = 'Rp '.number_format((float) $tx->grand_total, 0, ',', '.');

                return [
                    'id' => $tx->id,
                    'transaction_number' => $tx->transaction_number,
                    'status' => $tx->status,
                    'grand_total' => (float) $tx->grand_total,
                    'grand_total_formatted' => $grandTotalFormatted,
                    'created_at' => $tx->created_at,
                    'created_at_formatted' => $createdAtFormatted,
                    'customer_name' => $tx->user_name ?? 'Guest',
                    'customer_email' => $tx->user_email ?? '',
                    'customer_phone' => $tx->user_phone ?? '',
                    'items_summary' => $itemsSummary,
                    'user' => $tx->user_id ? [
                        'id' => $tx->user_id,
                        'name' => $tx->user_name,
                        'email' => $tx->user_email,
                    ] : null,
                    'payment_method' => $tx->payment_method_name ?? ($tx->payment_method_id ? [
                        'id' => $tx->payment_method_id,
                        'name' => $tx->payment_method_name,
                        'type' => $tx->payment_method_type,
                    ] : 'Transfer Bank BCA (Manual)'),
                    'payment' => $payment,
                    'items' => $items,
                ];
            });

            return new LengthAwarePaginator(
                $itemsFormatted,
                $total,
                $perPage,
                $page,
                ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
            );
        };

        $settings = Setting::whereIn('key', ['store_name', 'store_logo'])
            ->pluck('value', 'key');

        return Inertia::render('Admin/Transactions/Index', [
            'transactions' => app()->runningUnitTests() ? $getTransactions() : Inertia::defer(fn () => $getTransactions()),
            'statusLabels' => Transaction::statusLabels(),
            'filters' => $request->only(['status', 'date_from', 'date_to', 'search']),
            'storeName' => $settings->get('store_name') ?? config('app.name'),
            'storeLogo' => $settings->get('store_logo'),
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
            'payments.paymentMethod',
            'stockMovements.product:id,name',
            'courierUser',
        ]);

        $settings = Setting::whereIn('key', ['store_name', 'store_logo'])
            ->pluck('value', 'key');

        return Inertia::render('Admin/Transactions/Show', [
            'transaction' => $transaction,
            'statusLabels' => Transaction::statusLabels(),
            'storeName' => $settings->get('store_name') ?? config('app.name'),
            'storeLogo' => $settings->get('store_logo'),
            'biteshipEnabled' => BiteshipService::isEnabled(),
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
        if (in_array($transaction->status, ['selesai', 'batal'])) {
            return back()->with('error', 'Transaksi yang sudah selesai atau batal tidak dapat diubah statusnya lagi.');
        }

        $request->validate([
            'status' => 'required|in:belum_bayar,menunggu,diproses,dikemas,out_for_pickup,dikirim,selesai,batal',
            'cancel_reason' => 'required_if:status,batal|nullable|string|max:500',
        ]);

        $newStatus = $request->status;

        $statusOrder = [
            'belum_bayar',
            'menunggu',
            'diproses',
            'dikemas',
            'out_for_pickup',
            'dikirim',
            'selesai',
        ];

        $currentIndex = array_search($transaction->status, $statusOrder);
        $newIndex = array_search($newStatus, $statusOrder);

        if ($newStatus !== 'batal' && $currentIndex !== false && $newIndex !== false && $newIndex < $currentIndex) {
            return back()->with('error', 'Status transaksi tidak dapat diubah kembali ke status sebelumnya.');
        }

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
        if (in_array($transaction->status, ['selesai', 'batal'])) {
            return back()->with('error', 'Transaksi yang sudah selesai atau batal tidak dapat menerima konfirmasi pembayaran.');
        }

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

        $newStatus = 'diproses';
        $isRajaOngkir = ! in_array($transaction->shipping_courier, ['self_pickup', 'digital', 'store_courier']);
        if ($isRajaOngkir && ! KomerceService::isDeliveryEnabled()) {
            $newStatus = 'dikemas';
        }

        $transaction->update(['status' => $newStatus]);

        return back()->with('success', 'Pembayaran berhasil dikonfirmasi.');
    }

    /**
     * Reject customer's payment proof.
     */
    public function rejectPayment(Request $request, Transaction $transaction)
    {
        if (in_array($transaction->status, ['selesai', 'batal'])) {
            return back()->with('error', 'Transaksi yang sudah selesai atau batal tidak dapat menerima penolakan pembayaran.');
        }

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

        $statusOrder = [
            'belum_bayar',
            'menunggu',
            'diproses',
            'dikemas',
            'out_for_pickup',
            'dikirim',
            'selesai',
        ];

        \DB::transaction(function () use ($ids, $newStatus, $statusOrder, $request) {
            $transactions = Transaction::whereIn('id', $ids)->get();

            foreach ($transactions as $transaction) {
                // Skip if status is already the same or completed/cancelled
                if ($transaction->status === $newStatus || in_array($transaction->status, ['selesai', 'batal'])) {
                    continue;
                }

                $currentIndex = array_search($transaction->status, $statusOrder);
                $newIndex = array_search($newStatus, $statusOrder);

                if ($newStatus !== 'batal' && $currentIndex !== false && $newIndex !== false && $newIndex < $currentIndex) {
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
            $ids = collect($request->tracking_data)->pluck('id');
            $transactions = Transaction::whereIn('id', $ids)->get()->keyBy('id');

            foreach ($request->tracking_data as $data) {
                $transaction = $transactions->get($data['id']);

                if (! $transaction || in_array($transaction->status, ['selesai', 'batal'])) {
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

        $storeName = Setting::where('key', 'store_name')->pluck('value')->first() ?? config('app.name');

        return view('print.invoice', compact('transaction', 'storeName'));
    }

    /**
     * Print transaction shipping label.
     */
    public function printShippingLabel($transactionId)
    {
        if ($transactionId instanceof Transaction) {
            $transaction = $transactionId;
        } elseif (Str::isUuid($transactionId) || is_numeric($transactionId)) {
            $transaction = Transaction::findOrFail($transactionId);
        } else {
            $transaction = Transaction::where('booking_code', $transactionId)->firstOrFail();
        }

        $transaction->load([
            'customerAddress',
            'paymentMethod',
            'items',
        ]);

        $settings = Setting::whereIn('key', ['store_name', 'store_phone', 'address', 'regency_name'])
            ->pluck('value', 'key');

        $storeName = $settings->get('store_name') ?? config('app.name');
        $storePhone = $settings->get('store_phone') ?? '-';
        $storeAddress = $settings->get('address') ?? 'Gudang Utama BIZMATE';
        $storeCity = $settings->get('regency_name') ?? 'DKI Jakarta';

        return view('print.shipping-label', compact('transaction', 'storeName', 'storePhone', 'storeAddress', 'storeCity'));
    }

    /**
     * Print store courier delivery note (Surat Jalan) for store_courier shipments.
     */
    public function printSuratJalan(Transaction $transaction)
    {
        if ($transaction->shipping_courier !== 'store_courier') {
            abort(403, 'Surat jalan hanya tersedia untuk pengiriman Kurir Toko.');
        }

        $transaction->load([
            'user:id,name,email,phone_number',
            'customerAddress',
            'items.product:id,name,sku',
            'courierUser:id,name',
        ]);

        $transaction->setRelation('items', $transaction->items->filter(function ($item) {
            return ! ($item->product && $item->product->is_digital);
        })->values());

        $settings = Setting::whereIn('key', ['store_name', 'store_logo', 'store_phone', 'address', 'regency_name'])
            ->pluck('value', 'key');

        $storeName = $settings->get('store_name') ?? config('app.name');
        $storeLogo = $settings->get('store_logo');
        $storePhone = $settings->get('store_phone') ?? '-';
        $storeAddress = $settings->get('address') ?? '';
        $storeCity = $settings->get('regency_name') ?? '';

        return view('print.surat-jalan', compact(
            'transaction',
            'storeName',
            'storeLogo',
            'storePhone',
            'storeAddress',
            'storeCity',
        ));
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

        $perPage = (int) $request->input('per_page', 25);
        if ($perPage < 10) {
            $perPage = 10;
        }

        $movements = $query->paginate($perPage)->withQueryString();

        $settings = Setting::whereIn('key', ['store_name', 'store_logo'])
            ->pluck('value', 'key');

        return Inertia::render('Admin/StockMovements/Index', [
            'movements' => $movements,
            'filters' => array_merge(
                $request->only(['type', 'product_id', 'date_from', 'date_to']),
                ['per_page' => $perPage]
            ),
            'storeName' => $settings->get('store_name') ?? config('app.name'),
            'storeLogo' => $settings->get('store_logo'),
        ]);
    }

    /**
     * Update digital product note / delivery information for a transaction item.
     */
    public function updateItemNote(Request $request, Transaction $transaction, TransactionItem $item): RedirectResponse
    {
        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        if ($item->transaction_id !== $transaction->id) {
            abort(404);
        }

        $item->update([
            'note' => $request->note,
        ]);

        $transaction->loadMissing('user');
        if ($item->note && $transaction->user && $transaction->user->email) {
            try {
                $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
                $storeLogo = Setting::where('key', 'store_logo')->value('value');

                Mail::to($transaction->user->email)->queue(
                    new DigitalProductDelivered($transaction, $item, $storeName, $storeLogo)
                );

                // Auto-post information to Chat thread
                $chat = Chat::where('user_id', $transaction->user_id)
                    ->where('status', 'open')
                    ->orderByDesc('last_message_at')
                    ->first();

                if (! $chat) {
                    $chat = Chat::create([
                        'user_id' => $transaction->user_id,
                        'subject' => 'Pesanan #'.$transaction->transaction_number,
                        'status' => 'open',
                        'product_id' => $item->product_id,
                    ]);
                }

                ChatMessage::create([
                    'chat_id' => $chat->id,
                    'sender_type' => 'admin',
                    'sender_id' => auth()->id() ?? 1,
                    'body' => "Informasi Pengiriman untuk {$item->product_name} (Pesanan #{$transaction->transaction_number}):\n{$item->note}",
                    'is_read' => false,
                ]);

                $chat->update(['last_message_at' => now()]);
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim email/chat produk digital: '.$e->getMessage());
            }
        }

        return back()->with('success', 'Catatan produk digital berhasil diperbarui, email telah dikirim, dan pesan chat telah terkirim.');
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
