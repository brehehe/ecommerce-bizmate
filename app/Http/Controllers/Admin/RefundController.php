<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RefundRequest\ApproveRefundAction;
use App\Actions\RefundRequest\RejectRefundAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RefundRequest\ApproveRefundRequest;
use App\Http\Requests\Admin\RefundRequest\RejectRefundRequest;
use App\Models\Notification;
use App\Models\RefundRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RefundController extends Controller
{
    public function __construct(
        protected ApproveRefundAction $approveRefundAction,
        protected RejectRefundAction $rejectRefundAction
    ) {}

    /**
     * Display a listing of cancellation/refund requests.
     */
    public function index(Request $request): InertiaResponse
    {
        $driver = DB::connection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

        $query = DB::table('refund_requests')
            ->leftJoin('users', 'refund_requests.user_id', '=', 'users.id')
            ->leftJoin('transactions', 'refund_requests.transaction_id', '=', 'transactions.id')
            ->select([
                'refund_requests.id',
                'refund_requests.refund_number',
                'refund_requests.user_id',
                'refund_requests.transaction_id',
                'refund_requests.refund_amount as amount',
                'refund_requests.refund_method',
                'refund_requests.status',
                'refund_requests.reason',
                'refund_requests.created_at',
                'users.name as user_name',
                'users.email as user_email',
                'transactions.transaction_number as transaction_number',
                'transactions.grand_total as transaction_grand_total',
                'transactions.status as transaction_status',
            ])
            ->orderBy('refund_requests.created_at', 'desc');

        $user = $request->user();
        $isSeller = $user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin']);
        if ($isSeller) {
            $sellerProductIds = DB::table('products')->where('user_id', $user->id)->pluck('id');
            $sellerTxIds = DB::table('transaction_items')->whereIn('product_id', $sellerProductIds)->pluck('transaction_id');
            $query->whereIn('refund_requests.transaction_id', $sellerTxIds);
        }

        if ($request->filled('status')) {
            $query->where('refund_requests.status', $request->status);
        }

        if ($request->filled('refund_method')) {
            $query->where('refund_requests.refund_method', $request->refund_method);
        }

        if ($request->filled('date_from')) {
            $query->where('refund_requests.created_at', '>=', $request->date_from.' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('refund_requests.created_at', '<=', $request->date_to.' 23:59:59');
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search, $likeOperator) {
                $q->where('refund_requests.refund_number', $likeOperator, "{$search}%")
                    ->orWhere('transactions.transaction_number', $likeOperator, "{$search}%")
                    ->orWhere('users.name', $likeOperator, "%{$search}%")
                    ->orWhere('users.email', $likeOperator, "%{$search}%");
            });
        }

        $rawPage = $request->input('page', 1);
        $cleanPage = (int) preg_replace('/\D/', '', (string) $rawPage) ?: 1;
        $page = max(1, $cleanPage);
        $perPage = 10;

        $getRefunds = function () use ($request, $query, $page, $perPage, $isSeller) {
            $isFiltered = $request->filled('status') || $request->filled('date_from') || $request->filled('date_to') || $request->filled('search');

            if (! $isFiltered && ! $isSeller && ! app()->runningUnitTests()) {
                $total = Cache::remember('refunds_total_count', 120, fn () => DB::table('refund_requests')->count());
            } else {
                $total = (clone $query)->count();
            }

            $rawRefunds = $query->forPage($page, $perPage)->get();

            $formattedItems = $rawRefunds->map(fn ($r) => [
                'id' => $r->id,
                'refund_number' => $r->refund_number,
                'user_id' => $r->user_id,
                'transaction_id' => $r->transaction_id,
                'amount' => (float) $r->amount,
                'refund_method' => $r->refund_method,
                'status' => $r->status,
                'reason' => $r->reason,
                'created_at' => $r->created_at,
                'user' => $r->user_id ? [
                    'id' => $r->user_id,
                    'name' => $r->user_name,
                    'email' => $r->user_email,
                ] : null,
                'transaction' => $r->transaction_id ? [
                    'id' => $r->transaction_id,
                    'transaction_number' => $r->transaction_number,
                    'grand_total' => (float) $r->transaction_grand_total,
                    'status' => $r->transaction_status,
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

        return Inertia::render('Admin/Refunds/Index', [
            'refunds' => app()->runningUnitTests() ? $getRefunds() : Inertia::defer(fn () => $getRefunds()),
            'statusLabels' => RefundRequest::statusLabels(),
            'filters' => $request->only(['status', 'refund_method', 'search']),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Show a specific cancellation and refund request.
     */
    public function show(Request $request, RefundRequest $refund): InertiaResponse
    {
        $this->authorizeSellerRefund($request, $refund);

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
     * Approve a cancellation request.
     */
    public function approve(ApproveRefundRequest $request, RefundRequest $refund): RedirectResponse
    {
        $this->authorizeSellerRefund($request, $refund);

        $result = $this->approveRefundAction->execute($refund, $request->input('notes_admin'), $request->user());

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Pengajuan pembatalan berhasil disetujui.');
    }

    /**
     * Reject a cancellation/refund request.
     */
    public function reject(RejectRefundRequest $request, RefundRequest $refund): RedirectResponse
    {
        $this->authorizeSellerRefund($request, $refund);

        $notes = $request->input('notes_admin') ?? $request->input('admin_notes') ?? $request->input('reject_reason') ?? '';
        $result = $this->rejectRefundAction->execute($refund, $notes, $request->user());

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', 'Pengajuan pembatalan ditolak.');
    }

    /**
     * Mark a bank transfer refund as completed.
     */
    public function completeRefund(Request $request, RefundRequest $refund): RedirectResponse
    {
        $this->authorizeSellerRefund($request, $refund);
        if ($refund->status !== 'disetujui' || $refund->refund_method !== 'transfer') {
            return back()->with('error', 'Status pengajuan tidak valid untuk diselesaikan.');
        }

        $refund->update([
            'status' => 'selesai',
            'refunded_at' => now(),
        ]);

        Notification::create([
            'user_id' => $refund->user_id,
            'title' => 'Refund Berhasil Ditransfer',
            'message' => 'Dana refund sebesar Rp '.number_format((float) $refund->refund_amount, 0, ',', '.').' untuk transaksi #'.$refund->transaction->transaction_number.' telah berhasil ditransfer ke rekening Anda.',
            'type' => 'refund_completed',
            'url' => '/refunds/'.$refund->id,
            'is_read' => false,
        ]);

        return back()->with('success', 'Refund transfer bank berhasil diselesaikan.');
    }

    /**
     * Bulk approve multiple refund / cancellation requests.
     */
    public function bulkApprove(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:refund_requests,id',
            'notes_admin' => 'nullable|string|max:500',
        ]);

        $refunds = RefundRequest::whereIn('id', $request->ids)
            ->where('status', 'menunggu_konfirmasi')
            ->get();

        if ($refunds->isEmpty()) {
            return back()->with('error', 'Tidak ada pengajuan layak disetujui yang dipilih.');
        }

        $count = 0;
        foreach ($refunds as $refund) {
            $result = $this->approveRefundAction->execute($refund, $request->input('notes_admin'), $request->user());
            if ($result['success']) {
                $count++;
            }
        }

        return back()->with('success', "Berhasil menyetujui {$count} pengajuan pembatalan.");
    }

    /**
     * Mark multiple bank transfer refunds as completed.
     */
    public function bulkComplete(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:refund_requests,id',
        ]);

        $refunds = RefundRequest::with('transaction')
            ->whereIn('id', $request->ids)
            ->where('status', 'disetujui')
            ->where('refund_method', 'transfer')
            ->get();

        if ($refunds->isEmpty()) {
            return back()->with('error', 'Tidak ada pengajuan layak diselesaikan yang dipilih.');
        }

        $count = 0;
        foreach ($refunds as $refund) {
            $refund->update([
                'status' => 'selesai',
                'refunded_at' => now(),
            ]);

            try {
                Notification::create([
                    'user_id' => $refund->user_id,
                    'title' => 'Refund Berhasil Ditransfer',
                    'message' => 'Dana refund sebesar Rp '.number_format((float) $refund->refund_amount, 0, ',', '.').' untuk transaksi #'.$refund->transaction->transaction_number.' telah berhasil ditransfer ke rekening Anda.',
                    'type' => 'refund_completed',
                    'url' => '/refunds/'.$refund->id,
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                // Fail silently
            }

            $count++;
        }

        return back()->with('success', "Berhasil menyelesaikan {$count} pengajuan refund.");
    }

    /**
     * Authorize seller access to refund request.
     */
    private function authorizeSellerRefund(Request $request, RefundRequest $refund): void
    {
        $user = $request->user();
        if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
            $sellerProductIds = DB::table('products')->where('user_id', $user->id)->pluck('id');
            $hasProduct = $refund->transaction->items()->whereIn('product_id', $sellerProductIds)->exists();
            if (! $hasProduct) {
                abort(403, 'Anda tidak memiliki akses ke pengajuan pembatalan ini.');
            }
        }
    }
}
