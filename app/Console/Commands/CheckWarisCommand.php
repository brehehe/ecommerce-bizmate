<?php

namespace App\Console\Commands;

use App\Services\WarisQrisService;
use Illuminate\Console\Command;

class CheckWarisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'waris:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa status koneksi, saldo akun, dan daftar QRIS statis terdaftar pada gateway WARIS';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== PEMERIKSAAN KONEKSI WARIS QRIS GATEWAY ===');
        $baseUrl = WarisQrisService::getBaseUrl();
        $apiKey = WarisQrisService::getApiKey();

        $this->line("URL Gateway : {$baseUrl}");
        $this->line('API Key     : '.($apiKey ? substr($apiKey, 0, 10).'***'.substr($apiKey, -4) : '<KOSONG>'));

        if (empty($apiKey)) {
            $this->error('✗ API Key belum disetting di .env atau tabel Setting.');

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('1. Memeriksa Saldo & Akun Merchant...');
        $balanceResult = WarisQrisService::checkBalance();

        if (! $balanceResult['success']) {
            $this->error('✗ Gagal terhubung ke API: '.($balanceResult['error'] ?? 'Unknown error'));

            return Command::FAILURE;
        }

        $acc = $balanceResult['data']['data'] ?? $balanceResult['data'] ?? [];
        $merchantName = $acc['merchant_name'] ?? '—';
        $balance = (int) ($acc['balance'] ?? 0);
        $activeQrisCount = (int) ($acc['active_qris_count'] ?? 0);
        $feePerGen = (int) ($acc['fee_per_generate'] ?? 0);
        $remGen = (int) ($acc['estimated_remaining_generations'] ?? 0);

        $this->line("  ✓ Merchant         : {$merchantName}");
        $this->line('  ✓ Saldo Akun       : Rp '.number_format($balance, 0, ',', '.'));
        $this->line('  ✓ Biaya per Gen    : Rp '.number_format($feePerGen, 0, ',', '.'));
        $this->line("  ✓ Sisa Kuota Gen   : {$remGen} kali transaksi");
        $this->line("  ✓ Jumlah QRIS Aktif: {$activeQrisCount}");

        $this->newLine();
        $this->info('2. Memeriksa Daftar QRIS Statis...');
        $qrisListResult = WarisQrisService::listQris();

        $items = $qrisListResult['data'] ?? [];
        if (empty($items)) {
            $this->warn('  ⚠️ PERINGATAN: Belum ada QRIS statis yang terdaftar pada akun merchant Anda!');
            $this->warn('     Sistem tidak dapat meng-generate dynamic QRIS jika belum ada QRIS statis yang didaftarkan.');
            $this->line('     Solusi: Buka dashboard gateway di https://webapiqris.biz.id/ dan tambahkan QRIS toko Anda.');
        } else {
            $this->info('  Daftar QRIS terdaftar:');
            foreach ($items as $idx => $q) {
                $code = $q['qris_code'] ?? $q['code'] ?? '—';
                $name = $q['merchant_name'] ?? $q['label'] ?? '—';
                $status = ($q['is_active'] ?? true) ? 'AKTIF' : 'NONAKTIF';
                $this->line('  '.($idx + 1).". [{$code}] {$name} ({$status})");
            }
        }

        $this->newLine();
        $this->info('Pemeriksaan selesai.');

        return Command::SUCCESS;
    }
}

