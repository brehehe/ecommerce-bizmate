<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuModifier;
use App\Models\MenuModifierItem;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\RestoProfile;
use App\Models\StoreReview;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class IkanGorengNikmatAbadiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Pastikan role Seller & Customer tersedia
        if (! Role::where('name', 'Seller')->exists()) {
            Role::create(['name' => 'Seller']);
        }
        if (! Role::where('name', 'Customer')->exists()) {
            Role::create(['name' => 'Customer']);
        }

        // 2. Simpan Logo Resto
        $logoDest = 'logos/ikan-goreng-nikmat-abadi.jpg';
        $sourceLogo = base_path('public/images/ikan-goreng-nikmat-abadi.jpg');
        $storageDest = storage_path('app/public/'.$logoDest);

        if (File::exists($sourceLogo)) {
            File::ensureDirectoryExists(dirname($storageDest));
            File::copy($sourceLogo, $storageDest);
        }

        // 3. Akun Login Resto (Owner)
        $sellerEmail = 'nikmatabadi@foodpick.com';
        $seller = User::where('email', $sellerEmail)->first();

        if (! $seller) {
            $seller = User::create([
                'name' => 'Owner Ikan Goreng Nikmat Abadi',
                'email' => $sellerEmail,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'phone_number' => '081234567890',
                'is_seller' => true,
                'is_active' => true,
                'store_name' => 'Ikan Goreng Nikmat Abadi',
                'store_slug' => 'ikan-goreng-nikmat-abadi',
                'store_description' => 'Spesialis olahan ikan laut & tawar segar goreng renyah bumbu rempah, disajikan hangat dengan sambal khas Nikmat Abadi dan sayur asem segar.',
                'store_logo' => $logoDest,
            ]);
            $seller->assignRole('Seller');
        } else {
            $seller->update([
                'name' => 'Owner Ikan Goreng Nikmat Abadi',
                'email_verified_at' => $seller->email_verified_at ?? now(),
                'is_seller' => true,
                'is_active' => true,
                'store_name' => 'Ikan Goreng Nikmat Abadi',
                'store_slug' => 'ikan-goreng-nikmat-abadi',
                'store_description' => 'Spesialis olahan ikan laut & tawar segar goreng renyah bumbu rempah, disajikan hangat dengan sambal khas Nikmat Abadi dan sayur asem segar.',
                'store_logo' => $logoDest,
            ]);
        }

        // 4. Profil Resto (RestoProfile)
        $resto = RestoProfile::updateOrCreate(
            ['user_id' => $seller->id],
            [
                'resto_name' => 'Ikan Goreng Nikmat Abadi',
                'slug' => 'ikan-goreng-nikmat-abadi',
                'culinary_category' => 'Seafood & Ikan Bakar',
                'description' => 'Spesialis olahan aneka ikan goreng segar khas Surabaya. Pilihan ikan dorang, bandeng cabut duri, kerapu, belanak, udang, lengkap dengan sambel nikmat abadi dan aneka sayuran segar.',
                'logo_path' => $logoDest,
                'banner_path' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=1200&auto=format&fit=crop&q=80',
                'address' => 'Jl. Dharmahusada Indah No. 88, Mulyorejo, Surabaya, Jawa Timur 60115',
                'latitude' => -7.26543000,
                'longitude' => 112.77581000,
                'avg_prep_time_minutes' => 20,
                'allow_pickup' => true,
                'allow_delivery' => true,
                'max_delivery_radius_km' => 12.00,
                'delivery_fee_type' => 'flat',
                'delivery_fee_base' => 8000.00,
                'delivery_free_min_order' => 100000.00,
                'is_open_now' => true,
                'approval_status' => 'approved',
                'operating_hours' => [
                    'Senin' => '10:00 - 22:00',
                    'Selasa' => '10:00 - 22:00',
                    'Rabu' => '10:00 - 22:00',
                    'Kamis' => '10:00 - 22:00',
                    'Jumat' => '10:00 - 22:00',
                    'Sabtu' => '10:00 - 22:00',
                    'Minggu' => '10:00 - 22:00',
                ],
                'payment_methods' => [
                    'qris' => [
                        'is_active' => true,
                        'merchant_name' => 'IKAN GORENG NIKMAT ABADI',
                        'account_name' => 'IKAN GORENG NIKMAT ABADI',
                        'account_number' => 'NMID-992144812',
                    ],
                    'cod' => [
                        'is_active' => true,
                        'label' => 'Bayar di Tempat (COD / Kasir Resto)',
                    ],
                ],
            ]
        );

        // 5. Kategori Menu
        $categories = [
            'seafood' => Category::firstOrCreate(['slug' => 'menu-ikan-seafood'], [
                'name' => 'Menu Ikan & Seafood',
                'icon' => 'ti-fish',
            ]),
            'pendamping' => Category::firstOrCreate(['slug' => 'nasi-lauk-pendamping'], [
                'name' => 'Nasi & Lauk Pendamping',
                'icon' => 'ti-bowl',
            ]),
            'sambal' => Category::firstOrCreate(['slug' => 'aneka-sambal'], [
                'name' => 'Aneka Sambal',
                'icon' => 'ti-flame',
            ]),
            'minuman' => Category::firstOrCreate(['slug' => 'minuman-segar'], [
                'name' => 'Minuman Segar',
                'icon' => 'ti-cup',
            ]),
        ];

        // 6. Daftar 18 Menu Ikan Goreng Nikmat Abadi
        $menuList = [
            // Menu Ikan & Seafood
            [
                'name' => 'Ikan Dorang',
                'category' => $categories['seafood'],
                'price' => 40000,
                'image' => 'https://images.unsplash.com/photo-1534939561126-855b8675edd7?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Ikan dorang laut segar digoreng garing renyah di luar, daging lembut gurih manis alami.',
                'order' => 1,
                'has_fish_modifiers' => true,
            ],
            [
                'name' => 'Ikan Bandeng Cabut Duri',
                'category' => $categories['seafood'],
                'price' => 40000,
                'image' => 'https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Ikan bandeng segar bersih tanpa duri sama sekali, digoreng bumbu kuning renyah dan empuk.',
                'order' => 2,
                'has_fish_modifiers' => true,
            ],
            [
                'name' => 'Ikan Kerapu',
                'category' => $categories['seafood'],
                'price' => 35000,
                'image' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Ikan kerapu tangkapan segar pilihan, tekstur daging tebal gurih digoreng garing istimewa.',
                'order' => 3,
                'has_fish_modifiers' => true,
            ],
            [
                'name' => 'Ikan Belanak',
                'category' => $categories['seafood'],
                'price' => 30000,
                'image' => 'https://images.unsplash.com/photo-1580476262798-bddd9f4b7369?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Ikan belanak pesisir digoreng renyah garing hingga ke bagian sirip, gurih mantap.',
                'order' => 4,
                'has_fish_modifiers' => true,
            ],
            [
                'name' => 'Udang Goreng',
                'category' => $categories['seafood'],
                'price' => 30000,
                'image' => 'https://images.unsplash.com/photo-1565680018434-b513d5e5fd47?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Udang laut segar digoreng renyah keemasan, manis alami dan sangat gurih.',
                'order' => 5,
                'has_fish_modifiers' => true,
            ],
            [
                'name' => 'Pindang Goreng',
                'category' => $categories['seafood'],
                'price' => 18000,
                'image' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Ikan pindang berbumbu asin gurih khas, digoreng garing cocok dinikmati dengan sayur asem.',
                'order' => 6,
                'has_fish_modifiers' => true,
            ],
            [
                'name' => 'Ikan Tongkol',
                'category' => $categories['seafood'],
                'price' => 18000,
                'image' => 'https://images.unsplash.com/photo-1509722747041-616f39b57569?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Potongan ikan tongkol segar tebal dan padat, digoreng harum bumbu tradisional.',
                'order' => 7,
                'has_fish_modifiers' => true,
            ],

            // Nasi & Lauk Pendamping
            [
                'name' => 'Nasi Nikmat Abadi',
                'category' => $categories['pendamping'],
                'price' => 10000,
                'image' => 'https://images.unsplash.com/photo-1516684732162-798a0062be99?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Nasi gurih harum rempah khas resep dapur Nikmat Abadi dengan taburan serundeng kelapa & bawang goreng.',
                'order' => 8,
                'has_fish_modifiers' => false,
            ],
            [
                'name' => 'Nasi Putih',
                'category' => $categories['pendamping'],
                'price' => 5000,
                'image' => 'https://images.unsplash.com/photo-1536304993881-ff6e9eefa2a6?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Nasi putih pulen hangat dari beras berkualitas pilihan.',
                'order' => 9,
                'has_fish_modifiers' => false,
            ],
            [
                'name' => 'Sayur Asem',
                'category' => $categories['pendamping'],
                'price' => 5000,
                'image' => 'https://images.unsplash.com/photo-1547592180-85f173990554?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Kuah sayur asem bening segar asam manis seimbang, isi jagung manis, kacang panjang, labu siam, dan melinjo.',
                'order' => 10,
                'has_fish_modifiers' => false,
            ],
            [
                'name' => 'Tempe Goreng',
                'category' => $categories['pendamping'],
                'price' => 5000,
                'image' => 'https://images.unsplash.com/photo-1584947897667-27b6eb74a007?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Tempe kedelai murni digoreng gurih bumbu ketumbar renyah di luar lembut di dalam (isi 3 potong).',
                'order' => 11,
                'has_fish_modifiers' => false,
            ],
            [
                'name' => 'Tumis Kangkung',
                'category' => $categories['pendamping'],
                'price' => 8000,
                'image' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Kangkung hijau segar ditumis cepat dengan api besar, bawang merah, cabai rawit, dan terasi gurih harum.',
                'order' => 12,
                'has_fish_modifiers' => false,
            ],

            // Aneka Sambal
            [
                'name' => 'Sambel Nikmat Abadi',
                'category' => $categories['sambal'],
                'price' => 7000,
                'image' => 'https://images.unsplash.com/photo-1563227812-0ea4c22e6cc8?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Sambal racikan rahasia khas resto Nikmat Abadi, perpaduan cabai rawit pedas mantap dan tomat rampai segar.',
                'order' => 13,
                'has_fish_modifiers' => false,
            ],
            [
                'name' => 'Sambel Bawang',
                'category' => $categories['sambal'],
                'price' => 5000,
                'image' => 'https://images.unsplash.com/photo-1596797038530-2c107229654b?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Cabai rawit dan bawang putih diulek kasar, disiram minyak kelapa panas mendesis yang harum pedas nampol.',
                'order' => 14,
                'has_fish_modifiers' => false,
            ],

            // Minuman Segar
            [
                'name' => 'Lemon Tea',
                'category' => $categories['minuman'],
                'price' => 10000,
                'image' => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Teh seduh melati segar berpadu dengan sari lemon asli dan es batu dingin menyegarkan.',
                'order' => 15,
                'has_drink_modifiers' => true,
            ],
            [
                'name' => 'Orange Sunquick',
                'category' => $categories['minuman'],
                'price' => 10000,
                'image' => 'https://images.unsplash.com/photo-1613478223719-2ab802602423?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Minuman sari buah jeruk Sunquick kental manis dan asam segar dingin yang klasik dan melegakan dahaga.',
                'order' => 16,
                'has_drink_modifiers' => true,
            ],
            [
                'name' => 'Teh Panas',
                'category' => $categories['minuman'],
                'price' => 8000,
                'image' => 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Teh tubruk wangi melati seduh panas manis legit pas diseruput hangat.',
                'order' => 17,
                'has_drink_modifiers' => false,
            ],
            [
                'name' => 'Mineral',
                'category' => $categories['minuman'],
                'price' => 5000,
                'image' => 'https://images.unsplash.com/photo-1548839140-29a749e1bc4e?w=600&auto=format&fit=crop&q=80',
                'summary' => 'Air mineral pegunungan alami kemasan botol 600ml dingin atau suhu ruang.',
                'order' => 18,
                'has_drink_modifiers' => false,
            ],
        ];

        foreach ($menuList as $m) {
            $slug = Str::slug($m['name'].'-nikmat-abadi');

            $product = Product::updateOrCreate(
                [
                    'user_id' => $seller->id,
                    'slug' => $slug,
                ],
                [
                    'name' => $m['name'],
                    'sku' => 'NA-'.strtoupper(Str::substr(Str::slug($m['name']), 0, 8)),
                    'category_id' => $m['category']->id,
                    'brand' => 'Ikan Goreng Nikmat Abadi',
                    'summary' => $m['summary'],
                    'description' => '<p>'.$m['summary'].'</p><p>Disajikan hangat dan segar setiap saat langsung dari dapur Ikan Goreng Nikmat Abadi.</p>',
                    'image' => $m['image'],
                    'active' => true,
                    'stock_status' => 'instock',
                    'order' => $m['order'],
                    'condition' => 'new',
                ]
            );

            // Price
            ProductPrice::updateOrCreate(
                ['product_id' => $product->id, 'product_variant_id' => null],
                [
                    'price' => $m['price'],
                    'cost' => (int) round($m['price'] * 0.65),
                ]
            );

            // Stock
            ProductStock::updateOrCreate(
                ['product_id' => $product->id, 'product_variant_id' => null],
                [
                    'stock' => 100,
                    'min_stock' => 5,
                    'min_purchase' => 1,
                    'is_unlimited' => true,
                ]
            );

            // Main Image
            if ($product->images()->count() === 0) {
                $product->images()->create([
                    'path' => $m['image'],
                    'is_main' => true,
                    'sort_order' => 0,
                ]);
            }

            // Modifier untuk Menu Ikan (Tingkat Kematangan & Pilihan Ekstra)
            if (! empty($m['has_fish_modifiers'])) {
                if ($product->menuModifiers()->count() === 0) {
                    $modKematangan = MenuModifier::create([
                        'product_id' => $product->id,
                        'group_name' => 'Tingkat Kematangan',
                        'is_required' => false,
                        'is_multiple' => false,
                        'max_selectable' => 1,
                    ]);
                    MenuModifierItem::create([
                        'menu_modifier_id' => $modKematangan->id,
                        'name' => 'Goreng Kering Renyah (Standar)',
                        'additional_price' => 0,
                        'is_default' => true,
                        'is_available' => true,
                    ]);
                    MenuModifierItem::create([
                        'menu_modifier_id' => $modKematangan->id,
                        'name' => 'Goreng Lembut / Tidak Terlalu Kering',
                        'additional_price' => 0,
                        'is_default' => false,
                        'is_available' => true,
                    ]);

                    $modTambahan = MenuModifier::create([
                        'product_id' => $product->id,
                        'group_name' => 'Pilihan Ekstra Sambal',
                        'is_required' => false,
                        'is_multiple' => true,
                        'max_selectable' => 2,
                    ]);
                    MenuModifierItem::create([
                        'menu_modifier_id' => $modTambahan->id,
                        'name' => 'Ekstra Sambel Nikmat Abadi',
                        'additional_price' => 3000,
                        'is_default' => false,
                        'is_available' => true,
                    ]);
                    MenuModifierItem::create([
                        'menu_modifier_id' => $modTambahan->id,
                        'name' => 'Ekstra Sambel Bawang',
                        'additional_price' => 2000,
                        'is_default' => false,
                        'is_available' => true,
                    ]);
                }
            }

            // Modifier Minuman (Es & Tingkat Manis)
            if (! empty($m['has_drink_modifiers'])) {
                if ($product->menuModifiers()->count() === 0) {
                    $modEs = MenuModifier::create([
                        'product_id' => $product->id,
                        'group_name' => 'Penyajian',
                        'is_required' => false,
                        'is_multiple' => false,
                        'max_selectable' => 1,
                    ]);
                    MenuModifierItem::create([
                        'menu_modifier_id' => $modEs->id,
                        'name' => 'Dingin (Pakai Es Batu)',
                        'additional_price' => 0,
                        'is_default' => true,
                        'is_available' => true,
                    ]);
                    MenuModifierItem::create([
                        'menu_modifier_id' => $modEs->id,
                        'name' => 'Tanpa Es / Suhu Ruang',
                        'additional_price' => 0,
                        'is_default' => false,
                        'is_available' => true,
                    ]);
                }
            }
        }

        // 7. Seed 3 Ulasan Real-Time Pelanggan Puas
        $customerA = User::firstOrCreate(['email' => 'hendra.kusuma@gmail.com'], [
            'name' => 'Hendra Kusuma',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $customerB = User::firstOrCreate(['email' => 'maya.safitri@gmail.com'], [
            'name' => 'Maya Safitri',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $customerC = User::firstOrCreate(['email' => 'andi.wijaya@gmail.com'], [
            'name' => 'Andi Wijaya',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $reviewsData = [
            [
                'user' => $customerA,
                'name' => 'Hendra Kusuma',
                'phone' => '081298761234',
                'rating' => 5,
                'comment' => 'Ikan dorang gorengnya garing pol sampai ke tulang kepalanya bisa dimakan! Sambel nikmat abadinya juara pedasnya, mantap!',
                'trx_num' => 'TRX-NA-'.now()->format('Ymd').'-0001',
            ],
            [
                'user' => $customerB,
                'name' => 'Maya Safitri',
                'phone' => '082155443322',
                'rating' => 5,
                'comment' => 'Bandeng cabut durinya beneran bersih tanpa duri sama sekali, anak-anak suka banget. Sayur asemnya juga seger manis asamnya pas.',
                'trx_num' => 'TRX-NA-'.now()->format('Ymd').'-0002',
            ],
            [
                'user' => $customerC,
                'name' => 'Andi Wijaya',
                'phone' => '087811223344',
                'rating' => 5,
                'comment' => 'Kerapu dan udang gorengnya renyah gurih, porsi nasi nikmat abadinya harum pulen. Sangat direkomendasikan untuk makan siang keluarga.',
                'trx_num' => 'TRX-NA-'.now()->format('Ymd').'-0003',
            ],
        ];

        foreach ($reviewsData as $rev) {
            $trx = Transaction::firstOrCreate(['transaction_number' => $rev['trx_num']], [
                'user_id' => $rev['user']->id,
                'resto_id' => $seller->id,
                'status' => 'selesai',
                'payment_status' => 'lunas',
                'grand_total' => 85000,
            ]);

            StoreReview::firstOrCreate(['transaction_id' => $trx->id], [
                'resto_profile_id' => $resto->id,
                'seller_id' => $seller->id,
                'user_id' => $rev['user']->id,
                'customer_name' => $rev['name'],
                'customer_phone' => $rev['phone'],
                'rating' => $rev['rating'],
                'comment' => $rev['comment'],
                'is_public' => true,
            ]);
        }
    }
}
