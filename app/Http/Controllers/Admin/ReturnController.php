<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReturnRequest\ApproveReturnAction;
use App\Actions\ReturnRequest\ConfirmReceiptAction;
use App\Actions\ReturnRequest\ProcessRefundAction;
use App\Actions\ReturnRequest\ProcessReplacementAction;
use App\Actions\ReturnRequest\RejectReturnAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReturnRequest\ApproveReturnRequest;
use App\Http\Requests\Admin\ReturnRequest\ConfirmReceiptRequest;
use App\Http\Requests\Admin\ReturnRequest\ProcessRefundRequest;
use App\Http\Requests\Admin\ReturnRequest\ProcessReplacementRequest;
use App\Http\Requests\Admin\ReturnRequest\RejectReturnRequest;
use App\Models\Notification;
use App\Models\ProductStock;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
{
    public function __construct(
        protected ApproveReturnAction $approveReturnAction,
        protected RejectReturnAction $rejectReturnAction,
        protected ConfirmReceiptAction $confirmReceiptAction,
        protected ProcessRefundAction $processRefundAction,
        protected ProcessReplacementAction $processReplacementAction
    ) {}

    /**
     * Display a listing of return requests.
     */
    public function index(Request $request): Response
    {
        $driver = DB::connection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = DB::table('returns')
            ->leftJoin('users', 'returns.user_id', '=', 'users.id')
            ->leftJoin('transactions', 'returns.transaction_id', '=', 'transactions.id')
            ->select([
                'returns.id',
                'returns.return_number',
                'returns.user_id',
                'returns.transaction_id',
                'returns.type',
                'returns.reason',
                'returns.status',
                'returns.refund_amount',
                'returns.created_at',
                'users.name as user_name',
                'users.email as user_email',
                'transactions.transaction_number as transaction_number',
                'transactions.grand_total as transaction_grand_total',
            ])
            ->orderBy('returns.created_at', 'desc');

        $user = $request->user();
        $isSeller = $user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin']);
        if ($isSeller) {
            $sellerProductIds = DB::table('products')->where('user_id', $user->id)->pluck('id');
            $sellerTxIds = DB::table('transaction_items')->whereIn('product_id', $sellerProductIds)->pluck('transaction_id');
            $query->whereIn('returns.transaction_id', $sellerTxIds);
        }

        if ($request->filled('status')) {
            $query->where('returns.status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('returns.type', $request->type);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('returns.return_number', $likeOperator, "{$search}%")
                    ->orWhere('transactions.transaction_number', $likeOperator, "{$search}%")
                    ->orWhere('users.name', $likeOperator, "%{$search}%")
                    ->orWhere('users.email', $likeOperator, "%{$search}%");
            });
        }

        $rawPage = $request->input('page', 1);
        $cleanPage = (int) preg_replace('/\D/', '', (string) $rawPage) ?: 1;
        $page = max(1, $cleanPage);
        $perPage = 10;

        $getReturns = function () use ($request, $query, $page, $perPage, $isSeller) {
            $isFiltered = $request->filled('status') || $request->filled('type') || $request->filled('search');

            if (! $isFiltered && ! $isSeller && ! app()->runningUnitTests()) {
                $total = Cache::remember('returns_total_count', 120, fn () => DB::table('returns')->count());
            } else {
                $total = (clone $query)->count();
            }

            $rawReturns = $query->forPage($page, $perPage)->get();

            $formattedItems = $rawReturns->map(fn ($r) => [
                'id' => $r->id,
                'return_number' => $r->return_number,
                'user_id' => $r->user_id,
                'transaction_id' => $r->transaction_id,
                'type' => $r->type,
                'reason' => $r->reason,
                'status' => $r->status,
                'refund_amount' => (float) $r->refund_amount,
                'created_at' => $r->created_at,
                'created_at_formatted' => $r->created_at ? Carbon::parse($r->created_at)->translatedFormat('d M Y H:i') : '—',
                'user' => $r->user_id ? [
                    'id' => $r->user_id,
                    'name' => $r->user_name,
                    'email' => $r->user_email,
                ] : null,
                'transaction' => $r->transaction_id ? [
                    'id' => $r->transaction_id,
                    'transaction_number' => $r->transaction_number,
                    'grand_total' => (float) $r->transaction_grand_total,
                ] : null,
            ]);

            return new LengthAwarePaginator(
                $formattedItems,
                $total,
                $perPage,
                $page,
                ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
            );
        };

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Admin/Returns/Index', [
            'returns' => app()->runningUnitTests() ? $getReturns() : Inertia::defer(fn () => $getReturns()),
            'statusLabels' => ReturnRequest::statusLabels(),
            'filters' => $request->only(['status', 'type', 'search']),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Show a single return request detail.
     */
    public function show(Request $request, ReturnRequest $return): Response
    {
        $this->authorizeSellerReturn($request, $return);

        $return->load([
            'user:id,name,email',
            'user.customerBankAccounts',
            'transaction:id,transaction_number,grand_total,shipping_fee,status,user_id,admin_fee,application_fee',
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
    public function approve(ApproveReturnRequest $request, ReturnRequest $return): RedirectResponse
    {
        $this->authorizeSellerReturn($request, $return);

        $result = $this->approveReturnAction->execute($return, $request->input('notes_admin'), $request->user());

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Pengajuan retur berhasil disetujui.');
    }

    /**
     * Reject a return request.
     */
    public function reject(RejectReturnRequest $request, ReturnRequest $return): RedirectResponse
    {
        $result = $this->rejectReturnAction->execute($return, $request->input('notes_admin'));

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Pengajuan retur berhasil ditolak.');
    }

    /**
     * Admin inputs the customer's return tracking number.
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
    public function confirmReceipt(ConfirmReceiptRequest $request, ReturnRequest $return): RedirectResponse
    {
        $stockAction = $request->input('stock_action', 'active');
        $result = $this->confirmReceiptAction->execute($return, $stockAction, $request->user());

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Penerimaan barang retur berhasil dikonfirmasi. Stok produk telah dikembalikan.');
    }

    /**
     * Process refund (for type = refund).
     */
    public function processRefund(ProcessRefundRequest $request, ReturnRequest $return): RedirectResponse
    {
        $result = $this->processRefundAction->execute($return, $request->input('notes_admin'));

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Pengembalian dana berhasil diproses.');
    }

    /**
     * Process replacement goods delivery (for type = tukar_barang).
     */
    public function processReplacement(ProcessReplacementRequest $request, ReturnRequest $return): RedirectResponse
    {
        $result = $this->processReplacementAction->execute($return, $request->validated(), $request->user());

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Barang pengganti berhasil dibuat dan dikirim.');
    }

    /**
     * Bulk confirm receipt of returned items.
     */
    public function bulkConfirmReceipt(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:returns,id',
            'stock_action' => 'required|in:active,damaged',
        ]);

        $returns = ReturnRequest::with(['transaction', 'items'])
            ->whereIn('id', $request->ids)
            ->where('status', 'barang_dikirim_customer')
            ->get();

        if ($returns->isEmpty()) {
            return back()->with('error', 'Tidak ada pengajuan retur layak yang dipilih.');
        }

        $stockAction = $request->stock_action;
        $count = 0;

        foreach ($returns as $return) {
            $return->update([
                'status' => 'barang_diterima_toko',
                'received_by' => $request->user()->id,
                'received_at' => now(),
            ]);

            $return->transaction->update(['return_status' => 'barang_diterima_toko']);

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

            $count++;
        }

        return back()->with('success', "Berhasil mengonfirmasi penerimaan {$count} barang retur.");
    }

    /**
     * Bulk approve multiple return requests.
     */
    public function bulkApprove(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:returns,id',
            'notes_admin' => 'nullable|string|max:500',
        ]);

        $returns = ReturnRequest::with('transaction')
            ->whereIn('id', $request->ids)
            ->where('status', 'menunggu_review')
            ->get();

        if ($returns->isEmpty()) {
            return back()->with('error', 'Tidak ada pengajuan retur layak disetujui yang dipilih.');
        }

        $count = 0;
        foreach ($returns as $return) {
            $return->update([
                'status' => 'disetujui',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'notes_admin' => $request->notes_admin,
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
            $count++;
        }

        return back()->with('success', "Berhasil menyetujui {$count} pengajuan retur.");
    }

    /**
     * Update replacement shipment tracking info.
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

        if ($return->replacement_transaction_id) {
            $return->replacementTransaction->update([
                'tracking_number' => $request->replacement_tracking_number,
                'courier_name' => $request->replacement_courier_name,
                'status' => 'dikirim',
            ]);
        }

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
     * Mark refund as fully completed.
     */
    public function completeRefund(Request $request, ReturnRequest $return): RedirectResponse
    {
        if ($return->status !== 'refund_diproses') {
            return back()->with('error', 'Status tidak valid untuk menyelesaikan refund.');
        }

        $return->update(['status' => 'selesai']);
        $return->transaction->update(['return_status' => 'selesai']);

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

    /**
     * Authorize seller access to return request.
     */
    private function authorizeSellerReturn(Request $request, ReturnRequest $return): void
    {
        $user = $request->user();
        if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
            $sellerProductIds = DB::table('products')->where('user_id', $user->id)->pluck('id');
            $hasProduct = $return->transaction->items()->whereIn('product_id', $sellerProductIds)->exists();
            if (! $hasProduct) {
                abort(403, 'Anda tidak memiliki akses ke pengajuan retur ini.');
            }
        }
    }
}
