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

        // Cache key per filter — invalidasi otomatis setiap 5 menit
        $getKpis = function () use (
            $filter,
            $dateFrom,
            $dateTo,
            $prevDateFrom,
            $prevDateTo,
            $paidStatuses
        ) {
            return Cache::remember("dashboard_kpis_v2_{$filter}", 3600, function () use (
                $dateFrom,
                $dateTo,
                $prevDateFrom,
                $prevDateTo,
                $paidStatuses
            ) {
                $usePreAggregated = ! app()->runningUnitTests() && DB::table('dashboard_daily_summaries')->exists();

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
                    $currentAgg = DB::table('transactions')->whereIn('status', $paidStatuses)
                        ->whereBetween('created_at', [$dateFrom, $dateTo])
                        ->selectRaw('SUM(grand_total) as revenue, COUNT(id) as orders')
                        ->first();

                    $previousAgg = DB::table('transactions')->whereIn('status', $paidStatuses)
                        ->whereBetween('created_at', [$prevDateFrom, $prevDateTo])
                        ->selectRaw('SUM(grand_total) as revenue, COUNT(id) as orders')
                        ->first();

                    $currentRevenue = (float) ($currentAgg->revenue ?? 0);
                    $previousRevenue = (float) ($previousAgg->revenue ?? 0);
                    $currentOrders = (int) ($currentAgg->orders ?? 0);
                    $previousOrders = (int) ($previousAgg->orders ?? 0);

                    $currentRefundAgg = DB::table('refund_requests')->whereBetween('created_at', [$dateFrom, $dateTo])
                        ->selectRaw('SUM(refund_amount) as total_amount, COUNT(id) as total_count')
                        ->first();

                    $prevRefundAgg = DB::table('refund_requests')->whereBetween('created_at', [$prevDateFrom, $prevDateTo])
                        ->selectRaw('SUM(refund_amount) as total_amount, COUNT(id) as total_count')
                        ->first();

                    $currentRefundAmount = (float) ($currentRefundAgg->total_amount ?? 0);
                    $prevRefundAmount = (float) ($prevRefundAgg->total_amount ?? 0);
                    $currentRefundCount = (int) ($currentRefundAgg->total_count ?? 0);
                    $prevRefundCount = (int) ($prevRefundAgg->total_count ?? 0);

                    $currentReturnAgg = DB::table('returns')->whereBetween('created_at', [$dateFrom, $dateTo])
                        ->selectRaw('SUM(refund_amount) as total_amount, COUNT(id) as total_count')
                        ->first();

                    $prevReturnAgg = DB::table('returns')->whereBetween('created_at', [$prevDateFrom, $prevDateTo])
                        ->selectRaw('SUM(refund_amount) as total_amount, COUNT(id) as total_count')
                        ->first();

                    $currentReturnAmount = (float) ($currentReturnAgg->total_amount ?? 0);
                    $prevReturnAmount = (float) ($prevReturnAgg->total_amount ?? 0);
                    $currentReturnCount = (int) ($currentReturnAgg->total_count ?? 0);
                    $prevReturnCount = (int) ($prevReturnAgg->total_count ?? 0);
                }

                $productAgg = DB::table('products')->selectRaw(
                    'COUNT(CASE WHEN active = true THEN 1 END) as current_active,
                     COUNT(CASE WHEN active = true AND created_at < ? THEN 1 END) as previous_active',
                    [$dateFrom]
                )->first();

                $customerAgg = DB::table('users')
                    ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->where('roles.name', 'Customer')
                    ->selectRaw(
                        'COUNT(*) as current_total,
                         COUNT(CASE WHEN users.created_at < ? THEN 1 END) as previous_total',
                        [$dateFrom]
                    )->first();

                $currentActiveProducts = (int) ($productAgg->current_active ?? 0);
                $previousActiveProducts = (int) ($productAgg->previous_active ?? 0);
                $currentCustomers = (int) ($customerAgg->current_total ?? 0);
                $previousCustomers = (int) ($customerAgg->previous_total ?? 0);

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

        $getPipeline = function () use ($filter, $dateFrom, $dateTo) {
            return Cache::remember("dashboard_pipeline_v2_{$filter}", 3600, function () use ($dateFrom, $dateTo) {
                $opStats = DB::table('transactions')
                    ->whereIn('status', ['belum_bayar', 'menunggu', 'diproses', 'dikemas', 'dikirim'])
                    ->where('created_at', '>=', $dateFrom)
                    ->selectRaw("
                        COUNT(CASE WHEN status = 'belum_bayar' THEN 1 END) as unpaid_count,
                        COUNT(CASE WHEN status = 'menunggu' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN status IN ('belum_bayar', 'menunggu') THEN 1 END) as new_count,
                        COUNT(CASE WHEN status IN ('diproses', 'dikemas') THEN 1 END) as ready_count,
                        COUNT(CASE WHEN status = 'dikirim' THEN 1 END) as shipping_count
                    ")->first();

                $refundStatusCounts = DB::table('refund_requests')->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->selectRaw("
                        COUNT(CASE WHEN status = 'menunggu_konfirmasi' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN status = 'disetujui' THEN 1 END) as approved_count,
                        COUNT(CASE WHEN status = 'selesai' THEN 1 END) as completed_count,
                        COUNT(CASE WHEN status = 'ditolak' THEN 1 END) as rejected_count
                    ")->first();

                $returnStatusCounts = DB::table('returns')->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->selectRaw("
                        COUNT(CASE WHEN status = 'menunggu_review' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN status = 'disetujui' THEN 1 END) as approved_count,
                        COUNT(CASE WHEN status = 'barang_dikirim_customer' THEN 1 END) as in_transit_count,
                        COUNT(CASE WHEN status = 'barang_diterima_toko' THEN 1 END) as received_count,
                        COUNT(CASE WHEN status = 'refund_diproses' THEN 1 END) as refunding_count,
                        COUNT(CASE WHEN status = 'selesai' THEN 1 END) as completed_count,
                        COUNT(CASE WHEN status = 'ditolak' THEN 1 END) as rejected_count
                    ")->first();

                return [
                    'orderStats' => [
                        'unpaidCount' => (int) ($opStats->unpaid_count ?? 0),
                        'pendingCount' => (int) ($opStats->pending_count ?? 0),
                        'newCount' => (int) ($opStats->new_count ?? 0),
                        'readyCount' => (int) ($opStats->ready_count ?? 0),
                        'shippingCount' => (int) ($opStats->shipping_count ?? 0),
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

        $getRecentOrders = function () {
            return Cache::remember('dashboard_recent_orders_v2', 300, function () {
                $recentTransactions = DB::table('transactions')
                    ->leftJoin('users', 'transactions.user_id', '=', 'users.id')
                    ->select([
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

        $getRecentRefunds = function () {
            return Cache::remember('dashboard_recent_refunds_v2', 300, function () {
                return DB::table('refund_requests')
                    ->leftJoin('users', 'refund_requests.user_id', '=', 'users.id')
                    ->leftJoin('transactions', 'refund_requests.transaction_id', '=', 'transactions.id')
                    ->select([
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

        $getRecentReturns = function () {
            return Cache::remember('dashboard_recent_returns_v2', 300, function () {
                return DB::table('returns')
                    ->leftJoin('users', 'returns.user_id', '=', 'users.id')
                    ->leftJoin('transactions', 'returns.transaction_id', '=', 'transactions.id')
                    ->select([
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

        $getRecentCustomers = function () {
            return Cache::remember('dashboard_recent_customers_v2', 300, function () {
                return DB::table('users')
                    ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                    ->where('roles.name', 'Customer')
                    ->select([
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

        $getRecentStockOut = function () use ($paidStatuses) {
            return Cache::remember('dashboard_recent_stockout_v2', 300, function () use ($paidStatuses) {
                $recentStockOut = DB::table('stock_movements')
                    ->leftJoin('products', 'stock_movements.product_id', '=', 'products.id')
                    ->leftJoin('product_variants', 'stock_movements.product_variant_id', '=', 'product_variants.id')
                    ->leftJoin('transactions', 'stock_movements.transaction_id', '=', 'transactions.id')
                    ->select([
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
                    $recentStockOut = DB::table('transaction_items')
                        ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                        ->whereIn('transactions.status', $paidStatuses)
                        ->selectRaw('
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

        $getTopProducts = function () use ($filter, $dateFrom, $paidStatuses) {
            return Cache::remember("dashboard_top_products_v2_{$filter}", 3600, function () use ($dateFrom, $paidStatuses) {
                $topProductsRaw = DB::table('transaction_items')
                    ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                    ->whereIn('transactions.status', $paidStatuses)
                    ->where('transactions.created_at', '>=', $dateFrom)
                    ->selectRaw('
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

        $getChartData = function () use ($paidStatuses, $sixMonthsAgo, $monthFormatTx, $monthFormatSelf) {
            return Cache::remember('dashboard_chart_data_v2', 3600, function () use ($paidStatuses, $sixMonthsAgo, $monthFormatTx, $monthFormatSelf) {
                $chartLabels = [];
                $chartValues = [];
                $chartRefundValues = [];
                $chartReturnValues = [];

                $monthlyRevenue = DB::table('transactions')->whereIn('status', $paidStatuses)
                    ->where('created_at', '>=', $sixMonthsAgo)
                    ->selectRaw("
                        {$monthFormatTx} as month,
                        SUM(grand_total) as revenue
                    ")
                    ->groupBy(DB::raw($monthFormatTx))
                    ->orderBy('month', 'asc')
                    ->get();

                $monthlyRefunds = DB::table('refund_requests')->where('created_at', '>=', $sixMonthsAgo)
                    ->where('status', '!=', 'ditolak')
                    ->selectRaw("
                        {$monthFormatSelf} as month,
                        SUM(refund_amount) as amount
                    ")
                    ->groupBy(DB::raw($monthFormatSelf))
                    ->orderBy('month', 'asc')
                    ->get();

                $monthlyReturns = DB::table('returns')->where('created_at', '>=', $sixMonthsAgo)
                    ->where('status', '!=', 'ditolak')
                    ->selectRaw("
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

        // Product Stock Overview
        $search = $request->input('search');
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

        $stockQuery = DB::table('products')
            ->leftJoin('product_stocks', function ($join) {
                $join->on('products.id', '=', 'product_stocks.product_id')
                    ->whereNull('product_stocks.product_variant_id');
            })
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->when($search, function ($query) use ($search, $likeOperator) {
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
            'stats' => $getDeferred(fn () => $getKpis()['stats'], 'kpi_stats'),
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
}
