<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        $paidStatuses = ['diproses', 'dikemas', 'dikirim', 'selesai'];

        // 1. Total Revenue
        $currentRevenue = Transaction::whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('grand_total');

        $previousRevenue = Transaction::whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$prevDateFrom, $prevDateTo])
            ->sum('grand_total');

        $revenueChange = $this->getPercentageChange((float) $currentRevenue, (float) $previousRevenue);

        // 2. Total Orders
        $currentOrders = Transaction::whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();

        $previousOrders = Transaction::whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$prevDateFrom, $prevDateTo])
            ->count();

        $ordersChange = $this->getPercentageChange((float) $currentOrders, (float) $previousOrders);

        // 3. Active Products
        $currentActiveProducts = Product::where('active', true)->count();
        $previousActiveProducts = Product::where('active', true)
            ->where('created_at', '<', $dateFrom)
            ->count();

        $productsChange = $this->getPercentageChange((float) $currentActiveProducts, (float) $previousActiveProducts);

        // 4. Total Customers
        $currentCustomers = User::whereHas('roles', function ($q) {
            $q->where('name', 'Customer');
        })->count();

        $previousCustomers = User::whereHas('roles', function ($q) {
            $q->where('name', 'Customer');
        })->where('created_at', '<', $dateFrom)->count();

        $customersChange = $this->getPercentageChange((float) $currentCustomers, (float) $previousCustomers);

        $stats = [
            'revenueFormatted' => $this->formatRupiah((float) $currentRevenue),
            'revenueChange' => $revenueChange,
            'ordersCount' => $currentOrders,
            'ordersChange' => $ordersChange,
            'activeProductsCount' => $currentActiveProducts,
            'productsChange' => $productsChange,
            'customersCount' => $currentCustomers,
            'customersChange' => $customersChange,
        ];

        // Operational Order Stats
        $orderStats = [
            'newCount' => Transaction::whereIn('status', ['belum_bayar', 'menunggu'])->whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'readyCount' => Transaction::whereIn('status', ['diproses', 'dikemas'])->whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'shippingCount' => Transaction::where('status', 'dikirim')->whereBetween('created_at', [$dateFrom, $dateTo])->count(),
        ];

        // Recent Orders (last 5 transactions)
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

        // Top Products (last 3 products based on quantity sold in the selected period)
        $topProductsRaw = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->whereIn('transactions.status', $paidStatuses)
            ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
            ->selectRaw('
                transaction_items.product_id,
                transaction_items.product_name as name,
                COALESCE(categories.name, \'Tanpa Kategori\') as category,
                SUM(transaction_items.quantity) as sales
            ')
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

        // Chart Data (monthly trend of revenue for the last 6 months)
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
            // Value in Millions
            $chartValues[] = round($revenue / 1_000_000, 2);
        }

        $chartData = [
            'labels' => $chartLabels,
            'data' => $chartValues,
        ];

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

        // Recent Stock-Out History — from stock_movements (type=keluar) OR fallback to transaction_items
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

        // If no stock movements recorded, fallback to recent sold transaction items
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

        // Fetch 8 most recent customers
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
