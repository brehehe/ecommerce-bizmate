<?php

namespace App\Actions\Cart;

use App\Models\Promotion;
use App\Models\User;

class SelectCartVouchersAction
{
    /**
     * Store chosen voucher IDs in session.
     */
    public function execute(?string $belanjaId, ?string $ongkirId, User $user): array
    {
        $codes = [];

        if ($belanjaId) {
            $promoBelanja = Promotion::where('id', $belanjaId)->where('is_active', true)->first();
            if ($promoBelanja && $promoBelanja->code) {
                $codes[] = $promoBelanja->code;
            }
        }

        if ($ongkirId) {
            $promoOngkir = Promotion::where('id', $ongkirId)->where('is_active', true)->first();
            if ($promoOngkir && $promoOngkir->code) {
                $codes[] = $promoOngkir->code;
            }
        }

        $mergedVoucherCode = implode(',', $codes);
        session(['cart_voucher_code' => $mergedVoucherCode]);

        return [
            'voucher_code' => $mergedVoucherCode,
            'belanja_id' => $belanjaId,
            'ongkir_id' => $ongkirId,
        ];
    }
}
