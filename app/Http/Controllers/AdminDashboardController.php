<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\AdminDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected AdminDashboardService $dashboardService
    ) {}

    /**
     * Display the admin dashboard with real-time statistics.
     */
    public function index(Request $request): Response
    {
        @set_time_limit(300);

        $filter = $request->input('filter', '7_hari');
        [$dateFrom, $dateTo, $prevDateFrom, $prevDateTo] = $this->dashboardService->getDateRange($filter);

        $user = $request->user();
        $isSeller = $user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin']);
        $isAdminOrSuperAdmin = $user && $user->hasAnyRole(['Super Admin', 'Admin']);
        $userId = $user ? $user->id : 'guest';

        $sellerProductIds = $isSeller ? DB::table('products')->where('user_id', $userId)->pluck('id') : collect([]);
        $sellerTransactionIds = $isSeller ? DB::table('transaction_items')->whereIn('product_id', $sellerProductIds)->pluck('transaction_id') : collect([]);

        $search = trim((string) $request->input('search', ''));
        $driver = DB::connection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ilike' : 'like';

        // Stock Overview
        $stockQuery = DB::table('products')
            ->leftJoin('product_stocks', function ($join) {
                $join->on('products.id', '=', 'product_stocks.product_id')
                    ->whereNull('product_stocks.product_variant_id');
            })
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->when($isSeller, fn ($query) => $query->where('products.user_id', $userId))
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

        $visitorPage = (int) $request->input('visitor_page', 1);

        return Inertia::render('Admin/Dashboard', [
            'isSeller' => $isSeller,
            'stats' => $getDeferred(fn () => $this->dashboardService->getKpis($filter, $dateFrom, $dateTo, $prevDateFrom, $prevDateTo, $isSeller, $userId, $sellerProductIds, $sellerTransactionIds)['stats'], 'kpi_stats'),
            'visitorStats' => $getDeferred(fn () => $this->dashboardService->getVisitorStats($filter, $dateFrom, $dateTo, $prevDateFrom, $prevDateTo, $isSeller, $userId, $search, $likeOperator), 'visitor_stats'),
            'topVisitedPages' => $getDeferred(fn () => $this->dashboardService->getTopVisitedPages($filter, $dateFrom, $dateTo, $isSeller, $userId, $search, $likeOperator), 'visitor_stats'),
            'orderStats' => $getDeferred(fn () => $this->dashboardService->getPipeline($isSeller, $userId, $sellerTransactionIds)['orderStats'], 'pipeline_stats'),
            'recentOrders' => $getDeferred(fn () => $this->dashboardService->getRecentOrders($isSeller, $userId, $sellerTransactionIds, $filter, $dateFrom, $dateTo, $search, $likeOperator), 'recent_orders'),
            'topProducts' => $getDeferred(fn () => $this->dashboardService->getTopProducts($filter, $dateFrom, $dateTo, $isSeller, $userId, $sellerProductIds, $search, $likeOperator), 'top_products'),
            'chartData' => $getDeferred(fn () => $this->dashboardService->getChartData($isSeller, $userId, $sellerTransactionIds), 'chart_data'),
            'currentFilter' => $filter,
            'productStockInfo' => $productStockInfo,
            'recentStockOut' => $getDeferred(fn () => $this->dashboardService->getRecentStockOut($isSeller, $userId, $sellerProductIds, $filter, $dateFrom, $dateTo, $search, $likeOperator), 'recent_stock_out'),
            'recentCustomers' => $getDeferred(fn () => $this->dashboardService->getRecentCustomers($isSeller, $filter, $dateFrom, $dateTo, $search, $likeOperator), 'recent_customers'),
            'search' => $search,
            'refundStats' => $getDeferred(fn () => $this->dashboardService->getKpis($filter, $dateFrom, $dateTo, $prevDateFrom, $prevDateTo, $isSeller, $userId, $sellerProductIds, $sellerTransactionIds)['refundStats'], 'kpi_stats'),
            'returnStats' => $getDeferred(fn () => $this->dashboardService->getKpis($filter, $dateFrom, $dateTo, $prevDateFrom, $prevDateTo, $isSeller, $userId, $sellerProductIds, $sellerTransactionIds)['returnStats'], 'kpi_stats'),
            'refundPipeline' => $getDeferred(fn () => $this->dashboardService->getPipeline($isSeller, $userId, $sellerTransactionIds)['refundPipeline'], 'pipeline_stats'),
            'returnPipeline' => $getDeferred(fn () => $this->dashboardService->getPipeline($isSeller, $userId, $sellerTransactionIds)['returnPipeline'], 'pipeline_stats'),
            'recentRefunds' => $getDeferred(fn () => $this->dashboardService->getRecentRefunds($isSeller, $userId, $sellerTransactionIds, $filter, $dateFrom, $dateTo, $search, $likeOperator), 'recent_refunds'),
            'recentReturns' => $getDeferred(fn () => $this->dashboardService->getRecentReturns($isSeller, $userId, $sellerTransactionIds, $filter, $dateFrom, $dateTo, $search, $likeOperator), 'recent_returns'),
            'visitorIpLogs' => $isAdminOrSuperAdmin ? $getDeferred(fn () => $this->dashboardService->getVisitorIpLogs($filter, $dateFrom, $dateTo, $search, $likeOperator, $visitorPage), 'visitor_stats') : null,
            'ipTrafficAnalytics' => $isAdminOrSuperAdmin ? $getDeferred(fn () => $this->dashboardService->getIpTrafficAnalytics($filter, $dateFrom, $dateTo, $search, $likeOperator), 'visitor_stats') : null,
        ]);
    }
}
