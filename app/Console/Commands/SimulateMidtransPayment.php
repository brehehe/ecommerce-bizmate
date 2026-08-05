<?php

namespace App\Console\Commands;

use App\Events\ListingPaymentConfirmed;
use App\Models\Product;
use App\Models\ProductListingPayment;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Services\MidtransService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateMidtransPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'midtrans:simulate-payment {order_id=all : Order ID spesifik (LISTING-XXXX / TRX-XXXX) atau "all" untuk lunaskan semua yang pending} {--force : Paksa lunaskan tanpa mengecek status riil di Midtrans API}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulasikan callback webhook pembayaran Midtrans sukses (dukung TRX-, LISTING-, dan mode "all")';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orderId = trim((string) $this->argument('order_id'));
        $force = (bool) $this->option('force');

        if ($force && app()->environment('production')) {
            $this->error('DILARANG: Flag --force tidak dapat digunakan di lingkungan Production demi keamanan transaksi riil!');

            return Command::FAILURE;
        }

        if (empty($orderId) || strtolower($orderId) === 'all') {
            return $this->handleSimulateAll();
        }

        // Handle single LISTING-* payment simulation
        if (str_starts_with($orderId, 'LISTING-')) {
            return $this->handleSingleListingPayment($orderId);
        }

        // Handle single regular transaction (TRX-...)
        return $this->handleSingleTransaction($orderId);
    }

    /**
     * Simulates payment for all pending listing payments and regular transactions.
     */
    protected function handleSimulateAll(): int
    {
        $force = (bool) $this->option('force');

        if ($force) {
            $this->warn('MODE SIMULASI PAKSA (--force): Melunaskan seluruh transaksi pending tanpa cek Midtrans API...');
        } else {
            $this->info('Memulai verifikasi status riil ke API Midtrans Core untuk SEMUA transaksi pending...');
        }

        // 1. Process all pending Listing Payments
        $pendingListingPayments = ProductListingPayment::where('status', 'pending')->get();
        $listingPaidCount = 0;
        $listingPendingCount = 0;
        $listingFailedCount = 0;

        foreach ($pendingListingPayments as $listingPayment) {
            $isPaid = false;

            if (! $force) {
                $midtransRes = MidtransService::status($listingPayment->order_id);
                if ($midtransRes['success'] && ! empty($midtransRes['status'])) {
                    $trxStatus = $midtransRes['status'];
                    if (in_array($trxStatus, ['settlement', 'capture', 'paid'])) {
                        $isPaid = true;
                    } elseif (in_array($trxStatus, ['deny', 'cancel', 'expire', 'failure'])) {
                        $listingPayment->update(['status' => 'failed']);
                        $listingFailedCount++;
                        $this->line("  ✗ Listing Payment [{$listingPayment->order_id}] GAGAL/KEDALUWARSA di Midtrans.");

                        continue;
                    } else {
                        $listingPendingCount++;
                        $this->line("  ⏳ Listing Payment [{$listingPayment->order_id}] masih MENUNGGU pembayaran di Midtrans.");

                        continue;
                    }
                } else {
                    $listingPendingCount++;
                    $this->line("  ⏳ Listing Payment [{$listingPayment->order_id}] belum terdeteksi di Midtrans.");

                    continue;
                }
            } else {
                $isPaid = true;
            }

            if ($isPaid) {
                DB::transaction(function () use ($listingPayment) {
                    $product = Product::find($listingPayment->product_id);

                    if ($product) {
                        $baseDate = ($product->listing_expires_at && $product->listing_expires_at->isFuture())
                            ? $product->listing_expires_at
                            : now();

                        $newExpiresAt = (clone $baseDate)->addDays($listingPayment->days);

                        $product->update([
                            'listing_expires_at' => $newExpiresAt,
                            'listing_fee' => (float) $product->listing_fee + (float) $listingPayment->amount,
                            'listing_days' => (int) $product->listing_days + $listingPayment->days,
                            'active' => true,
                        ]);
                    }

                    $listingPayment->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'gateway_transaction_id' => 'MIDTRANS-API-ALL-'.time(),
                        'gateway_response' => ['checked_api' => true, 'mode' => 'all', 'order_id' => $listingPayment->order_id],
                    ]);
                });

                // Broadcast Reverb Event for each
                event(new ListingPaymentConfirmed($listingPayment->fresh()));
                $listingPaidCount++;
                $this->line("  ✓ Listing Payment [{$listingPayment->order_id}] terkonfirmasi LUNAS dari Midtrans API & Reverb disiarkan.");
            }
        }

        // 2. Process all pending Regular Transactions
        $pendingTransactions = Transaction::whereIn('status', ['belum_bayar', 'menunggu'])->get();
        $trxPaidCount = 0;
        $trxPendingCount = 0;

        foreach ($pendingTransactions as $transaction) {
            $payment = TransactionPayment::where('transaction_id', $transaction->id)
                ->where('status', 'pending')
                ->first();

            $isTrxPaid = false;

            if (! $force && $transaction->transaction_number) {
                $midtransRes = MidtransService::status($transaction->transaction_number);
                if ($midtransRes['success'] && ! empty($midtransRes['status'])) {
                    $trxStatus = $midtransRes['status'];
                    if (in_array($trxStatus, ['settlement', 'capture', 'paid'])) {
                        $isTrxPaid = true;
                    } else {
                        $trxPendingCount++;
                        $this->line("  ⏳ Transaksi [{$transaction->transaction_number}] masih MENUNGGU pembayaran di Midtrans.");

                        continue;
                    }
                } else {
                    $trxPendingCount++;
                    $this->line("  ⏳ Transaksi [{$transaction->transaction_number}] belum terdeteksi di Midtrans.");

                    continue;
                }
            } else {
                $isTrxPaid = true;
            }

            if ($isTrxPaid) {
                DB::transaction(function () use ($transaction, $payment) {
                    if ($payment) {
                        $payment->update([
                            'status' => 'confirmed',
                            'gateway_status' => 'settlement',
                            'gateway_response' => json_encode([
                                'transaction_status' => 'settlement',
                                'checked_api' => true,
                                'mode' => 'all',
                            ]),
                            'confirmed_at' => now(),
                        ]);
                    }

                    $transaction->update([
                        'status' => 'diproses',
                    ]);
                });

                $trxPaidCount++;
                $this->line("  ✓ Transaksi [{$transaction->transaction_number}] terkonfirmasi LUNAS & diubah menjadi 'diproses'.");
            }
        }

        $this->newLine();
        $this->info('SELESAI PEKERJAAN VERIFIKASI!');
        $this->info("Listing Payments: {$listingPaidCount} Lunas, {$listingPendingCount} Menunggu, {$listingFailedCount} Gagal.");
        $this->info("Transaksi Belanja: {$trxPaidCount} Lunas, {$trxPendingCount} Menunggu.");

        return Command::SUCCESS;
    }

    /**
     * Simulates payment for a single LISTING-* order ID.
     */
    protected function handleSingleListingPayment(string $orderId): int
    {
        $listingPayment = ProductListingPayment::where('order_id', $orderId)->first();

        if (! $listingPayment) {
            $this->error("Listing payment dengan Order ID {$orderId} tidak ditemukan!");

            return Command::FAILURE;
        }

        if ($listingPayment->status === 'paid') {
            $this->warn("Listing payment {$orderId} sudah berstatus 'paid'.");

            return Command::SUCCESS;
        }

        $force = (bool) $this->option('force');

        if (! $force) {
            $this->info("Memeriksa status riil Order ID {$orderId} dari API Midtrans Core...");
            $midtransRes = MidtransService::status($orderId);

            if (! $midtransRes['success']) {
                $this->error('Status Midtrans: '.($midtransRes['error'] ?? 'Transaksi tidak ditemukan di Midtrans API.'));
                $this->line('Petunjuk: Selesaikan pembayaran di E-Wallet / Midtrans Simulator, atau tambahkan --force untuk simulasi paksa.');

                return Command::FAILURE;
            }

            $status = $midtransRes['status'] ?? 'pending';

            if (! in_array($status, ['settlement', 'capture', 'paid'])) {
                if (in_array($status, ['deny', 'cancel', 'expire', 'failure'])) {
                    $listingPayment->update(['status' => 'failed']);
                    $this->error("Order ID {$orderId} di Midtrans berstatus '{$status}'. Status di database diubah menjadi 'failed'.");

                    return Command::FAILURE;
                }

                $this->warn("Order ID {$orderId} di Midtrans masih berstatus '{$status}' (Belum dibayar oleh pembeli).");
                $this->line('Petunjuk: Silakan selesaikan pembayaran di E-Wallet / Midtrans Simulator, atau tambahkan --force jika ingin simulasi paksa.');

                return Command::SUCCESS;
            }
        }

        DB::transaction(function () use ($listingPayment) {
            $product = Product::find($listingPayment->product_id);

            if ($product) {
                $baseDate = ($product->listing_expires_at && $product->listing_expires_at->isFuture())
                    ? $product->listing_expires_at
                    : now();

                $newExpiresAt = (clone $baseDate)->addDays($listingPayment->days);

                $product->update([
                    'listing_expires_at' => $newExpiresAt,
                    'listing_fee' => (float) $product->listing_fee + (float) $listingPayment->amount,
                    'listing_days' => (int) $product->listing_days + $listingPayment->days,
                    'active' => true,
                ]);
            }

            $listingPayment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'gateway_transaction_id' => 'MIDTRANS-CHECK-'.time(),
                'gateway_response' => ['checked_api' => true, 'order_id' => $listingPayment->order_id],
            ]);
        });

        // Broadcast Reverb Event
        event(new ListingPaymentConfirmed($listingPayment->fresh()));

        $this->info("Berhasil mengonfirmasi LUNAS untuk Listing Order ID: {$orderId} dari Midtrans API!");
        $this->info("Status listing payment diubah menjadi 'paid', produk telah diaktifkan, dan event Reverb disiarkan.");

        return Command::SUCCESS;
    }

    /**
     * Simulates payment for a single regular TRX-* order.
     */
    protected function handleSingleTransaction(string $orderId): int
    {
        $transaction = Transaction::where('transaction_number', $orderId)->first();

        if (! $transaction) {
            $this->error("Transaksi dengan nomor {$orderId} tidak ditemukan!");

            return Command::FAILURE;
        }

        if ($transaction->status !== 'belum_bayar' && $transaction->status !== 'menunggu') {
            $this->warn("Transaksi {$orderId} sudah memiliki status: {$transaction->status}");

            return Command::SUCCESS;
        }

        $payment = TransactionPayment::where('transaction_id', $transaction->id)
            ->where('status', 'pending')
            ->first();

        DB::transaction(function () use ($transaction, $payment) {
            if ($payment) {
                $payment->update([
                    'status' => 'confirmed',
                    'gateway_status' => 'settlement',
                    'gateway_response' => json_encode([
                        'transaction_status' => 'settlement',
                        'simulated' => true,
                        'message' => 'Simulated via artisan command midtrans:simulate-payment',
                    ]),
                    'confirmed_at' => now(),
                ]);
            }

            $transaction->update([
                'status' => 'diproses',
            ]);
        });

        $this->info("Berhasil mensimulasikan pembayaran Midtrans untuk transaksi {$orderId}!");
        $this->info("Status transaksi diubah menjadi 'diproses' dan status pembayaran diubah menjadi 'confirmed'.");

        return Command::SUCCESS;
    }
}
