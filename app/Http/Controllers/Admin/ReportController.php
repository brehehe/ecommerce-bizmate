<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Get date range from request or set defaults (last 30 days).
     */
    protected function getDateRange(Request $request): array
    {
        $dateFrom = $request->input('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();

        $dateTo = $request->input('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();

        return [$dateFrom, $dateTo];
    }

    /**
     * Laporan Penjualan (Sales Report)
     */
    public function sales(Request $request): Response
    {
        [$dateFrom, $dateTo] = $this->getDateRange($request);
        $paidStatuses = ['diproses', 'dikemas', 'dikirim', 'selesai'];

        // Metrik Utama
        $metrics = Transaction::whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('
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
            ->sum('transaction_items.quantity');

        // Tren Penjualan Harian
        $salesTrend = Transaction::whereIn('status', $paidStatuses)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
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

        // Distribusi Metode Pembayaran
        $paymentDistribution = Transaction::whereIn('status', $paidStatuses)
            ->whereBetween('transactions.created_at', [$dateFrom, $dateTo])
            ->join('payment_methods', 'transactions.payment_method_id', '=', 'payment_methods.id')
            ->selectRaw('payment_methods.name as name, COUNT(transactions.id) as count, SUM(transactions.grand_total) as revenue')
            ->groupBy('payment_methods.name')
            ->get();

        // Distribusi Status Pesanan
        $statusDistribution = Transaction::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('status, COUNT(id) as count')
            ->groupBy('status')
            ->get();

        // Format data untuk grafik
        $chartData = [
            'labels' => $salesTrend->map(fn ($t) => Carbon::parse($t->date)->format('d M Y'))->toArray(),
            'revenue' => $salesTrend->map(fn ($t) => (float) $t->net_sales)->toArray(),
            'orders' => $salesTrend->map(fn ($t) => (int) $t->order_count)->toArray(),
        ];

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
            'paymentDistribution' => $paymentDistribution,
            'statusDistribution' => $statusDistribution,
            'chartData' => $chartData,
            'filters' => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to' => $dateTo->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Laporan Penjualan Produk (Product Sales Report)
     */
    public function products(Request $request): Response
    {
        [$dateFrom, $dateTo] = $this->getDateRange($request);
        $paidStatuses = ['diproses', 'dikemas', 'dikirim', 'selesai'];

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
        [$dateFrom, $dateTo] = $this->getDateRange($request);
        $paidStatuses = ['diproses', 'dikemas', 'dikirim', 'selesai'];

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
            ],
        ]);
    }

    /**
     * Laporan Pelanggan (Customer Report)
     */
    public function customers(Request $request): Response
    {
        [$dateFrom, $dateTo] = $this->getDateRange($request);
        $paidStatuses = ['diproses', 'dikemas', 'dikirim', 'selesai'];

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
                'search' => $request->search,
            ],
        ]);
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

        // Clone untuk Metrik sebelum pagination
        $allStocksData = (clone $unionQuery)->get();

        $totalSku = $allStocksData->count();
        $totalStock = $allStocksData->where('is_unlimited', false)->sum('stock');

        $totalAssetCost = $allStocksData->reduce(function ($carry, $item) {
            if ($item->is_unlimited) {
                return $carry;
            }

            return $carry + ($item->stock * $item->cost);
        }, 0);

        $totalRetailValue = $allStocksData->reduce(function ($carry, $item) {
            if ($item->is_unlimited) {
                return $carry;
            }

            return $carry + ($item->stock * $item->price);
        }, 0);

        $potentialProfit = $totalRetailValue - $totalAssetCost;

        $lowStockCount = $allStocksData->where('is_unlimited', false)->filter(function ($item) {
            return $item->stock <= $item->min_stock;
        })->count();

        // Paginated output
        $stocks = $unionQuery->orderBy('all_stocks.stock', 'asc')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Reports/Stocks', [
            'stocks' => $stocks,
            'metrics' => [
                'total_sku' => $totalSku,
                'total_stock' => $totalStock,
                'total_asset_value' => (float) $totalAssetCost,
                'total_retail_value' => (float) $totalRetailValue,
                'potential_profit' => (float) $potentialProfit,
                'low_stock_count' => $lowStockCount,
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
        [$dateFrom, $dateTo] = $this->getDateRange($request);
        $paidStatuses = ['diproses', 'dikemas', 'dikirim', 'selesai'];
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
            'fast'   => ['count' => 0, 'qty' => 0, 'value' => 0.0],
            'medium' => ['count' => 0, 'qty' => 0, 'value' => 0.0],
            'slow'   => ['count' => 0, 'qty' => 0, 'value' => 0.0],
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
                'type' => $type,
            ],
        ]);
    }
}
