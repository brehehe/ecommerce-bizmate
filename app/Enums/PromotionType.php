<?php

namespace App\Enums;

enum PromotionType: string
{
    case PROMO_PRODUK = 'promo_produk';
    case PROMO_TOKO = 'promo_toko';
    case BUNDLING_GIFT = 'bundling_gift';
    case FLASH_SALE = 'flash_sale';
    case VOUCHER_BELANJA = 'voucher_belanja';
    case VOUCHER_GRATIS_ONGKIR = 'voucher_gratis_ongkir';
    case SPECIAL_DEALS = 'special_deals';

    public function label(): string
    {
        return match ($this) {
            self::PROMO_PRODUK => 'Diskon Produk',
            self::PROMO_TOKO => 'Diskon Toko',
            self::BUNDLING_GIFT => 'Bundling & Free Gift',
            self::FLASH_SALE => 'Flash Sale',
            self::VOUCHER_BELANJA => 'Voucher Belanja',
            self::VOUCHER_GRATIS_ONGKIR => 'Voucher Gratis Ongkir',
            self::SPECIAL_DEALS => 'Special Deals',
        };
    }
}
