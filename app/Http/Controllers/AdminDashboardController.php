<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
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

        // Cache key per filter — invalidasi otomatis setiap 5 menit
        $cacheKey = "dashboard_stats_{$filter}";

        [$stats, $orderStats, $recentOrders, $topProducts, $chartData, $recentStockOut, $recentCustomers] = Cache::remember($cacheKey, 300, function () use (
            $dateFrom, $dateTo, $prevDateFrom, $prevDateTo, $paidStatuses
        ) {
            // --- 1. Aggregate stats: current & previous period dalam 2 query ---
            $currentAgg = Transaction::whereIn('status', $paidStatuses)
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('SUM(grand_total) as revenue, COUNT(id) as orders')
                ->first();

            $previousAgg = Transaction::whereIn('status', $paidStatuses)
                ->whereBetween('created_at', [$prevDateFrom, $prevDateTo])
                ->selectRaw('SUM(grand_total) as revenue, COUNT(id) as orders')
                ->first();

            $currentRevenue = (float) ($currentAgg->revenue ?? 0);
            $previousRevenue = (float) ($previousAgg->revenue ?? 0);
            $currentOrders = (int) ($currentAgg->orders ?? 0);
            $previousOrders = (int) ($previousAgg->orders ?? 0);

            // Products & customers — 1 query each with conditional aggregate
            $productAgg = Product::selectRaw(
                'COUNT(*) FILTER (WHERE active = true) as current_active,
                 COUNT(*) FILTER (WHERE active = true AND created_at < ?) as previous_active',
                [$dateFrom]
            )->first();

            $customerAgg = DB::table('users')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('roles.name', 'Customer')
                ->selectRaw(
                    'COUNT(*) as current_total,
                     COUNT(*) FILTER (WHERE users.created_at < ?) as previous_total',
                    [$dateFrom]
                )->first();

            $currentActiveProducts = (int) ($productAgg->current_active ?? 0);
            $previousActiveProducts = (int) ($productAgg->previous_active ?? 0);
            $currentCustomers = (int) ($customerAgg->current_total ?? 0);
            $previousCustomers = (int) ($customerAgg->previous_total ?? 0);

            // Operational stats — 1 query dengan CASE WHEN
            $opStats = Transaction::whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw("
                    COUNT(*) FILTER (WHERE status IN ('belum_bayar', 'menunggu')) as new_count,
                    COUNT(*) FILTER (WHERE status IN ('diproses', 'dikemas')) as ready_count,
                    COUNT(*) FILTER (WHERE status = 'dikirim') as shipping_count
                ")->first();

            $stats = [
                'revenueFormatted' => (new self)->formatRupiah($currentRevenue),
                'revenueChange' => (new self)->getPercentageChange($currentRevenue, $previousRevenue),
                'ordersCount' => $currentOrders,
                'ordersChange' => (new self)->getPercentageChange((float) $currentOrders, (float) $previousOrders),
                'activeProductsCount' => $currentActiveProducts,
                'productsChange' => (new self)->getPercentageChange((float) $currentActiveProducts, (float) $previousActiveProducts),
                'customersCount' => $currentCustomers,
                'customersChange' => (new self)->getPercentageChange((float) $currentCustomers, (float) $previousCustomers),
            ];

            $orderStats = [
                'newCount' => (int) ($opStats->new_count ?? 0),
                'readyCount' => (int) ($opStats->ready_count ?? 0),
                'shippingCount' => (int) ($opStats->shipping_count ?? 0),
            ];

            // --- 2. Recent Orders ---
            $recentTransactions = Transaction::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $recentOrders = $recentTransactions->map(function ($transaction) {
                $user = $transaction->user;
                $customerName = $user ? $user->name : 'Guest';
                $customerEmail = $user ? $user->email : 'guest@email.com';

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
                    'date' => $transaction->created_at->translatedFormat('d M Y, H:i'),
                    'amount' => (float) $transaction->grand_total,
                    'status' => $uiStatus,
                ];
            })->toArray();

            // --- 3. Top Products ---
            $topProductsRaw = DB::table('transaction_items')
                ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->join('products', 'transaction_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->whereIn('transactions.status', $paidStatuses)
                ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
                ->selectRaw("
                    transaction_items.product_id,
                    transaction_items.product_name as name,
                    COALESCE(categories.name, 'Tanpa Kategori') as category,
                    SUM(transaction_items.quantity) as sales
                ")
                ->groupBy('transaction_items.product_id', 'transaction_items.product_name', 'categories.name')
                ->orderBy('sales', 'desc')
                ->limit(3)
                ->get();

            $topProductIds = collect($topProductsRaw)->pluck('product_id')->filter()->all();
            $productModels = [];
            if (! empty($topProductIds)) {
                $productModels = Product::with('images')
                    ->whereIn('id', $topProductIds)
                    ->get()
                    ->keyBy('id');
            }

            $topProducts = [];
            foreach ($topProductsRaw as $item) {
                $productModel = $productModels[$item->product_id] ?? null;
                $imageUrl = 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?auto=format&fit=crop&w=150&q=80';
                if ($productModel) {
                    if ($productModel->image) {
                        $imageUrl = str_starts_with($productModel->image, 'http')
                            ? $productModel->image
                            : '/'.ltrim($productModel->image, '/');
                    } elseif ($productModel->images->first()) {
                        $imageUrl = '/'.ltrim($productModel->images->first()->path, '/');
                    }
                }

                $topProducts[] = [
                    'name' => $item->name,
                    'category' => $item->category,
                    'image' => $imageUrl,
                    'sales' => (int) $item->sales,
                ];
            }

            // --- 4. Chart Data (6-month revenue trend) ---
            $driver = DB::connection()->getDriverName();
            $monthFormat = $driver === 'sqlite'
                ? "strftime('%Y-%m', transactions.created_at)"
                : "TO_CHAR(transactions.created_at, 'YYYY-MM')";

            $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth();

            $monthlyRevenue = Transaction::whereIn('status', $paidStatuses)
                ->where('created_at', '>=', $sixMonthsAgo)
                ->selectRaw("
                    {$monthFormat} as month,
                    SUM(grand_total) as revenue
                ")
                ->groupBy(DB::raw($monthFormat))
                ->orderBy('month', 'asc')
                ->get();

            $chartLabels = [];
            $chartValues = [];

            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $monthKey = $date->format('Y-m');
                $monthLabel = $date->translatedFormat('M Y');

                $row = $monthlyRevenue->firstWhere('month', $monthKey);
                $revenue = $row ? (float) $row->revenue : 0.0;

                $chartLabels[] = $monthLabel;
                $chartValues[] = round($revenue / 1_000_000, 2);
            }

            $chartData = [
                'labels' => $chartLabels,
                'data' => $chartValues,
            ];

            // --- 5. Recent Stock-Out ---
            $recentStockOut = StockMovement::with([
                'product:id,name,sku,image',
                'productVariant:id,sku',
                'transaction:id,transaction_number',
            ])
                ->where('type', 'keluar')
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($mov) {
                    $imageUrl = null;
                    if ($mov->product && $mov->product->image) {
                        $img = $mov->product->image;
                        $imageUrl = str_starts_with($img, 'http') ? $img : '/'.ltrim($img, '/');
                    }

                    return [
                        'id' => $mov->id,
                        'product_name' => $mov->product->name ?? '-',
                        'product_sku' => $mov->productVariant?->sku ?? $mov->product?->sku ?? '-',
                        'image' => $imageUrl,
                        'quantity' => (int) $mov->quantity,
                        'stock_before' => (int) $mov->stock_before,
                        'stock_after' => (int) $mov->stock_after,
                        'transaction_number' => $mov->transaction?->transaction_number,
                        'transaction_id' => $mov->transaction?->id,
                        'notes' => $mov->notes,
                        'date' => $mov->created_at->translatedFormat('d M Y, H:i'),
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
                        transaction_items.created_at
                    ')
                    ->orderBy('transaction_items.created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($item) {
                        $imageUrl = null;
                        if ($item->product_image) {
                            $imageUrl = str_starts_with($item->product_image, 'http')
                                ? $item->product_image
                                : '/'.ltrim($item->product_image, '/');
                        }

                        return [
                            'id' => $item->id,
                            'product_name' => $item->product_name,
                            'product_sku' => $item->product_sku,
                            'image' => $imageUrl,
                            'quantity' => (int) $item->quantity,
                            'stock_before' => null,
                            'stock_after' => null,
                            'transaction_number' => $item->transaction_number,
                            'transaction_id' => $item->transaction_id,
                            'notes' => 'Penjualan',
                            'date' => Carbon::parse($item->created_at)->translatedFormat('d M Y, H:i'),
                            'source' => 'transaction',
                        ];
                    })
                    ->toArray();
            }

            // --- 6. Recent Customers ---
            $recentCustomers = User::whereHas('roles', function ($q) {
                $q->where('name', 'Customer');
            })
                ->orderBy('created_at', 'desc')
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
                        'date' => $u->created_at->translatedFormat('d M Y'),
                    ];
                })->toArray();

            return [$stats, $orderStats, $recentOrders, $topProducts, $chartData, $recentStockOut, $recentCustomers];
        });

        // Product Stock Overview — all active products with stock info & total sold
        $search = $request->input('search');
        $driver = DB::connection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

        $stockQuery = DB::table('products')
            ->leftJoin('product_stocks', function ($join) {
                $join->on('products.id', '=', 'product_stocks.product_id')
                    ->whereNull('product_stocks.product_variant_id');
            })
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin(
                DB::raw('(SELECT product_id, SUM(quantity) as total_sold FROM transaction_items JOIN transactions ON transaction_items.transaction_id = transactions.id WHERE transactions.status IN (\'diproses\', \'dikemas\', \'dikirim\', \'selesai\') GROUP BY product_id) as sold_data'),
                'products.id',
                '=',
                'sold_data.product_id'
            )
            ->where('products.active', true);

        if ($search) {
            $stockQuery->where(function ($q) use ($search, $likeOperator) {
                $q->where('products.name', $likeOperator, "%{$search}%")
                    ->orWhere('products.sku', $likeOperator, "%{$search}%");
            });
        }

        $productStockInfo = $stockQuery->selectRaw('
                products.id,
                products.name,
                products.sku,
                products.image,
                COALESCE(categories.name, \'Tanpa Kategori\') as category,
                COALESCE(product_stocks.stock, 0) as current_stock,
                COALESCE(product_stocks.min_stock, 0) as min_stock,
                COALESCE(product_stocks.is_unlimited, false) as is_unlimited,
                COALESCE(sold_data.total_sold, 0) as total_sold
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

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'orderStats' => $orderStats,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'chartData' => $chartData,
            'currentFilter' => $filter,
            'productStockInfo' => $productStockInfo,
            'recentStockOut' => $recentStockOut,
            'recentCustomers' => $recentCustomers,
            'search' => $search,
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
