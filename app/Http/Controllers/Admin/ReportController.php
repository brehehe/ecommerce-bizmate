<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Courier;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

class ReportController extends Controller
{
    /**
     * Get date range from request or set defaults (last 30 days).
     */
    protected function getDateRange(Request $request): array
    {
        $preset = $request->input('preset');

        if ($preset) {
            switch ($preset) {
                case 'harian':
                    $dateFrom = Carbon::today()->startOfDay();
                    $dateTo = Carbon::today()->endOfDay();
                    break;
                case 'mingguan':
                    $dateFrom = Carbon::now()->subDays(6)->startOfDay();
                    $dateTo = Carbon::now()->endOfDay();
                    break;
                case 'bulanan':
                    $dateFrom = Carbon::now()->subDays(29)->startOfDay();
                    $dateTo = Carbon::now()->endOfDay();
                    break;
                case 'tahunan':
                    $dateFrom = Carbon::now()->startOfYear()->startOfDay();
                    $dateTo = Carbon::now()->endOfDay();
                    break;
                default:
                    $dateFrom = $request->input('date_from')
                        ? Carbon::parse($request->input('date_from'))->startOfDay()
                        : Carbon::now()->subDays(29)->startOfDay();
                    $dateTo = $request->input('date_to')
                        ? Carbon::parse($request->input('date_to'))->endOfDay()
                        : Carbon::now()->endOfDay();
                    $preset = 'custom';
                    break;
            }
        } else {
            $dateFrom = $request->input('date_from')
                ? Carbon::parse($request->input('date_from'))->startOfDay()
                : Carbon::now()->subDays(29)->startOfDay();

            $dateTo = $request->input('date_to')
                ? Carbon::parse($request->input('date_to'))->endOfDay()
                : Carbon::now()->endOfDay();

            // Auto deduce preset
            $today = Carbon::today()->format('Y-m-d');
            $weekAgo = Carbon::now()->subDays(6)->format('Y-m-d');
            $monthAgo = Carbon::now()->subDays(29)->format('Y-m-d');
            $yearStart = Carbon::now()->startOfYear()->format('Y-m-d');
            $nowStr = Carbon::now()->format('Y-m-d');

            $fromStr = $dateFrom->format('Y-m-d');
            $toStr = $dateTo->format('Y-m-d');

            if ($fromStr === $today && $toStr === $today) {
                $preset = 'harian';
            } elseif ($fromStr === $weekAgo && $toStr === $nowStr) {
                $preset = 'mingguan';
            } elseif ($fromStr === $monthAgo && $toStr === $nowStr) {
                $preset = 'bulanan';
            } elseif ($fromStr === $yearStart && $toStr === $nowStr) {
                $preset = 'tahunan';
            } else {
                $preset = 'custom';
            }
        }

        return [$dateFrom, $dateTo, $preset];
    }

    /**
     * Laporan Penjualan (Sales Report)
     */
    public function sales(Request $request): Response
    {
        $perPage = (int) $request->input('per_page', 15);
        if ($perPage < 10) {
            $perPage = 10;
        }

        [$dateFrom, $dateTo, $preset] = $this->getDateRange($request);
        $paidStatuses = Transaction::PAID_STATUSES;

        // Get active payment methods for dropdown
        $paymentMethods = PaymentMethod::where('is_active', true)
            ->select('id', 'name', 'bank_name')
            ->get();

        // Build base query without status constraint
        $baseQueryNoStatus = Transaction::whereBetween('transactions.created_at', [$dateFrom, $dateTo]);

        // Apply payment method filter
        if ($request->filled('payment_method_id') && $request->payment_method_id !== 'all') {
            $baseQueryNoStatus->where('payment_method_id', $request->payment_method_id);
        }

        // Apply search filter (transaction number, customer name, product name)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $baseQueryNoStatus->where(function ($q) use ($search) {
                $q->where('transaction_number', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('items', function ($iq) use ($search) {
                        $iq->where('product_name', 'ilike', "%{$search}%");
                    });
            });
        }

        // Build base query with status constraint
        $baseQuery = clone $baseQueryNoStatus;
        if ($request->filled('status') && $request->status !== 'all') {
            $baseQuery->where('status', $request->status);
        } else {
            $baseQuery->whereIn('status', $paidStatuses);
        }

        // Metrik Utama
        $metrics = (clone $baseQuery)->selectRaw('
                COALESCE(SUM(grand_total), 0) as net_sales,
                COALESCE(SUM(subtotal), 0) as gross_sales,
                COALESCE(SUM(discount_amount), 0) as total_discount,
                COALESCE(SUM(shipping_fee), 0) as total_shipping,
                COALESCE(SUM(admin_fee), 0) as total_admin,
                COUNT(id) as order_count
            ')
            ->first();

        // Jumlah item terjual
        $itemsSold = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereIn('transactions.status', $paidStatuses)
            ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
            ->when($request->filled('payment_method_id') && $request->payment_method_id !== 'all', function ($q) use ($request) {
                $q->where('transactions.payment_method_id', $request->payment_method_id);
            })
            ->when($request->filled('status') && $request->status !== 'all', function ($q) use ($request) {
                $q->where('transactions.status', $request->status);
            })
            ->sum('transaction_items.quantity');

        // Tren Penjualan Harian
        $salesTrend = (clone $baseQuery)
            ->selectRaw('
                CAST(created_at AS DATE) as date,
                COUNT(id) as order_count,
                SUM(grand_total) as net_sales,
                SUM(subtotal) as gross_sales,
                SUM(discount_amount) as total_discount,
                SUM(shipping_fee) as total_shipping,
                SUM(admin_fee) as total_admin
            ')
            ->groupBy(DB::raw('CAST(created_at AS DATE)'))
            ->orderBy('date', 'asc')
            ->get();

        // Paginated daily details table (recent dates first)
        $salesTrendPaginated = (clone $baseQuery)
            ->selectRaw('
                CAST(created_at AS DATE) as date,
                COUNT(id) as order_count,
                SUM(grand_total) as net_sales,
                SUM(subtotal) as gross_sales,
                SUM(discount_amount) as total_discount,
                SUM(shipping_fee) as total_shipping,
                SUM(admin_fee) as total_admin
            ')
            ->groupBy(DB::raw('CAST(created_at AS DATE)'))
            ->orderBy('date', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Distribusi Metode Pembayaran
        $paymentDistribution = (clone $baseQuery)
            ->join('payment_methods', 'transactions.payment_method_id', '=', 'payment_methods.id')
            ->selectRaw('payment_methods.name as name, COUNT(transactions.id) as count, SUM(transactions.grand_total) as revenue')
            ->groupBy('payment_methods.name')
            ->get();

        // Distribusi Status Pesanan (based on base query without status filter to show full spectrum)
        $statusDistribution = (clone $baseQueryNoStatus)
            ->selectRaw('status, COUNT(id) as count')
            ->groupBy('status')
            ->get();

        // Format data untuk grafik
        $chartData = [
            'labels' => $salesTrend->map(fn ($t) => Carbon::parse($t->date)->format('d M Y'))->toArray(),
            'revenue' => $salesTrend->map(fn ($t) => (float) $t->net_sales)->toArray(),
            'orders' => $salesTrend->map(fn ($t) => (int) $t->order_count)->toArray(),
        ];

        // Paginated individual transaction details list
        $transactions = (clone $baseQuery)
            ->with(['user:id,name', 'paymentMethod:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'transactions_page')
            ->withQueryString();

        return Inertia::render('Admin/Reports/Sales', [
            'metrics' => [
                'net_sales' => (float) $metrics->net_sales,
                'gross_sales' => (float) $metrics->gross_sales,
                'total_discount' => (float) $metrics->total_discount,
                'total_shipping' => (float) $metrics->total_shipping,
                'total_admin' => (float) $metrics->total_admin,
                'order_count' => (int) $metrics->order_count,
                'items_sold' => (int) $itemsSold,
                'aov' => $metrics->order_count > 0 ? (float) ($metrics->net_sales / $metrics->order_count) : 0,
            ],
            'salesTrend' => $salesTrend,
            'salesTrendPaginated' => $salesTrendPaginated,
            'paymentDistribution' => $paymentDistribution,
            'statusDistribution' => $statusDistribution,
            'paymentMethods' => $paymentMethods,
            'chartData' => $chartData,
            'transactions' => $transactions,
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'preset' => $preset,
                'search' => $request->input('search', ''),
                'payment_method_id' => $request->input('payment_method_id', 'all'),
                'status' => $request->input('status', 'all'),
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Laporan Penjualan Produk (Product Sales Report)
     */
    public function products(Request $request): Response
    {
        [$dateFrom, $dateTo, $preset] = $this->getDateRange($request);
        $paidStatuses = Transaction::PAID_STATUSES;

        $query = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->whereIn('transactions.status', $paidStatuses)
            ->whereBetween('transactions.created_at', [$dateFrom, $dateTo]);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_items.product_name', 'ilike', "%{$search}%")
                    ->orWhere('transaction_items.product_sku', 'ilike', "%{$search}%");
            });
        }

        // Category filter
        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }

        // Clone query for metrics and charts before pagination
        $totalSalesQty = (clone $query)->sum('transaction_items.quantity');
        $totalRevenueValue = (clone $query)->sum('transaction_items.subtotal');

        // Top 5 Products for Chart
        $topProducts = (clone $query)
            ->selectRaw('transaction_items.product_name as name, SUM(transaction_items.quantity) as qty, SUM(transaction_items.subtotal) as revenue')
            ->groupBy('transaction_items.product_name')
            ->orderBy('qty', 'desc')
            ->limit(5)
            ->get();

        // Kategori Terlaris for Chart
        $categorySales = (clone $query)
            ->selectRaw('COALESCE(categories.name, \'Tanpa Kategori\') as name, SUM(transaction_items.quantity) as qty, SUM(transaction_items.subtotal) as revenue')
            ->groupBy('categories.name')
            ->orderBy('revenue', 'desc')
            ->get();

        // Paginated Table Data
        $productSales = $query
            ->selectRaw('
                transaction_items.product_id,
                transaction_items.product_name,
                transaction_items.product_sku,
                COALESCE(categories.name, \'Tanpa Kategori\') as category_name,
                SUM(transaction_items.quantity) as qty_sold,
                SUM(transaction_items.subtotal) as total_revenue,
                AVG(transaction_items.harga_akhir) as avg_price
            ')
            ->groupBy('transaction_items.product_id', 'transaction_items.product_name', 'transaction_items.product_sku', 'categories.name')
            ->orderBy('qty_sold', 'desc')
            ->paginate(15)
            ->withQueryString();

        $categories = Category::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Admin/Reports/Products', [
            'productSales' => $productSales,
            'categories' => $categories,
            'metrics' => [
                'total_qty_sold' => (int) $totalSalesQty,
                'total_revenue' => (float) $totalRevenueValue,
                'top_category' => $categorySales->first()?->name ?? '-',
            ],
            'chartData' => [
                'topProducts' => $topProducts,
                'categorySales' => $categorySales,
            ],
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'preset' => $preset,
                'search' => $request->search,
                'category_id' => $request->category_id,
            ],
        ]);
    }

    /**
     * Laporan Laba Rugi (Profit & Loss Report)
     */
    public function profitLoss(Request $request): Response
    {
        [$dateFrom, $dateTo, $preset] = $this->getDateRange($request);
        $paidStatuses = Transaction::PAID_STATUSES;

        // Hitung Pendapatan
        // Pendapatan Produk: sum of items subtotal
        $productRevenue = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereIn('transactions.status', $paidStatuses)
            ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
            ->sum('transaction_items.subtotal');

        // Pendapatan Admin Fee
        $adminFeeRevenue = Transaction::whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('admin_fee');

        $totalRevenue = $productRevenue + $adminFeeRevenue;

        // Hitung HPP (COGS)
        $totalCogs = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereIn('transactions.status', $paidStatuses)
            ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
            ->selectRaw('SUM(hpp * quantity) as total_cogs')
            ->first()
            ->total_cogs ?? 0;

        $grossProfit = $totalRevenue - $totalCogs;

        // Hitung Pengeluaran/Subsidi
        $voucherDiscounts = Transaction::whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('discount_amount');

        $shippingDiscounts = Transaction::whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->sum('shipping_discount');

        $totalExpenses = $voucherDiscounts + $shippingDiscounts;
        $netProfit = $grossProfit - $totalExpenses;

        $driver = DB::connection()->getDriverName();
        $monthFormat = $driver === 'sqlite'
            ? "strftime('%Y-%m', transactions.created_at)"
            : "TO_CHAR(transactions.created_at, 'YYYY-MM')";

        // Tren Bulanan Laba Rugi
        $monthlyTrend = Transaction::whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw("
                {$monthFormat} as month,
                SUM(transactions.admin_fee) as admin_revenue,
                SUM(transactions.discount_amount) as voucher_expense,
                SUM(transactions.shipping_discount) as shipping_expense
            ")
            ->groupBy(DB::raw($monthFormat))
            ->orderBy('month', 'asc')
            ->get();

        // Dapatkan HPP dan Pendapatan Produk bulanan
        $monthlyProductData = DB::table('transaction_items')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereIn('transactions.status', $paidStatuses)
            ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
            ->selectRaw("
                {$monthFormat} as month,
                SUM(transaction_items.subtotal) as product_revenue,
                SUM(transaction_items.hpp * transaction_items.quantity) as cogs
            ")
            ->groupBy(DB::raw($monthFormat))
            ->get()
            ->keyBy('month');

        $trendData = [];
        foreach ($monthlyTrend as $mTrend) {
            $month = $mTrend->month;
            $pData = $monthlyProductData->get($month);
            $pRev = $pData ? (float) $pData->product_revenue : 0;
            $cogs = $pData ? (float) $pData->cogs : 0;

            $monthRevenue = $pRev + (float) $mTrend->admin_revenue;
            $monthExpenses = (float) $mTrend->voucher_expense + (float) $mTrend->shipping_expense;
            $monthNetProfit = ($monthRevenue - $cogs) - $monthExpenses;

            $trendData[] = [
                'month' => Carbon::parse($month.'-01')->format('M Y'),
                'revenue' => $monthRevenue,
                'cogs' => $cogs,
                'expenses' => $monthExpenses,
                'net_profit' => $monthNetProfit,
            ];
        }

        return Inertia::render('Admin/Reports/ProfitLoss', [
            'metrics' => [
                'product_revenue' => (float) $productRevenue,
                'admin_fee_revenue' => (float) $adminFeeRevenue,
                'total_revenue' => (float) $totalRevenue,
                'total_cogs' => (float) $totalCogs,
                'gross_profit' => (float) $grossProfit,
                'voucher_discounts' => (float) $voucherDiscounts,
                'shipping_discounts' => (float) $shippingDiscounts,
                'total_expenses' => (float) $totalExpenses,
                'net_profit' => (float) $netProfit,
                'profit_margin' => $totalRevenue > 0 ? (float) (($netProfit / $totalRevenue) * 100) : 0,
            ],
            'trendData' => $trendData,
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'preset' => $preset,
            ],
        ]);
    }

    /**
     * Laporan Pelanggan (Customer Report)
     */
    public function customers(Request $request): Response
    {
        [$dateFrom, $dateTo, $preset] = $this->getDateRange($request);
        $paidStatuses = Transaction::PAID_STATUSES;

        // Total Pelanggan Baru
        $newCustomersCount = User::whereHas('roles', function ($q) {
            $q->where('name', 'Customer');
        })->whereBetween('created_at', [$dateFrom, $dateTo])->count();

        // Total Pelanggan Aktif (pernah belanja sukses)
        $activeCustomersCount = Transaction::whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->distinct('user_id')
            ->count('user_id');

        // Total Pelanggan di Database
        $totalCustomers = User::whereHas('roles', function ($q) {
            $q->where('name', 'Customer');
        })->count();

        // Kueri Daftar Customer Top Spenders
        $query = User::whereHas('roles', function ($q) {
            $q->where('name', 'Customer');
        });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        // Dapatkan data spending
        $topSpenders = $query
            ->leftJoin('transactions', function ($join) use ($paidStatuses, $dateFrom, $dateTo) {
                $join->on('users.id', '=', 'transactions.user_id')
                    ->whereIn('transactions.status', $paidStatuses)
                    ->whereBetween('transactions.created_at', [$dateFrom, $dateTo]);
            })
            ->selectRaw('
                users.id,
                users.name,
                users.email,
                users.created_at as registered_at,
                COUNT(transactions.id) as orders_count,
                COALESCE(SUM(transactions.grand_total), 0) as total_spent,
                COALESCE(AVG(transactions.grand_total), 0) as average_order_value,
                COALESCE(SUM(transactions.discount_amount), 0) as total_discounts,
                COALESCE(SUM(transactions.coins_redeemed), 0) as total_coins_redeemed,
                MAX(transactions.created_at) as last_order_date
            ')
            ->groupBy('users.id', 'users.name', 'users.email', 'users.created_at')
            ->orderBy('total_spent', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Tren Pendaftaran Baru Harian
        $registrationsTrend = User::whereHas('roles', function ($q) {
            $q->where('name', 'Customer');
        })
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('CAST(created_at AS DATE) as date, COUNT(id) as count')
            ->groupBy(DB::raw('CAST(created_at AS DATE)'))
            ->orderBy('date', 'asc')
            ->get();

        return Inertia::render('Admin/Reports/Customers', [
            'customers' => $topSpenders,
            'metrics' => [
                'new_customers' => $newCustomersCount,
                'active_customers' => $activeCustomersCount,
                'total_customers' => $totalCustomers,
            ],
            'chartData' => [
                'labels' => $registrationsTrend->map(fn ($t) => Carbon::parse($t->date)->format('d M'))->toArray(),
                'counts' => $registrationsTrend->map(fn ($t) => (int) $t->count)->toArray(),
            ],
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'preset' => $preset,
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Get transaction history for a specific customer.
     */
    public function customerTransactions(Request $request, User $user): JsonResponse
    {
        $transactions = Transaction::where('user_id', $user->id)
            ->with(['paymentMethod'])
            ->withCount('items')
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return response()->json($transactions);
    }

    /**
     * Laporan Stok & Inventaris (Stock Report)
     */
    public function stocks(Request $request): Response
    {
        // 1. Dapatkan stok produk tanpa varian
        $simpleProductsQuery = DB::table('products')
            ->join('product_stocks', function ($join) {
                $join->on('products.id', '=', 'product_stocks.product_id')
                    ->whereNull('product_stocks.product_variant_id');
            })
            ->join('product_prices', function ($join) {
                $join->on('products.id', '=', 'product_prices.product_id')
                    ->whereNull('product_prices.product_variant_id');
            })
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('
                products.id as product_id,
                products.name as name,
                products.sku as sku,
                COALESCE(categories.name, \'Tanpa Kategori\') as category_name,
                product_stocks.stock as stock,
                product_stocks.min_stock as min_stock,
                product_stocks.is_unlimited as is_unlimited,
                product_prices.cost as cost,
                product_prices.price as price,
                NULL as variant_id
            ');

        // 2. Dapatkan stok produk dengan varian
        $driver = DB::connection()->getDriverName();
        $variantNameExpr = $driver === 'sqlite'
            ? "products.name || ' (' || COALESCE((
                SELECT GROUP_CONCAT(pvo.name, ', ')
                FROM product_variant_option_combinations pvoc
                JOIN product_variation_options pvo ON pvoc.product_variation_option_id = pvo.id
                WHERE pvoc.product_variant_id = product_variants.id
              ), '') || ')'"
            : "CONCAT(products.name, ' (', (
                SELECT STRING_AGG(pvo.name, ', ')
                FROM product_variant_option_combinations pvoc
                JOIN product_variation_options pvo ON pvoc.product_variation_option_id = pvo.id
                WHERE pvoc.product_variant_id = product_variants.id
              ), ')')";

        $variantProductsQuery = DB::table('products')
            ->join('product_variants', 'products.id', '=', 'product_variants.product_id')
            ->join('product_stocks', 'product_variants.id', '=', 'product_stocks.product_variant_id')
            ->join('product_prices', 'product_variants.id', '=', 'product_prices.product_variant_id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw("
                products.id as product_id,
                {$variantNameExpr} as name,
                product_variants.sku as sku,
                COALESCE(categories.name, 'Tanpa Kategori') as category_name,
                product_stocks.stock as stock,
                product_stocks.min_stock as min_stock,
                product_stocks.is_unlimited as is_unlimited,
                product_prices.cost as cost,
                product_prices.price as price,
                product_variants.id as variant_id
            ");

        // Union keduanya
        $unionQuery = DB::query()->fromSub($simpleProductsQuery->unionAll($variantProductsQuery), 'all_stocks');

        // Filter Pencarian
        if ($request->filled('search')) {
            $search = $request->search;
            $unionQuery->where(function ($q) use ($search) {
                $q->where('all_stocks.name', 'ilike', "%{$search}%")
                    ->orWhere('all_stocks.sku', 'ilike', "%{$search}%");
            });
        }

        // Filter Status Stok
        if ($request->filled('status')) {
            if ($request->status === 'kritis') {
                $unionQuery->where('all_stocks.is_unlimited', false)
                    ->whereRaw('all_stocks.stock <= all_stocks.min_stock')
                    ->where('all_stocks.stock', '>', 0);
            } elseif ($request->status === 'habis') {
                $unionQuery->where('all_stocks.is_unlimited', false)
                    ->where('all_stocks.stock', '<=', 0);
            } elseif ($request->status === 'aman') {
                $unionQuery->where(function ($q) {
                    $q->where('all_stocks.is_unlimited', true)
                        ->orWhereRaw('all_stocks.stock > all_stocks.min_stock');
                });
            }
        }

        // Hitung metrik agregat langsung di DB — tanpa menarik semua baris ke PHP
        $metrics = (clone $unionQuery)->selectRaw('
            COUNT(*) as total_sku,
            COALESCE(SUM(CASE WHEN is_unlimited = false THEN stock ELSE 0 END), 0) as total_stock,
            COALESCE(SUM(CASE WHEN is_unlimited = false THEN stock * cost ELSE 0 END), 0) as total_asset_cost,
            COALESCE(SUM(CASE WHEN is_unlimited = false THEN stock * price ELSE 0 END), 0) as total_retail_value,
            COUNT(CASE WHEN is_unlimited = false AND stock <= min_stock THEN 1 END) as low_stock_count
        ')->first();

        // Paginated output
        $stocks = $unionQuery->orderBy('all_stocks.stock', 'asc')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Reports/Stocks', [
            'stocks' => $stocks,
            'metrics' => [
                'total_sku' => (int) $metrics->total_sku,
                'total_stock' => (int) $metrics->total_stock,
                'total_asset_value' => (float) $metrics->total_asset_cost,
                'total_retail_value' => (float) $metrics->total_retail_value,
                'potential_profit' => (float) ($metrics->total_retail_value - $metrics->total_asset_cost),
                'low_stock_count' => (int) $metrics->low_stock_count,
            ],
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
            ],
        ]);
    }

    /**
     * Laporan Pareto (Pareto Analysis Report)
     * Mendukung 4 jenis analisis: produk (revenue), produk (qty), pelanggan, kategori.
     */
    public function pareto(Request $request): Response
    {
        [$dateFrom, $dateTo, $preset] = $this->getDateRange($request);
        $paidStatuses = Transaction::PAID_STATUSES;
        $type = $request->input('type', 'product_revenue'); // default

        $rawItems = collect();

        if ($type === 'product_revenue') {
            // Pareto: Produk berdasarkan Omset
            $rawItems = DB::table('transaction_items')
                ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->leftJoin('products', 'transaction_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->whereIn('transactions.status', $paidStatuses)
                ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
                ->selectRaw('
                    transaction_items.product_name as label,
                    COALESCE(categories.name, \'Tanpa Kategori\') as category_name,
                    SUM(transaction_items.quantity) as qty_sold,
                    SUM(transaction_items.subtotal) as value
                ')
                ->groupBy('transaction_items.product_name', 'categories.name')
                ->orderBy('value', 'desc')
                ->get();
        } elseif ($type === 'product_qty') {
            // Pareto: Produk berdasarkan Kuantitas Terjual
            $rawItems = DB::table('transaction_items')
                ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->leftJoin('products', 'transaction_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->whereIn('transactions.status', $paidStatuses)
                ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
                ->selectRaw('
                    transaction_items.product_name as label,
                    COALESCE(categories.name, \'Tanpa Kategori\') as category_name,
                    SUM(transaction_items.quantity) as qty_sold,
                    SUM(transaction_items.subtotal) as revenue,
                    SUM(transaction_items.quantity) as value
                ')
                ->groupBy('transaction_items.product_name', 'categories.name')
                ->orderBy('value', 'desc')
                ->get();
        } elseif ($type === 'customer_spending') {
            // Pareto: Pelanggan berdasarkan Total Belanja
            $rawItems = DB::table('transactions')
                ->join('users', 'transactions.user_id', '=', 'users.id')
                ->whereIn('transactions.status', $paidStatuses)
                ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
                ->selectRaw('
                    users.name as label,
                    users.email as category_name,
                    COUNT(transactions.id) as qty_sold,
                    SUM(transactions.grand_total) as value
                ')
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderBy('value', 'desc')
                ->get();
        } elseif ($type === 'category_revenue') {
            // Pareto: Kategori berdasarkan Omset
            $rawItems = DB::table('transaction_items')
                ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
                ->leftJoin('products', 'transaction_items.product_id', '=', 'products.id')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->whereIn('transactions.status', $paidStatuses)
                ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
                ->selectRaw('
                    COALESCE(categories.name, \'Tanpa Kategori\') as label,
                    NULL as category_name,
                    SUM(transaction_items.quantity) as qty_sold,
                    SUM(transaction_items.subtotal) as value
                ')
                ->groupBy('categories.name')
                ->orderBy('value', 'desc')
                ->get();
        }

        // Hitung kumulatif & persentase berdasarkan value (omset/qty)
        $total = $rawItems->sum('value');
        $cumulativeValue = 0;
        $items = $rawItems->values()->map(function ($item, $index) use ($total, &$cumulativeValue) {
            $cumulativeValue += $item->value;
            $percentage = $total > 0 ? ($item->value / $total) * 100 : 0;
            $cumulativePercentage = $total > 0 ? ($cumulativeValue / $total) * 100 : 0;

            return [
                'rank' => $index + 1,
                'label' => $item->label,
                'category_name' => $item->category_name ?? null,
                'qty_sold' => (int) ($item->qty_sold ?? 0),
                'value' => (float) $item->value,
                'percentage' => round($percentage, 2),
                'cumulative_percentage' => round($cumulativePercentage, 2),
                'is_vital_few' => $cumulativePercentage <= 80, // 80/20 rule
            ];
        });

        // --- Klasifikasi Moving / Tier untuk SEMUA tipe analisis ---
        // Produk (revenue/qty) : Fast/Medium/Slow berdasarkan kumulatif QTY terjual
        // Pelanggan            : High Value / Mid Value / Low Value berdasarkan kumulatif VALUE belanja
        // Kategori             : Unggulan / Menengah / Lemah berdasarkan kumulatif VALUE omset
        // Threshold: fast = posisi kumulatif mulai < 50%, medium = 50–80%, slow/low = ≥ 80%
        $isProductType = in_array($type, ['product_revenue', 'product_qty']);
        $movingMetrics = [
            'fast' => ['count' => 0, 'qty' => 0, 'value' => 0.0],
            'medium' => ['count' => 0, 'qty' => 0, 'value' => 0.0],
            'slow' => ['count' => 0, 'qty' => 0, 'value' => 0.0],
        ];

        // Tentukan basis sorting & total untuk kumulatif moving
        if ($isProductType) {
            // Produk → basis kumulatif = qty terjual
            $sortedForMoving = $items->sortByDesc('qty_sold')->values();
            $totalMoving = $sortedForMoving->sum('qty_sold');
            $movingKey = 'qty_sold';
        } else {
            // Pelanggan / Kategori → basis kumulatif = value (omset / spending)
            $sortedForMoving = $items->sortByDesc('value')->values();
            $totalMoving = $sortedForMoving->sum('value');
            $movingKey = 'value';
        }

        // Hitung label moving berdasarkan posisi kumulatif SEBELUM item ini ditambahkan.
        // Produk/item #1 (tertinggi) selalu dimulai dari 0% → selalu masuk fast.
        $labelMap = [];
        $cumulativeMoving = 0;
        foreach ($sortedForMoving as $it) {
            $prevCumulativePct = $totalMoving > 0 ? ($cumulativeMoving / $totalMoving) * 100 : 0;
            $cumulativeMoving += (float) $it[$movingKey];
            if ($prevCumulativePct < 50) {
                $labelMap[$it['label']] = 'fast';
            } elseif ($prevCumulativePct < 80) {
                $labelMap[$it['label']] = 'medium';
            } else {
                $labelMap[$it['label']] = 'slow';
            }
        }

        // Terapkan moving_category ke setiap item (urutan asli = sorted by value desc)
        $items = $items->map(function ($it) use ($labelMap) {
            $it['moving_category'] = $labelMap[$it['label']] ?? 'slow';

            return $it;
        });

        // Hitung ringkasan per kategori moving
        foreach ($items as $it) {
            $cat = $it['moving_category'];
            $movingMetrics[$cat]['count']++;
            $movingMetrics[$cat]['qty'] += $it['qty_sold'];
            $movingMetrics[$cat]['value'] += $it['value'];
        }

        // Hitung metrik ringkasan pareto
        $vitalFewCount = $items->filter(fn ($i) => $i['is_vital_few'])->count();
        $vitalFewValue = $items->filter(fn ($i) => $i['is_vital_few'])->sum('value');
        $trivialManyCount = $items->count() - $vitalFewCount;
        $trivialManyValue = $total - $vitalFewValue;

        // Chart data (top 20 saja untuk keterbacaan)
        $chartItems = $items->take(20);

        return Inertia::render('Admin/Reports/Pareto', [
            'items' => $items->values(),
            'chartData' => [
                'labels' => $chartItems->pluck('label')->toArray(),
                'values' => $chartItems->pluck('value')->toArray(),
                'cumulativePercentages' => $chartItems->pluck('cumulative_percentage')->toArray(),
            ],
            'metrics' => [
                'total_items' => $items->count(),
                'total_value' => (float) $total,
                'vital_few_count' => $vitalFewCount,
                'vital_few_value' => (float) $vitalFewValue,
                'trivial_many_count' => $trivialManyCount,
                'trivial_many_value' => (float) $trivialManyValue,
                'vital_few_pct' => $items->count() > 0 ? round(($vitalFewCount / $items->count()) * 100, 1) : 0,
            ],
            'movingMetrics' => $movingMetrics,
            'isProductType' => $isProductType,
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'preset' => $preset,
                'type' => $type,
            ],
        ]);
    }

    /**
     * Laporan Kurir & Logistik (Courier & Logistics Report)
     */
    public function couriers(Request $request): Response
    {
        [$dateFrom, $dateTo, $preset] = $this->getDateRange($request);

        // 1. Shipping Summary: Aggregates total orders, completed/cancelled count, total sales, shipping fees collected, and average fee
        $shippingSummaryRaw = Transaction::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw("
                CASE 
                    WHEN shipping_courier = 'store_courier' THEN 'store_courier'
                    WHEN shipping_courier = 'self_pickup' THEN 'self_pickup'
                    ELSE 'rajaongkir'
                END as method,
                COUNT(id) as total_orders,
                SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'batal' THEN 1 ELSE 0 END) as cancelled_count,
                SUM(CASE WHEN status IN ('diproses', 'dikemas', 'dikirim', 'selesai') THEN grand_total ELSE 0 END) as total_sales,
                SUM(CASE WHEN status IN ('diproses', 'dikemas', 'dikirim', 'selesai') THEN shipping_fee ELSE 0 END) as shipping_fees,
                AVG(CASE WHEN status IN ('diproses', 'dikemas', 'dikirim', 'selesai') AND shipping_fee > 0 THEN shipping_fee ELSE NULL END) as avg_shipping_fee
            ")
            ->groupBy(DB::raw("
                CASE 
                    WHEN shipping_courier = 'store_courier' THEN 'store_courier'
                    WHEN shipping_courier = 'self_pickup' THEN 'self_pickup'
                    ELSE 'rajaongkir'
                END
            "))
            ->get()
            ->keyBy('method');

        // Ensure all three main methods are populated with default empty values if not found
        $methods = ['rajaongkir', 'store_courier', 'self_pickup'];
        $shippingSummary = [];
        foreach ($methods as $m) {
            $raw = $shippingSummaryRaw->get($m);
            $shippingSummary[$m] = [
                'method' => $m,
                'total_orders' => $raw ? (int) $raw->total_orders : 0,
                'completed_count' => $raw ? (int) $raw->completed_count : 0,
                'cancelled_count' => $raw ? (int) $raw->cancelled_count : 0,
                'total_sales' => $raw ? (float) $raw->total_sales : 0,
                'shipping_fees' => $raw ? (float) $raw->shipping_fees : 0,
                'avg_shipping_fee' => $raw ? (float) $raw->avg_shipping_fee : 0,
            ];
        }

        // 2. RajaOngkir Breakdown: Details for 3PL couriers
        $rajaongkirBreakdownRaw = Transaction::whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereNotIn('shipping_courier', ['store_courier', 'self_pickup'])
            ->whereNotNull('shipping_courier')
            ->where('shipping_courier', '!=', '')
            ->selectRaw("
                shipping_courier as courier_code,
                COUNT(id) as total_orders,
                SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status IN ('diproses', 'dikemas', 'dikirim', 'selesai') THEN shipping_fee ELSE 0 END) as shipping_fees,
                SUM(CASE WHEN status IN ('diproses', 'dikemas', 'dikirim', 'selesai') THEN grand_total ELSE 0 END) as total_sales
            ")
            ->groupBy('shipping_courier')
            ->get();

        $courierNames = Courier::pluck('name', 'code')->toArray();
        $rajaongkirBreakdown = $rajaongkirBreakdownRaw->map(function ($row) use ($courierNames) {
            $code = strtolower($row->courier_code);

            return [
                'courier_code' => $row->courier_code,
                'name' => $courierNames[$code] ?? strtoupper($row->courier_code),
                'total_orders' => (int) $row->total_orders,
                'completed_count' => (int) $row->completed_count,
                'shipping_fees' => (float) $row->shipping_fees,
                'total_sales' => (float) $row->total_sales,
            ];
        })->sortByDesc('total_orders')->values()->toArray();

        // 3. Store Courier Performance: Drivers list and their metrics
        try {
            $driverIds = User::role('Kurir Toko')->pluck('id')->toArray();
        } catch (RoleDoesNotExist $e) {
            $driverIds = [];
        }

        $driver = DB::connection()->getDriverName();
        $diffExpr = $driver === 'sqlite'
            ? "(strftime('%s', delivery_arrived_at) - strftime('%s', created_at)) / 3600.0"
            : 'EXTRACT(EPOCH FROM (delivery_arrived_at - created_at)) / 3600.0';

        $performanceData = collect();
        if (! empty($driverIds)) {
            $performanceData = Transaction::whereIn('courier_user_id', $driverIds)
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw("
                    courier_user_id,
                    COUNT(id) as total_assigned,
                    SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN status IN ('diproses', 'dikemas', 'out_for_pickup', 'dikirim') THEN 1 ELSE 0 END) as active_count,
                    SUM(CASE WHEN status = 'batal' THEN 1 ELSE 0 END) as cancelled_count,
                    AVG(CASE WHEN delivery_arrived_at IS NOT NULL THEN {$diffExpr} ELSE NULL END) as avg_delivery_hours
                ")
                ->groupBy('courier_user_id')
                ->get()
                ->keyBy('courier_user_id');
        }

        try {
            $courierPerformance = User::role('Kurir Toko')->get()->map(function ($user) use ($performanceData) {
                $perf = $performanceData->get($user->id);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'total_assigned' => $perf ? (int) $perf->total_assigned : 0,
                    'completed_count' => $perf ? (int) $perf->completed_count : 0,
                    'active_count' => $perf ? (int) $perf->active_count : 0,
                    'cancelled_count' => $perf ? (int) $perf->cancelled_count : 0,
                    'avg_delivery_hours' => $perf && $perf->avg_delivery_hours !== null ? round((float) $perf->avg_delivery_hours, 1) : null,
                ];
            })->toArray();
        } catch (RoleDoesNotExist $e) {
            $courierPerformance = [];
        }

        // 4. Recent Deliveries: latest 20 shipments
        $recentDeliveries = Transaction::with(['user:id,name', 'courierUser:id,name', 'customerAddress'])
            ->whereNotNull('shipping_courier')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest()
            ->limit(20)
            ->get()
            ->map(function ($tx) use ($courierNames) {
                $code = strtolower($tx->shipping_courier);

                return [
                    'id' => $tx->id,
                    'transaction_number' => $tx->transaction_number,
                    'shipping_courier' => $tx->shipping_courier,
                    'courier_name' => $tx->shipping_courier === 'store_courier' ? 'Kurir Toko' : ($tx->shipping_courier === 'self_pickup' ? 'Ambil di Toko' : ($courierNames[$code] ?? strtoupper($tx->shipping_courier))),
                    'shipping_service' => $tx->shipping_service,
                    'tracking_number' => $tx->tracking_number,
                    'booking_code' => $tx->booking_code,
                    'status' => $tx->status,
                    'created_at' => $tx->created_at->toISOString(),
                    'user_name' => $tx->user?->name ?? 'Guest',
                    'courier_driver_name' => $tx->courierUser?->name,
                    'address' => $tx->customerAddress ? [
                        'name' => $tx->customerAddress->name,
                        'phone' => $tx->customerAddress->phone,
                        'address_line' => $tx->customerAddress->address_line,
                        'city' => $tx->customerAddress->city_name ?? $tx->customerAddress->city,
                    ] : null,
                ];
            })->toArray();

        // Calculate overall metrics
        $metrics = [
            'total_shipping_revenue' => array_sum(array_column($shippingSummary, 'shipping_fees')),
            'total_delivery_volume' => array_sum(array_column($shippingSummary, 'total_orders')),
            'completed_deliveries' => array_sum(array_column($shippingSummary, 'completed_count')),
            'cancelled_deliveries' => array_sum(array_column($shippingSummary, 'cancelled_count')),
        ];

        return Inertia::render('Admin/Reports/Couriers', [
            'metrics' => $metrics,
            'shippingSummary' => array_values($shippingSummary),
            'rajaongkirBreakdown' => $rajaongkirBreakdown,
            'courierPerformance' => $courierPerformance,
            'recentDeliveries' => $recentDeliveries,
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'preset' => $preset,
            ],
        ]);
    }

    /**
     * Review reports: listed reviews with anonymous/reported flags.
     */
    public function reviews(Request $request): Response
    {
        [$dateFrom, $dateTo, $preset] = $this->getDateRange($request);

        $search = $request->input('search');
        $ratingFilter = $request->input('rating');
        $reportedOnly = $request->boolean('reported_only');
        $anonymousOnly = $request->boolean('anonymous_only');

        $query = ProductReview::with(['user', 'product', 'productVariant.options'])
            ->withTrashed()
            ->whereBetween('product_reviews.created_at', [$dateFrom->startOfDay(), $dateTo->copy()->endOfDay()]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'ilike', "%{$search}%"))
                    ->orWhere('comment', 'ilike', "%{$search}%");
            });
        }

        if ($ratingFilter) {
            $query->where('rating', (int) $ratingFilter);
        }

        if ($reportedOnly) {
            $query->where('is_reported', true);
        }

        if ($anonymousOnly) {
            $query->where('is_anonymous', true);
        }

        $reviews = $query->latest('product_reviews.created_at')->paginate(20)->withQueryString();

        /** @var array<int,int> $ratingDistribution */
        $ratingDistribution = ProductReview::selectRaw('rating, count(*) as total')
            ->whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->copy()->endOfDay()])
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->toArray();

        $totalReviews = ProductReview::whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->copy()->endOfDay()])->count();
        $reportedCount = ProductReview::whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->copy()->endOfDay()])->where('is_reported', true)->count();
        $anonymousCount = ProductReview::whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->copy()->endOfDay()])->where('is_anonymous', true)->count();
        $avgRating = ProductReview::whereBetween('created_at', [$dateFrom->startOfDay(), $dateTo->copy()->endOfDay()])->avg('rating');

        return Inertia::render('Admin/Reports/Reviews', [
            'reviews' => $reviews,
            'summary' => [
                'total' => $totalReviews,
                'reported' => $reportedCount,
                'anonymous' => $anonymousCount,
                'avg_rating' => round((float) $avgRating, 2),
            ],
            'ratingDistribution' => $ratingDistribution,
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
                'preset' => $preset,
                'search' => $search,
                'rating' => $ratingFilter,
                'reported_only' => $reportedOnly,
                'anonymous_only' => $anonymousOnly,
            ],
        ]);
    }
}
