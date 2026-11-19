<?php

namespace App\Http\Controllers\Kurir;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KurirDashboardController extends Controller
{
    /**
     * Display the courier dashboard with all store_courier transactions.
     */
    public function index(Request $request): Response
    {
        $tab = $request->input('tab', 'my_tasks');
        $courierId = auth()->id();

        $query = Transaction::with([
            'user:id,name,email,phone_number',
            'customerAddress',
            'items.product:id,name',
            'items.product.images:id,product_id,path,is_main',
            'restoProfile:id,user_id,resto_name,address,latitude,longitude,logo_path',
            'paymentMethod:id,name,type',
        ])
            ->where(function ($q) {
                $q->where('shipping_courier', 'store_courier')
                    ->orWhere('fulfillment_type', 'store_courier');
            })
            ->latest();

        if ($tab === 'my_tasks') {
            $query->where('courier_user_id', $courierId)
                ->whereNotIn('status', ['selesai', 'batal']);
        } elseif ($tab === 'available') {
            $query->whereNull('courier_user_id')
                ->whereIn('status', ['diproses', 'dimasak', 'dikemas', 'siap_diambil']);
        } elseif ($tab === 'history') {
            $query->where('courier_user_id', $courierId)
                ->whereIn('status', ['selesai', 'batal']);
        }

        // Search by transaction number, booking code, tracking number
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'ilike', "%{$search}%")
                    ->orWhere('booking_code', 'ilike', "%{$search}%")
                    ->orWhere('tracking_number', 'ilike', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'ilike', "%{$search}%");
                    });
            });
        }

        $transactions = $query->paginate(20)->withQueryString();

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Kurir/Dashboard', [
            'transactions' => $transactions,
            'statusLabels' => Transaction::statusLabels(),
            'filters' => $request->only(['search', 'tab']),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Display a single transaction detail for the courier.
     */
    public function show(Transaction $transaction): Response|RedirectResponse
    {
        // Couriers can only view store_courier transactions
        if ($transaction->shipping_courier !== 'store_courier' && $transaction->fulfillment_type !== 'store_courier') {
            return redirect()->route('kurir.dashboard');
        }

        $transaction->load([
            'user:id,name,email,phone_number',
            'customerAddress',
            'items.product:id,name',
            'items.product.images:id,product_id,path,is_main',
            'statusHistories',
            'courierUser',
            'restoProfile:id,user_id,resto_name,address,latitude,longitude,logo_path',
            'paymentMethod:id,name,type',
        ]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Kurir/TransactionDetail', [
            'transaction' => $transaction,
            'statusLabels' => Transaction::statusLabels(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Find a transaction by number, booking code, or tracking number (JSON).
     */
    public function scan(string $number): JsonResponse
    {
        $transaction = Transaction::where(function ($q) use ($number) {
            $q->where('transaction_number', $number)
                ->orWhere('booking_code', $number)
                ->orWhere('tracking_number', $number);
        })
            ->where(function ($q) {
                $q->where('shipping_courier', 'store_courier')
                    ->orWhere('fulfillment_type', 'store_courier');
            })
            ->first();

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan.',
            ], 404);
        }

        // If the scanned order is out_for_pickup, mark as picked up and transition to dikirim
        if ($transaction->status === 'out_for_pickup') {
            $updateData = [
                'status' => 'dikirim',
                'courier_user_id' => auth()->id(),
            ];

            if (empty($transaction->tracking_number)) {
                $updateData['tracking_number'] = 'RSI-'.str_replace('TRX-', '', $transaction->transaction_number).'-'.now()->format('Ymd');
            }

            $transaction->update($updateData);

            $transaction->statusHistories()->create([
                'status' => 'dikirim',
                'description' => 'Pesanan telah dipickup oleh kurir toko dan status berubah menjadi Dikirim.',
                'created_by' => auth()->id(),
            ]);
        }

        return response()->json([
            'success' => true,
            'id' => $transaction->id,
            'redirect_url' => "/kurir/transactions/{$transaction->id}",
        ]);
    }
}
