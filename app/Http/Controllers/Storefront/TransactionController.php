<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\ProductReview;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\KomerceService;
use App\Services\MidtransService as MidtransServiceAlias;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Display customer transaction history.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        if (config('app.is_seller') || $request->user()?->is_seller) {
            return redirect('/profile');
        }

        Transaction::processAutoStatusUpdates($request->user()->id);

        $status = $request->input('status', 'all');

        $query = Transaction::with([
            'paymentMethod:id,name,type',
            'payment',
            'items',
        ])
            ->where('user_id', $request->user()->id);

        if ($status !== 'all') {
            if ($status === 'belum_bayar') {
                $query->where('status', 'belum_bayar');
            } elseif ($status === 'berjalan') {
                $query->whereIn('status', ['menunggu', 'diproses', 'dikemas', 'dikirim']);
            } elseif ($status === 'selesai') {
                $query->where('status', 'selesai');
            } elseif ($status === 'batal') {
                $query->where('status', 'batal');
            } elseif ($status === 'refund') {
                $query->whereHas('returns', function ($q) {
                    $q->where('type', 'refund');
                });
            } elseif ($status === 'retur') {
                $query->where(function ($q) {
                    $q->where('status', 'retur')
                        ->orWhereHas('returns', function ($r) {
                            $r->where('type', 'replacement');
                        });
                });
            }
        }

        $transactions = $query->with(['items.product', 'returns'])->latest()
            ->paginate(10)
            ->withQueryString();

        // Count for all statuses to display in the header tabs accurately
        $statusCounts = [
            'all' => Transaction::where('user_id', $request->user()->id)->count(),
            'belum_bayar' => Transaction::where('user_id', $request->user()->id)->where('status', 'belum_bayar')->count(),
            'berjalan' => Transaction::where('user_id', $request->user()->id)->whereIn('status', ['menunggu', 'diproses', 'dikemas', 'dikirim'])->count(),
            'selesai' => Transaction::where('user_id', $request->user()->id)->where('status', 'selesai')->count(),
            'batal' => Transaction::where('user_id', $request->user()->id)->where('status', 'batal')->count(),
            'refund' => Transaction::where('user_id', $request->user()->id)->whereHas('returns', function ($q) {
                $q->where('type', 'refund');
            })->count(),
            'retur' => Transaction::where('user_id', $request->user()->id)->where(function ($q) {
                $q->where('status', 'retur')->orWhereHas('returns', function ($r) {
                    $r->where('type', 'replacement');
                });
            })->count(),
        ];

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        return Inertia::render('Storefront/TransactionHistory', [
            'transactions' => $transactions,
            'statusLabels' => Transaction::statusLabels(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
            'currentStatus' => $status,
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * Display a single transaction detail for the customer.
     */
    public function show(Request $request, Transaction $transaction): Response
    {
        Transaction::processAutoStatusUpdates($request->user()->id);

        // Sync Komerce payment methods to ensure they reflect current setting status and admin fees
        KomerceService::syncPaymentMethods();

        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $transaction->load([
            'customerAddress',
            'paymentMethod',
            'items.product',
            'payments',
            'payment',
            'courier',
            'courierUser',
            'statusHistories',
            'returns.items',
            'returns.media',
            'activeReturn.items',
            'activeReturn.media',
            'activeRefundRequest',
            'refundRequests',
        ]);

        if ($transaction->status === 'belum_bayar' && $request->query('simulated_payment') == '1') {
            $latestPayment = $transaction->payment;
            if ($latestPayment) {
                DB::transaction(function () use ($transaction, $latestPayment) {
                    $latestPayment->update([
                        'status' => 'confirmed',
                        'gateway_status' => 'PAID',
                        'confirmed_at' => now(),
                    ]);

                    $transaction->update([
                        'status' => 'diproses',
                    ]);

                    Log::info('Simulated Payment Verified on Page Load', [
                        'transaction_id' => $transaction->id,
                    ]);
                });

                $transaction->load(['payments', 'payment']);
            }
        }

        // Let's resolve the user reviews properly
        $userReviews = ProductReview::where('user_id', $request->user()->id)
            ->where('transaction_id', $transaction->id)
            ->get()
            ->keyBy(function ($review) {
                return $review->product_id.'_'.$review->product_variant_id;
            });

        // Auto-check gateway payment status if the transaction is still unpaid and is a gateway payment
        if ($transaction->status === 'belum_bayar' && $transaction->paymentMethod?->type === 'gateway') {
            $latestPayment = $transaction->payment;

            if ($latestPayment) {
                $pmNameLower = strtolower($transaction->paymentMethod->name);

                if (str_contains($pmNameLower, 'qris (komerce)') || str_contains($pmNameLower, 'komerce payment')) {
                    try {
                        $refId = $latestPayment->gateway_transaction_id ?: $transaction->transaction_number;
                        $statusCheck = KomerceService::checkPaymentStatus($refId);

                        if ($statusCheck['success'] && ($statusCheck['status'] ?? '') === 'paid') {
                            DB::transaction(function () use ($transaction, $latestPayment) {
                                $latestPayment->update([
                                    'status' => 'confirmed',
                                    'gateway_status' => 'PAID',
                                    'confirmed_at' => now(),
                                ]);

                                $transaction->update([
                                    'status' => 'diproses',
                                ]);

                                Log::info('Komerce Payment Auto-verified on Page Load', [
                                    'transaction_id' => $transaction->id,
                                    'status' => 'paid',
                                ]);
                            });

                            $transaction->load(['payments', 'payment']);
                        }
                    } catch (\Exception $e) {
                        Log::error('Komerce Payment Auto-check Exception: '.$e->getMessage());
                    }
                } elseif (str_contains($pmNameLower, 'midtrans')) {
                    try {
                        $serverKey = $transaction->paymentMethod->api_key ?: config('app.midtrans.server_key');
                        $baseUrl = $transaction->paymentMethod->settings['url'] ?? config('app.midtrans.snap_url', 'https://app.sandbox.midtrans.com');

                        $isSandbox = str_contains($baseUrl, 'sandbox');
                        $apiUrl = $isSandbox ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com';
                        $midtransOrderId = null;
                        if ($latestPayment) {
                            if ($latestPayment->gateway_response) {
                                $resp = is_string($latestPayment->gateway_response)
                                    ? json_decode($latestPayment->gateway_response, true)
                                    : $latestPayment->gateway_response;
                                $midtransOrderId = $resp['order_id'] ?? null;
                            }
                            if (! $midtransOrderId) {
                                $midtransOrderId = $latestPayment->gateway_transaction_id;
                            }
                        }
                        if (! $midtransOrderId || strlen($midtransOrderId) < 5) {
                            $midtransOrderId = $transaction->transaction_number;
                        }

                        $midtransUrl = rtrim($apiUrl, '/').'/v2/'.$midtransOrderId.'/status';

                        $response = Http::withBasicAuth($serverKey, '')
                            ->timeout(10)
                            ->get($midtransUrl);

                        if ($response->successful()) {
                            $responseData = $response->json();
                            $status = $responseData['transaction_status'] ?? '';

                            if ($status === 'settlement' || $status === 'capture') {
                                DB::transaction(function () use ($transaction, $latestPayment, $status, $responseData) {
                                    $latestPayment->update([
                                        'status' => 'confirmed',
                                        'gateway_status' => $status,
                                        'gateway_response' => json_encode($responseData),
                                        'confirmed_at' => now(),
                                    ]);

                                    $transaction->update([
                                        'status' => 'diproses',
                                    ]);

                                    Log::info('Midtrans Auto-verified on Page Load', [
                                        'transaction_id' => $transaction->id,
                                        'status' => $status,
                                    ]);
                                });

                                $transaction->load(['payments', 'payment']);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Midtrans Auto-check Exception: '.$e->getMessage());
                    }
                } elseif (str_contains(strtolower($transaction->paymentMethod->name), 'flip')) {
                    try {
                        $secretKey = $transaction->paymentMethod->api_key ?: config('app.flip.secret_key');
                        $baseUrl = $transaction->paymentMethod->settings['url'] ?? config('app.flip.base_url', 'https://bigflip.id/big_sandbox_api');
                        $billId = $latestPayment->gateway_transaction_id;

                        $flipUrl = rtrim($baseUrl, '/').'/v2/pwf/'.$billId.'/bill';

                        $response = Http::withBasicAuth($secretKey, '')
                            ->timeout(10)
                            ->get($flipUrl);

                        if ($response->successful()) {
                            $responseData = $response->json();
                            $status = $responseData['status'] ?? '';

                            if ($status === 'SUCCESSFUL' || $status === 'INACTIVE') {
                                DB::transaction(function () use ($transaction, $latestPayment, $status, $responseData) {
                                    $latestPayment->update([
                                        'status' => 'confirmed',
                                        'gateway_status' => $status,
                                        'gateway_response' => json_encode($responseData),
                                        'confirmed_at' => now(),
                                    ]);

                                    $transaction->update([
                                        'status' => 'diproses',
                                    ]);

                                    Log::info('Flip Auto-verified on Page Load', [
                                        'transaction_id' => $transaction->id,
                                        'status' => $status,
                                    ]);
                                });

                                $transaction->load(['payments', 'payment']);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Flip Auto-check Exception: '.$e->getMessage());
                    }
                } else {
                    try {
                        $invoiceId = $latestPayment->gateway_transaction_id;
                        $secretKey = $transaction->paymentMethod->api_secret ?: config('app.xendit.private_key');

                        $baseUrl = ($transaction->paymentMethod->settings && isset($transaction->paymentMethod->settings['url']))
                            ? $transaction->paymentMethod->settings['url']
                            : config('app.xendit.url', 'https://api.xendit.co');

                        $xenditUrl = rtrim($baseUrl, '/').'/v2/invoices/'.$invoiceId;

                        $response = Http::withBasicAuth($secretKey, '')
                            ->timeout(10)
                            ->get($xenditUrl);

                        if ($response->successful()) {
                            $responseData = $response->json();
                            $status = strtoupper($responseData['status'] ?? '');

                            if ($status === 'PAID' || $status === 'SETTLED') {
                                DB::transaction(function () use ($transaction, $latestPayment, $status, $responseData) {
                                    $latestPayment->update([
                                        'status' => 'confirmed',
                                        'gateway_status' => $status,
                                        'gateway_response' => json_encode($responseData),
                                        'confirmed_at' => now(),
                                    ]);

                                    $transaction->update([
                                        'status' => 'diproses',
                                    ]);

                                    Log::info('Xendit Invoice Auto-verified on Page Load', [
                                        'transaction_id' => $transaction->id,
                                        'invoice_id' => $latestPayment->gateway_transaction_id,
                                        'status' => $status,
                                    ]);
                                });

                                $transaction->load(['payments', 'payment']);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Xendit Auto-check Exception: '.$e->getMessage());
                    }
                }
            }
        }

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');

        $midtransEnabled = config('app.midtrans_enabled', true) && Setting::where('key', 'midtrans_api_enabled')->value('value') === '1';
        $midtransEnabledMethods = $midtransEnabled ? MidtransServiceAlias::getEnabledMethods() : [];

        return Inertia::render('Storefront/TransactionDetail', [
            'transaction' => $transaction,
            'statusLabels' => Transaction::statusLabels(),
            'returnStatusLabels' => Transaction::returnStatusLabels(),
            'paymentMethods' => PaymentMethod::select('id', 'name', 'type')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'midtransEnabledMethods' => $midtransEnabledMethods,
            'userReviews' => $userReviews,
            'userBankAccounts' => $request->user()->customerBankAccounts()->orderByDesc('is_primary')->get(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
        ]);
    }

    /**
     * Print customer transaction invoice.
     */
    public function printInvoice(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        $transaction->load([
            'user:id,name,email',
            'customerAddress',
            'paymentMethod',
            'items',
        ]);

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');

        return view('print.invoice', compact('transaction', 'storeName'));
    }

    /**
     * Cancel a transaction (customer-initiated).
     * Only allowed when status is belum_bayar or menunggu.
     */
    public function cancel(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        if (! in_array($transaction->status, ['belum_bayar', 'menunggu'])) {
            return redirect()->back()->with('error', 'Pesanan ini tidak dapat dibatalkan.');
        }

        $validated = $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ]);

        $transaction->update([
            'status' => 'batal',
            'cancel_reason' => $validated['cancel_reason'],
            'cancelled_at' => now(),
        ]);

        return redirect()->route('transactions.show', $transaction->id)
            ->with('success', 'Pesanan berhasil dibatalkan.');
    }

    /**
     * Change payment method for an unpaid transaction.
     * Only allowed when status is belum_bayar.
     */
    public function changePayment(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($transaction->status !== 'belum_bayar') {
            return redirect()->back()->with('error', 'Metode pembayaran hanya bisa diubah untuk pesanan yang belum dibayar.');
        }

        $validated = $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
            'midtrans_payment_type_key' => 'nullable|string|in:'.implode(',', array_keys(MidtransServiceAlias::$paymentTypes)),
        ]);

        $paymentMethod = PaymentMethod::findOrFail($validated['payment_method_id']);
        $midtransPaymentTypeKey = $validated['midtrans_payment_type_key'] ?? null;

        $transaction->update([
            'payment_method_id' => $paymentMethod->id,
        ]);

        // If Midtrans Core API selected, create a new charge
        if ($midtransPaymentTypeKey && str_contains(strtolower($paymentMethod->name), 'midtrans')) {
            $user = $request->user();
            $result = MidtransServiceAlias::charge(
                ($transaction->transaction_number ?? $transaction->id).'-'.time(),
                (int) $transaction->grand_total,
                $midtransPaymentTypeKey,
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone ?? '',
                ],
            );

            if ($result['success']) {
                $instructions = $result['data'];
                $latestPayment = $transaction->payments()->latest()->first() ?? $transaction->payment;
                $gatewayResponse = ['_payment_instructions' => $instructions];
                if ($latestPayment) {
                    $latestPayment->update([
                        'gateway_response' => json_encode($gatewayResponse),
                        'gateway_transaction_id' => $result['raw']['transaction_id'] ?? null,
                    ]);
                } else {
                    $transaction->payments()->create([
                        'amount' => $transaction->grand_total,
                        'gateway_response' => json_encode($gatewayResponse),
                        'gateway_transaction_id' => $result['raw']['transaction_id'] ?? null,
                        'status' => 'pending',
                    ]);
                }
            } else {
                return redirect()->route('transactions.show', $transaction->id)
                    ->with('error', 'Metode pembayaran diubah tetapi gagal membuat charge Midtrans: '.($result['error'] ?? 'Unknown error'));
            }
        }

        return redirect()->route('transactions.show', $transaction->id)
            ->with('success', 'Metode pembayaran berhasil diubah.');
    }

    /**
     * Complete a transaction (customer received the order).
     * Only allowed when status is dikirim.
     */
    public function complete(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($transaction->status !== 'dikirim') {
            return redirect()->back()->with('error', 'Status pesanan harus dikirim terlebih dahulu.');
        }

        $transaction->update([
            'status' => 'selesai',
        ]);

        return redirect()->route('transactions.show', $transaction->id)
            ->with('success', 'Pesanan telah diterima. Terima kasih telah berbelanja!');
    }

    /**
     * Extend the order auto-complete confirmation period.
     * Only allowed when status is dikirim and is_extended is false.
     */
    public function extendAutoComplete(Request $request, Transaction $transaction): RedirectResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($transaction->status !== 'dikirim') {
            return redirect()->back()->with('error', 'Status pesanan harus dikirim terlebih dahulu.');
        }

        if ($transaction->is_extended) {
            return redirect()->back()->with('error', 'Jangka waktu konfirmasi pesanan ini sudah pernah diperpanjang sebelumnya.');
        }

        $days = (int) (Setting::where('key', 'extend_auto_complete_days')->value('value') ?? 3);

        $currentAutoComplete = $transaction->auto_complete_at ?: now();
        $transaction->update([
            'auto_complete_at' => $currentAutoComplete->addDays($days),
            'is_extended' => true,
        ]);

        // Add history log
        $transaction->statusHistories()->create([
            'status' => 'dikirim',
            'description' => "Jangka waktu konfirmasi pesanan diperpanjang selama {$days} hari.",
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('transactions.show', $transaction->id)
            ->with('success', "Jangka waktu konfirmasi pesanan berhasil diperpanjang selama {$days} hari.");
    }
}
