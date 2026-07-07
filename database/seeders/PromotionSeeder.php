<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Clear existing promotions to prevent duplicates
        Promotion::query()->delete();

        // Retrieve some products from CategoryAndProductSeeder to attach as promotional items
        $products = Product::with(['productPrice'])->limit(10)->get();

        if ($products->isEmpty()) {
            return;
        }

        // 2. Seed a Store Promotion (Promo Toko - Diskon Spesial Hari Ini)
        $promoToko = Promotion::create([
            'name' => 'Promo Spesial Hari Ini',
            'type' => 'promo_toko',
            'discount_type' => 'percentage',
            'discount_value' => 15.00,
            'start_time' => Carbon::now()->startOfDay(),
            'end_time' => Carbon::now()->addDays(7)->endOfDay(),
            'is_active' => true,
        ]);

        // Attach some products to Promo Toko
        foreach ($products->slice(0, 3) as $product) {
            $originalPrice = $product->productPrice ? (float) $product->productPrice->price : 150000;
            $discountValue = 15.00; // 15%
            $promoPrice = $originalPrice * (1 - ($discountValue / 100));

            PromotionItem::create([
                'promotion_id' => $promoToko->id,
                'product_id' => $product->id,
                'discount_type' => 'percentage',
                'discount_value' => $discountValue,
                'promo_price' => $promoPrice,
                'promo_stock' => 50,
            ]);
        }

        // 3. Seed a Flash Sale (Flash Sale Gila-Gilaan)
        $flashSale = Promotion::create([
            'name' => 'Flash Sale Gila-Gilaan',
            'type' => 'flash_sale',
            'discount_type' => 'percentage',
            'discount_value' => 30.00,
            'start_time' => Carbon::now()->startOfDay(),
            'end_time' => Carbon::now()->addDays(2)->endOfDay(),
            'is_active' => true,
        ]);

        // Attach some products to Flash Sale
        foreach ($products->slice(3, 3) as $product) {
            $originalPrice = $product->productPrice ? (float) $product->productPrice->price : 200000;
            $discountValue = 30.00; // 30%
            $promoPrice = $originalPrice * (1 - ($discountValue / 100));

            PromotionItem::create([
                'promotion_id' => $flashSale->id,
                'product_id' => $product->id,
                'discount_type' => 'percentage',
                'discount_value' => $discountValue,
                'promo_price' => $promoPrice,
                'promo_stock' => 10,
            ]);
        }

        // 4. Seed Shopping Voucher (Voucher Belanja) - Percentage Discount
        Promotion::create([
            'name' => 'Voucher Hemat Awal Bulan',
            'type' => 'voucher_belanja',
            'code' => 'BIZMATEHEMAT',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'min_purchase' => 100000.00,
            'max_discount' => 50000.00,
            'quota' => 200,
            'used_count' => 0,
            'start_time' => Carbon::now()->startOfDay(),
            'end_time' => Carbon::now()->addMonth()->endOfDay(),
            'is_active' => true,
        ]);

        // 5. Seed Shopping Voucher (Voucher Belanja) - Fixed Amount Discount
        Promotion::create([
            'name' => 'Voucher Gajian Untung',
            'type' => 'voucher_belanja',
            'code' => 'BIZMATEGAJIAN',
            'discount_type' => 'fixed',
            'discount_value' => 25000.00,
            'min_purchase' => 150000.00,
            'quota' => 100,
            'used_count' => 0,
            'start_time' => Carbon::now()->startOfDay(),
            'end_time' => Carbon::now()->addMonth()->endOfDay(),
            'is_active' => true,
        ]);

        // 6. Seed Free Shipping Voucher (Voucher Gratis Ongkir)
        Promotion::create([
            'name' => 'Voucher Gratis Ongkir Seluruh Indonesia',
            'type' => 'voucher_gratis_ongkir',
            'code' => 'FREEONGKIR',
            'discount_type' => 'fixed',
            'discount_value' => 20000.00, // Ongkir cut off value
            'min_purchase' => 50000.00,
            'quota' => 500,
            'used_count' => 0,
            'start_time' => Carbon::now()->startOfDay(),
            'end_time' => Carbon::now()->addMonths(2)->endOfDay(),
            'is_active' => true,
        ]);
    }
}
