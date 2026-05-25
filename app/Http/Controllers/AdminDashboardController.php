<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        $stats = [
            'revenueFormatted' => 'Rp 45.2M',
            'ordersCount' => 1204,
            'activeProductsCount' => 342,
            'customersCount' => 8591,
        ];

        $orderStats = [
            'newCount' => 12,
            'readyCount' => 8,
            'shippingCount' => 22,
        ];

        $recentOrders = [
            [
                'id' => '#ORD-9021',
                'customer' => 'Ahmad Subagyo',
                'email' => 'ahmad@email.com',
                'initials' => 'AS',
                'date' => '19 Mei 2026, 14:30',
                'amount' => 1899000,
                'status' => 'Paid',
            ],
            [
                'id' => '#ORD-9020',
                'customer' => 'Diana Maharani',
                'email' => 'diana@email.com',
                'initials' => 'DM',
                'date' => '19 Mei 2026, 11:15',
                'amount' => 4500000,
                'status' => 'Pending',
            ],
            [
                'id' => '#ORD-9019',
                'customer' => 'Budi Kurniawan',
                'email' => 'budi.k@email.com',
                'initials' => 'BK',
                'date' => '18 Mei 2026, 09:45',
                'amount' => 850000,
                'status' => 'Refunded',
            ],
        ];

        $topProducts = [
            [
                'name' => 'Kursi Santai Telur',
                'category' => 'Mebel Outdoor',
                'image' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?auto=format&fit=crop&w=150&q=80',
                'sales' => 452,
            ],
            [
                'name' => 'Sofa Premium Velvet',
                'category' => 'Kursi & Sofa',
                'image' => 'https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?auto=format&fit=crop&w=150&q=80',
                'sales' => 315,
            ],
            [
                'name' => 'Meja Kopi Jati',
                'category' => 'Meja & Rak',
                'image' => 'https://images.unsplash.com/photo-1533090161767-e6ffed986c88?auto=format&fit=crop&w=150&q=80',
                'sales' => 289,
            ],
        ];

        $chartData = [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            'data' => [25, 32, 28, 45, 60, 92],
        ];

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'orderStats' => $orderStats,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'chartData' => $chartData,

        ]);
    }
}
