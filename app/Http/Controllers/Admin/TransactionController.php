<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Transaction\BulkUpdateStatusAction;
use App\Actions\Transaction\BulkUpdateTrackingAction;
use App\Actions\Transaction\ConfirmPaymentAction;
use App\Actions\Transaction\CreateAdminTransactionAction;
use App\Actions\Transaction\RejectPaymentAction;
use App\Actions\Transaction\UpdateTrackingAction;
use App\Actions\Transaction\UpdateTransactionStatusAction;
use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Transaction\BulkStatusRequest;
use App\Http\Requests\Admin\Transaction\BulkTrackingRequest;
use App\Http\Requests\Admin\Transaction\StoreAdminTransactionRequest;
use App\Http\Requests\Admin\Transaction\UpdateStatusRequest;
use App\Http\Requests\Admin\Transaction\UpdateTrackingRequest;
use App\Mail\DigitalProductDelivered;
use App\Models\Category;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Courier;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\BiteshipService;
use App\Services\KomerceService;
use App\Services\MidtransService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class TransactionController extends Controller
{
    public function __construct(
        protected CreateAdminTransactionAction $createAdminTransactionAction,
        protected UpdateTransactionStatusAction $updateTransactionStatusAction,
        protected ConfirmPaymentAction $confirmPaymentAction,
        protected RejectPaymentAction $rejectPaymentAction,
        protected UpdateTrackingAction $updateTrackingAction,
        protected BulkUpdateStatusAction $bulkUpdateStatusAction,
        protected BulkUpdateTrackingAction $bulkUpdateTrackingAction
    ) {}

    /**
     * Show POS / Cashier Direct Transaction Creation page.
     */
    public function create(Request $request): Response
    {
        KomerceService::syncPaymentMethods();

        $user = $request->user();
        $isSeller = $user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin']);

        $productQuery = Product::with([
            'productPrice',
            'productStock',
            'images',
            'variants.productPrice',
            'variants.productStock',
            'variants.options',
            'category',
            'user:id,name,store_name',
        ])
            ->where('active', true);

        if ($isSeller) {
            $productQuery->where('user_id', $user->id);
        }

        $products = $productQuery->orderBy('name', 'asc')->get();
        $categories = Category::orderBy('name', 'asc')->get();
        $customers = User::select('id', 'name', 'email', 'phone_number')->orderBy('name', 'asc')->get();
        $sellers = User::where('is_seller', true)->select('id', 'name', 'store_name', 'email')->orderBy('name', 'asc')->get();
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('name', 'asc')->get();

        $midtransEnabled = config('app.midtrans_enabled', true) && Setting::where('key', 'midtrans_api_enabled')->value('value') === '1';
        $midtransEnabledMethods = $midtransEnabled ? MidtransService::getEnabledMethods() : [];
        $midtransAdminFee = (float) (Setting::where('key', 'midtrans_admin_fee')->value('value') ?? 0);

        $couriers = Courier::where('is_active', true)->orderBy('name', 'asc')->get();
        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');

        return Inertia::render('Admin/Transactions/Create', [
            'products' => $products,
            'categories' => $categories,
            'customers' => $customers,
            'sellers' => $sellers,
            'isSeller' => $isSeller,
            'paymentMethods' => $paymentMethods,
            'midtransEnabledMethods' => $midtransEnabledMethods,
            'midtransAdminFee' => $midtransAdminFee,
            'couriers' => $couriers,
            'storeName' => $storeName,
        ]);
    }

    /**
     * Store POS / Cashier Direct Transaction.
     */
    public function store(StoreAdminTransactionRequest $request): RedirectResponse
    {
        $transaction = $this->createAdminTransactionAction->execute($request->validated(), $request->user());

        return redirect()->route('admin.transactions.show', $transaction->id)
            ->with('success', 'Transaksi Kasir berhasil dibuat.');
    }

    /**
     * Display a listing of transactions.
     */
    public function index(Request $request): Response
    {
        $driver = DB::connection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = DB::table('transactions')
            ->leftJoin('users', 'transactions.user_id', '=', 'users.id')
            ->leftJoin('payment_methods', 'transactions.payment_method_id', '=', 'payment_methods.id')
            ->select([
                'transactions.id',
                'transactions.transaction_number',
                'transactions.status',
                'transactions.grand_total',
                'transactions.created_at',
                'transactions.user_id',
                'transactions.payment_method_id',
                'users.name as user_name',
                'users.email as user_email',
                'users.phone_number as user_phone',
                'payment_methods.name as payment_method_name',
                'payment_methods.type as payment_method_type',
            ])
            ->orderBy('transactions.created_at', 'desc');

        $user = $request->user();
        $isSeller = $user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin']);
        if ($isSeller) {
            $sellerProductIds = DB::table('products')->where('user_id', $user->id)->pluck('id');
            $query->whereIn('transactions.id', function ($sub) use ($sellerProductIds) {
                $sub->select('transaction_id')
                    ->from('transaction_items')
                    ->whereIn('product_id', $sellerProductIds);
            });
        }

        if ($request->filled('status')) {
            $query->where('transactions.status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('transactions.created_at', '>=', $request->date_from.' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('transactions.created_at', '<=', $request->date_to.' 23:59:59');
        }

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

        $rawPage = $request->input('page', 1);
        $cleanPage = (int) preg_replace('/\D/', '', (string) $rawPage) ?: 1;
        $page = max(1, $cleanPage);
        $perPage = 10;

        $getTransactions = function () use ($request, $query, $page, $perPage, $isSeller) {
            $isFiltered = $request->filled('status') || $request->filled('date_from') || $request->filled('date_to') || $request->filled('search');

            if (! $isFiltered && ! $isSeller && ! app()->runningUnitTests()) {
                $total = Cache::remember('transactions_total_count', 120, fn () => DB::table('transactions')->count());
            } else {
                $total = (clone $query)->count();
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

        $settings = Setting::whereIn('key', ['store_name', 'store_logo'])->pluck('value', 'key');

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
    public function show(Transaction $transaction): Response
    {
        $user = auth()->user();
        if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
            $sellerProductIds = DB::table('products')->where('user_id', $user->id)->pluck('id');
            $hasProduct = $transaction->items()->whereIn('product_id', $sellerProductIds)->exists();
            if (! $hasProduct) {
                abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
            }
        }

        $transaction->load([
            'user:id,name,email',
            'customerAddress',
            'paymentMethod',
            'items.product.images',
            'payments.confirmedByUser:id,name',
            'payments.paymentMethod',
            'stockMovements.product:id,name',
            'courierUser',
            'statusHistories' => fn ($q) => $q->orderBy('created_at', 'asc'),
        ]);

        $settings = Setting::whereIn('key', ['store_name', 'store_logo'])->pluck('value', 'key');
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('name', 'asc')->get();
        $midtransEnabledMethods = MidtransService::getEnabledMethods();
        $storeCouriers = Role::where('name', 'Kurir Toko')->where('guard_name', 'web')->exists()
            ? User::role('Kurir Toko')
                ->where('is_active', true)
                ->select('id', 'name', 'phone_number', 'email')
                ->orderBy('name')
                ->get()
            : collect();

        return Inertia::render('Admin/Transactions/Show', [
            'transaction' => $transaction,
            'statusLabels' => Transaction::statusLabels(),
            'storeName' => $settings->get('store_name') ?? config('app.name'),
            'storeLogo' => $settings->get('store_logo'),
            'biteshipEnabled' => BiteshipService::isEnabled(),
            'paymentMethods' => $paymentMethods,
            'midtransEnabledMethods' => $midtransEnabledMethods,
            'storeCouriers' => $storeCouriers,
        ]);
    }

    /**
     * Change payment method for a transaction.
     */
    public function changePaymentMethod(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
            'midtrans_payment_type_key' => 'nullable|string',
        ]);

        $paymentMethod = PaymentMethod::findOrFail($validated['payment_method_id']);
        $midtransKey = $validated['midtrans_payment_type_key'] ?? null;

        $notes = $transaction->notes ?? '';
        if ($midtransKey) {
            $channelLabel = strtoupper(str_replace('_', ' ', $midtransKey));
            $notes = preg_replace('/\[(?:Channel|Midtrans Channel):\s*[^\]]+\]\s*/i', '', $notes);
            $notes = "[Channel: {$channelLabel}] ".trim($notes);
        }

        $transaction->update([
            'payment_method_id' => $paymentMethod->id,
            'notes' => $notes,
        ]);

        if ($midtransKey && str_contains(strtolower($paymentMethod->name), 'midtrans')) {
            $user = $transaction->user ?? $request->user();
            $result = MidtransService::charge(
                ($transaction->transaction_number ?? $transaction->id).'-'.time(),
                (int) $transaction->grand_total,
                $midtransKey,
                [
                    'name' => $transaction->customer_name ?? $user->name,
                    'email' => $transaction->customer_email ?? $user->email,
                    'phone' => $transaction->customer_phone ?? '',
                ]
            );

            if ($result['success']) {
                $instructions = $result['data'];
                $latestPayment = $transaction->payments()->latest()->first();
                $gatewayResponse = ['_payment_instructions' => $instructions];
                if ($latestPayment) {
                    $latestPayment->update([
                        'gateway_response' => json_encode($gatewayResponse),
                        'gateway_transaction_id' => $result['raw']['transaction_id'] ?? null,
                    ]);
                } else {
                    $transaction->payments()->create([
                        'user_id' => $user->id,
                        'payment_method_id' => $paymentMethod->id,
                        'amount' => $transaction->grand_total,
                        'status' => 'pending',
                        'gateway_response' => json_encode($gatewayResponse),
                        'gateway_transaction_id' => $result['raw']['transaction_id'] ?? null,
                    ]);
                }
            }
        }

        return back()->with('success', 'Metode pembayaran transaksi berhasil diperbarui.');
    }

    /**
     * Find transaction by transaction number, booking code, or tracking number.
     */
    public function findByNumber(string $number): JsonResponse
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
    public function updateStatus(UpdateStatusRequest $request, Transaction $transaction): RedirectResponse
    {
        $result = $this->updateTransactionStatusAction->execute(
            $transaction,
            $request->input('status'),
            $request->input('cancel_reason'),
            $request->user()
        );

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Status transaksi berhasil diperbarui.');
    }

    /**
     * Confirm customer's payment proof.
     */
    public function confirmPayment(Request $request, Transaction $transaction): RedirectResponse
    {
        $result = $this->confirmPaymentAction->execute($transaction, $request->input('notes'), $request->user());

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Pembayaran berhasil dikonfirmasi.');
    }

    /**
     * Reject customer's payment proof.
     */
    public function rejectPayment(Request $request, Transaction $transaction): RedirectResponse
    {
        $request->validate(['notes' => 'required|string|max:500']);

        $result = $this->rejectPaymentAction->execute($transaction, $request->input('notes'), $request->user());

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Pembayaran ditolak. Customer perlu upload ulang bukti bayar.');
    }

    /**
     * Update tracking number (resi).
     */
    public function updateTracking(UpdateTrackingRequest $request, Transaction $transaction): RedirectResponse
    {
        $result = $this->updateTrackingAction->execute($transaction, $request->validated());

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Informasi pengiriman berhasil diperbarui.');
    }

    /**
     * Add custom delivery log history for store courier.
     */
    public function addDeliveryHistory(Request $request, Transaction $transaction): RedirectResponse
    {
        $request->validate(['description' => 'required|string|max:500']);

        $transaction->statusHistories()->create([
            'status' => $transaction->status,
            'description' => $request->description,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Riwayat pengiriman berhasil ditambahkan.');
    }

    /**
     * Upload delivery photos for transaction.
     */
    public function uploadDeliveryPhotos(Request $request, Transaction $transaction): RedirectResponse
    {
        $request->validate([
            'photos' => 'required|array|min:1',
            'photos.*' => 'image|max:5120',
        ]);

        $currentPhotos = $transaction->delivery_photos ?? [];
        foreach ($request->file('photos') as $file) {
            $currentPhotos[] = ImageHelper::compressAndStore($file, 'delivery_photos', 'public');
        }

        $updateData = ['delivery_photos' => $currentPhotos];
        if (empty($transaction->delivery_arrived_at)) {
            $updateData['delivery_arrived_at'] = now();
        }

        $transaction->update($updateData);

        return back()->with('success', 'Foto bukti pengiriman berhasil diunggah.');
    }

    /**
     * Delete a delivery photo from transaction.
     */
    public function deleteDeliveryPhoto(Transaction $transaction, int $index): RedirectResponse
    {
        $currentPhotos = $transaction->delivery_photos ?? [];
        if (isset($currentPhotos[$index])) {
            $photoPath = $currentPhotos[$index];
            if (Storage::disk('public')->exists($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }
            array_splice($currentPhotos, $index, 1);
            $transaction->update([
                'delivery_photos' => empty($currentPhotos) ? null : array_values($currentPhotos),
            ]);
        }

        return back()->with('success', 'Foto bukti pengiriman berhasil dihapus.');
    }

    /**
     * Bulk update status of multiple transactions.
     */
    public function bulkStatus(BulkStatusRequest $request): RedirectResponse
    {
        $this->bulkUpdateStatusAction->execute(
            $request->input('ids'),
            $request->input('status'),
            $request->input('cancel_reason'),
            $request->user()
        );

        return back()->with('success', count($request->input('ids')).' transaksi berhasil diperbarui.');
    }

    /**
     * Bulk update tracking numbers for multiple transactions.
     */
    public function bulkTracking(BulkTrackingRequest $request): RedirectResponse
    {
        $this->bulkUpdateTrackingAction->execute($request->input('tracking_data'));

        return back()->with('success', 'Nomor resi untuk '.count($request->input('tracking_data')).' transaksi berhasil disimpan.');
    }

    /**
     * Display stock movements report.
     */
    public function stockMovements(Request $request)
    {
        $user = $request->user();
        $query = StockMovement::with([
            'product:id,name,sku',
            'productVariant:id,sku',
            'transaction:id,transaction_number',
            'createdByUser:id,name',
        ])->latest();

        if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
            $sellerProductIds = DB::table('products')->where('user_id', $user->id)->pluck('id');
            $query->whereIn('product_id', $sellerProductIds);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

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
     * Update digital delivery note and notify customer via email & chat.
     */
    public function updateDigitalNote(Request $request, TransactionItem $item): RedirectResponse
    {
        $request->validate(['note' => 'required|string|max:5000']);

        $item->update(['note' => $request->note]);

        $transaction = $item->transaction;
        if ($transaction && $transaction->user) {
            try {
                $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
                Mail::to($transaction->user->email)->queue(new DigitalProductDelivered($transaction, $item, $storeName));

                $chat = Chat::where('user_id', $transaction->user_id)
                    ->where('product_id', $item->product_id)
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
     * Update item note (digital product delivery note) from route binding.
     */
    public function updateItemNote(Request $request, Transaction $transaction, TransactionItem $item): RedirectResponse
    {
        $request->validate(['note' => 'required|string|max:5000']);

        $item->update(['note' => $request->note]);

        if ($transaction->user_id) {
            $chat = Chat::firstOrCreate([
                'user_id' => $transaction->user_id,
                'product_id' => $item->product_id,
            ]);

            ChatMessage::create([
                'chat_id' => $chat->id,
                'sender_type' => 'admin',
                'sender_id' => $request->user()?->id ?? $transaction->user_id,
                'body' => "Informasi Pengiriman untuk {$item->product_name} (Pesanan #{$transaction->transaction_number}):\n{$request->note}",
                'is_read' => false,
            ]);
        }

        return back()->with('success', 'Catatan produk berhasil diperbarui.');
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
        } elseif (Str::isUuid($transactionId)) {
            $transaction = Transaction::findOrFail($transactionId);
        } else {
            $transaction = Transaction::where('booking_code', $transactionId)
                ->orWhere('transaction_number', $transactionId)
                ->firstOrFail();
        }

        $transaction->load([
            'customerAddress',
            'paymentMethod',
            'items',
        ]);

        $settings = Setting::whereIn('key', ['store_name', 'store_logo', 'store_phone', 'store_email', 'store_website', 'store_url', 'address', 'regency_name'])
            ->pluck('value', 'key');

        $storeName = $settings->get('store_name') ?? config('app.name');
        $storeLogo = $settings->get('store_logo');
        $storePhone = $settings->get('store_phone') ?? '-';
        $storeAddress = $settings->get('address') ?? 'Gudang Utama BIZMATE';
        $storeCity = $settings->get('regency_name') ?? 'DKI Jakarta';

        $customUrl = $settings->get('store_website') ?? $settings->get('store_url');
        if (! empty($customUrl)) {
            $storeUrl = preg_replace('#^https?://#', '', rtrim($customUrl, '/'));
        } else {
            $host = parse_url(config('app.url'), PHP_URL_HOST);
            if (! empty($host) && ! in_array($host, ['localhost', '127.0.0.1'])) {
                $storeUrl = $host;
            } else {
                $email = $settings->get('store_email');
                $emailDomain = $email ? substr(strrchr($email, '@'), 1) : null;
                if ($emailDomain && ! in_array($emailDomain, ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'])) {
                    $storeUrl = 'www.'.$emailDomain;
                } else {
                    $storeUrl = request()->getHttpHost();
                }
            }
        }

        return view('print.shipping-label', compact(
            'transaction',
            'storeName',
            'storeLogo',
            'storePhone',
            'storeAddress',
            'storeCity',
            'storeUrl'
        ));
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
}
