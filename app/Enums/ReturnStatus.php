<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case MENUNGGU_REVIEW = 'menunggu_review';
    case DISETUJUI = 'disetujui';
    case DITOLAK = 'ditolak';
    case BARANG_DIKIRIM_CUSTOMER = 'barang_dikirim_customer';
    case BARANG_DITERIMA_TOKO = 'barang_diterima_toko';
    case REFUND_DIPROSES = 'refund_diproses';
    case BARANG_PENGGANTI_DIKIRIM = 'barang_pengganti_dikirim';
    case SELESAI = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU_REVIEW => 'Menunggu Review Admin',
            self::DISETUJUI => 'Pengajuan Disetujui',
            self::DITOLAK => 'Pengajuan Ditolak',
            self::BARANG_DIKIRIM_CUSTOMER => 'Barang Sedang Dikirim Pembeli',
            self::BARANG_DITERIMA_TOKO => 'Barang Diterima Penjual',
            self::REFUND_DIPROSES => 'Pengembalian Dana Diproses',
            self::BARANG_PENGGANTI_DIKIRIM => 'Barang Pengganti Dikirim',
            self::SELESAI => 'Selesai',
        };
    }
}
