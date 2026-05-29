<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\TransactionPayment;
use Illuminate\Support\Facades\DB;

class SimulateMidtransPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'midtrans:simulate-payment {transaction_number : Nomor transaksi, misal: TRX-20260528-00007}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulasikan callback webhook pembayaran Midtrans sukses untuk lokal/staging';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $transactionNumber = $this->argument('transaction_number');

        $transaction = Transaction::where('transaction_number', $transactionNumber)->first();

        if (! $transaction) {
            $this->error("Transaksi dengan nomor {$transactionNumber} tidak ditemukan!");

            return Command::FAILURE;
        }

        if ($transaction->status !== 'belum_bayar' && $transaction->status !== 'menunggu') {
            $this->warn("Transaksi {$transactionNumber} sudah memiliki status: {$transaction->status}");

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

        $this->info("Berhasil mensimulasikan pembayaran Midtrans untuk transaksi {$transactionNumber}!");
        $this->info("Status transaksi diubah menjadi 'diproses' dan status pembayaran diubah menjadi 'confirmed'.");

        return Command::SUCCESS;
    }
}
