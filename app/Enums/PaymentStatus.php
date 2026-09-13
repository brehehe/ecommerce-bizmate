<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum Dibayar',
            self::PENDING => 'Menunggu Pembayaran',
            self::PAID => 'Sudah Dibayar',
            self::FAILED => 'Gagal',
            self::EXPIRED => 'Kadaluarsa',
            self::CANCELLED => 'Dibatalkan',
            self::REFUNDED => 'Dikembalikan',
        };
    }
}
