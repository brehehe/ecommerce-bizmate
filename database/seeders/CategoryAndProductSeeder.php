<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoryAndProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 10 Realistic Categories with Products (some have variations/variants)
        $categoriesData = [
            [
                'name' => 'Elektronik & Gadget',
                'icon' => 'ti-cpu',
                'slug' => 'elektronik-gadget',
                'products' => [
                    [
                        'name' => 'Laptop ASUS Vivobook Go 14',
                        'brand' => 'ASUS',
                        'price' => 6499000,
                        'image' => 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1496181130204-7552cc14ac1a?w=600&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1531297484001-80022131f5a1?w=600&auto=format&fit=crop&q=80',
                        ],
                        'video_path' => 'https://www.w3schools.com/html/mov_bbb.mp4',
                        'model_3d_path' => 'https://modelviewer.dev/shared-assets/models/Astronaut.glb',
                        'model_3d_usdz_path' => 'https://modelviewer.dev/shared-assets/models/Astronaut.usdz',
                        'summary' => 'Laptop ringan & kencang untuk produktivitas harian Anda.',
                    ],
                    [
                        'name' => 'Smartwatch Xiaomi Band 8 Pro',
                        'brand' => 'Xiaomi',
                        'price' => 999000,
                        'image' => 'https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=600&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1434494878577-86c23bcb06b9?w=600&auto=format&fit=crop&q=80',
                        ],
                        'video_path' => 'https://www.w3schools.com/html/mov_bbb.mp4',
                        'model_3d_path' => 'https://modelviewer.dev/shared-assets/models/NeilArmstrong.glb',
                        'model_3d_usdz_path' => 'https://modelviewer.dev/shared-assets/models/NeilArmstrong.usdz',
                        'summary' => 'Pelacak kesehatan pintar dengan layar AMOLED lebar.',
                        'variations' => [
                            [
                                'name' => 'Warna',
                                'options' => [
                                    [
                                        'name' => 'Space Black',
                                        'image' => 'https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=600&auto=format&fit=crop&q=80',
                                    ],
                                    [
                                        'name' => 'Light Gold',
                                        'image' => 'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=600&auto=format&fit=crop&q=80',
                                    ],
                                    [
                                        'name' => 'Blue Edition',
                                        'image' => 'https://images.unsplash.com/photo-1434494878577-86c23bcb06b9?w=600&auto=format&fit=crop&q=80',
                                    ],
                                ],
                            ],
                        ],
                        'variants' => [
                            [
                                'combination' => ['Space Black'],
                                'price' => 999000,
                                'stock' => 50,
                            ],
                            [
                                'combination' => ['Light Gold'],
                                'price' => 1049000,
                                'stock' => 30,
                            ],
                            [
                                'combination' => ['Blue Edition'],
                                'price' => 1099000,
                                'stock' => 15,
                            ],
                        ],
                    ],
                    [
                        'name' => 'TWS Bluetooth Earbuds ANC',
                        'brand' => 'Soundcore',
                        'price' => 450000,
                        'image' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1588444837495-c6cfeb53f32d?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Audio bass mendalam dengan peredam kebisingan aktif.',
                        'variations' => [
                            [
                                'name' => 'Warna',
                                'options' => [
                                    [
                                        'name' => 'Phantom Black',
                                        'image' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=600&auto=format&fit=crop&q=80',
                                    ],
                                    [
                                        'name' => 'Pearl White',
                                        'image' => 'https://images.unsplash.com/photo-1588444837495-c6cfeb53f32d?w=600&auto=format&fit=crop&q=80',
                                    ],
                                ],
                            ],
                        ],
                        'variants' => [
                            [
                                'combination' => ['Phantom Black'],
                                'price' => 450000,
                                'stock' => 80,
                            ],
                            [
                                'combination' => ['Pearl White'],
                                'price' => 450000,
                                'stock' => 60,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Powerbank Fast Charge 20000mAh',
                        'brand' => 'Anker',
                        'price' => 380000,
                        'image' => 'https://images.unsplash.com/photo-1609592424109-dd9892f1b17c?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1609592534837-2cd9d9b23b18?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Daya cadangan ekstra besar untuk gadget Anda selama perjalanan.',
                    ],
                    [
                        'name' => 'Keyboard Mechanical Wireless RGB',
                        'brand' => 'Keychron',
                        'price' => 1250000,
                        'image' => 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1595225476474-87563907a212?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Keyboard mekanik premium dengan tactile switch yang memuaskan.',
                        'variations' => [
                            [
                                'name' => 'Switch',
                                'options' => [
                                    ['name' => 'Blue Switch'],
                                    ['name' => 'Red Switch'],
                                    ['name' => 'Brown Switch'],
                                ],
                            ],
                        ],
                        'variants' => [
                            [
                                'combination' => ['Blue Switch'],
                                'price' => 1250000,
                                'stock' => 40,
                            ],
                            [
                                'combination' => ['Red Switch'],
                                'price' => 1250000,
                                'stock' => 35,
                            ],
                            [
                                'combination' => ['Brown Switch'],
                                'price' => 1299000,
                                'stock' => 20,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Pakaian Pria',
                'icon' => 'ti-shirt',
                'slug' => 'pakaian-pria',
                'products' => [
                    [
                        'name' => 'Kaos Polos Cotton Combed 30s Premium',
                        'brand' => 'Aerostreet',
                        'price' => 59000,
                        'image' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?w=600&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1562157873-818bc0726f68?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Bahan 100% katun murni, adem, nyaman dipakai sehari-hari.',
                        'variations' => [
                            [
                                'name' => 'Warna',
                                'options' => [
                                    [
                                        'name' => 'Hitam',
                                        'image' => 'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?w=600&auto=format&fit=crop&q=80',
                                    ],
                                    [
                                        'name' => 'Putih',
                                        'image' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80',
                                    ],
                                    [
                                        'name' => 'Merah',
                                        'image' => 'https://images.unsplash.com/photo-1562157873-818bc0726f68?w=600&auto=format&fit=crop&q=80',
                                    ],
                                ],
                            ],
                            [
                                'name' => 'Ukuran',
                                'options' => [
                                    ['name' => 'M'],
                                    ['name' => 'L'],
                                    ['name' => 'XL'],
                                ],
                            ],
                        ],
                        'variants' => [
                            ['combination' => ['Hitam', 'M'], 'price' => 59000, 'stock' => 20],
                            ['combination' => ['Hitam', 'L'], 'price' => 59000, 'stock' => 25],
                            ['combination' => ['Hitam', 'XL'], 'price' => 64000, 'stock' => 15],
                            ['combination' => ['Putih', 'M'], 'price' => 59000, 'stock' => 30],
                            ['combination' => ['Putih', 'L'], 'price' => 59000, 'stock' => 35],
                            ['combination' => ['Putih', 'XL'], 'price' => 64000, 'stock' => 18],
                            ['combination' => ['Merah', 'M'], 'price' => 59000, 'stock' => 12],
                            ['combination' => ['Merah', 'L'], 'price' => 59000, 'stock' => 15],
                            ['combination' => ['Merah', 'XL'], 'price' => 64000, 'stock' => 10],
                        ],
                    ],
                    [
                        'name' => 'Kemeja Flanel Slim Fit Lengan Panjang',
                        'brand' => 'Uniqlo',
                        'price' => 299000,
                        'image' => 'https://images.unsplash.com/photo-1598033129183-c4f50c736f10?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1617137968427-85924c800a22?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Motif kotak kasual, pas di badan, bahan katun flanel lembut.',
                        'variations' => [
                            [
                                'name' => 'Warna',
                                'options' => [
                                    [
                                        'name' => 'Red Plaid',
                                        'image' => 'https://images.unsplash.com/photo-1598033129183-c4f50c736f10?w=600&auto=format&fit=crop&q=80',
                                    ],
                                    [
                                        'name' => 'Blue Plaid',
                                        'image' => 'https://images.unsplash.com/photo-1617137968427-85924c800a22?w=600&auto=format&fit=crop&q=80',
                                    ],
                                ],
                            ],
                        ],
                        'variants' => [
                            ['combination' => ['Red Plaid'], 'price' => 299000, 'stock' => 15],
                            ['combination' => ['Blue Plaid'], 'price' => 299000, 'stock' => 20],
                        ],
                    ],
                    [
                        'name' => 'Celana Chino Panjang Stretch Premium',
                        'brand' => 'Erigo',
                        'price' => 175000,
                        'image' => 'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1479064555552-3ef4979f8908?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Bahan melar elastis untuk pergerakan kaki yang bebas dan nyaman.',
                        'variations' => [
                            [
                                'name' => 'Warna',
                                'options' => [
                                    [
                                        'name' => 'Black Chino',
                                        'image' => 'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?w=600&auto=format&fit=crop&q=80',
                                    ],
                                    [
                                        'name' => 'Grey Chino',
                                        'image' => 'https://images.unsplash.com/photo-1479064555552-3ef4979f8908?w=600&auto=format&fit=crop&q=80',
                                    ],
                                ],
                            ],
                        ],
                        'variants' => [
                            ['combination' => ['Black Chino'], 'price' => 175000, 'stock' => 25],
                            ['combination' => ['Grey Chino'], 'price' => 185000, 'stock' => 18],
                        ],
                    ],
                    [
                        'name' => 'Jaket Bomber Windproof Kasual',
                        'brand' => 'Roughneck',
                        'price' => 220000,
                        'image' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1483985988355-763728e1935b?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Jaket pelindung angin dengan desain trendi anak muda.',
                    ],
                    [
                        'name' => 'Sweater Hoodie Fleece Tebal',
                        'brand' => 'Maternal',
                        'price' => 195000,
                        'image' => 'https://images.unsplash.com/photo-1556821840-3a63f95609a7?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Hoodie hangat dengan bahan fleece lembut berkualitas ekspor.',
                    ],
                ],
            ],
            [
                'name' => 'Pakaian Wanita',
                'icon' => 'ti-shirt',
                'slug' => 'pakaian-wanita',
                'products' => [
                    [
                        'name' => 'Blouse Katun Rayon Adem Premium',
                        'brand' => 'Berrybenka',
                        'price' => 129000,
                        'image' => 'https://images.unsplash.com/photo-1585487000160-6ebcfceb0d03?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1609357605129-26f69add5d6e?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Model cantik dan elegan cocok untuk kerja maupun hangout.',
                        'variations' => [
                            [
                                'name' => 'Warna',
                                'options' => [
                                    [
                                        'name' => 'Lilac Blouse',
                                        'image' => 'https://images.unsplash.com/photo-1585487000160-6ebcfceb0d03?w=600&auto=format&fit=crop&q=80',
                                    ],
                                    [
                                        'name' => 'Sage Green Blouse',
                                        'image' => 'https://images.unsplash.com/photo-1609357605129-26f69add5d6e?w=600&auto=format&fit=crop&q=80',
                                    ],
                                ],
                            ],
                        ],
                        'variants' => [
                            ['combination' => ['Lilac Blouse'], 'price' => 129000, 'stock' => 30],
                            ['combination' => ['Sage Green Blouse'], 'price' => 129000, 'stock' => 25],
                        ],
                    ],
                    [
                        'name' => 'Tunik Plisket Flowy Lengan Balon',
                        'brand' => 'Zoya',
                        'price' => 189000,
                        'image' => 'https://images.unsplash.com/photo-1609357605129-26f69add5d6e?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1585487000160-6ebcfceb0d03?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Tunik plisket modis dan syari dengan kain jatuh elegan.',
                    ],
                    [
                        'name' => 'Dress Panjang Floral Kasual',
                        'brand' => 'Sorella',
                        'price' => 245000,
                        'image' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1596783074918-c84cb06531ca?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Gamis motif bunga yang feminin dan menyejukkan mata.',
                    ],
                    [
                        'name' => 'Cardigan Rajut Oversize Retro',
                        'brand' => 'ShopAtVelvet',
                        'price' => 140000,
                        'image' => 'https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Outer hangat rajutan retro dengan potongan trendi korea.',
                    ],
                    [
                        'name' => 'Celana Kulot Highwaist Loose Fit',
                        'brand' => 'Minimal',
                        'price' => 160000,
                        'image' => 'https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1594633313093-5959444d326a?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Membuat kaki terlihat lebih jenjang dengan kenyamanan loose fit.',
                    ],
                ],
            ],
            [
                'name' => 'Sepatu & Sandal',
                'icon' => 'ti-shoe',
                'slug' => 'sepatu-sandal',
                'products' => [
                    [
                        'name' => 'Sepatu Sneakers Aerostreet Massive',
                        'brand' => 'Aerostreet',
                        'price' => 149000,
                        'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Sepatu sneakers lokal kualitas jempolan tahan jebol luar biasa.',
                        'variations' => [
                            [
                                'name' => 'Warna',
                                'options' => [
                                    [
                                        'name' => 'Red White',
                                        'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&auto=format&fit=crop&q=80',
                                    ],
                                    [
                                        'name' => 'All White',
                                        'image' => 'https://images.unsplash.com/photo-1600185365483-26d7a4cc7519?w=600&auto=format&fit=crop&q=80',
                                    ],
                                ],
                            ],
                            [
                                'name' => 'Ukuran',
                                'options' => [
                                    ['name' => '40'],
                                    ['name' => '41'],
                                    ['name' => '42'],
                                ],
                            ],
                        ],
                        'variants' => [
                            ['combination' => ['Red White', '40'], 'price' => 149000, 'stock' => 10],
                            ['combination' => ['Red White', '41'], 'price' => 149000, 'stock' => 12],
                            ['combination' => ['Red White', '42'], 'price' => 149000, 'stock' => 8],
                            ['combination' => ['All White', '40'], 'price' => 159000, 'stock' => 15],
                            ['combination' => ['All White', '41'], 'price' => 159000, 'stock' => 20],
                            ['combination' => ['All White', '42'], 'price' => 159000, 'stock' => 12],
                        ],
                    ],
                    [
                        'name' => 'Sepatu Running Breathable Mesh',
                        'brand' => 'League',
                        'price' => 380000,
                        'image' => 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Sepatu olahraga super ringan dengan ventilasi udara mesh aktif.',
                        'variations' => [
                            [
                                'name' => 'Warna',
                                'options' => [
                                    [
                                        'name' => 'Neon Green',
                                        'image' => 'https://images.unsplash.com/photo-1606107557195-0e29a4b5b4aa?w=600&auto=format&fit=crop&q=80',
                                    ],
                                ],
                            ],
                        ],
                        'variants' => [
                            ['combination' => ['Neon Green'], 'price' => 380000, 'stock' => 14],
                        ],
                    ],
                    [
                        'name' => 'Sandal Slide Slip-on Casual',
                        'brand' => 'Carvil',
                        'price' => 79000,
                        'image' => 'https://images.unsplash.com/photo-1603808033192-082d6919d3e1?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Sandal kasual empuk anti-selip untuk menemani waktu santai Anda.',
                    ],
                    [
                        'name' => 'Sepatu Formal Pantofel Kulit Sintetis',
                        'brand' => 'Jim Joker',
                        'price' => 299000,
                        'image' => 'https://images.unsplash.com/photo-1533867617858-e7b97e060509?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Desain elegan kilap untuk pesta formal maupun urusan dinas kantor.',
                    ],
                    [
                        'name' => 'Sepatu Kanvas Slip-on Kasual',
                        'brand' => 'Compass',
                        'price' => 420000,
                        'image' => 'https://images.unsplash.com/photo-1525966222134-fcfa99dd8ec7?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Sepatu kanvas legendaris bergaya retro vintage yang ikonik.',
                    ],
                ],
            ],
            [
                'name' => 'Kecantikan & Kosmetik',
                'icon' => 'ti-sparkles',
                'slug' => 'kecantikan-kosmetik',
                'products' => [
                    [
                        'name' => 'Lip Cream Matte Tahan Lama',
                        'brand' => 'Wardah',
                        'price' => 62000,
                        'image' => 'https://images.unsplash.com/photo-1586495777744-4413f21062fa?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Tekstur lembut velvet matte tidak membuat bibir kering pecah.',
                    ],
                    [
                        'name' => 'Serum Wajah Brightening Niacinamide 10%',
                        'brand' => 'Somethinc',
                        'price' => 115000,
                        'image' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Serum pencerah kulit intensif menyamarkan noda hitam bekas jerawat.',
                    ],
                    [
                        'name' => 'Sunscreen SPF 50 PA++++ Gel Cair',
                        'brand' => 'Azarine',
                        'price' => 59000,
                        'image' => 'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Tabir surya harian super ringan, no white-cast, cepat meresap di kulit.',
                    ],
                    [
                        'name' => 'Micellar Water Hydrating Pembersih',
                        'brand' => 'Garnier',
                        'price' => 45000,
                        'image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Mengangkat make-up kotoran sekali usap lembut melembapkan.',
                    ],
                    [
                        'name' => 'Masker Wajah Clay Mask Mugwort',
                        'brand' => 'Glad2Glow',
                        'price' => 39000,
                        'image' => 'https://images.unsplash.com/photo-1567894340315-735d7c361db0?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Masker lumpur pembersih pori-pori komedo secara mendalam & meredakan jerawat.',
                    ],
                ],
            ],
            [
                'name' => 'Kesehatan',
                'icon' => 'ti-stethoscope',
                'slug' => 'kesehatan',
                'products' => [
                    [
                        'name' => 'Multivitamin Vitamin C 1000mg Strip',
                        'brand' => 'Enervon-C',
                        'price' => 42000,
                        'image' => 'https://images.unsplash.com/photo-1616679911721-eff6eec18fcd?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Meningkatkan daya tahan tubuh menangkal radikal bebas penyakit.',
                    ],
                    [
                        'name' => 'Minyak Kayu Putih Ambon Asli 120ml',
                        'brand' => 'Cap Lang',
                        'price' => 48000,
                        'image' => 'https://images.unsplash.com/photo-1608571423902-eed4a5ad8108?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Meredakan perut kembung, masuk angin, pegal linu dengan aroma hangat khas.',
                    ],
                    [
                        'name' => 'Madu Murni Asli Alami 500g',
                        'brand' => 'Madu Nusantara',
                        'price' => 85000,
                        'image' => 'https://images.unsplash.com/photo-1587049352846-4a222e784d38?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Madu hutan asli bernutrisi tinggi baik untuk kesehatan organ dalam.',
                    ],
                    [
                        'name' => 'Masker Medis 3-Ply Earloop Box',
                        'brand' => 'Sensi',
                        'price' => 35000,
                        'image' => 'https://images.unsplash.com/photo-1584622650111-993a426fbf0a?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Masker pelindung saluran napas dari bakteri kuman debu udara luar.',
                    ],
                    [
                        'name' => 'Plester Luka Elastis Box isi 100',
                        'brand' => 'Hansaplast',
                        'price' => 22000,
                        'image' => 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Plester luka berpori mencegah infeksi goresan luar dengan aman.',
                    ],
                ],
            ],
            [
                'name' => 'Olahraga & Outdoor',
                'icon' => 'ti-ball-basketball',
                'slug' => 'olahraga-outdoor',
                'products' => [
                    [
                        'name' => 'Matras Yoga Anti-Slip TPE Tebal',
                        'brand' => 'Specs',
                        'price' => 125000,
                        'image' => 'https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Matras busa olahraga yang empuk, tidak licin untuk kelenturan tubuh.',
                    ],
                    [
                        'name' => 'Tas Ransel Outdoor Carrier 40L',
                        'brand' => 'Eiger',
                        'price' => 650000,
                        'image' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Carrier tangguh tahan gores air untuk mendaki gunung camping.',
                    ],
                    [
                        'name' => 'Botol Minum Tumbler Sport Vakum',
                        'brand' => 'LocknLock',
                        'price' => 199000,
                        'image' => 'https://images.unsplash.com/photo-1602143407151-7111542de6e8?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Menjaga air dingin/panas tetap segar tahan hingga 12 jam lamanya.',
                    ],
                    [
                        'name' => 'Tenda Camping Dome Kapasitas 4 Orang',
                        'brand' => 'Consina',
                        'price' => 450000,
                        'image' => 'https://images.unsplash.com/photo-1510312305653-8ed496efae75?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Tenda camping gampang dipasang, kuat terpaan badai angin kencang.',
                    ],
                    [
                        'name' => 'Jersey Olahraga Dry-Fit Polos',
                        'brand' => 'Ortuseight',
                        'price' => 69000,
                        'image' => 'https://images.unsplash.com/photo-1517466787929-bc90951d0974?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Bahan kaos jersey cepat kering, tidak bau keringat, melar fleksibel.',
                    ],
                ],
            ],
            [
                'name' => 'Perlengkapan Rumah',
                'icon' => 'ti-home',
                'slug' => 'perlengkapan-rumah',
                'products' => [
                    [
                        'name' => 'Sprei Microtex Lembut King Size',
                        'brand' => 'Kangaroo',
                        'price' => 135000,
                        'image' => 'https://images.unsplash.com/photo-1505691938895-1758d7feb511?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Kain sprei super halus sejuk tidak luntur pudar walau dicuci berulang kali.',
                    ],
                    [
                        'name' => 'Gantungan Baju Hanger Besi Kayu isi 10',
                        'brand' => 'Goto',
                        'price' => 45000,
                        'image' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Gantungan kayu ganteng elegan tidak merusak kerah baju kaos jas.',
                    ],
                    [
                        'name' => 'Rak Sepatu Susun Kayu Minimalis',
                        'brand' => 'Olympic',
                        'price' => 195000,
                        'image' => 'https://images.unsplash.com/photo-1595428774223-ef52624120d2?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Lemari rak pajangan sepatu estetik menghias sudut ruang tamu depan.',
                    ],
                    [
                        'name' => 'Lampu LED Bohlam Hemat Energi 12W',
                        'brand' => 'Philips',
                        'price' => 38000,
                        'image' => 'https://images.unsplash.com/photo-1550985616-10810253b84d?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Terang benderang merata ke sekeliling rumah tanpa boros tagihan listrik.',
                    ],
                    [
                        'name' => 'Kotak Keranjang Penyimpanan Organizer',
                        'brand' => 'Informa',
                        'price' => 59000,
                        'image' => 'https://images.unsplash.com/photo-1532372320978-9b4d6a3a854c?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Kotak box serbaguna menyusun mainan baju agar rumah senantiasa rapi.',
                    ],
                ],
            ],
            [
                'name' => 'Makanan & Minuman',
                'icon' => 'ti-salad',
                'slug' => 'makanan-minuman',
                'products' => [
                    [
                        'name' => 'Kopi Bubuk Arabika Gayo Asli 250g',
                        'brand' => 'Excelso',
                        'price' => 69000,
                        'image' => 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Biji kopi arabika pilihan aceh gayo aroma semerbak memikat.',
                    ],
                    [
                        'name' => 'Kripik Singkong Balado Gurih 500g',
                        'brand' => 'Maicih',
                        'price' => 28000,
                        'image' => 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Camilan keripik singkong renyah dengan bumbu pedas manis gurih nagih.',
                    ],
                    [
                        'name' => 'Teh Celup Jasmine Box isi 50',
                        'brand' => 'Sariwangi',
                        'price' => 15000,
                        'image' => 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Seduhan teh melati wangi menyegarkan pikiran di kala sore hari.',
                    ],
                    [
                        'name' => 'Susu Almond Tanpa Pemanis 1L',
                        'brand' => 'Almond Breeze',
                        'price' => 55000,
                        'image' => 'https://images.unsplash.com/photo-1568651343853-2198c6a47a97?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Susu almond sehat kaya kalsium bebas kolesterol pengganti susu sapi.',
                    ],
                    [
                        'name' => 'Mie Instan Goreng Rasa Klasik Dus',
                        'brand' => 'Indomie',
                        'price' => 118000,
                        'image' => 'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Mie instan goreng favorit sejuta umat lezat tiada duanya.',
                    ],
                ],
            ],
            [
                'name' => 'Aksesoris & Jam Tangan',
                'icon' => 'ti-watch',
                'slug' => 'aksesoris-jam-tangan',
                'products' => [
                    [
                        'name' => 'Jam Tangan Pria Quartz Analog',
                        'brand' => 'Casio',
                        'price' => 380000,
                        'image' => 'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Desain klasik kasual tahan cipratan air sangat awet bertahun-tahun.',
                        'variations' => [
                            [
                                'name' => 'Warna',
                                'options' => [
                                    [
                                        'name' => 'Black Leather',
                                        'image' => 'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=600&auto=format&fit=crop&q=80',
                                    ],
                                ],
                            ],
                        ],
                        'variants' => [
                            ['combination' => ['Black Leather'], 'price' => 380000, 'stock' => 12],
                        ],
                    ],
                    [
                        'name' => 'Kacamata Hitam Polarized UV400',
                        'brand' => 'Ray-Ban',
                        'price' => 185000,
                        'image' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Lensa kacamata hitam pelindung silaunya terik matahari pantai.',
                    ],
                    [
                        'name' => 'Dompet Kulit Sapi Minimalis Cardholder',
                        'brand' => 'Bostanten',
                        'price' => 120000,
                        'image' => 'https://images.unsplash.com/photo-1627124112126-e23e7465363b?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Dompet saku tipis kulit asli muat banyak kartu ATM uang kertas.',
                    ],
                    [
                        'name' => 'Gantungan Kunci Kulit Tali Kepang',
                        'brand' => 'Levis',
                        'price' => 25000,
                        'image' => 'https://images.unsplash.com/photo-1582139329536-e7284fece509?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Pengikat kunci motor rumah estetik dari kulit sintetis anyaman.',
                    ],
                    [
                        'name' => 'Topi Baseball Polos Kanvas Unisex',
                        'brand' => 'Eiger',
                        'price' => 55000,
                        'image' => 'https://images.unsplash.com/photo-1588850561407-ed78c282e89b?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [],
                        'summary' => 'Topi lidah melengkung melindungi wajah dari sengatan sinar panas.',
                    ],
                ],
            ],
        ];

        // Create active Brands based on data
        $brandNames = [];
        foreach ($categoriesData as $catData) {
            foreach ($catData['products'] as $prodData) {
                if (! empty($prodData['brand'])) {
                    $brandNames[] = $prodData['brand'];
                }
            }
        }
        $brandNames = array_unique($brandNames);

        $brandModels = [];
        $order = 1;
        foreach ($brandNames as $name) {
            $slug = Str::slug($name);
            $brandModels[$name] = Brand::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'is_active' => true,
                    'order' => $order++,
                ]
            );
        }

        // Seed Categories and Products
        foreach ($categoriesData as $catData) {
            // 1. Create Category
            $category = Category::create([
                'name' => $catData['name'],
                'icon' => $catData['icon'],
                'slug' => $catData['slug'],
                'image' => null,
                'parent_id' => null,
            ]);

            // 2. Create Products for each Category
            foreach ($catData['products'] as $prodData) {
                $sku = 'SKU-' . strtoupper(Str::random(3)) . '-' . rand(1000, 9999);
                $brandModel = $brandModels[$prodData['brand']] ?? null;

                $product = Product::create([
                    'name' => $prodData['name'],
                    'slug' => Str::slug($prodData['name']) . '-' . Str::random(5),
                    'sku' => $sku,
                    'category_id' => $category->id,
                    'brand_id' => $brandModel?->id,
                    'brand' => $prodData['brand'],
                    'stock_status' => 'in_stock',
                    'summary' => $prodData['summary'],
                    'description' => $this->getLongDescriptionForProduct($category->slug, $prodData['name']),
                    'size_chart' => $this->getSizeChartForProduct($category->slug, $prodData['name']),
                    'weight' => rand(100, 2000),
                    'length' => rand(5, 50),
                    'width' => rand(5, 50),
                    'height' => rand(5, 50),
                    'tax_enabled' => false,
                    'tax_rate' => 0,
                    'active' => true,
                    'image' => $prodData['image'],
                    'specifications' => $this->getSpecificationsForProduct($category->slug, $prodData['name']),
                    'video_path' => $prodData['video_path'] ?? null,
                    'model_3d_path' => $prodData['model_3d_path'] ?? null,
                    'model_3d_usdz_path' => $prodData['model_3d_usdz_path'] ?? null,
                ]);

                // Sync many-to-many pivots
                $product->categories()->sync([$category->id]);
                if ($brandModel) {
                    $product->brands()->sync([$brandModel->id]);
                }

                // 3. Create Master Price
                $product->productPrice()->create([
                    'price' => $prodData['price'],
                    'cost' => round($prodData['price'] * 0.8),
                ]);

                // 4. Create Master Stock
                $product->productStock()->create([
                    'stock' => rand(20, 150),
                    'min_stock' => rand(2, 5),
                    'min_purchase' => 1,
                    'is_unlimited' => false,
                ]);

                // 5. Create Main Product Image Link
                $product->images()->create([
                    'path' => $prodData['image'],
                    'is_main' => true,
                ]);

                // 6. Create Gallery Product Image Links
                if (isset($prodData['gallery']) && is_array($prodData['gallery'])) {
                    foreach ($prodData['gallery'] as $galleryImg) {
                        $product->images()->create([
                            'path' => $galleryImg,
                            'is_main' => false,
                        ]);
                    }
                }

                // 7. Seed Variations if defined
                if (isset($prodData['variations']) && is_array($prodData['variations'])) {
                    $optionMap = [];

                    foreach ($prodData['variations'] as $vData) {
                        $variation = $product->variations()->create([
                            'name' => $vData['name'],
                        ]);

                        foreach ($vData['options'] as $optData) {
                            $option = $variation->options()->create([
                                'name' => $optData['name'],
                                'description' => $optData['description'] ?? null,
                                'image' => $optData['image'] ?? null,
                            ]);

                            $optionMap[$vData['name'] . ':' . $optData['name']] = $option;
                        }
                    }

                    // 8. Seed Variants combinations if defined
                    if (isset($prodData['variants']) && is_array($prodData['variants'])) {
                        foreach ($prodData['variants'] as $variantData) {
                            $variantSku = $product->sku . '-' . strtoupper(implode('-', array_map(function ($c) {
                                return substr(Str::slug($c), 0, 3);
                            }, $variantData['combination'])));

                            $attachedOptionIds = [];
                            $variantImage = null;

                            foreach ($variantData['combination'] as $combOptName) {
                                foreach ($optionMap as $key => $optionModel) {
                                    if (str_ends_with($key, ':' . $combOptName)) {
                                        $attachedOptionIds[] = $optionModel->id;
                                        if ($optionModel->image && ! $variantImage) {
                                            $variantImage = $optionModel->image;
                                        }
                                    }
                                }
                            }

                            $variant = $product->variants()->create([
                                'sku' => $variantSku,
                                'weight' => $product->weight,
                                'length' => $product->length,
                                'width' => $product->width,
                                'height' => $product->height,
                                'image' => $variantImage,
                            ]);

                            // Custom price for variant
                            $variant->productPrice()->create([
                                'product_id' => $product->id,
                                'price' => $variantData['price'],
                                'cost' => round($variantData['price'] * 0.8),
                            ]);

                            // Custom stock for variant
                            $variant->productStock()->create([
                                'product_id' => $product->id,
                                'stock' => $variantData['stock'],
                                'min_stock' => rand(1, 3),
                                'min_purchase' => 1,
                                'is_unlimited' => false,
                            ]);

                            // Attach option combinations
                            $variant->options()->attach($attachedOptionIds);
                        }
                    }
                }
            }
        }
    }

    /**
     * Generate realistic specifications based on category and product name.
     */
    private function getSpecificationsForProduct(string $categorySlug, string $productName): array
    {
        if (
            str_contains($categorySlug, 'elektronik') ||
            str_contains($categorySlug, 'gadget')
        ) {
            return [
                'Merek' => 'Original Brand',
                'Model' => $productName,
                'Kondisi' => 'Baru',
                'Garansi' => '1 Tahun Resmi',
                'Warna' => 'Hitam / Putih / Silver',
                'Kapasitas Baterai' => '5000 mAh',
                'Daya / Voltase' => '5V / 2A',
                'Konektivitas' => 'Bluetooth, WiFi, USB-C',
                'Material' => 'ABS Premium',
                'Fitur Utama' => 'Hemat Energi & Efisien',
                'Isi Kemasan' => 'Unit, Kabel, Buku Manual',
                'Sertifikasi' => 'CE, RoHS',
                'Negara Asal' => 'Indonesia / China',
                'Berat' => '500 Gram',
                'Dimensi' => '20 x 10 x 5 cm',
            ];
        }

        if (
            str_contains($categorySlug, 'komputer') ||
            str_contains($categorySlug, 'laptop')
        ) {
            return [
                'Processor' => 'Intel Core i5 / AMD Ryzen 5',
                'RAM' => '16 GB DDR4',
                'Storage' => '512 GB SSD NVMe',
                'VGA' => 'Integrated / Dedicated',
                'Ukuran Layar' => '15.6 Inch Full HD',
                'Resolusi' => '1920 x 1080',
                'Sistem Operasi' => 'Windows 11',
                'Konektivitas' => 'WiFi 6, Bluetooth 5.3',
                'Port' => 'USB-C, HDMI, USB 3.0',
                'Kamera' => 'HD Webcam',
                'Baterai' => '5000 mAh',
                'Garansi' => '2 Tahun Resmi',
                'Berat' => '1.8 Kg',
            ];
        }

        if (
            str_contains($categorySlug, 'hp') ||
            str_contains($categorySlug, 'smartphone')
        ) {
            return [
                'RAM' => '8 GB',
                'Memori Internal' => '256 GB',
                'Ukuran Layar' => '6.7 Inch',
                'Resolusi Layar' => 'FHD+',
                'Chipset' => 'Snapdragon / MediaTek',
                'Kamera Belakang' => '50 MP',
                'Kamera Depan' => '16 MP',
                'Baterai' => '5000 mAh',
                'Fast Charging' => '67 Watt',
                'Dual SIM' => 'Ya',
                'Jaringan' => '4G / 5G',
                'Garansi' => '1 Tahun Resmi',
            ];
        }

        if (
            str_contains($categorySlug, 'fashion') ||
            str_contains($categorySlug, 'pakaian')
        ) {
            return [
                'Bahan' => 'Katun Combed Premium',
                'Ukuran' => 'S, M, L, XL, XXL',
                'Warna' => 'Hitam, Putih, Navy, Abu',
                'Model' => 'Regular Fit',
                'Jenis Kelamin' => 'Pria / Wanita',
                'Motif' => 'Polos / Printing',
                'Ketebalan' => 'Sedang',
                'Elastisitas' => 'Normal',
                'Instruksi Cuci' => 'Cuci Air Dingin',
                'Negara Asal' => 'Indonesia',
                'Kondisi' => 'Baru',
            ];
        }

        if (
            str_contains($categorySlug, 'sepatu') ||
            str_contains($categorySlug, 'sandal')
        ) {
            return [
                'Bahan Upper' => 'Kulit Sintetis / Mesh',
                'Bahan Sole' => 'Phylon / Rubber',
                'Ukuran' => '39, 40, 41, 42, 43, 44',
                'Warna' => 'Hitam, Putih, Abu',
                'Jenis' => 'Casual / Running',
                'Anti Slip' => 'Ya',
                'Tinggi Sol' => '3 cm',
                'Berat' => '800 Gram',
                'Kelengkapan' => 'Dus Original',
                'Kondisi' => 'Baru',
            ];
        }

        if (
            str_contains($categorySlug, 'tas')
        ) {
            return [
                'Material' => 'Kulit Sintetis Premium',
                'Ukuran' => '30 x 15 x 40 cm',
                'Kapasitas' => '20 Liter',
                'Jumlah Kompartemen' => '5',
                'Jenis Penutup' => 'Resleting',
                'Tali' => 'Adjustable',
                'Water Resistant' => 'Ya',
                'Warna' => 'Hitam, Coklat, Navy',
                'Berat' => '700 Gram',
            ];
        }

        if (
            str_contains($categorySlug, 'kecantikan') ||
            str_contains($categorySlug, 'kosmetik')
        ) {
            return [
                'Jenis Produk' => 'Skincare / Makeup',
                'Isi Bersih' => '100 ml',
                'BPOM' => 'Terdaftar',
                'Halal' => 'Ya',
                'Jenis Kulit' => 'Semua Jenis Kulit',
                'Masa Simpan' => '24 Bulan',
                'Manfaat' => 'Melembabkan & Menutrisi',
                'Cara Pakai' => 'Gunakan Secukupnya',
                'Negara Asal' => 'Indonesia',
            ];
        }

        if (
            str_contains($categorySlug, 'makanan') ||
            str_contains($categorySlug, 'minuman')
        ) {
            return [
                'Berat Bersih' => '500 Gram',
                'Jenis Kemasan' => 'Pouch / Botol',
                'Tanggal Kadaluarsa' => '12 Bulan',
                'Penyimpanan' => 'Suhu Ruangan',
                'Komposisi' => 'Bahan Pilihan',
                'Sertifikasi' => 'Halal',
                'BPOM / PIRT' => 'Terdaftar',
                'Asal Produk' => 'Indonesia',
            ];
        }

        if (
            str_contains($categorySlug, 'rumah') ||
            str_contains($categorySlug, 'furniture')
        ) {
            return [
                'Material' => 'Kayu Solid / MDF',
                'Dimensi' => '120 x 60 x 75 cm',
                'Warna' => 'Natural Wood',
                'Berat' => '20 Kg',
                'Tahan Air' => 'Ya',
                'Anti Rayap' => 'Ya',
                'Perakitan' => 'Knock Down',
                'Kapasitas Beban' => '100 Kg',
                'Garansi' => '3 Bulan',
            ];
        }

        if (
            str_contains($categorySlug, 'otomotif')
        ) {
            return [
                'Jenis Produk' => 'Aksesoris Kendaraan',
                'Material' => 'ABS Premium',
                'Kompatibilitas' => 'Universal',
                'Warna' => 'Hitam',
                'Berat' => '500 Gram',
                'Pemasangan' => 'Mudah',
                'Garansi' => '1 Bulan',
                'Kondisi' => 'Baru',
            ];
        }

        return [
            'Merek' => 'Generic',
            'Kondisi' => 'Baru',
            'Garansi' => '7 Hari Toko',
            'Kualitas' => 'Original',
            'Berat' => '500 Gram',
            'Dimensi' => '10 x 10 x 10 cm',
            'Ketersediaan' => 'Ready Stock',
            'Negara Asal' => 'Indonesia',
        ];
    }

    private function getLongDescriptionForProduct(string $categorySlug, string $productName): string
    {
        $desc = '';

        $desc .= "<h2><strong>{$productName}</strong></h2>";

        $desc .= "<p>
        {$productName} merupakan produk berkualitas premium yang dirancang untuk memberikan
        pengalaman terbaik dalam penggunaan sehari-hari. Dengan mengutamakan kualitas material,
        desain modern, serta fungsi yang optimal, produk ini menjadi pilihan tepat bagi konsumen
        yang menginginkan kombinasi antara kualitas, kenyamanan, ketahanan, dan nilai investasi
        jangka panjang.
    </p>";

        $desc .= "<h3><strong>Mengapa Memilih {$productName}?</strong></h3>";

        $desc .= '<ul>
        <li>Material berkualitas tinggi dan tahan lama.</li>
        <li>Desain modern yang mengikuti tren terkini.</li>
        <li>Proses produksi dengan standar quality control ketat.</li>
        <li>Mudah digunakan dan dirawat.</li>
        <li>Cocok untuk penggunaan pribadi maupun profesional.</li>
        <li>Memberikan nilai lebih dibandingkan produk sejenis.</li>
    </ul>';

        if (
            str_contains($categorySlug, 'elektronik') ||
            str_contains($categorySlug, 'gadget')
        ) {

            $desc .= '
        <h3><strong>Performa dan Teknologi Modern</strong></h3>

        <p>
            Produk ini telah dibekali teknologi terkini yang dirancang untuk memberikan
            performa stabil, responsif, dan efisien dalam berbagai kondisi penggunaan.
            Sistem internal telah melalui serangkaian pengujian untuk memastikan
            kestabilan daya, keamanan penggunaan, serta daya tahan jangka panjang.
        </p>

        <p>
            Dukungan konektivitas modern memungkinkan perangkat terhubung dengan mudah
            ke berbagai sistem operasi dan perangkat lainnya. Proses instalasi yang
            sederhana membuat produk dapat langsung digunakan tanpa memerlukan
            konfigurasi yang rumit.
        </p>

        <h3><strong>Keunggulan Utama</strong></h3>

        <ul>
            <li>Konsumsi daya lebih hemat.</li>
            <li>Performa stabil untuk penggunaan harian.</li>
            <li>Sistem proteksi keamanan berlapis.</li>
            <li>Kompatibel dengan berbagai perangkat.</li>
            <li>Desain elegan dan modern.</li>
            <li>Usia pakai lebih panjang.</li>
        </ul>

        <h3><strong>Cocok Digunakan Untuk</strong></h3>

        <ul>
            <li>Kebutuhan rumah tangga.</li>
            <li>Pekerjaan kantor.</li>
            <li>Aktivitas belajar dan pendidikan.</li>
            <li>Kebutuhan bisnis dan usaha.</li>
            <li>Penggunaan profesional.</li>
        </ul>';
        } elseif (
            str_contains($categorySlug, 'pakaian') ||
            str_contains($categorySlug, 'fashion')
        ) {

            $desc .= '
        <h3><strong>Kenyamanan Premium untuk Aktivitas Sehari-hari</strong></h3>

        <p>
            Dibuat menggunakan bahan pilihan yang nyaman digunakan sepanjang hari.
            Material yang digunakan memiliki karakteristik lembut, tidak panas,
            serta mampu menyerap keringat dengan baik sehingga cocok digunakan
            pada berbagai kondisi cuaca.
        </p>

        <p>
            Setiap detail jahitan dikerjakan secara presisi untuk menghasilkan
            tampilan yang rapi sekaligus meningkatkan ketahanan produk terhadap
            penggunaan jangka panjang.
        </p>

        <h3><strong>Keunggulan Fashion</strong></h3>

        <ul>
            <li>Bahan nyaman dan tidak mudah kusut.</li>
            <li>Jahitan rapi dan kuat.</li>
            <li>Warna lebih awet.</li>
            <li>Model modern dan mengikuti tren.</li>
            <li>Mudah dipadukan dengan berbagai outfit.</li>
            <li>Cocok untuk pria maupun wanita.</li>
        </ul>

        <h3><strong>Rekomendasi Penggunaan</strong></h3>

        <p>
            Sangat cocok digunakan untuk aktivitas harian, bekerja, kuliah,
            bepergian, berkumpul bersama teman, maupun acara semi-formal.
            Padukan dengan celana jeans, chino, atau sneakers favorit Anda
            untuk mendapatkan tampilan yang lebih menarik.
        </p>';
        } elseif (
            str_contains($categorySlug, 'furnitur') ||
            str_contains($categorySlug, 'rumah') ||
            str_contains($categorySlug, 'mebel')
        ) {

            $desc .= '
        <h3><strong>Konstruksi Kuat dan Tahan Lama</strong></h3>

        <p>
            Menggunakan material pilihan yang diproses melalui standar produksi
            modern untuk menghasilkan produk yang kokoh, stabil, dan memiliki
            daya tahan tinggi terhadap penggunaan sehari-hari.
        </p>

        <p>
            Finishing dilakukan secara detail untuk menghasilkan permukaan yang
            halus, elegan, dan mudah dibersihkan sehingga tetap terlihat menarik
            meskipun digunakan dalam jangka waktu yang lama.
        </p>

        <h3><strong>Manfaat Produk</strong></h3>

        <ul>
            <li>Mempercantik tampilan ruangan.</li>
            <li>Memberikan kenyamanan penggunaan.</li>
            <li>Meningkatkan efisiensi ruang.</li>
            <li>Memiliki daya tahan tinggi.</li>
            <li>Mudah dipadukan dengan berbagai konsep interior.</li>
        </ul>';
        }

        $desc .= "
    <h3><strong>Proses Quality Control</strong></h3>

    <p>
        Setiap produk melewati proses pemeriksaan kualitas secara menyeluruh
        sebelum dikirim kepada pelanggan. Pemeriksaan meliputi kondisi fisik,
        fungsi utama produk, kualitas finishing, kelengkapan aksesoris,
        hingga keamanan kemasan.
    </p>

    <h3><strong>Isi Paket</strong></h3>

    <ul>
        <li>1 x {$productName}</li>
        <li>Manual penggunaan (jika tersedia).</li>
        <li>Kartu garansi (sesuai kategori produk).</li>
        <li>Kemasan pelindung standar pengiriman.</li>
    </ul>

    <h3><strong>Informasi Pengiriman</strong></h3>

    <p>
        Produk dikemas menggunakan lapisan pelindung tambahan untuk mengurangi
        risiko kerusakan selama proses pengiriman. Kami bekerja sama dengan
        berbagai ekspedisi terpercaya untuk memastikan produk sampai dengan aman.
    </p>

    <h3><strong>Tips Perawatan</strong></h3>

    <ul>
        <li>Bersihkan produk secara berkala.</li>
        <li>Gunakan sesuai petunjuk penggunaan.</li>
        <li>Hindari benturan atau tekanan berlebihan.</li>
        <li>Simpan pada tempat yang bersih dan kering.</li>
        <li>Hindari penggunaan bahan kimia keras.</li>
    </ul>

    <h3><strong>Pertanyaan yang Sering Diajukan (FAQ)</strong></h3>

    <p><strong>Apakah produk ini original?</strong><br>
    Ya, produk yang kami kirim merupakan produk baru dan sesuai deskripsi.</p>

    <p><strong>Apakah produk sudah melalui pengecekan?</strong><br>
    Ya, seluruh produk telah melewati proses quality control sebelum dikirim.</p>

    <p><strong>Bagaimana jika produk rusak saat diterima?</strong><br>
    Silakan hubungi layanan pelanggan kami dengan menyertakan video unboxing.</p>

    <h3><strong>Kesimpulan</strong></h3>

    <p>
        {$productName} merupakan pilihan tepat bagi Anda yang mengutamakan kualitas,
        kenyamanan, daya tahan, dan nilai guna dalam satu produk. Dengan desain modern,
        material pilihan, serta proses produksi berkualitas tinggi, produk ini siap
        menjadi solusi terbaik untuk kebutuhan Anda.
    </p>";

        return $desc;
    }

    private function getSizeChartForProduct(string $categorySlug, string $productName): ?array
    {
        if (! str_contains($categorySlug, 'pakaian')) {
            return null;
        }

        return [
            'enabled' => true,
            'headers' => ['Ukuran', 'Lebar Dada (cm)', 'Panjang (cm)', 'Panjang Lengan (cm)', 'Lebar Bahu (cm)'],
            'rows' => [
                [
                    'size' => 'S',
                    'values' => ['48', '68', '21', '40'],
                    'min_height' => 150,
                    'max_height' => 160,
                    'min_weight' => 45,
                    'max_weight' => 55,
                ],
                [
                    'size' => 'M',
                    'values' => ['50', '70', '22', '42'],
                    'min_height' => 160,
                    'max_height' => 170,
                    'min_weight' => 55,
                    'max_weight' => 65,
                ],
                [
                    'size' => 'L',
                    'values' => ['52', '72', '23', '44'],
                    'min_height' => 170,
                    'max_height' => 180,
                    'min_weight' => 65,
                    'max_weight' => 75,
                ],
                [
                    'size' => 'XL',
                    'values' => ['54', '74', '24', '46'],
                    'min_height' => 180,
                    'max_height' => 190,
                    'min_weight' => 75,
                    'max_weight' => 85,
                ],
                [
                    'size' => 'XXL',
                    'values' => ['58', '76', '25', '48'],
                    'min_height' => 185,
                    'max_height' => 200,
                    'min_weight' => 85,
                    'max_weight' => 110,
                ],
            ],
        ];
    }
}
