<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\RefundRequest;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RefundController extends Controller
{
    /**
     * Display a listing of the customer's cancellation/refund requests.
     */
    public function index(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        $refunds = RefundRequest::where('user_id', $user->id)
            ->with(['transaction'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/Refunds', [
            'refunds' => $refunds,
            'statusLabels' => RefundRequest::statusLabels(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Submit a new cancellation and refund request.
     */
    public function store(Request $request, Transaction $transaction): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Check ownership
        if ($transaction->user_id !== $user->id) {
            abort(403);
        }

        // Check transaction status (only allow cancellation request for 'menunggu' or 'diproses')
        if (! in_array($transaction->status, ['menunggu', 'diproses'])) {
            return back()->with('error', 'Pembatalan hanya dapat diajukan untuk pesanan dengan status Menunggu Konfirmasi atau Diproses.');
        }

        // Check if there's already an active cancellation request
        $existing = RefundRequest::where('transaction_id', $transaction->id)
            ->whereNotIn('status', ['ditolak'])
            ->exists();

        if ($existing) {
            return back()->with('error', 'Pesanan ini sudah memiliki pengajuan pembatalan yang aktif.');
        }

        // Validate basic inputs
        $request->validate([
            'reason' => 'required|string|max:1000',
            'refund_method' => 'required|in:transfer,poin',
        ], [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'refund_method.required' => 'Pilih metode pengembalian dana.',
        ]);

        $refundMethod = $request->refund_method;

        // Check if refund method is coin and coins/points refund is enabled
        if ($refundMethod === 'poin') {
            $pointsEnabled = Setting::where('key', 'coins_enabled')->value('value') === '1';
            $refundPointsEnabled = Setting::where('key', 'refund_points_enabled')->value('value') === '1';
            if (! $pointsEnabled || ! $refundPointsEnabled) {
                return back()->with('error', 'Metode refund koin tidak tersedia saat ini.');
            }

            // Check minimal amount
            $minPointsAmount = (float) (Setting::where('key', 'refund_min_amount_points')->value('value') ?? 0);
            if ($transaction->grand_total < $minPointsAmount) {
                $formattedMin = 'Rp '.number_format($minPointsAmount, 0, ',', '.');

                return back()->with('error', 'Nominal transaksi minimal untuk refund koin adalah '.$formattedMin);
            }
        } else {
            // Check minimal amount for transfer
            $minTransferAmount = (float) (Setting::where('key', 'refund_min_amount_transfer')->value('value') ?? 0);
            if ($transaction->grand_total < $minTransferAmount) {
                $formattedMin = 'Rp '.number_format($minTransferAmount, 0, ',', '.');

                return back()->with('error', 'Nominal transaksi minimal untuk refund transfer adalah '.$formattedMin);
            }

            // Validate bank fields for transfer
            $request->validate([
                'bank_name' => 'required|string|max:100',
                'account_number' => 'required|string|max:100',
                'account_name' => 'required|string|max:100',
            ], [
                'bank_name.required' => 'Nama bank wajib diisi.',
                'account_number.required' => 'Nomor rekening wajib diisi.',
                'account_name.required' => 'Nama pemilik rekening wajib diisi.',
            ]);
        }

        DB::transaction(function () use ($request, $transaction, $user, $refundMethod) {
            $refundRequest = RefundRequest::create([
                'refund_number' => RefundRequest::generateNumber(),
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'status' => 'menunggu_konfirmasi',
                'refund_method' => $refundMethod,
                'reason' => $request->reason,
                'bank_name' => $refundMethod === 'transfer' ? $request->bank_name : null,
                'account_number' => $refundMethod === 'transfer' ? $request->account_number : null,
                'account_name' => $refundMethod === 'transfer' ? $request->account_name : null,
                'refund_amount' => $transaction->grand_total,
            ]);

            // Create notification for admin
            Notification::create([
                'user_id' => null, // admin global
                'title' => 'Pengajuan Pembatalan Baru',
                'message' => 'Ada pengajuan pembatalan baru dari '.$user->name.' untuk transaksi #'.$transaction->transaction_number.'.',
                'type' => 'refund_request',
                'url' => '/admin/refunds/'.$refundRequest->id,
                'is_read' => false,
            ]);
        });

        return back()->with('success', 'Pengajuan pembatalan dan refund berhasil dikirim. Admin akan segera meninjaunya.');
    }

    /**
     * Show detail of a cancellation/refund request.
     */
    public function show(Request $request, RefundRequest $refundRequest): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($refundRequest->user_id !== $user->id) {
            abort(403);
        }

        $refundRequest->load(['transaction.items.product', 'processedByUser:id,name']);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/RefundDetail', [
            'refund' => $refundRequest,
            'statusLabels' => RefundRequest::statusLabels(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }
}
