<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\Checkout\UploadPaymentProofAction;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\BiteshipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ]);

            $found = $query->where('transaction_number', $orderNumber)->first();

            if (! $found) {
                $found = $query->whereRaw('LOWER(transaction_number) = ?', [strtolower($orderNumber)])->first();
            }

            if (! $found) {
                $error = 'Pesanan dengan nomor "'.$orderNumber.'" tidak ditemukan. Pastikan nomor pesanan sudah benar.';
            } else {
                $isOwnerLoggedIn = $request->user() && $request->user()->id === $found->user_id;
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
}
