<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RefundRequest;
use App\Models\ReturnRequest;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin dashboard with real-time statistics.
     */
    public function index(Request $request): Response
    {
        @set_time_limit(300);

        $filter = $request->input('filter', '7_hari');

        switch ($filter) {
            case 'today':
            case 'hari_ini':
                $dateFrom = Carbon::today();
                $dateTo = Carbon::now()->endOfDay();
                break;
            case 'last_7_days':
            case '7_hari':
                $dateFrom = Carbon::now()->subDays(6)->startOfDay();
                $dateTo = Carbon::now()->endOfDay();
                break;
            case 'this_year':
            case '1_tahun':
                $dateFrom = Carbon::now()->subYear()->startOfDay();
                $dateTo = Carbon::now()->endOfDay();
                break;
            case 'last_year':
            case 'tahun_lalu':
                $dateFrom = Carbon::now()->subYear()->startOfYear();
                $dateTo = Carbon::now()->subYear()->endOfYear();
                break;
            case 'last_30_days':
            case '1_bulan':
            default:
                $dateFrom = Carbon::now()->subDays(29)->startOfDay();
                $dateTo = Carbon::now()->endOfDay();
                break;
        }

        // Calculate previous period for comparison
        $periodDuration = $dateTo->diffInSeconds($dateFrom);
        $prevDateFrom = $dateFrom->copy()->subSeconds($periodDuration + 1);
        $prevDateTo = $dateFrom->copy()->subSecond();

        $paidStatuses = Transaction::PAID_STATUSES;
        $driver = DB::connection()->getDriverName();
        $monthFormatTx = $driver === 'sqlite' ? "strftime('%Y-%m', transactions.created_at)" : "TO_CHAR(transactions.created_at, 'YYYY-MM')";
        $monthFormatSelf = $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "TO_CHAR(created_at, 'YYYY-MM')";
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();

        $user = $request->user();
        $isSeller = $user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin']);
        $isAdminOrSuperAdmin = $user && $user->hasAnyRole(['Super Admin', 'Admin']);
        $userId = $user ? $user->id : 'guest';

        $sellerProductIds = $isSeller ? DB::table('products')->where('user_id', $userId)->pluck('id') : collect([]);
        $sellerTransactionIds = $isSeller ? DB::table('transaction_items')->whereIn('product_id', $sellerProductIds)->pluck('transaction_id') : collect([]);

        // Global search & filter variables
        $search = trim((string) $request->input('search', ''));
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

        // Cache key per filter & user — invalidasi otomatis setiap 5 menit
        $getKpis = function () use (
            $filter,
            $dateFrom,
            $dateTo,
            $prevDateFrom,
            $prevDateTo,
            $paidStatuses,
            $isSeller,
            $userId,
            $sellerProductIds,
            $sellerTransactionIds
        ) {
            return Cache::remember("dashboard_kpis_v4_{$userId}_{$filter}", 3600, function () use (
                $dateFrom,
                $dateTo,
                $prevDateFrom,
                $prevDateTo,
                $paidStatuses,
                $isSeller,
                $userId,
                $sellerProductIds,
                $sellerTransactionIds
            ) {
                $usePreAggregated = ! $isSeller && ! app()->runningUnitTests() && DB::table('dashboard_daily_summaries')->exists();

                if ($usePreAggregated) {
                    $startDateStr = Carbon::parse($dateFrom)->toDateString();
                    $endDateStr = Carbon::parse($dateTo)->toDateString();
                    $prevStartDateStr = Carbon::parse($prevDateFrom)->toDateString();
                    $prevEndDateStr = Carbon::parse($prevDateTo)->toDateString();

                    $currentSummary = DB::table('dashboard_daily_summaries')
                        ->whereBetween('date', [$startDateStr, $endDateStr])
                        ->selectRaw('SUM(revenue) as revenue, SUM(paid_orders_count) as paid_orders, SUM(orders_count) as total_orders, SUM(refunds_amount) as refunds_amount, SUM(refunds_count) as refunds_count, SUM(returns_amount) as returns_amount, SUM(returns_count) as returns_count')
                        ->first();

                    $previousSummary = DB::table('dashboard_daily_summaries')
                        ->whereBetween('date', [$prevStartDateStr, $prevEndDateStr])
                        ->selectRaw('SUM(revenue) as revenue, SUM(paid_orders_count) as paid_orders, SUM(orders_count) as total_orders, SUM(refunds_amount) as refunds_amount, SUM(refunds_count) as refunds_count, SUM(returns_amount) as returns_amount, SUM(returns_count) as returns_count')
                        ->first();

                    $currentRevenue = (float) ($currentSummary->revenue ?? 0);
                    $previousRevenue = (float) ($previousSummary->revenue ?? 0);
                    $currentOrders = (int) ($currentSummary->paid_orders ?? 0);
                    $previousOrders = (int) ($previousSummary->paid_orders ?? 0);

                    $currentRefundAmount = (float) ($currentSummary->refunds_amount ?? 0);
                    $prevRefundAmount = (float) ($previousSummary->refunds_amount ?? 0);
                    $currentRefundCount = (int) ($currentSummary->refunds_count ?? 0);
                    $prevRefundCount = (int) ($previousSummary->refunds_count ?? 0);

                    $currentReturnAmount = (float) ($currentSummary->returns_amount ?? 0);
                    $prevReturnAmount = (float) ($previousSummary->returns_amount ?? 0);
                    $currentReturnCount = (int) ($currentSummary->returns_count ?? 0);
                    $prevReturnCount = (int) ($previousSummary->returns_count ?? 0);
                } else {
                    if ($isSeller) {
                        $currentAgg = DB::table('transaction_items')
                            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                            ->whereIn('transactions.status', $paidStatuses)
                            ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
                            ->whereIn('transaction_items.product_id', $sellerProductIds)
                            ->selectRaw('SUM(transaction_items.subtotal) as revenue, COUNT(DISTINCT transactions.id) as orders')
                            ->first();

                        $previousAgg = DB::table('transaction_items')
                            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                            ->whereIn('transactions.status', $paidStatuses)
                            ->whereBetween('transactions.created_at', [$prevDateFrom, $prevDateTo])
                            ->whereIn('transaction_items.product_id', $sellerProductIds)
                            ->selectRaw('SUM(transaction_items.subtotal) as revenue, COUNT(DISTINCT transactions.id) as orders')
                            ->first();
                    } else {
                        $currentAgg = DB::table('transactions')->whereIn('status', $paidStatuses)
                            ->whereBetween('created_at', [$dateFrom, $dateTo])
                            ->selectRaw('SUM(grand_total) as revenue, COUNT(id) as orders')
                            ->first();

                        $previousAgg = DB::table('transactions')->whereIn('status', $paidStatuses)
                            ->whereBetween('created_at', [$prevDateFrom, $prevDateTo])
                            ->selectRaw('SUM(grand_total) as revenue, COUNT(id) as orders')
                            ->first();
                    }

                    $currentRevenue = (float) ($currentAgg->revenue ?? 0);
                    $previousRevenue = (float) ($previousAgg->revenue ?? 0);
                    $currentOrders = (int) ($currentAgg->orders ?? 0);
                    $previousOrders = (int) ($previousAgg->orders ?? 0);

                    $currentRefundQuery = DB::table('refund_requests')->whereBetween('created_at', [$dateFrom, $dateTo]);
                    $prevRefundQuery = DB::table('refund_requests')->whereBetween('created_at', [$prevDateFrom, $prevDateTo]);

                    $currentReturnQuery = DB::table('returns')->whereBetween('created_at', [$dateFrom, $dateTo]);
                    $prevReturnQuery = DB::table('returns')->whereBetween('created_at', [$prevDateFrom, $prevDateTo]);

                    if ($isSeller) {
                        $currentRefundQuery->whereIn('transaction_id', $sellerTransactionIds);
                        $prevRefundQuery->whereIn('transaction_id', $sellerTransactionIds);
                        $currentReturnQuery->whereIn('transaction_id', $sellerTransactionIds);
                        $prevReturnQuery->whereIn('transaction_id', $sellerTransactionIds);
                    }

                    $currentRefundAgg = $currentRefundQuery->selectRaw('SUM(refund_amount) as total_amount, COUNT(id) as total_count')->first();
                    $prevRefundAgg = $prevRefundQuery->selectRaw('SUM(refund_amount) as total_amount, COUNT(id) as total_count')->first();

                    $currentRefundAmount = (float) ($currentRefundAgg->total_amount ?? 0);
                    $prevRefundAmount = (float) ($prevRefundAgg->total_amount ?? 0);
                    $currentRefundCount = (int) ($currentRefundAgg->total_count ?? 0);
                    $prevRefundCount = (int) ($prevRefundAgg->total_count ?? 0);

                    $currentReturnAgg = $currentReturnQuery->selectRaw('SUM(refund_amount) as total_amount, COUNT(id) as total_count')->first();
                    $prevReturnAgg = $prevReturnQuery->selectRaw('SUM(refund_amount) as total_amount, COUNT(id) as total_count')->first();

                    $currentReturnAmount = (float) ($currentReturnAgg->total_amount ?? 0);
                    $prevReturnAmount = (float) ($prevReturnAgg->total_amount ?? 0);
                    $currentReturnCount = (int) ($currentReturnAgg->total_count ?? 0);
                    $prevReturnCount = (int) ($prevReturnAgg->total_count ?? 0);
                }

                $productQuery = DB::table('products');
                if ($isSeller) {
                    $productQuery->where('user_id', $userId);
                }
                $productAgg = $productQuery->selectRaw(
                    'COUNT(CASE WHEN active = true THEN 1 END) as current_active,
                     COUNT(CASE WHEN active = true AND created_at < ? THEN 1 END) as previous_active',
                    [$dateFrom]
                )->first();

                if ($isSeller) {
                    $currentCustomers = DB::table('transactions')
                        ->whereIn('id', $sellerTransactionIds)
                        ->distinct('user_id')
                        ->count('user_id');
                    $previousCustomers = DB::table('transactions')
                        ->whereIn('id', $sellerTransactionIds)
                        ->where('created_at', '<', $dateFrom)
                        ->distinct('user_id')
                        ->count('user_id');
                } else {
                    $customerAgg = DB::table('users')
                        ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                        ->where('roles.name', 'Customer')
                        ->selectRaw(
                            'COUNT(*) as current_total,
                             COUNT(CASE WHEN users.created_at < ? THEN 1 END) as previous_total',
                            [$dateFrom]
                        )->first();
                    $currentCustomers = (int) ($customerAgg->current_total ?? 0);
                    $previousCustomers = (int) ($customerAgg->previous_total ?? 0);
                }

                $currentActiveProducts = (int) ($productAgg->current_active ?? 0);
                $previousActiveProducts = (int) ($productAgg->previous_active ?? 0);

                return [
                    'stats' => [
                        'revenueFormatted' => $this->formatRupiah($currentRevenue),
                        'revenueChange' => $this->getPercentageChange($currentRevenue, $previousRevenue),
                        'ordersCount' => $currentOrders,
                        'ordersChange' => $this->getPercentageChange((float) $currentOrders, (float) $previousOrders),
                        'activeProductsCount' => $currentActiveProducts,
                        'productsChange' => $this->getPercentageChange((float) $currentActiveProducts, (float) $previousActiveProducts),
                        'customersCount' => $currentCustomers,
                        'customersChange' => $this->getPercentageChange((float) $currentCustomers, (float) $previousCustomers),
                    ],
                    'refundStats' => [
                        'count' => $currentRefundCount,
                        'totalAmount' => $currentRefundAmount,
                        'formattedAmount' => $this->formatRupiah($currentRefundAmount),
                        'countChange' => $this->getPercentageChange((float) $currentRefundCount, (float) $prevRefundCount),
                        'amountChange' => $this->getPercentageChange($currentRefundAmount, $prevRefundAmount),
                    ],
                    'returnStats' => [
                        'count' => $currentReturnCount,
                        'totalAmount' => $currentReturnAmount,
                        'formattedAmount' => $this->formatRupiah($currentReturnAmount),
                        'countChange' => $this->getPercentageChange((float) $currentReturnCount, (float) $prevReturnCount),
                        'amountChange' => $this->getPercentageChange($currentReturnAmount, $prevReturnAmount),
                    ],
                ];
            });
        };

        $getPipeline = function () use ($isSeller, $userId, $sellerTransactionIds) {
            return Cache::remember("dashboard_pipeline_v3_{$userId}", 300, function () use ($isSeller, $sellerTransactionIds) {
                $txQuery = DB::table('transactions');
                if ($isSeller) {
                    $txQuery->whereIn('id', $sellerTransactionIds);
                }
                $orderStatusCounts = $txQuery->selectRaw("
                        COUNT(CASE WHEN status = 'belum_bayar' THEN 1 END) as pending_payment,
                        COUNT(CASE WHEN status = 'menunggu' THEN 1 END) as waiting_confirm,
                        COUNT(CASE WHEN status = 'diproses' THEN 1 END) as processing,
                        COUNT(CASE WHEN status = 'dikemas' THEN 1 END) as packing,
                        COUNT(CASE WHEN status = 'dikirim' THEN 1 END) as shipping,
                        COUNT(CASE WHEN status = 'selesai' THEN 1 END) as completed,
                        COUNT(CASE WHEN status = 'dibatalkan' THEN 1 END) as cancelled
                    ")->first();

                $rfQuery = DB::table('refund_requests');
                if ($isSeller) {
                    $rfQuery->whereIn('transaction_id', $sellerTransactionIds);
                }
                $refundStatusCounts = $rfQuery->selectRaw("
                        COUNT(CASE WHEN status = 'menunggu_konfirmasi' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN status = 'disetujui' THEN 1 END) as approved_count,
                        COUNT(CASE WHEN status = 'selesai' THEN 1 END) as completed_count,
                        COUNT(CASE WHEN status = 'ditolak' THEN 1 END) as rejected_count
                    ")->first();

                $rtQuery = DB::table('returns');
                if ($isSeller) {
                    $rtQuery->whereIn('transaction_id', $sellerTransactionIds);
                }
                $returnStatusCounts = $rtQuery->selectRaw("
                        COUNT(CASE WHEN status = 'menunggu_review' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN status = 'disetujui' THEN 1 END) as approved_count,
                        COUNT(CASE WHEN status = 'dikirim' THEN 1 END) as in_transit_count,
                        COUNT(CASE WHEN status = 'diterima' THEN 1 END) as received_count,
                        COUNT(CASE WHEN status = 'refund_diproses' THEN 1 END) as refunding_count,
                        COUNT(CASE WHEN status = 'selesai' THEN 1 END) as completed_count,
                        COUNT(CASE WHEN status = 'ditolak' THEN 1 END) as rejected_count
                    ")->first();

                return [
                    'orderStats' => [
                        'pendingPayment' => (int) ($orderStatusCounts->pending_payment ?? 0),
                        'waitingConfirm' => (int) ($orderStatusCounts->waiting_confirm ?? 0),
                        'processing' => (int) ($orderStatusCounts->processing ?? 0),
                        'packing' => (int) ($orderStatusCounts->packing ?? 0),
                        'shipping' => (int) ($orderStatusCounts->shipping ?? 0),
                        'completed' => (int) ($orderStatusCounts->completed ?? 0),
                        'cancelled' => (int) ($orderStatusCounts->cancelled ?? 0),
                    ],
                    'refundPipeline' => [
                        'pending' => (int) ($refundStatusCounts->pending_count ?? 0),
                        'approved' => (int) ($refundStatusCounts->approved_count ?? 0),
                        'completed' => (int) ($refundStatusCounts->completed_count ?? 0),
                        'rejected' => (int) ($refundStatusCounts->rejected_count ?? 0),
                    ],
                    'returnPipeline' => [
                        'pending' => (int) ($returnStatusCounts->pending_count ?? 0),
                        'approved' => (int) ($returnStatusCounts->approved_count ?? 0),
                        'inTransit' => (int) ($returnStatusCounts->in_transit_count ?? 0),
                        'received' => (int) ($returnStatusCounts->received_count ?? 0),
                        'refunding' => (int) ($returnStatusCounts->refunding_count ?? 0),
                        'completed' => (int) ($returnStatusCounts->completed_count ?? 0),
                        'rejected' => (int) ($returnStatusCounts->rejected_count ?? 0),
                    ],
                ];
            });
        };

        $getRecentOrders = function () use ($isSeller, $userId, $sellerTransactionIds, $filter, $dateFrom, $dateTo, $search, $likeOperator) {
            return Cache::remember("dashboard_recent_orders_v4_{$userId}_{$filter}_".md5((string) $search), 60, function () use ($isSeller, $sellerTransactionIds, $dateFrom, $dateTo, $search, $likeOperator) {
                $query = DB::table('transactions')
                    ->leftJoin('users', 'transactions.user_id', '=', 'users.id')
                    ->whereBetween('transactions.created_at', [$dateFrom, $dateTo]);
                if ($isSeller) {
                    $query->whereIn('transactions.id', $sellerTransactionIds);
                }
                if ($search !== '') {
                    $query->where(function ($q) use ($search, $likeOperator) {
                        $q->where('transactions.transaction_number', $likeOperator, "%{$search}%")
                            ->orWhere('users.name', $likeOperator, "%{$search}%")
                            ->orWhere('users.email', $likeOperator, "%{$search}%");
                    });
                }
                $recentTransactions = $query->select([
                    'transactions.id',
                    'transactions.transaction_number',
                    'transactions.grand_total',
                    'transactions.status',
                    'transactions.created_at',
                    'users.name as user_name',
                    'users.email as user_email',
                ])
                    ->orderBy('transactions.created_at', 'desc')
                    ->limit(5)
                    ->get();

                return $recentTransactions->map(function ($transaction) {
                    $customerName = $transaction->user_name ?? 'Guest';
                    $customerEmail = $transaction->user_email ?? 'guest@email.com';

                    $initials = collect(explode(' ', $customerName))
                        ->map(fn ($n) => strtoupper(substr($n, 0, 1)))
                        ->take(2)
                        ->implode('');

                    if (empty($initials)) {
                        $initials = 'GS';
                    }

                    $uiStatus = 'Batal';
                    if (in_array($transaction->status, ['selesai', 'diproses', 'dikemas', 'dikirim'])) {
                        $uiStatus = 'Paid';
                    } elseif (in_array($transaction->status, ['belum_bayar', 'menunggu'])) {
                        $uiStatus = 'Pending';
                    }

                    return [
                        'raw_id' => $transaction->id,
                        'id' => '#'.$transaction->transaction_number,
                        'customer' => $customerName,
                        'email' => $customerEmail,
                        'initials' => $initials,
                        'date' => Carbon::parse($transaction->created_at)->translatedFormat('d M Y, H:i'),
                        'amount' => (float) $transaction->grand_total,
                        'status' => $uiStatus,
                    ];
                })->toArray();
            });
        };

        $getRecentRefunds = function () use ($isSeller, $userId, $sellerTransactionIds, $filter, $dateFrom, $dateTo, $search, $likeOperator) {
            return Cache::remember("dashboard_recent_refunds_v4_{$userId}_{$filter}_".md5((string) $search), 60, function () use ($isSeller, $sellerTransactionIds, $dateFrom, $dateTo, $search, $likeOperator) {
                $query = DB::table('refund_requests')
                    ->leftJoin('users', 'refund_requests.user_id', '=', 'users.id')
                    ->leftJoin('transactions', 'refund_requests.transaction_id', '=', 'transactions.id')
                    ->whereBetween('refund_requests.created_at', [$dateFrom, $dateTo]);
                if ($isSeller) {
                    $query->whereIn('refund_requests.transaction_id', $sellerTransactionIds);
                }
                if ($search !== '') {
                    $query->where(function ($q) use ($search, $likeOperator) {
                        $q->where('refund_requests.refund_number', $likeOperator, "%{$search}%")
                            ->orWhere('transactions.transaction_number', $likeOperator, "%{$search}%")
                            ->orWhere('users.name', $likeOperator, "%{$search}%")
                            ->orWhere('users.email', $likeOperator, "%{$search}%");
                    });
                }

                return $query->select([
                    'refund_requests.id',
                    'refund_requests.refund_number',
                    'refund_requests.transaction_id',
                    'refund_requests.refund_amount',
                    'refund_requests.refund_method',
                    'refund_requests.status',
                    'refund_requests.reason',
                    'refund_requests.created_at',
                    'users.email as user_email',
                    'users.name as user_name',
                    'transactions.transaction_number as transaction_number',
                ])
                    ->orderBy('refund_requests.created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($rf) {
                        $customerName = $rf->user_name ?? 'Guest';

                        return [
                            'id' => $rf->id,
                            'refund_number' => $rf->refund_number,
                            'transaction_number' => $rf->transaction_number ?? '—',
                            'transaction_id' => $rf->transaction_id,
                            'customer' => $customerName,
                            'email' => $rf->user_email ?? '',
                            'amount' => (float) $rf->refund_amount,
                            'amount_formatted' => $this->formatRupiah((float) $rf->refund_amount),
                            'method' => $rf->refund_method ?? 'Transfer Bank',
                            'status' => $rf->status,
                            'status_label' => RefundRequest::statusLabels()[$rf->status] ?? $rf->status,
                            'reason' => $rf->reason ?? '-',
                            'date' => Carbon::parse($rf->created_at)->translatedFormat('d M Y, H:i'),
                        ];
                    })->toArray();
            });
        };

        $getRecentReturns = function () use ($isSeller, $userId, $sellerTransactionIds, $filter, $dateFrom, $dateTo, $search, $likeOperator) {
            return Cache::remember("dashboard_recent_returns_v4_{$userId}_{$filter}_".md5((string) $search), 60, function () use ($isSeller, $sellerTransactionIds, $dateFrom, $dateTo, $search, $likeOperator) {
                $query = DB::table('returns')
                    ->leftJoin('users', 'returns.user_id', '=', 'users.id')
                    ->leftJoin('transactions', 'returns.transaction_id', '=', 'transactions.id')
                    ->whereBetween('returns.created_at', [$dateFrom, $dateTo]);
                if ($isSeller) {
                    $query->whereIn('returns.transaction_id', $sellerTransactionIds);
                }
                if ($search !== '') {
                    $query->where(function ($q) use ($search, $likeOperator) {
                        $q->where('returns.return_number', $likeOperator, "%{$search}%")
                            ->orWhere('transactions.transaction_number', $likeOperator, "%{$search}%")
                            ->orWhere('users.name', $likeOperator, "%{$search}%")
                            ->orWhere('users.email', $likeOperator, "%{$search}%");
                    });
                }

                return $query->select([
                    'returns.id',
                    'returns.return_number',
                    'returns.transaction_id',
                    'returns.type',
                    'returns.refund_amount',
                    'returns.status',
                    'returns.reason',
                    'returns.created_at',
                    'users.email as user_email',
                    'users.name as user_name',
                    'transactions.transaction_number as transaction_number',
                ])
                    ->orderBy('returns.created_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($rt) {
                        $customerName = $rt->user_name ?? 'Guest';

                        return [
                            'id' => $rt->id,
                            'return_number' => $rt->return_number,
                            'transaction_number' => $rt->transaction_number ?? '—',
                            'transaction_id' => $rt->transaction_id,
                            'customer' => $customerName,
                            'email' => $rt->user_email ?? '',
                            'type' => $rt->type === 'tukar_barang' ? 'Tukar Barang' : 'Refund',
                            'amount' => (float) $rt->refund_amount,
                            'amount_formatted' => $this->formatRupiah((float) $rt->refund_amount),
                            'status' => $rt->status,
                            'status_label' => ReturnRequest::statusLabels()[$rt->status] ?? $rt->status,
                            'reason' => $rt->reason ?? '-',
                            'date' => Carbon::parse($rt->created_at)->translatedFormat('d M Y, H:i'),
                        ];
                    })->toArray();
            });
        };

        $getRecentCustomers = function () use ($isSeller, $filter, $dateFrom, $dateTo, $search, $likeOperator) {
            if ($isSeller) {
                return [];
            }

            return Cache::remember("dashboard_recent_customers_v4_{$filter}_".md5((string) $search), 300, function () use ($dateFrom, $dateTo, $search, $likeOperator) {
                $query = DB::table('users')
                    ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->where('roles.name', 'Customer')
                    ->whereBetween('users.created_at', [$dateFrom, $dateTo]);

                if ($search !== '') {
                    $query->where(function ($q) use ($search, $likeOperator) {
                        $q->where('users.name', $likeOperator, "%{$search}%")
                            ->orWhere('users.email', $likeOperator, "%{$search}%")
                            ->orWhere('users.phone_number', $likeOperator, "%{$search}%");
                    });
                }

                return $query->select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.phone_number',
                    'users.created_at',
                ])
                    ->orderBy('users.created_at', 'desc')
                    ->limit(8)
                    ->get()
                    ->map(function ($u) {
                        $initials = collect(explode(' ', $u->name))
                            ->map(fn ($n) => strtoupper(substr($n, 0, 1)))
                            ->take(2)
                            ->implode('');

                        return [
                            'id' => $u->id,
                            'name' => $u->name,
                            'email' => $u->email,
                            'phone' => $u->phone_number ?? '—',
                            'initials' => $initials ?: 'C',
                            'date' => Carbon::parse($u->created_at)->translatedFormat('d M Y'),
                        ];
                    })->toArray();
            });
        };

        $getRecentStockOut = function () use ($paidStatuses, $isSeller, $userId, $sellerProductIds, $filter, $dateFrom, $dateTo, $search, $likeOperator) {
            return Cache::remember("dashboard_recent_stockout_v4_{$userId}_{$filter}_".md5((string) $search), 300, function () use ($paidStatuses, $isSeller, $sellerProductIds, $dateFrom, $dateTo, $search, $likeOperator) {
                $stockQuery = DB::table('stock_movements')
                    ->leftJoin('products', 'stock_movements.product_id', '=', 'products.id')
                    ->leftJoin('product_variants', 'stock_movements.product_variant_id', '=', 'product_variants.id')
                    ->leftJoin('transactions', 'stock_movements.transaction_id', '=', 'transactions.id')
                    ->whereBetween('stock_movements.created_at', [$dateFrom, $dateTo]);
                if ($isSeller) {
                    $stockQuery->whereIn('stock_movements.product_id', $sellerProductIds);
                }
                if ($search !== '') {
                    $stockQuery->where(function ($q) use ($search, $likeOperator) {
                        $q->where('products.name', $likeOperator, "%{$search}%")
                            ->orWhere('products.sku', $likeOperator, "%{$search}%")
                            ->orWhere('transactions.transaction_number', $likeOperator, "%{$search}%");
                    });
                }
                $recentStockOut = $stockQuery->select([
                    'stock_movements.id',
                    'stock_movements.quantity',
                    'stock_movements.stock_before',
                    'stock_movements.stock_after',
                    'stock_movements.notes',
                    'stock_movements.created_at',
                    'products.name as product_name',
                    'products.sku as product_sku',
                    'products.image as product_image',
                    'product_variants.sku as variant_sku',
                    'transactions.transaction_number',
                    'transactions.id as transaction_id',
                ])
                    ->where('stock_movements.type', 'keluar')
                    ->orderBy('stock_movements.created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($mov) {
                        $imageUrl = null;
                        if ($mov->product_image) {
                            $imageUrl = str_starts_with($mov->product_image, 'http') ? $mov->product_image : '/'.ltrim($mov->product_image, '/');
                        }

                        return [
                            'id' => $mov->id,
                            'product_name' => $mov->product_name ?? '-',
                            'product_sku' => $mov->variant_sku ?? $mov->product_sku ?? '-',
                            'image' => $imageUrl,
                            'quantity' => (int) $mov->quantity,
                            'stock_before' => (int) $mov->stock_before,
                            'stock_after' => (int) $mov->stock_after,
                            'transaction_number' => $mov->transaction_number,
                            'transaction_id' => $mov->transaction_id,
                            'notes' => $mov->notes,
                            'date' => Carbon::parse($mov->created_at)->translatedFormat('d M Y, H:i'),
                            'source' => 'movement',
                        ];
                    })
                    ->toArray();

                if (empty($recentStockOut)) {
                    $itemQuery = DB::table('transaction_items')
                        ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                        ->whereIn('transactions.status', $paidStatuses)
                        ->whereBetween('transactions.created_at', [$dateFrom, $dateTo]);
                    if ($isSeller) {
                        $itemQuery->whereIn('transaction_items.product_id', $sellerProductIds);
                    }
                    if ($search !== '') {
                        $itemQuery->where(function ($q) use ($search, $likeOperator) {
                            $q->where('transaction_items.product_name', $likeOperator, "%{$search}%")
                                ->orWhere('transaction_items.product_sku', $likeOperator, "%{$search}%")
                                ->orWhere('transactions.transaction_number', $likeOperator, "%{$search}%");
                        });
                    }
                    $recentStockOut = $itemQuery->selectRaw('
                            transaction_items.id,
                            transaction_items.product_name,
                            transaction_items.product_sku,
                            transaction_items.product_image,
                            transaction_items.quantity,
                            transactions.transaction_number,
                            transactions.id as transaction_id,
                            transactions.created_at
                        ')
                        ->orderBy('transactions.created_at', 'desc')
                        ->limit(10)
                        ->get()
                        ->map(function ($item) {
                            $imageUrl = null;
                            if ($item->product_image) {
                                $imageUrl = str_starts_with($item->product_image, 'http') ? $item->product_image : '/'.ltrim($item->product_image, '/');
                            }

                            return [
                                'id' => $item->id,
                                'product_name' => $item->product_name,
                                'product_sku' => $item->product_sku ?? '-',
                                'image' => $imageUrl,
                                'quantity' => (int) $item->quantity,
                                'stock_before' => 0,
                                'stock_after' => 0,
                                'transaction_number' => $item->transaction_number,
                                'transaction_id' => $item->transaction_id,
                                'notes' => 'Otomatis dari penjualan',
                                'date' => Carbon::parse($item->created_at)->translatedFormat('d M Y, H:i'),
                                'source' => 'transaction_item',
                            ];
                        })
                        ->toArray();
                }

                return $recentStockOut;
            });
        };

        $getTopProducts = function () use ($filter, $dateFrom, $dateTo, $paidStatuses, $isSeller, $userId, $sellerProductIds, $search, $likeOperator) {
            return Cache::remember("dashboard_top_products_v4_{$userId}_{$filter}_".md5((string) $search), 3600, function () use ($dateFrom, $dateTo, $paidStatuses, $isSeller, $sellerProductIds, $search, $likeOperator) {
                $query = DB::table('transaction_items')
                    ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                    ->whereIn('transactions.status', $paidStatuses)
                    ->whereBetween('transactions.created_at', [$dateFrom, $dateTo]);
                if ($isSeller) {
                    $query->whereIn('transaction_items.product_id', $sellerProductIds);
                }
                if ($search !== '') {
                    $query->where('transaction_items.product_name', $likeOperator, "%{$search}%");
                }
                $topProductsRaw = $query->selectRaw('
                        transaction_items.product_id,
                        transaction_items.product_name as name,
                        SUM(transaction_items.quantity) as sales
                    ')
                    ->groupBy('transaction_items.product_id', 'transaction_items.product_name')
                    ->orderBy('sales', 'desc')
                    ->limit(3)
                    ->get();

                $topProductIds = collect($topProductsRaw)->pluck('product_id')->filter()->all();
                $productModels = [];
                $categoriesMap = [];
                if (! empty($topProductIds)) {
                    $productsWithCategories = DB::table('products')
                        ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                        ->whereIn('products.id', $topProductIds)
                        ->select(['products.id', 'products.image', 'categories.name as category_name'])
                        ->get();

                    foreach ($productsWithCategories as $pObj) {
                        $productModels[$pObj->id] = $pObj;
                        $categoriesMap[$pObj->id] = $pObj->category_name ?? 'Tanpa Kategori';
                    }
                }

                $topProducts = [];
                foreach ($topProductsRaw as $item) {
                    $productModel = $productModels[$item->product_id] ?? null;
                    $imageUrl = 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?auto=format&fit=crop&w=150&q=80';
                    if ($productModel && $productModel->image) {
                        $imageUrl = str_starts_with($productModel->image, 'http')
                            ? $productModel->image
                            : '/'.ltrim($productModel->image, '/');
                    }

                    $topProducts[] = [
                        'id' => $item->product_id,
                        'name' => $item->name,
                        'category' => $categoriesMap[$item->product_id] ?? 'Tanpa Kategori',
                        'image' => $imageUrl,
                        'sales' => (int) $item->sales,
                    ];
                }

                return $topProducts;
            });
        };

        $getChartData = function () use ($paidStatuses, $sixMonthsAgo, $monthFormatTx, $monthFormatSelf, $isSeller, $userId, $sellerTransactionIds) {
            return Cache::remember("dashboard_chart_data_v3_{$userId}", 3600, function () use ($paidStatuses, $sixMonthsAgo, $monthFormatTx, $monthFormatSelf, $isSeller, $sellerTransactionIds) {
                $chartLabels = [];
                $chartValues = [];
                $chartRefundValues = [];
                $chartReturnValues = [];

                $txQuery = DB::table('transactions')->whereIn('status', $paidStatuses)
                    ->where('created_at', '>=', $sixMonthsAgo);
                if ($isSeller) {
                    $txQuery->whereIn('id', $sellerTransactionIds);
                }
                $monthlyRevenue = $txQuery->selectRaw("
                        {$monthFormatTx} as month,
                        SUM(grand_total) as revenue
                    ")
                    ->groupBy(DB::raw($monthFormatTx))
                    ->orderBy('month', 'asc')
                    ->get();

                $rfQuery = DB::table('refund_requests')->where('created_at', '>=', $sixMonthsAgo)
                    ->where('status', '!=', 'ditolak');
                if ($isSeller) {
                    $rfQuery->whereIn('transaction_id', $sellerTransactionIds);
                }
                $monthlyRefunds = $rfQuery->selectRaw("
                        {$monthFormatSelf} as month,
                        SUM(refund_amount) as amount
                    ")
                    ->groupBy(DB::raw($monthFormatSelf))
                    ->orderBy('month', 'asc')
                    ->get();

                $rtQuery = DB::table('returns')->where('created_at', '>=', $sixMonthsAgo)
                    ->where('status', '!=', 'ditolak');
                if ($isSeller) {
                    $rtQuery->whereIn('transaction_id', $sellerTransactionIds);
                }
                $monthlyReturns = $rtQuery->selectRaw("
                        {$monthFormatSelf} as month,
                        SUM(refund_amount) as amount,
                        COUNT(id) as total_count
                    ")
                    ->groupBy(DB::raw($monthFormatSelf))
                    ->orderBy('month', 'asc')
                    ->get();

                $revByMonth = collect($monthlyRevenue)->pluck('revenue', 'month');
                $refByMonth = collect($monthlyRefunds)->pluck('amount', 'month');
                $retByMonth = collect($monthlyReturns)->pluck('amount', 'month');

                for ($i = 5; $i >= 0; $i--) {
                    $date = Carbon::now()->subMonths($i);
                    $monthKey = $date->format('Y-m');
                    $monthLabel = $date->translatedFormat('M Y');

                    $revenue = (float) ($revByMonth[$monthKey] ?? 0);
                    $refundAmt = (float) ($refByMonth[$monthKey] ?? 0);
                    $returnAmt = (float) ($retByMonth[$monthKey] ?? 0);

                    $chartLabels[] = $monthLabel;
                    $chartValues[] = round($revenue / 1_000_000, 2);
                    $chartRefundValues[] = round($refundAmt / 1_000_000, 2);
                    $chartReturnValues[] = round($returnAmt / 1_000_000, 2);
                }

                return [
                    'labels' => $chartLabels,
                    'data' => $chartValues,
                    'refunds' => $chartRefundValues,
                    'returns' => $chartReturnValues,
                ];
            });
        };

        $getVisitorStats = function () use ($filter, $dateFrom, $dateTo, $prevDateFrom, $prevDateTo, $isSeller, $userId, $search, $likeOperator) {
            return Cache::remember("dashboard_visitor_stats_v2_{$userId}_{$filter}_".md5((string) $search), 15, function () use ($dateFrom, $dateTo, $prevDateFrom, $prevDateTo, $isSeller, $userId, $search, $likeOperator) {
                $fiveMinutesAgo = Carbon::now()->subMinutes(5);

                // 1. Real-time online visitors (last 5 minutes)
                $onlineQuery = DB::table('page_views')->where('created_at', '>=', $fiveMinutesAgo);
                if ($isSeller) {
                    $onlineQuery->where('seller_id', $userId);
                }
                if ($search !== '') {
                    $onlineQuery->where(function ($q) use ($search, $likeOperator) {
                        $q->where('ip_address', $likeOperator, "%{$search}%")
                            ->orWhere('path', $likeOperator, "%{$search}%");
                    });
                }
                $onlineVisitors = $onlineQuery->distinct('session_id')->count('session_id');

                // 2. Current period pageviews & unique visitors
                $currentQuery = DB::table('page_views')->whereBetween('created_at', [$dateFrom, $dateTo]);
                if ($isSeller) {
                    $currentQuery->where('seller_id', $userId);
                }
                if ($search !== '') {
                    $currentQuery->where(function ($q) use ($search, $likeOperator) {
                        $q->where('ip_address', $likeOperator, "%{$search}%")
                            ->orWhere('path', $likeOperator, "%{$search}%");
                    });
                }
                $currentPageviews = $currentQuery->count();
                $currentUniqueVisitors = $currentQuery->distinct('session_id')->count('session_id');

                // 3. Previous period pageviews & unique visitors
                $prevQuery = DB::table('page_views')->whereBetween('created_at', [$prevDateFrom, $prevDateTo]);
                if ($isSeller) {
                    $prevQuery->where('seller_id', $userId);
                }
                if ($search !== '') {
                    $prevQuery->where(function ($q) use ($search, $likeOperator) {
                        $q->where('ip_address', $likeOperator, "%{$search}%")
                            ->orWhere('path', $likeOperator, "%{$search}%");
                    });
                }
                $prevPageviews = $prevQuery->count();
                $prevUniqueVisitors = $prevQuery->distinct('session_id')->count('session_id');

                // 4. Device breakdown in current period
                $deviceQuery = DB::table('page_views')->whereBetween('created_at', [$dateFrom, $dateTo]);
                if ($isSeller) {
                    $deviceQuery->where('seller_id', $userId);
                }
                if ($search !== '') {
                    $deviceQuery->where(function ($q) use ($search, $likeOperator) {
                        $q->where('ip_address', $likeOperator, "%{$search}%")
                            ->orWhere('path', $likeOperator, "%{$search}%");
                    });
                }
                $devices = $deviceQuery->select('device', DB::raw('COUNT(*) as total'))
                    ->groupBy('device')
                    ->pluck('total', 'device')
                    ->toArray();

                $totalDeviceViews = array_sum($devices);
                $mobilePct = $totalDeviceViews > 0 ? round((($devices['mobile'] ?? 0) / $totalDeviceViews) * 100, 1) : 0;
                $desktopPct = $totalDeviceViews > 0 ? round((($devices['desktop'] ?? 0) / $totalDeviceViews) * 100, 1) : 0;
                $tabletPct = $totalDeviceViews > 0 ? round((($devices['tablet'] ?? 0) / $totalDeviceViews) * 100, 1) : 0;

                return [
                    'onlineVisitors' => $onlineVisitors,
                    'uniqueVisitors' => $currentUniqueVisitors,
                    'uniqueVisitorsChange' => $this->getPercentageChange((float) $currentUniqueVisitors, (float) $prevUniqueVisitors),
                    'pageviewsCount' => $currentPageviews,
                    'pageviewsChange' => $this->getPercentageChange((float) $currentPageviews, (float) $prevPageviews),
                    'devices' => [
                        'mobile' => $mobilePct,
                        'desktop' => $desktopPct,
                        'tablet' => $tabletPct,
                        'mobileCount' => (int) ($devices['mobile'] ?? 0),
                        'desktopCount' => (int) ($devices['desktop'] ?? 0),
                    ],
                ];
            });
        };

        $getTopVisitedPages = function () use ($filter, $dateFrom, $dateTo, $isSeller, $userId, $search, $likeOperator) {
            return Cache::remember("dashboard_top_visited_pages_v2_{$userId}_{$filter}_".md5((string) $search), 60, function () use ($dateFrom, $dateTo, $isSeller, $userId, $search, $likeOperator) {
                $query = DB::table('page_views')
                    ->whereBetween('created_at', [$dateFrom, $dateTo]);

                if ($isSeller) {
                    $query->where('seller_id', $userId);
                }

                if ($search !== '') {
                    $query->where(function ($q) use ($search, $likeOperator) {
                        $q->where('path', $likeOperator, "%{$search}%")
                            ->orWhere('url', $likeOperator, "%{$search}%");
                    });
                }

                $topPages = $query->select('path', DB::raw('COUNT(*) as views'), DB::raw('COUNT(DISTINCT session_id) as unique_views'))
                    ->groupBy('path')
                    ->orderBy('views', 'desc')
                    ->limit(6)
                    ->get();

                $maxViews = $topPages->max('views') ?: 1;

                return $topPages->map(function ($item) use ($maxViews) {
                    $path = $item->path;
                    $title = match (true) {
                        $path === '/' => 'Beranda Utama (Home)',
                        $path === '/search' => 'Pencarian Produk',
                        $path === '/flash-sale' => 'Flash Sale',
                        $path === '/produk-terlaris' => 'Produk Terlaris',
                        str_starts_with($path, '/category') => 'Kategori: '.urldecode(substr($path, 10)),
                        str_starts_with($path, '/brands') => 'Brand: '.urldecode(substr($path, 8)),
                        str_starts_with($path, '/products/') => 'Detail Produk: '.urldecode(substr($path, 10)),
                        default => $path,
                    };

                    return [
                        'path' => $path,
                        'title' => $title,
                        'views' => (int) $item->views,
                        'unique_views' => (int) $item->unique_views,
                        'percentage' => round(($item->views / $maxViews) * 100, 1),
                    ];
                })->toArray();
            });
        };

        $visitorPage = (int) $request->input('visitor_page', 1);

        $getVisitorIpLogs = function () use ($isAdminOrSuperAdmin, $filter, $dateFrom, $dateTo, $search, $likeOperator, $visitorPage) {
            if (! $isAdminOrSuperAdmin) {
                return null;
            }

            return Cache::remember("dashboard_visitor_ip_logs_v3_{$filter}_p{$visitorPage}_".md5((string) $search), 15, function () use ($dateFrom, $dateTo, $search, $likeOperator) {
                $query = DB::table('page_views')
                    ->leftJoin('users', 'page_views.user_id', '=', 'users.id')
                    ->whereBetween('page_views.created_at', [$dateFrom, $dateTo]);

                if ($search !== '') {
                    $query->where(function ($q) use ($search, $likeOperator) {
                        $q->where('page_views.ip_address', $likeOperator, "%{$search}%")
                            ->orWhere('page_views.path', $likeOperator, "%{$search}%")
                            ->orWhere('page_views.url', $likeOperator, "%{$search}%")
                            ->orWhere('page_views.user_agent', $likeOperator, "%{$search}%")
                            ->orWhere('page_views.referer', $likeOperator, "%{$search}%")
                            ->orWhere('page_views.device', $likeOperator, "%{$search}%")
                            ->orWhere('users.name', $likeOperator, "%{$search}%")
                            ->orWhere('users.email', $likeOperator, "%{$search}%");
                    });
                }

                $fiveMinutesAgo = Carbon::now()->subMinutes(5);

                return $query->select([
                    'page_views.id',
                    'page_views.session_id',
                    'page_views.user_id',
                    'page_views.ip_address',
                    'page_views.url',
                    'page_views.path',
                    'page_views.route_name',
                    'page_views.device',
                    'page_views.referer',
                    'page_views.user_agent',
                    'page_views.created_at',
                    'users.name as user_name',
                    'users.email as user_email',
                    'users.avatar as user_avatar',
                ])
                    ->orderBy('page_views.created_at', 'desc')
                    ->paginate(15, ['*'], 'visitor_page')
                    ->withQueryString()
                    ->through(function ($log) use ($fiveMinutesAgo) {
                        $createdAt = Carbon::parse($log->created_at);
                        $path = $log->path;
                        $title = match (true) {
                            $path === '/' => 'Beranda Utama (Home)',
                            $path === '/search' => 'Pencarian Produk',
                            $path === '/flash-sale' => 'Flash Sale',
                            $path === '/produk-terlaris' => 'Produk Terlaris',
                            $path === '/cart' => 'Keranjang Belanja',
                            $path === '/checkout' => 'Halaman Checkout',
                            str_starts_with($path, '/category') => 'Kategori: '.urldecode(substr($path, 10)),
                            str_starts_with($path, '/brands') => 'Brand: '.urldecode(substr($path, 8)),
                            str_starts_with($path, '/products/') => 'Detail Produk: '.urldecode(substr($path, 10)),
                            default => $path,
                        };

                        $browser = $this->parseBrowser($log->user_agent ?? '');
                        $os = $this->parseOs($log->user_agent ?? '');
                        $refererSource = $this->parseRefererSource($log->referer);

                        return [
                            'id' => $log->id,
                            'ip_address' => $log->ip_address ?: '127.0.0.1',
                            'session_id' => $log->session_id,
                            'user_id' => $log->user_id,
                            'user_name' => $log->user_name,
                            'user_email' => $log->user_email,
                            'user_avatar' => $log->user_avatar,
                            'path' => $log->path,
                            'title' => $title,
                            'route_name' => $log->route_name,
                            'device' => $log->device ?: 'desktop',
                            'browser' => $browser,
                            'os' => $os,
                            'referer' => $log->referer,
                            'referer_source' => $refererSource,
                            'user_agent' => $log->user_agent,
                            'created_at' => $createdAt->toISOString(),
                            'time_ago' => $createdAt->diffForHumans(),
                            'formatted_time' => $createdAt->timezone('Asia/Jakarta')->format('d M Y, H:i:s').' WIB',
                            'is_online' => $createdAt->greaterThanOrEqualTo($fiveMinutesAgo),
                        ];
                    });
            });
        };

        $getIpTrafficAnalytics = function () use ($isAdminOrSuperAdmin, $filter, $dateFrom, $dateTo, $search, $likeOperator) {
            if (! $isAdminOrSuperAdmin) {
                return null;
            }

            return Cache::remember("dashboard_ip_analytics_v2_{$filter}_".md5((string) $search), 30, function () use ($dateFrom, $dateTo, $search, $likeOperator) {
                $query = DB::table('page_views')->whereBetween('created_at', [$dateFrom, $dateTo]);

                if ($search !== '') {
                    $query->where(function ($q) use ($search, $likeOperator) {
                        $q->where('ip_address', $likeOperator, "%{$search}%")
                            ->orWhere('path', $likeOperator, "%{$search}%")
                            ->orWhere('url', $likeOperator, "%{$search}%")
                            ->orWhere('user_agent', $likeOperator, "%{$search}%")
                            ->orWhere('referer', $likeOperator, "%{$search}%")
                            ->orWhere('device', $likeOperator, "%{$search}%");
                    });
                }

                $totalUniqueIps = (clone $query)->whereNotNull('ip_address')->distinct('ip_address')->count('ip_address');

                $topIps = (clone $query)
                    ->whereNotNull('ip_address')
                    ->select(
                        'ip_address',
                        DB::raw('COUNT(*) as total_requests'),
                        DB::raw('COUNT(DISTINCT path) as unique_paths'),
                        DB::raw('MAX(created_at) as last_seen_at'),
                        DB::raw('MAX(device) as device')
                    )
                    ->groupBy('ip_address')
                    ->orderByDesc('total_requests')
                    ->limit(8)
                    ->get();

                $fiveMinutesAgo = Carbon::now()->subMinutes(5);

                $formattedTopIps = $topIps->map(function ($item) use ($fiveMinutesAgo) {
                    $lastSeen = Carbon::parse($item->last_seen_at);

                    return [
                        'ip_address' => $item->ip_address,
                        'total_requests' => (int) $item->total_requests,
                        'unique_paths' => (int) $item->unique_paths,
                        'device' => $item->device ?: 'desktop',
                        'last_seen_ago' => $lastSeen->diffForHumans(),
                        'is_online' => $lastSeen->greaterThanOrEqualTo($fiveMinutesAgo),
                    ];
                })->toArray();

                // Referer Breakdown
                $referers = (clone $query)->select('referer', DB::raw('COUNT(*) as total'))
                    ->groupBy('referer')
                    ->get();

                $trafficSources = [
                    'direct' => 0,
                    'google' => 0,
                    'social' => 0,
                    'external' => 0,
                ];

                foreach ($referers as $ref) {
                    $parsed = $this->parseRefererSource($ref->referer);
                    $count = (int) $ref->total;
                    if ($parsed['type'] === 'direct') {
                        $trafficSources['direct'] += $count;
                    } elseif ($parsed['type'] === 'search') {
                        $trafficSources['google'] += $count;
                    } elseif ($parsed['type'] === 'social') {
                        $trafficSources['social'] += $count;
                    } else {
                        $trafficSources['external'] += $count;
                    }
                }

                $totalSources = array_sum($trafficSources);

                return [
                    'total_unique_ips' => $totalUniqueIps,
                    'top_ips' => $formattedTopIps,
                    'traffic_sources' => [
                        'direct' => $totalSources > 0 ? round(($trafficSources['direct'] / $totalSources) * 100, 1) : 0,
                        'google' => $totalSources > 0 ? round(($trafficSources['google'] / $totalSources) * 100, 1) : 0,
                        'social' => $totalSources > 0 ? round(($trafficSources['social'] / $totalSources) * 100, 1) : 0,
                        'external' => $totalSources > 0 ? round(($trafficSources['external'] / $totalSources) * 100, 1) : 0,
                        'direct_count' => $trafficSources['direct'],
                        'google_count' => $trafficSources['google'],
                        'social_count' => $trafficSources['social'],
                        'external_count' => $trafficSources['external'],
                    ],
                ];
            });
        };

        // Product Stock Overview
        $stockQuery = DB::table('products')
            ->leftJoin('product_stocks', function ($join) {
                $join->on('products.id', '=', 'product_stocks.product_id')
                    ->whereNull('product_stocks.product_variant_id');
            })
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->when($isSeller, function ($query) use ($userId) {
                $query->where('products.user_id', $userId);
            })
            ->when($search !== '', function ($query) use ($search, $likeOperator) {
                $query->where(function ($q) use ($search, $likeOperator) {
                    $q->where('products.name', $likeOperator, "%{$search}%")
                        ->orWhere('products.sku', $likeOperator, "%{$search}%");
                });
            })
            ->where('products.active', true);

        $productStockInfo = $stockQuery
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                products.image,
                COALESCE(categories.name, \'Tanpa Kategori\') as category,
                COALESCE(product_stocks.stock, 0) as current_stock,
                COALESCE(product_stocks.min_stock, 0) as min_stock,
                COALESCE(product_stocks.is_unlimited, false) as is_unlimited,
                0 as total_sold
            ')
            ->orderByRaw('COALESCE(product_stocks.stock, 0) ASC')
            ->paginate(10)
            ->withQueryString()
            ->through(function ($item) {
                $imageUrl = null;
                if ($item->image) {
                    $imageUrl = str_starts_with($item->image, 'http')
                        ? $item->image
                        : '/'.ltrim($item->image, '/');
                }

                $stockStatus = 'normal';
                if (! $item->is_unlimited) {
                    if ($item->current_stock <= 0) {
                        $stockStatus = 'habis';
                    } elseif ($item->current_stock <= $item->min_stock) {
                        $stockStatus = 'menipis';
                    }
                }

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'image' => $imageUrl,
                    'category' => $item->category,
                    'current_stock' => (int) $item->current_stock,
                    'min_stock' => (int) $item->min_stock,
                    'is_unlimited' => (bool) $item->is_unlimited,
                    'total_sold' => (int) $item->total_sold,
                    'stock_status' => $stockStatus,
                ];
            });

        $testing = app()->runningUnitTests();
        $getDeferred = function (callable $callback, string $group) use ($testing) {
            if ($testing) {
                return $callback();
            }

            return Inertia::defer($callback, $group);
        };

        return Inertia::render('Admin/Dashboard', [
            'isSeller' => $isSeller,
            'stats' => $getDeferred(fn () => $getKpis()['stats'], 'kpi_stats'),
            'visitorStats' => $getDeferred(fn () => $getVisitorStats(), 'visitor_stats'),
            'topVisitedPages' => $getDeferred(fn () => $getTopVisitedPages(), 'visitor_stats'),
            'orderStats' => $getDeferred(fn () => $getPipeline()['orderStats'], 'pipeline_stats'),
            'recentOrders' => $getDeferred(fn () => $getRecentOrders(), 'recent_orders'),
            'topProducts' => $getDeferred(fn () => $getTopProducts(), 'top_products'),
            'chartData' => $getDeferred(fn () => $getChartData(), 'chart_data'),
            'currentFilter' => $filter,
            'productStockInfo' => $productStockInfo,
            'recentStockOut' => $getDeferred(fn () => $getRecentStockOut(), 'recent_stock_out'),
            'recentCustomers' => $getDeferred(fn () => $getRecentCustomers(), 'recent_customers'),
            'search' => $search,
            'refundStats' => $getDeferred(fn () => $getKpis()['refundStats'], 'kpi_stats'),
            'returnStats' => $getDeferred(fn () => $getKpis()['returnStats'], 'kpi_stats'),
            'refundPipeline' => $getDeferred(fn () => $getPipeline()['refundPipeline'], 'pipeline_stats'),
            'returnPipeline' => $getDeferred(fn () => $getPipeline()['returnPipeline'], 'pipeline_stats'),
            'recentRefunds' => $getDeferred(fn () => $getRecentRefunds(), 'recent_refunds'),
            'recentReturns' => $getDeferred(fn () => $getRecentReturns(), 'recent_returns'),
            'visitorIpLogs' => $isAdminOrSuperAdmin ? $getDeferred(fn () => $getVisitorIpLogs(), 'visitor_stats') : null,
            'ipTrafficAnalytics' => $isAdminOrSuperAdmin ? $getDeferred(fn () => $getIpTrafficAnalytics(), 'visitor_stats') : null,
        ]);
    }

    /**
     * Format a numerical value as Rupiah with optional suffix.
     */
    private function formatRupiah(float $value): string
    {
        if ($value >= 1_000_000_000_000) {
            return 'Rp '.number_format($value / 1_000_000_000_000, 1, ',', '.').' T';
        }
        if ($value >= 1_000_000_000) {
            return 'Rp '.number_format($value / 1_000_000_000, 1, ',', '.').' M';
        }
        if ($value >= 1_000_000) {
            return 'Rp '.number_format($value / 1_000_000, 1, ',', '.').' Jt';
        }

        return 'Rp '.number_format($value, 0, ',', '.');
    }

    /**
     * Calculate percentage change between current and previous values.
     *
     * @return array{value: string, type: 'up'|'down'|'neutral'}
     */
    private function getPercentageChange(float $current, float $previous): array
    {
        if ($previous == 0.0) {
            if ($current > 0.0) {
                return ['value' => '+100%', 'type' => 'up'];
            }

            return ['value' => '0%', 'type' => 'neutral'];
        }

        $change = (($current - $previous) / $previous) * 100;
        $formatted = number_format(abs($change), 1, ',', '.');

        if ($change > 0) {
            return ['value' => '+'.$formatted.'%', 'type' => 'up'];
        } elseif ($change < 0) {
            return ['value' => '-'.$formatted.'%', 'type' => 'down'];
        }

        return ['value' => '0%', 'type' => 'neutral'];
    }

    /**
     * Parse browser name from User Agent string.
     */
    private function parseBrowser(string $userAgent): string
    {
        if (preg_match('/edg/i', $userAgent)) {
            return 'Microsoft Edge';
        }
        if (preg_match('/opr|opera/i', $userAgent)) {
            return 'Opera';
        }
        if (preg_match('/chrome|crios/i', $userAgent)) {
            return 'Google Chrome';
        }
        if (preg_match('/firefox|fxios/i', $userAgent)) {
            return 'Mozilla Firefox';
        }
        if (preg_match('/safari/i', $userAgent)) {
            return 'Apple Safari';
        }
        if (preg_match('/msie|trident/i', $userAgent)) {
            return 'Internet Explorer';
        }

        return 'Browser Lainnya';
    }

    /**
     * Parse Operating System name from User Agent string.
     */
    private function parseOs(string $userAgent): string
    {
        if (preg_match('/windows nt 10/i', $userAgent)) {
            return 'Windows 10/11';
        }
        if (preg_match('/windows nt 6\.3/i', $userAgent)) {
            return 'Windows 8.1';
        }
        if (preg_match('/windows nt 6\.1/i', $userAgent)) {
            return 'Windows 7';
        }
        if (preg_match('/windows/i', $userAgent)) {
            return 'Windows';
        }
        if (preg_match('/iphone/i', $userAgent)) {
            return 'iOS (iPhone)';
        }
        if (preg_match('/ipad/i', $userAgent)) {
            return 'iPadOS';
        }
        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            return 'macOS';
        }
        if (preg_match('/android/i', $userAgent)) {
            return 'Android';
        }
        if (preg_match('/linux/i', $userAgent)) {
            return 'Linux';
        }

        return 'OS Lainnya';
    }

    /**
     * Parse and format referer source.
     *
     * @return array{type: string, label: string, icon: string}
     */
    private function parseRefererSource(?string $referer): array
    {
        if (empty($referer)) {
            return ['type' => 'direct', 'label' => 'Langsung (Direct URL)', 'icon' => 'ti-link'];
        }

        $host = parse_url($referer, PHP_URL_HOST);
        $host = strtolower($host ?? '');

        if (str_contains($host, 'google.')) {
            return ['type' => 'search', 'label' => 'Google Search', 'icon' => 'ti-brand-google'];
        }
        if (str_contains($host, 'instagram.com')) {
            return ['type' => 'social', 'label' => 'Instagram', 'icon' => 'ti-brand-instagram'];
        }
        if (str_contains($host, 'tiktok.com')) {
            return ['type' => 'social', 'label' => 'TikTok', 'icon' => 'ti-brand-tiktok'];
        }
        if (str_contains($host, 'facebook.com') || str_contains($host, 'fb.me')) {
            return ['type' => 'social', 'label' => 'Facebook', 'icon' => 'ti-brand-facebook'];
        }
        if (str_contains($host, 'youtube.com')) {
            return ['type' => 'social', 'label' => 'YouTube', 'icon' => 'ti-brand-youtube'];
        }
        if (str_contains($host, 'twitter.com') || str_contains($host, 'x.com')) {
            return ['type' => 'social', 'label' => 'X / Twitter', 'icon' => 'ti-brand-x'];
        }
        if (str_contains($host, 'whatsapp.com') || str_contains($host, 'wa.me')) {
            return ['type' => 'social', 'label' => 'WhatsApp', 'icon' => 'ti-brand-whatsapp'];
        }

        return ['type' => 'external', 'label' => $host ?: 'Tautan Luar', 'icon' => 'ti-world'];
    }
}
