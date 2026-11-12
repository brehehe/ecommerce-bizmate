<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {recipient? : Alamat email penerima (default: MAIL_FROM_ADDRESS)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim email uji coba untuk memverifikasi konfigurasi SMTP Gmail/Mail server';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $recipient = $this->argument('recipient') ?: config('mail.from.address');
        $appName = config('app.name');
        $fromAddress = config('mail.from.address');
        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');

        $this->info('=== UJI COBA PENGIRIMAN EMAIL ===');
        $this->line("Mailer   : {$mailer}");
        $this->line("Host     : {$host}:{$port}");
        $this->line("Pengirim : {$fromAddress}");
        $this->line("Penerima : {$recipient}");
        $this->newLine();

        $this->info("Sedang mengirim email uji coba ke {$recipient}...");

        try {
            Mail::raw("Halo!\n\nIni adalah email uji coba dari {$appName}.\nJika Anda menerima pesan ini, konfigurasi SMTP Gmail Anda telah BERHASIL dan SIAP digunakan untuk pengiriman notifikasi pesanan dan fitur lainnya.\n\nWaktu kirim: ".now()->translatedFormat('d F Y H:i:s'), function ($message) use ($recipient, $appName) {
                $message->to($recipient)
                    ->subject("Uji Coba Pengiriman Email - {$appName}");
            });

            $this->info("✓ Sukses! Email berhasil terkirim ke {$recipient}.");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('✗ Gagal mengirim email.');
            $this->line('Pesan Error: '.$e->getMessage());

            if (str_contains($e->getMessage(), 'Username and Password not accepted') || str_contains($e->getMessage(), '535-5.7.8')) {
                $this->newLine();
                $this->warn('PANDUAN GMAIL SMTP:');
                $this->line('1. Jangan gunakan password akun Google utama Anda.');
                $this->line('2. Aktifkan 2-Step Verification pada akun Google.');
                $this->line('3. Buat App Password (Sandi Aplikasi) di: https://myaccount.google.com/apppasswords');
                $this->line('4. Masukkan 16 karakter Sandi Aplikasi tersebut ke MAIL_PASSWORD di file .env.');
            }

            return Command::FAILURE;
        }
    }
}
