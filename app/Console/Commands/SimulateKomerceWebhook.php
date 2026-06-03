<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class SimulateKomerceWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'komerce:simulate-webhook {transaction_number : Nomor transaksi, misal: TRX-20260603-00003} {status : Status callback, misal: shipping atau delivered}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulasikan callback webhook status pengiriman Komerce (shipping / delivered) untuk lokal/staging';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $transactionNumber = $this->argument('transaction_number');
        $status = strtolower($this->argument('status'));

        $validStatuses = [
            'shipping', 'dikirim', 'on_delivery', 'transit', 'picked_up', 'pickup', 'diserahkan', 'paket_diterima_kurir', 'hub', 'sorting_center', 'dalam_perjalanan',
            'delivered', 'diterima', 'success', 'selesai', 'arrived',
            'cancelled', 'batal', 'returned', 'retur', 'gagal',
        ];

        if (! in_array($status, $validStatuses)) {
            $this->error('Status tidak valid! Gunakan status logistik Komerce yang didukung (misal: shipping, transit, delivered, cancelled, returned).');

            return Command::FAILURE;
        }

        $transaction = Transaction::where('transaction_number', $transactionNumber)->first();

        if (! $transaction) {
            $this->error("Transaksi dengan nomor {$transactionNumber} tidak ditemukan!");

            return Command::FAILURE;
        }

        if (! $transaction->booking_code) {
            $this->error("Transaksi {$transactionNumber} belum memiliki Kode Booking Komerce. Silakan pesan pengiriman terlebih dahulu!");

            return Command::FAILURE;
        }

        $this->info("Mengirim simulasi webhook Komerce untuk transaksi {$transactionNumber}...");
        $this->line("Kode Booking: {$transaction->booking_code}");
        $this->line("Status yang dikirim: {$status}");

        // Dispatch request internally into the Laravel routing lifecycle
        $request = Request::create(
            route('api.komerce.webhook'),
            'POST',
            [
                'booking_code' => $transaction->booking_code,
                'status' => $status,
            ]
        );

        // Run the request through the app kernel
        $response = app()->handle($request);

        if ($response->getStatusCode() === 200) {
            $this->info('Simulasi webhook berhasil diproses oleh controller!');
            $this->line('Response: '.$response->getContent());

            $transaction->refresh();
            $this->info("Status transaksi saat ini: {$transaction->status}");

            return Command::SUCCESS;
        }

        $this->error('Simulasi webhook gagal diproses. Status code: '.$response->getStatusCode());
        $this->line('Response: '.$response->getContent());

        return Command::FAILURE;
    }
}
