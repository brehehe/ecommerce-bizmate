<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KitchenController extends Controller
{
    /**
     * Display Kitchen Display System (KDS) board.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSeller = $user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin']);

        $query = Transaction::with([
            'items.product.images',
            'items.productVariant',
            'user:id,name,phone_number,email',
            'customerAddress',
            'paymentMethod:id,name,type',
            'restoProfile',
        ])
            ->whereNotIn('status', ['batal', 'kadaluarsa']);

        if ($isSeller) {
            $query->where(function ($q) use ($user) {
                $q->where('resto_id', $user->id)
                    ->orWhereHas('items.product', fn ($pq) => $pq->where('user_id', $user->id));
            });
        } elseif ($request->filled('resto_id')) {
            $query->where('resto_id', $request->input('resto_id'));
        }

        // Only show orders from today or orders that are still active (not yet completed)
        $today = Carbon::today();
        $query->where(function ($q) use ($today) {
            $q->whereDate('created_at', '>=', $today)
                ->orWhereNotIn('kitchen_status', ['selesai']);
        });

        $transactions = $query->orderBy('created_at', 'asc')->get();

        // Categorize into tickets
        $tickets = [
            'pesanan_masuk' => [],
            'dimasak' => [],
            'siap_diambil' => [],
            'selesai' => [],
        ];

        foreach ($transactions as $trx) {
            $status = $trx->kitchen_status ?: 'pesanan_masuk';
            if (! isset($tickets[$status])) {
                $status = 'pesanan_masuk';
            }

            $tickets[$status][] = [
                'id' => $trx->id,
                'transaction_number' => $trx->transaction_number,
                'customer_name' => $trx->user?->name ?? 'Pelanggan',
                'customer_phone' => $trx->user?->phone_number,
                'fulfillment_type' => $trx->fulfillment_type ?? ($trx->shipping_courier === 'self_pickup' ? 'self_pickup' : 'store_courier'),
                'pickup_type' => $trx->pickup_type ?? 'takeaway',
                'table_number' => $trx->table_number,
                'scheduled_date' => $trx->scheduled_date,
                'scheduled_time_slot' => $trx->scheduled_time_slot,
                'status' => $trx->status,
                'kitchen_status' => $status,
                'grand_total' => (float) $trx->grand_total,
                'created_at' => $trx->created_at->toISOString(),
                'created_at_formatted' => $trx->created_at->format('H:i').' WIB',
                'elapsed_minutes' => (int) $trx->created_at->diffInMinutes(now()),
                'items' => $trx->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->product_name ?? $item->product?->name,
                    'quantity' => $item->quantity,
                    'variant_name' => $item->variant_name,
                    'note' => $item->note,
                    'selected_modifiers' => $item->selected_modifiers,
                ]),
                'notes' => $trx->notes,
                'resto_name' => $trx->restoProfile?->resto_name ?? $trx->resto?->store_name,
            ];
        }

        // Resto filter options for Super Admin
        $restos = [];
        if (! $isSeller) {
            $restos = User::where('is_seller', true)
                ->select('id', 'name', 'store_name')
                ->orderBy('store_name')
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->store_name ?: $u->name,
                ]);
        }

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');

        return Inertia::render('Admin/Kitchen/Index', [
            'tickets' => $tickets,
            'counts' => [
                'incoming' => count($tickets['pesanan_masuk']),
                'cooking' => count($tickets['dimasak']),
                'ready' => count($tickets['siap_diambil']),
                'completed' => count($tickets['selesai']),
                'total_active' => count($tickets['pesanan_masuk']) + count($tickets['dimasak']) + count($tickets['siap_diambil']),
            ],
            'isSeller' => $isSeller,
            'selectedRestoId' => $request->input('resto_id'),
            'restos' => $restos,
            'storeName' => $storeName,
        ]);
    }

    /**
     * Update kitchen status for a transaction ticket.
     */
    public function updateStatus(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($request->isMethod('get')) {
            return redirect()->route('admin.kitchen.index');
        }

        $validated = $request->validate([
            'kitchen_status' => 'required|string|in:pesanan_masuk,dimasak,siap_diambil,selesai',
        ]);

        $user = $request->user();
        $isSeller = $user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin']);

        if ($isSeller && $transaction->resto_id !== $user->id) {
            $hasItem = $transaction->items()->whereHas('product', fn ($q) => $q->where('user_id', $user->id))->exists();
            if (! $hasItem) {
                return redirect()->route('admin.kitchen.index')->with('error', 'Anda tidak memiliki hak akses untuk pesanan ini.');
            }
        }

        $newStatus = $validated['kitchen_status'];
        $transaction->update(['kitchen_status' => $newStatus]);

        $statusLabels = [
            'pesanan_masuk' => 'Pesanan Masuk',
            'dimasak' => 'Sedang Dimasak',
            'siap_diambil' => 'Siap Diambil / Diantar',
            'selesai' => 'Selesai',
        ];

        // Synchronize with transaction status
        if ($newStatus === 'dimasak' && in_array($transaction->status, ['belum_bayar', 'sudah_bayar', 'menunggu_konfirmasi'])) {
            $transaction->update(['status' => 'diproses']);
            $transaction->statusHistories()->create([
                'status' => 'diproses',
                'description' => 'Dapur resto mulai memasak pesanan makanan.',
                'created_by' => $user->id,
            ]);
        } elseif ($newStatus === 'siap_diambil') {
            $courier = $transaction->shipping_courier;
            $newTrxStatus = ($courier === 'self_pickup') ? 'ready_for_pickup' : 'dikemas';

            $transaction->update(['status' => $newTrxStatus]);
            $transaction->statusHistories()->create([
                'status' => $newTrxStatus,
                'description' => ($courier === 'self_pickup')
                    ? 'Makanan selesai dimasak dan siap diambil di outlet resto.'
                    : 'Makanan selesai dimasak & dikemas, menunggu penjemputan kurir.',
                'created_by' => $user->id,
            ]);
        } elseif ($newStatus === 'selesai' && $transaction->status !== 'selesai') {
            $isCod = str_contains(strtolower($transaction->paymentMethod?->name ?? ''), 'cod')
                || str_contains(strtolower($transaction->paymentMethod?->name ?? ''), 'bayar di tempat');

            $updateData = ['status' => 'selesai'];
            if ($isCod) {
                $updateData['payment_status'] = 'paid';
                if ($transaction->payment) {
                    $transaction->payment->update([
                        'status' => 'confirmed',
                        'confirmed_at' => now(),
                    ]);
                }
            }

            $transaction->update($updateData);
            $transaction->statusHistories()->create([
                'status' => 'selesai',
                'description' => $isCod
                    ? 'Pesanan makanan telah diserahkan di kasir resto & pembayaran tunai (COD) sebesar Rp '.number_format((float) $transaction->grand_total, 0, ',', '.').' telah lunas diterima.'
                    : 'Pesanan makanan telah diserahkan dan selesai.',
                'created_by' => $user->id,
            ]);
        }

        $previousUrl = url()->previous();
        if ($previousUrl && ! str_contains($previousUrl, 'update-status')) {
            return redirect()->to($previousUrl)->with('success', "Status tiket #{$transaction->transaction_number} berhasil diperbarui: {$statusLabels[$newStatus]}");
        }

        return redirect()->route('admin.kitchen.index')->with('success', "Status tiket #{$transaction->transaction_number} berhasil diperbarui: {$statusLabels[$newStatus]}");
    }

    /**
     * Print thermal kitchen slip or cashier receipt.
     */
    public function printReceipt(Request $request, Transaction $transaction)
    {
        $user = $request->user();
        $isSeller = $user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin']);

        if ($isSeller) {
            $isOwner = $transaction->resto_id === $user->id ||
                $transaction->items()->whereHas('product', fn ($q) => $q->where('user_id', $user->id))->exists();
            if (! $isOwner) {
                abort(403, 'Anda tidak memiliki hak akses untuk mencetak struk transaksi ini.');
            }
        }

        $transaction->load([
            'items.product',
            'items.productVariant',
            'user:id,name,phone_number,email',
            'customerAddress',
            'paymentMethod',
            'restoProfile',
            'resto',
        ]);

        $format = $request->query('format', '58mm');
        if (! in_array($format, ['58mm', '80mm'])) {
            $format = '58mm';
        }

        $type = $request->query('type', 'kitchen');
        if (! in_array($type, ['kitchen', 'cashier'])) {
            $type = 'kitchen';
        }

        $autoPrint = $request->boolean('autoprint', true);
        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');

        return view('print.thermal-receipt', compact('transaction', 'format', 'type', 'autoPrint', 'storeName'));
    }
}
