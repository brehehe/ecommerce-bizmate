<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Transaction\SyncShipmentTrackingStatusAction;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\BiteshipService;
use App\Services\KomerceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KomerceShipmentController extends Controller
{
    public function __construct(private readonly SyncShipmentTrackingStatusAction $syncShipmentTrackingStatus) {}

    /**
     * Store/book shipment.
     */
    public function storeShipment(Transaction $transaction)
    {
        if (! config('app.pickup_enabled', true)) {
            return back()->with('error', 'Layanan pengiriman API sedang dinonaktifkan.');
        }

        if (BiteshipService::isEnabled()) {
            $address = $transaction->customerAddress;
            if (! $address) {
                return back()->with('error', 'Alamat pengiriman tidak ditemukan.');
            }

            Log::info('Biteship Shipment Store Action for Transaction: '.$transaction->transaction_number);

            $response = BiteshipService::storeShipment($transaction);

            if (isset($response['success']) && $response['success']) {
                $data = $response['data'];

                $bookingCode = $data['booking_code'] ?? null;
                $trackingNumber = $data['airway_bill'] ?? $transaction->tracking_number;

                // Save booking details to transaction
                $transaction->update([
                    'booking_code' => $bookingCode,
                    'tracking_number' => $trackingNumber,
                    'status' => 'dikemas', // Change transaction status to packaging (dikemas)
                ]);

                return back()->with('success', 'Pengiriman berhasil dipesan ke Biteship. Kode Booking: '.($bookingCode ?? ''));
            }

            $errorMsg = $response['error'] ?? 'Gagal membuat pesanan pengiriman di Biteship.';

            return back()->with('error', $errorMsg);
        }

        if (! KomerceService::isDeliveryEnabled()) {
            return back()->with('error', 'Layanan Komerce Shipping Delivery belum diaktifkan di pengaturan.');
        }

        $address = $transaction->customerAddress;
        if (! $address) {
            return back()->with('error', 'Alamat pengiriman tidak ditemukan.');
        }

        Log::info('Komerce Shipment Store Action for Transaction: '.$transaction->transaction_number);

        $response = KomerceService::storeShipment($transaction);

        if (isset($response['success']) && $response['success']) {
            $data = $response['data'];

            $bookingCode = $data['booking_code'] ?? $data['order_no'] ?? null;
            $trackingNumber = $data['airway_bill'] ?? $data['awb'] ?? $data['tracking_number'] ?? $transaction->tracking_number;

            // Save booking details to transaction
            $transaction->update([
                'booking_code' => $bookingCode,
                'tracking_number' => $trackingNumber,
                'status' => 'dikemas', // Change transaction status to packaging (dikemas)
            ]);

            return back()->with('success', 'Pengiriman berhasil dipesan ke Komerce. Kode Booking: '.($bookingCode ?? ''));
        }

        $errorMsg = $response['error'] ?? 'Gagal membuat pesanan pengiriman di Komerce.';

        return back()->with('error', $errorMsg);
    }

    /**
     * Request courier pickup.
     */
    public function requestPickup(Transaction $transaction, Request $request)
    {
        if (! config('app.pickup_enabled', true)) {
            return back()->with('error', 'Layanan pengiriman API sedang dinonaktifkan.');
        }

        if (! in_array($transaction->status, ['diproses', 'dikemas', 'out_for_pickup'])) {
            return back()->with('error', 'Pesanan tidak eligible untuk pickup (status harus Diproses, Dikemas, atau Out for Pickup).');
        }

        if (! $transaction->booking_code) {
            return back()->with('error', 'Kode booking pengiriman tidak ditemukan. Silakan pesan pengiriman terlebih dahulu.');
        }

        if (BiteshipService::isEnabled()) {
            // Biteship orders schedule pickup automatically, but we allow manual status transition for UI consistency.
            $transaction->update([
                'status' => 'out_for_pickup',
            ]);

            return back()->with('success', 'Request pickup berhasil diajukan secara otomatis melalui Biteship.');
        }

        $request->validate([
            'pickup_time' => 'required|string', // e.g. "2026-06-04 14:00:00"
            'vehicle_type' => 'nullable|string', // e.g. "motorcycle", "car", "truck"
        ]);

        // 1. Validate pickup time is at least 90 minutes from now or order creation time (whichever is later)
        $pickupTime = Carbon::parse($request->pickup_time);
        $createdAt = $transaction->created_at;
        $now = now();
        $baseTime = $now->gt($createdAt) ? $now : $createdAt;

        if ($pickupTime->lt($baseTime->copy()->addMinutes(90))) {
            return back()->withErrors([
                'pickup_time' => 'Waktu pickup harus minimal 90 menit dari sekarang atau waktu pembuatan transaksi (mana yang lebih baru).',
            ]);
        }

        // 2. Calculate total weight of the order items
        $transaction->loadMissing(['items.product', 'items.productVariant']);
        $totalWeight = 0; // in grams
        foreach ($transaction->items as $item) {
            $product = $item->product;
            if ($product && $product->is_digital) {
                continue;
            }
            $variant = $item->productVariant;
            $qty = (int) $item->quantity;

            $weight = 1000;
            if ($variant && $variant->weight > 0) {
                $weight = (int) $variant->weight;
            } elseif ($product && $product->weight > 0) {
                $weight = (int) $product->weight;
            }

            $totalWeight += $weight * $qty;
        }

        // 3. Validate vehicle type based on weight constraints
        $vehicleType = $request->vehicle_type ?? 'motorcycle';
        if ($totalWeight >= 10000 && $vehicleType !== 'truck') {
            return back()->withErrors([
                'vehicle_type' => 'Pengiriman dengan total berat 10 kg or lebih wajib menggunakan kendaraan Truk.',
            ]);
        }

        if ($vehicleType === 'motorcycle' && $totalWeight > 5000) {
            return back()->withErrors([
                'vehicle_type' => 'Kendaraan Motor hanya dapat memuat paket dengan berat maksimal 5 kg per pesanan.',
            ]);
        }

        $payload = [
            'booking_code' => $transaction->booking_code,
            'pickup_time' => $request->pickup_time,
            'vehicle_type' => $vehicleType,
        ];

        $response = KomerceService::requestPickup($payload);

        if (isset($response['success']) && $response['success']) {
            $awb = null;
            $resData = $response['data'] ?? null;

            if ($resData && isset($resData['data']) && is_array($resData['data'])) {
                $firstItem = $resData['data'][0] ?? null;
                if ($firstItem && is_array($firstItem)) {
                    $awb = $firstItem['awb'] ?? $firstItem['airway_bill'] ?? null;
                }
            }

            if (empty($awb) && $resData && is_array($resData)) {
                $awb = $resData['awb'] ?? $resData['airway_bill'] ?? $resData['tracking_number'] ?? null;
            }

            if (empty($awb)) {
                if (isset($response['simulated']) && $response['simulated']) {
                    $awb = 'KOMERKOM'.$transaction->booking_code;
                } else {
                    $awb = null;
                }
            }

            $transaction->update([
                'tracking_number' => $awb,
                'status' => 'out_for_pickup',
            ]);

            return back()->with('success', 'Request pickup berhasil diajukan ke kurir. Nomor Resi: '.$awb);
        }

        $errorMsg = $response['error'] ?? 'Gagal mengajukan request pickup ke Komerce.';

        return back()->with('error', $errorMsg);
    }

    /**
     * Print Shipping Label.
     */
    public function printLabel(Transaction $transaction)
    {
        if (! $transaction->booking_code) {
            return back()->with('error', 'Kode booking pengiriman tidak ditemukan.');
        }

        if (BiteshipService::isEnabled()) {
            return redirect()->route('admin.transactions.biteship.label', $transaction->id);
        }

        $response = KomerceService::printLabel($transaction->booking_code);

        if (isset($response['success']) && $response['success']) {
            $url = $response['url'];

            return redirect()->away($url);
        }

        $errorMsg = $response['error'] ?? 'Gagal mengambil tautan cetak label.';

        return back()->with('error', $errorMsg);
    }

    /**
     * Render local Biteship Thermal Label view.
     */
    public function biteshipLabel(Transaction $transaction)
    {
        $transaction->load([
            'customerAddress',
            'paymentMethod',
            'items',
        ]);

        $routingCode = null;
        if ($transaction->booking_code) {
            $orderRes = BiteshipService::getOrderDetail($transaction->booking_code);
            if (isset($orderRes['success']) && $orderRes['success']) {
                $routingCode = $orderRes['data']['courier']['routing_code'] ?? null;
            }
        }

        $settings = Setting::whereIn('key', ['store_name', 'store_logo', 'store_phone', 'store_email', 'store_website', 'store_url', 'address', 'regency_name'])
            ->pluck('value', 'key');

        $storeName = $settings->get('store_name') ?? config('app.name');
        $storeLogo = $settings->get('store_logo');
        $storePhone = $settings->get('store_phone') ?? '-';
        $storeAddress = $settings->get('address') ?? 'Gudang Utama BIZMATE';
        $storeCity = $settings->get('regency_name') ?? 'DKI Jakarta';

        $customUrl = $settings->get('store_website') ?? $settings->get('store_url');
        if (! empty($customUrl)) {
            $storeUrl = preg_replace('#^https?://#', '', rtrim($customUrl, '/'));
        } else {
            $host = parse_url(config('app.url'), PHP_URL_HOST);
            if (! empty($host) && ! in_array($host, ['localhost', '127.0.0.1'])) {
                $storeUrl = $host;
            } else {
                $email = $settings->get('store_email');
                $emailDomain = $email ? substr(strrchr($email, '@'), 1) : null;
                if ($emailDomain && ! in_array($emailDomain, ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'])) {
                    $storeUrl = 'www.'.$emailDomain;
                } else {
                    $storeUrl = request()->getHttpHost();
                }
            }
        }

        return view('print.shipping-label', compact(
            'transaction',
            'storeName',
            'storeLogo',
            'storePhone',
            'storeAddress',
            'storeCity',
            'storeUrl',
            'routingCode'
        ));
    }

    /**
     * Cancel Shipment booking.
     */
    public function cancelShipment(Transaction $transaction)
    {
        if (! config('app.pickup_enabled', true)) {
            return back()->with('error', 'Layanan pengiriman API sedang dinonaktifkan.');
        }

        if (! $transaction->booking_code) {
            return back()->with('error', 'Kode booking pengiriman tidak ditemukan.');
        }

        if (BiteshipService::isEnabled()) {
            $response = BiteshipService::cancelShipment($transaction->booking_code);

            if (isset($response['success']) && $response['success']) {
                $transaction->update([
                    'booking_code' => null,
                    'tracking_number' => null,
                ]);

                return back()->with('success', 'Booking pengiriman Biteship berhasil dibatalkan.');
            }

            $errorMsg = $response['error'] ?? 'Gagal membatalkan booking pengiriman di Biteship.';

            return back()->with('error', $errorMsg);
        }

        $response = KomerceService::cancelShipment($transaction->booking_code);

        if (isset($response['success']) && $response['success']) {
            // Clear booking fields on transaction
            $transaction->update([
                'booking_code' => null,
                'tracking_number' => null,
            ]);

            return back()->with('success', 'Booking pengiriman Komerce berhasil dibatalkan.');
        }

        $errorMsg = $response['error'] ?? 'Gagal membatalkan booking pengiriman.';

        return back()->with('error', $errorMsg);
    }

    /**
     * Get real-time tracking history status.
     */
    public function trackShipment(Request $request, Transaction $transaction): JsonResponse
    {
        if (! $request->is('admin/*') && $transaction->user_id !== $request->user()?->id) {
            abort(403);
        }

        if (in_array($transaction->shipping_courier, ['store_courier', 'self_pickup', 'digital'])) {
            $history = $transaction->statusHistories()
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(fn ($h) => [
                    'desc' => $h->description,
                    'date' => $h->created_at?->toIso8601String(),
                    'status' => $h->status,
                ])
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'history' => $history,
                'transaction_status' => $transaction->status,
                'courier_type' => $transaction->shipping_courier,
            ]);
        }

        if (! config('app.pickup_enabled', true)) {
            return response()->json(['error' => 'Layanan pengiriman API sedang dinonaktifkan.'], 403);
        }

        $waybill = $transaction->tracking_number;
        if (! $waybill) {
            return response()->json(['error' => 'Resi pengiriman belum tersedia.'], 400);
        }

        $isBiteshipShipment = BiteshipService::isEnabled()
            && filled($transaction->booking_code)
            && ! str_starts_with(strtoupper($waybill), 'KOMERKOM');

        if ($isBiteshipShipment) {
            $response = BiteshipService::getShipmentHistory($waybill, $transaction->shipping_courier);

            if (isset($response['success']) && $response['success']) {
                if (! ($response['simulated'] ?? false)) {
                    $this->syncShipmentTrackingStatus->execute($transaction, $response['history'] ?? []);
                }

                return response()->json([
                    'success' => true,
                    'history' => $response['history'] ?? [],
                    'transaction_status' => $transaction->fresh()->status,
                ]);
            }

            return response()->json(['error' => $response['error'] ?? 'Gagal melacak status pengiriman dari Biteship.'], 422);
        }

        $response = KomerceService::getShipmentHistory($waybill, $transaction->shipping_courier);

        if (isset($response['success']) && $response['success']) {
            $this->syncShipmentTrackingStatus->execute($transaction, $response['history'] ?? []);

            return response()->json([
                'success' => true,
                'history' => $response['history'] ?? [],
                'transaction_status' => $transaction->fresh()->status,
            ]);
        }

        return response()->json(['error' => $response['error'] ?? 'Gagal melacak status pengiriman.'], 422);
    }

    /**
     * Get detailed order info from Komerce.
     */
    public function getOrderDetail(Transaction $transaction): JsonResponse
    {
        if (! config('app.pickup_enabled', true)) {
            return response()->json(['error' => 'Layanan pengiriman API sedang dinonaktifkan.'], 403);
        }

        if (! $transaction->booking_code) {
            return response()->json(['error' => 'Kode booking pengiriman belum tersedia.'], 400);
        }

        $response = KomerceService::getOrderDetail($transaction->booking_code);

        if (isset($response['success']) && $response['success']) {
            return response()->json([
                'success' => true,
                'data' => $response['data'] ?? null,
            ]);
        }

        return response()->json(['error' => $response['error'] ?? 'Gagal mengambil detail pesanan dari Komerce.'], 422);
    }
}
