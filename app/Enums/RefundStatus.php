<?php

namespace App\Enums;

enum RefundStatus: string
{
    case MENUNGGU_KONFIRMASI = 'menunggu_konfirmasi';
    case DISETUJUI = 'disetujui';
    case DITOLAK = 'ditolak';
    case SELESAI = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU_KONFIRMASI => 'Menunggu Konfirmasi',
            self::DISETUJUI => 'Disetujui',
            self::DITOLAK => 'Ditolak',
            self::SELESAI => 'Selesai',
        };
    }
}
