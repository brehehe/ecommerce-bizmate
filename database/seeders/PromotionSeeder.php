<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $promotions = [
            // 1. Voucher Belanja - Fixed
            [
                'name' => 'Voucher Belanja Hemat Awal Bulan',
                'type' => 'voucher_belanja',
                'code' => 'HEMATAWAL30',
                'discount_type' => 'fixed',
                'discount_value' => 30000,
                'min_purchase' => 150000,
                'max_discount' => 30000,
                'quota' => 100,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Minimal belanja Rp 150.000\n- Berlaku untuk semua produk\n- Satu pengguna hanya bisa menggunakan voucher ini satu kali\n- Kuota terbatas",
                ],
            ],
            // 2. Voucher Belanja - Percentage
            [
                'name' => 'Voucher Diskon Gajian 10%',
                'type' => 'voucher_belanja',
                'code' => 'GAJIAN10PERSEN',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'min_purchase' => 100000,
                'max_discount' => 50000,
                'quota' => 200,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Diskon 10% s/d Rp 50.000\n- Minimal belanja Rp 100.000\n- Berlaku selama periode gajian\n- Kuota terbatas",
                ],
            ],
            // 3. Voucher Gratis Ongkir
            [
                'name' => 'Gratis Ongkir Spesial Weekend',
                'type' => 'voucher_gratis_ongkir',
                'code' => 'ONGKIRFREEWKND',
                'discount_type' => 'free_shipping',
                'discount_value' => 20000,
                'min_purchase' => 50000,
                'max_discount' => 20000,
                'quota' => 500,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Potongan ongkos kirim s/d Rp 20.000\n- Minimal belanja Rp 50.000\n- Berlaku khusus hari Sabtu & Minggu\n- Kuota terbatas",
                ],
            ],
            // 4. Voucher Poin - New User (Hidden / Exclusive)
            [
                'name' => 'Welcome Points New Member',
                'type' => 'voucher_belanja',
                'code' => 'WELCOMEPOINTS',
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'min_purchase' => 0,
                'max_discount' => 0,
                'quota' => 1000,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => false, // Hidden, must scan QR or enter manually
                    'is_points_voucher' => true,
                    'points_new_user' => 15000,
                    'points_existing_user' => 0,
                    'terms' => "- Khusus untuk pengguna baru yang belum pernah belanja\n- Mendapatkan bonus +15.000 Poin setelah klaim\n- Hanya dapat diklaim 1x per pengguna baru",
                ],
            ],
            // 5. Voucher Poin - Existing User
            [
                'name' => 'Loyalty Reward Points',
                'type' => 'voucher_belanja',
                'code' => 'LOYALTYPOINTS',
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'min_purchase' => 50000,
                'max_discount' => 0,
                'quota' => 500,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => true,
                    'points_new_user' => 5000,
                    'points_existing_user' => 5000,
                    'terms' => "- Berlaku untuk pengguna lama maupun baru\n- Dapatkan +5.000 Poin\n- Minimal belanja Rp 50.000",
                ],
            ],
            // 6. Promo Toko - Automatic Discount
            [
                'name' => 'Promo Toko Cashback Mantap',
                'type' => 'promo_toko',
                'code' => 'CASHBACKMNTAP',
                'discount_type' => 'fixed',
                'discount_value' => 15000,
                'min_purchase' => 75000,
                'max_discount' => 15000,
                'quota' => 150,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Potongan Rp 15.000\n- Tanpa kode kupon, otomatis aktif\n- Minimal belanja Rp 75.000\n- Kuota terbatas",
                ],
            ],
            // 7. Flash Sale
            [
                'name' => 'Super Flash Sale 50% Off',
                'type' => 'flash_sale',
                'code' => 'FLASHSALE50',
                'discount_type' => 'percentage',
                'discount_value' => 50,
                'min_purchase' => 0,
                'max_discount' => 100000,
                'quota' => 50,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Diskon 50% s/d Rp 100.000\n- Hanya berlaku selama masa flash sale\n- Siapa cepat dia dapat\n- Kuota sangat terbatas",
                ],
            ],
            // 8. Voucher Belanja - Hidden (Exclusive QR scan)
            [
                'name' => 'Voucher Rahasia Pembaca Brosur',
                'type' => 'voucher_belanja',
                'code' => 'RAHASIABROSUR',
                'discount_type' => 'fixed',
                'discount_value' => 25000,
                'min_purchase' => 100000,
                'max_discount' => 25000,
                'quota' => 50,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => false, // Hidden, only by scanning QR on physical flyers
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Khusus pembaca brosur fisik\n- Wajib scan QR Code atau ketik manual kode kupon\n- Potongan Rp 25.000 minimal belanja Rp 100.000",
                ],
            ],
            // 9. Voucher Belanja - Percentage (High value)
            [
                'name' => 'Voucher Diskon Mega Sale 20%',
                'type' => 'voucher_belanja',
                'code' => 'MEGASALE20',
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'min_purchase' => 250000,
                'max_discount' => 100000,
                'quota' => 100,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Diskon 20% s/d Rp 100.000\n- Minimal belanja Rp 250.000\n- Satu kali klaim per akun",
                ],
            ],
            // 10. Voucher Gratis Ongkir - Unlimited Quota
            [
                'name' => 'Gratis Ongkir Seluruh Indonesia',
                'type' => 'voucher_gratis_ongkir',
                'code' => 'ONGKIRBEBAS',
                'discount_type' => 'free_shipping',
                'discount_value' => 15000,
                'min_purchase' => 30000,
                'max_discount' => 15000,
                'quota' => 99999,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Potongan ongkir s/d Rp 15.000\n- Minimal belanja Rp 30.000\n- Berlaku untuk semua metode pengiriman kurir",
                ],
            ],
            // 11. Voucher Belanja - Toko Khusus
            [
                'name' => 'Voucher Diskon Toko Baru',
                'type' => 'voucher_belanja',
                'code' => 'TOKOBARUPRO',
                'discount_type' => 'fixed',
                'discount_value' => 10000,
                'min_purchase' => 50000,
                'max_discount' => 10000,
                'quota' => 300,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Potongan Rp 10.000 dengan minimal belanja Rp 50.000\n- Berlaku untuk semua pengguna baru & lama",
                ],
            ],
            // 12. Voucher Poin - Event Kemerdekaan
            [
                'name' => 'Kado Poin Kemerdekaan RI',
                'type' => 'voucher_belanja',
                'code' => 'MERDEKA78',
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'min_purchase' => 17000,
                'max_discount' => 0,
                'quota' => 1945,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => true,
                    'points_new_user' => 17000,
                    'points_existing_user' => 8000,
                    'terms' => "- Kado spesial kemerdekaan\n- Dapatkan +17.000 Poin untuk pengguna baru\n- Dapatkan +8.000 Poin untuk pengguna lama\n- Minimal belanja Rp 17.000",
                ],
            ],
            // 13. Voucher Belanja - Promo Gajian Akhir Bulan
            [
                'name' => 'Voucher Cashback Gajian Akhir Bulan',
                'type' => 'voucher_belanja',
                'code' => 'GAJIANAKHIR',
                'discount_type' => 'fixed',
                'discount_value' => 50000,
                'min_purchase' => 300000,
                'max_discount' => 50000,
                'quota' => 80,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Potongan Rp 50.000\n- Minimal belanja Rp 300.000\n- Berlaku mulai tanggal 25 s/d akhir bulan",
                ],
            ],
            // 14. Voucher Gratis Ongkir - Min Belanja Tinggi
            [
                'name' => 'Gratis Ongkir Ekspedisi Kargo',
                'type' => 'voucher_gratis_ongkir',
                'code' => 'ONGKIRKARGO',
                'discount_type' => 'free_shipping',
                'discount_value' => 50000,
                'min_purchase' => 500000,
                'max_discount' => 50000,
                'quota' => 100,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Potongan ongkir s/d Rp 50.000\n- Minimal belanja Rp 500.000\n- Khusus pengiriman cargo",
                ],
            ],
            // 15. Voucher Belanja - Diskon Kilat Malam Hari (Hidden)
            [
                'name' => 'Night Flash Discount 15%',
                'type' => 'voucher_belanja',
                'code' => 'NIGHTOWL15',
                'discount_type' => 'percentage',
                'discount_value' => 15,
                'min_purchase' => 120000,
                'max_discount' => 40000,
                'quota' => 60,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => false, // Hidden, only for night owls
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Diskon 15% s/d Rp 40.000\n- Minimal belanja Rp 120.000\n- Hanya berlaku pukul 22:00 - 04:00 WIB",
                ],
            ],
            // 16. Voucher Poin - Cashback Belanja Gadget
            [
                'name' => 'Extra Poin Belanja Elektronik',
                'type' => 'voucher_belanja',
                'code' => 'POINTSGADGET',
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'min_purchase' => 1000000,
                'max_discount' => 0,
                'quota' => 100,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => true,
                    'points_new_user' => 50000,
                    'points_existing_user' => 25000,
                    'terms' => "- Dapatkan bonus poin belanja besar\n- Baru: +50.000 Poin\n- Lama: +25.000 Poin\n- Minimal belanja Rp 1.000.000",
                ],
            ],
            // 17. Voucher Belanja - Diskon Kasir (Hidden)
            [
                'name' => 'Voucher Spesial Admin Kasir',
                'type' => 'voucher_belanja',
                'code' => 'ADMINKASIR15K',
                'discount_type' => 'fixed',
                'discount_value' => 15000,
                'min_purchase' => 50000,
                'max_discount' => 15000,
                'quota' => 200,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => false,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Kupon khusus transaksi dibantu admin\n- Potongan Rp 15.000 minimal belanja Rp 50.000",
                ],
            ],
            // 18. Voucher Belanja - Diskon Flat Kecil
            [
                'name' => 'Voucher Diskon Belanja Asyik',
                'type' => 'voucher_belanja',
                'code' => 'BELANJAASYIK',
                'discount_type' => 'fixed',
                'discount_value' => 5000,
                'min_purchase' => 20000,
                'max_discount' => 5000,
                'quota' => 1000,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Potongan Rp 5.000\n- Tanpa batas produk\n- Minimal belanja sangat rendah Rp 20.000",
                ],
            ],
            // 19. Voucher Gratis Ongkir - Event Tanggal Kembar
            [
                'name' => 'Gratis Ongkir Super Double Date',
                'type' => 'voucher_gratis_ongkir',
                'code' => 'ONGKIRDOUBLEDATE',
                'discount_type' => 'free_shipping',
                'discount_value' => 30000,
                'min_purchase' => 80000,
                'max_discount' => 30000,
                'quota' => 250,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => false,
                    'points_new_user' => 0,
                    'points_existing_user' => 0,
                    'terms' => "- Bebas ongkir premium s/d Rp 30.000\n- Minimal belanja Rp 80.000\n- Berlaku selama event tanggal kembar",
                ],
            ],
            // 20. Voucher Poin - Cashback Weekend
            [
                'name' => 'Weekend Points Boost',
                'type' => 'voucher_belanja',
                'code' => 'WEEKENDPOINTS',
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'min_purchase' => 150000,
                'max_discount' => 0,
                'quota' => 150,
                'is_active' => true,
                'settings' => [
                    'show_publicly' => true,
                    'is_points_voucher' => true,
                    'points_new_user' => 20000,
                    'points_existing_user' => 10000,
                    'terms' => "- Kumpulkan poin ekstra di akhir pekan\n- Baru: +20.000 Poin\n- Lama: +10.000 Poin\n- Minimal belanja Rp 150.000",
                ],
            ],
        ];

        foreach ($promotions as $promo) {
            Promotion::updateOrCreate(
                ['code' => $promo['code']],
                [
                    'name' => $promo['name'],
                    'type' => $promo['type'],
                    'discount_type' => $promo['discount_type'],
                    'discount_value' => $promo['discount_value'],
                    'min_purchase' => $promo['min_purchase'],
                    'max_discount' => $promo['max_discount'],
                    'quota' => $promo['quota'],
                    'used_count' => 0,
                    'start_time' => Carbon::now(),
                    'end_time' => Carbon::now()->addDays(30),
                    'is_active' => $promo['is_active'],
                    'settings' => $promo['settings'],
                ]
            );
        }
    }
}
