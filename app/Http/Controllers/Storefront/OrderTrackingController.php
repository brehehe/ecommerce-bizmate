<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\Checkout\UploadPaymentProofAction;
use App\Http\Controllers\Controller;
use App\Models\RestoProfile;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\BiteshipService;
use App\Services\WarisQrisService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrderTrackingController extends Controller
{
    public function __construct(
        private readonly UploadPaymentProofAction $uploadPaymentProofAction
    ) {}

    /**
     * Show order tracking search or result page.
     */
    public function index(Request $request): Response
    {
        $orderNumber = trim((string) ($request->query('trx') ?? $request->query('order_number') ?? ''));
        $contact = trim((string) ($request->query('contact') ?? $request->query('email') ?? $request->query('phone') ?? ''));

        $transaction = null;
        $error = null;

        if (! empty($orderNumber)) {
            $query = Transaction::with([
                'customerAddress',
                'paymentMethod',
                'items.product',
                'payments',
                'payment',
                'courier',
                'courierUser',
                'statusHistories',
                'restoProfile',
                'resto:id,name,store_name,phone_number,email',
                'storeReview',
            ]);

            $found = $query->where('transaction_number', $orderNumber)->first();

            if (! $found) {
                $found = $query->whereRaw('LOWER(transaction_number) = ?', [strtolower($orderNumber)])->first();
            }

            if (! $found) {
                $error = 'Pesanan dengan nomor "'.$orderNumber.'" tidak ditemukan. Pastikan nomor pesanan sudah benar.';
            } else {
                $isOwnerLoggedIn = $request->user() && (
                    $request->user()->id === $found->user_id ||
                    $request->user()->id === $found->resto_id ||
                    $request->user()->hasAnyRole(['Super Admin', 'Admin'])
                );
                $isSessionAuthorized = session('guest_order_'.$found->transaction_number) === true;

                $isContactMatched = false;
                if (! empty($contact)) {
                    $cleanContact = strtolower($contact);
                    $targetEmail = strtolower((string) $found->customer_email);
                    $targetPhone = preg_replace('/\D/', '', (string) $found->customer_phone);
                    $inputPhone = preg_replace('/\D/', '', $contact);

                    if ($cleanContact === $targetEmail) {
                        $isContactMatched = true;
                    } elseif (! empty($inputPhone) && strlen($inputPhone) >= 6 && str_contains($targetPhone, $inputPhone)) {
                        $isContactMatched = true;
                    }
                }

                if ($isOwnerLoggedIn || $isSessionAuthorized || $isContactMatched) {
                    $transaction = $found;

                    // If transaction is unpaid, handle QRIS recovery and live status sync
                    if ($transaction->status === 'belum_bayar') {
                        $latestPayment = $transaction->payment;

                        // 1. If QRIS generation failed previously, attempt auto-regeneration
                        if ($latestPayment && $latestPayment->gateway_status === 'FAILED_API' && WarisQrisService::isWarisPaymentMethod($transaction->paymentMethod)) {
                            $regen = WarisQrisService::generateDynamicQris($transaction);
                            if ($regen['success']) {
                                $latestPayment->update([
                                    'gateway_transaction_id' => $regen['reference_id'] ?? $transaction->transaction_number,
                                    'gateway_status' => 'PENDING',
                                    'gateway_response' => [
                                        'qr_image_url' => $regen['qr_image_url'] ?? '',
                                        'qr_image_data_uri' => $regen['qr_image_url'] ?? '',
                                        'qr_string' => $regen['qr_string'] ?? '',
                                        'qris_image' => $regen['qr_image_url'] ?? '',
                                        'qris_string' => $regen['qr_string'] ?? '',
                                        'reference_id' => $regen['reference_id'] ?? $transaction->transaction_number,
                                        'amount' => $regen['amount'] ?? $transaction->grand_total,
                                        'final_amount' => $regen['final_amount'] ?? $transaction->grand_total,
                                        'fee_charged' => $regen['fee_charged'] ?? 0,
                                        'expires_at' => $regen['expires_at'] ?? null,
                                        'qris_source' => $regen['qris_source'] ?? null,
                                    ],
                                ]);
                                $transaction->load(['payment', 'payments']);
                            }
                        }

                        // 2. Real-time check from gateway API (/qris/status/{reference_id})
                        if (WarisQrisService::isWarisPaymentMethod($transaction->paymentMethod)) {
                            $check = WarisQrisService::checkTransactionStatus($transaction->transaction_number);
                            if ($check['is_paid']) {
                                DB::transaction(function () use ($transaction, $check) {
                                    if ($transaction->payment) {
                                        $transaction->payment->update([
                                            'status' => 'confirmed',
                                            'gateway_status' => 'PAID',
                                            'confirmed_at' => $check['paid_at'] ? Carbon::parse($check['paid_at']) : now(),
                                        ]);
                                    }
                                    $transaction->update([
                                        'status' => 'diproses',
                                        'payment_status' => 'lunas',
                                    ]);
                                    $transaction->statusHistories()->create([
                                        'status' => 'diproses',
                                        'description' => 'Pembayaran QRIS berhasil diverifikasi otomatis melalui pengecekan status gateway.',
                                    ]);
                                });
                                $transaction->load(['payment', 'payments', 'statusHistories']);
                            }
                        }
                    }
                } elseif (empty($contact)) {
                    $error = 'Masukkan email atau nomor HP yang digunakan saat pemesanan untuk melihat rincian.';
                } else {
                    $error = 'Nomor pesanan dan data kontak (Email/No. HP) tidak cocok.';
                }
            }
        }

        $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
        $storeLogo = Setting::where('key', 'store_logo')->value('value');
        $storeWhatsapp = Setting::where('key', 'store_whatsapp')->value('value') ?? Setting::where('key', 'store_phone')->value('value');
        $isBiteshipEnabled = BiteshipService::isEnabled();

        return Inertia::render('Storefront/TrackOrder', [
            'transaction' => $transaction,
            'searchTrx' => $orderNumber,
            'searchContact' => $contact,
            'errorMessage' => $error,
            'statusLabels' => Transaction::statusLabels(),
            'returnStatusLabels' => Transaction::returnStatusLabels(),
            'storeName' => $storeName,
            'storeLogo' => $storeLogo,
            'storeWhatsapp' => $storeWhatsapp,
            'biteshipEnabled' => $isBiteshipEnabled,
            'isFoodPick' => (bool) config('app.is_foodpick', false),
            'foodpickSettings' => config('app.foodpick', []),
        ]);
    }

    /**
     * Upload proof of payment from tracking page.
     */
    public function uploadProof(Request $request, Transaction $transaction): RedirectResponse
    {
        $request->validate([
            'proof_image' => 'required|image|max:5120',
        ]);

        $file = $request->file('proof_image');
        $this->uploadPaymentProofAction->execute($transaction, $file, $request->user());

        return back()->with('success', 'Bukti pembayaran berhasil diunggah. Kami akan segera memverifikasinya.');
    }

    /**
     * Submit store rating and review for completed order.
     */
    public function rateStore(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($transaction->status !== 'selesai') {
            return back()->with('error', 'Penilaian hanya dapat diberikan untuk pesanan yang telah selesai.');
        }

        if ($transaction->storeReview()->exists()) {
            return back()->with('error', 'Pesanan ini sudah pernah diberi ulasan.');
        }

        $restoProfileId = $transaction->restoProfile?->id
            ?? RestoProfile::where('user_id', $transaction->resto_id)->value('id');
        $sellerId = $transaction->resto_id ?? $transaction->items()->first()?->product?->user_id;

        $transaction->storeReview()->create([
            'resto_profile_id' => $restoProfileId,
            'seller_id' => $sellerId,
            'user_id' => $request->user()?->id ?? $transaction->user_id,
            'customer_name' => $request->user()?->name ?? $transaction->user?->name ?? 'Pelanggan',
            'customer_phone' => $request->user()?->phone_number ?? $transaction->user?->phone_number ?? $transaction->customerAddress?->phone,
            'rating' => (int) $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'is_public' => true,
        ]);

        return back()->with('success', 'Terima kasih atas ulasan Anda! Penilaian Anda telah tersimpan secara langsung.');
    }
}
