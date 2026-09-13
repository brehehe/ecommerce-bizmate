<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case BELUM_BAYAR = 'belum_bayar';
    case MENUNGGU = 'menunggu';
    case DIPROSES = 'diproses';
    case DIKEMAS = 'dikemas';
    case DIKIRIM = 'dikirim';
    case SELESAI = 'selesai';
    case BATAL = 'batal';

    /**
     * Get human-readable label in Indonesian.
     */
    public function label(): string
    {
        return match ($this) {
            self::BELUM_BAYAR => 'Belum Bayar',
            self::MENUNGGU => 'Menunggu Konfirmasi',
            self::DIPROSES => 'Diproses',
            self::DIKEMAS => 'Dikemas',
            self::DIKIRIM => 'Dikirim',
            self::SELESAI => 'Selesai',
            self::BATAL => 'Dibatalkan',
        };
    }

    /**
     * Array of statuses representing paid orders.
     *
     * @return array<string>
     */
    public static function paidStatuses(): array
    {
        return [
            self::DIPROSES->value,
            self::DIKEMAS->value,
            self::DIKIRIM->value,
            self::SELESAI->value,
        ];
    }
}
