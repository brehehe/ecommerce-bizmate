<?php

namespace App\Http\Controllers;

use App\Models\Product;
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
                ->map(fn($n) => strtoupper(substr($n, 0, 1)))
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
                'id' => '#' . $transaction->transaction_number,
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

        $topProducts = [];
        foreach ($topProductsRaw as $item) {
            $productModel = Product::with('images')->find($item->product_id);
            $imageUrl = 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?auto=format&fit=crop&w=150&q=80';
            if ($productModel) {
                if ($productModel->image) {
                    $imageUrl = str_starts_with($productModel->image, 'http')
                        ? $productModel->image
                        : '/' . ltrim($productModel->image, '/');
                } elseif ($productModel->images->first()) {
                    $imageUrl = '/' . ltrim($productModel->images->first()->path, '/');
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

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'orderStats' => $orderStats,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'chartData' => $chartData,
            'currentFilter' => $filter,
        ]);
    }

    /**
     * Format a numerical value as Rupiah with optional suffix.
     */
    private function formatRupiah(float $value): string
    {
        if ($value >= 1_000_000_000_000) {
            return 'Rp ' . number_format($value / 1_000_000_000_000, 1, ',', '.') . ' T';
        }
        if ($value >= 1_000_000_000) {
            return 'Rp ' . number_format($value / 1_000_000_000, 1, ',', '.') . ' M';
        }
        if ($value >= 1_000_000) {
            return 'Rp ' . number_format($value / 1_000_000, 1, ',', '.') . ' Jt';
        }

        return 'Rp ' . number_format($value, 0, ',', '.');
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
            return ['value' => '+' . $formatted . '%', 'type' => 'up'];
        } elseif ($change < 0) {
            return ['value' => '-' . $formatted . '%', 'type' => 'down'];
        }

        return ['value' => '0%', 'type' => 'neutral'];
    }
}
