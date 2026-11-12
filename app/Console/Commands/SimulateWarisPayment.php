<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\WarisWebhookController;
use App\Models\Transaction;
use App\Services\WarisQrisService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SimulateWarisPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'waris:simulate-payment 
                            {order_id : Nomor transaksi (contoh: TRX-20261105-00005) atau "all" untuk simulasi semua yang belum bayar}
                            {--method=BCA_QRIS : Nama metode pembayaran (misal BCA_QRIS, GOPAY, DANA, OVO)}
                            {--bank=BCA Mobile : Nama bank atau aplikasi pengirim}
                            {--amount= : Nominal pembayaran (opsional, default: grand total pesanan)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulasi notifikasi webhook pembayaran QRIS dari gateway WARIS untuk pengujian lokal';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orderId = trim((string) $this->argument('order_id'));
        $paymentMethod = (string) $this->option('method');
        $bankSender = (string) $this->option('bank');

        if (empty($orderId) || strtolower($orderId) === 'all') {
            return $this->handleAllPending();
        }

        return $this->simulateForOrder($orderId, $paymentMethod, $bankSender);
    }

    /**
     * Simulate payment for all pending WARIS QRIS transactions.
     */
    protected function handleAllPending(): int
    {
        $transactions = Transaction::whereIn('status', ['belum_bayar', 'menunggu'])
            ->whereHas('paymentMethod', function ($query) {
                $query->where('type', 'waris_qris')
                    ->orWhere('name', 'like', '%qris%');
            })
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('Tidak ada transaksi QRIS yang berstatus belum bayar.');

            return Command::SUCCESS;
        }

        $this->info("Ditemukan {$transactions->count()} transaksi QRIS yang belum bayar. Memproses simulasi...");

        $successCount = 0;
        foreach ($transactions as $tx) {
            $result = $this->dispatchWebhook(
                (string) $tx->transaction_number,
                (int) round((float) $tx->grand_total),
                'BCA_QRIS',
                'BCA Mobile'
            );

            if ($result) {
                $this->line("  ✓ Pesanan {$tx->transaction_number} berhasil dikonfirmasi lunas.");
                $successCount++;
            } else {
                $this->error("  ✗ Gagal memproses pesanan {$tx->transaction_number}.");
            }
        }

        $this->info("Selesai! {$successCount} dari {$transactions->count()} transaksi berhasil dikonfirmasi.");

        return Command::SUCCESS;
    }

    /**
     * Simulate payment for a single order.
     */
    protected function simulateForOrder(string $orderNumber, string $paymentMethod, string $bankSender): int
    {
        $transaction = Transaction::where('transaction_number', $orderNumber)->first();

        if (! $transaction) {
            $this->error("Pesanan dengan nomor {$orderNumber} tidak ditemukan di database.");

            return Command::FAILURE;
        }

        $amount = $this->option('amount')
            ? (int) $this->option('amount')
            : (int) round((float) $transaction->grand_total);

        $this->info("Mengirim simulasi webhook WARIS untuk pesanan {$transaction->transaction_number}...");
        $this->line('  - Total Tagihan : Rp '.number_format($amount, 0, ',', '.'));
        $this->line("  - Metode Bayar  : {$paymentMethod}");
        $this->line("  - Pengirim      : {$bankSender}");

        $success = $this->dispatchWebhook($transaction->transaction_number, $amount, $paymentMethod, $bankSender);

        if ($success) {
            $this->info("✓ Sukses! Status transaksi {$transaction->transaction_number} kini DIPROSES dan LUNAS.");

            return Command::SUCCESS;
        }

        $this->error("✗ Gagal memproses simulasi webhook untuk pesanan {$transaction->transaction_number}.");

        return Command::FAILURE;
    }

    /**
     * Dispatch webhook request to the controller.
     */
    protected function dispatchWebhook(string $referenceId, int $amount, string $paymentMethod, string $bankSender): bool
    {
        $payload = [
            'event' => 'payment.received',
            'data' => [
                'reference_id' => $referenceId,
                'amount' => $amount,
                'paid_at' => now()->toIso8601String(),
                'payment_method' => $paymentMethod,
                'bank_sender' => $bankSender,
                'status' => 'success',
            ],
        ];

        $jsonPayload = json_encode($payload);
        $secret = WarisQrisService::getWebhookSecret();

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (! empty($secret)) {
            $headers['X-WARIS-Signature'] = 'sha256='.hash_hmac('sha256', $jsonPayload, $secret);
        }

        $request = Request::create(
            '/api/webhooks/waris',
            'POST',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $jsonPayload
        );

        $controller = app(WarisWebhookController::class);
        $response = $controller->handleCallback($request);

        return $response->getStatusCode() === 200;
    }

    /**
     * Transform headers array into $_SERVER style variables for Request::create.
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function transformHeadersToServerVars(array $headers): array
    {
        $server = [];
        foreach ($headers as $key => $value) {
            $key = strtoupper(str_replace('-', '_', $key));
            if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $server[$key] = $value;
            } else {
                $server['HTTP_'.$key] = $value;
            }
        }

        return $server;
    }
}
