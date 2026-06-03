<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\KomerceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KomerceShipmentController extends Controller
{
    /**
     * Store/book shipment in Komerce.
     */
    public function storeShipment(Transaction $transaction)
    {
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
                'status' => 'diproses', // Change transaction status to processing
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
        if (! in_array($transaction->status, ['diproses', 'dikemas', 'out_for_pickup'])) {
            return back()->with('error', 'Pesanan tidak eligible untuk pickup (status harus Diproses, Dikemas, atau Out for Pickup).');
        }

        if (! $transaction->booking_code) {
            return back()->with('error', 'Kode booking pengiriman tidak ditemukan. Silakan pesan pengiriman terlebih dahulu.');
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
            $variant = $item->productVariant;
            $product = $item->product;
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
                'vehicle_type' => 'Pengiriman dengan total berat 10 kg atau lebih wajib menggunakan kendaraan Truk.',
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

        $response = KomerceService::printLabel($transaction->booking_code);

        if (isset($response['success']) && $response['success']) {
            $url = $response['url'];

            return redirect()->away($url);
        }

        $errorMsg = $response['error'] ?? 'Gagal mengambil tautan cetak label.';

        return back()->with('error', $errorMsg);
    }

    /**
     * Cancel Shipment booking.
     */
    public function cancelShipment(Transaction $transaction)
    {
        if (! $transaction->booking_code) {
            return back()->with('error', 'Kode booking pengiriman tidak ditemukan.');
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
    public function trackShipment(Transaction $transaction)
    {
        // We can track either by airway bill (tracking_number) or booking_code
        $waybill = $transaction->tracking_number;
        if (! $waybill) {
            return response()->json(['error' => 'Resi pengiriman belum tersedia.'], 400);
        }

        $response = KomerceService::getShipmentHistory($waybill, $transaction->shipping_courier);

        if (isset($response['success']) && $response['success']) {
            return response()->json([
                'success' => true,
                'history' => $response['history'] ?? [],
            ]);
        }

        return response()->json(['error' => $response['error'] ?? 'Gagal melacak status pengiriman.'], 422);
    }

    /**
     * Get detailed order info from Komerce.
     */
    public function getOrderDetail(Transaction $transaction): JsonResponse
    {
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
