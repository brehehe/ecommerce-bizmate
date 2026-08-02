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
        // 20 Realistic & Diverse E-Commerce Categories with Products & Variants
        $categoriesData = [
            [
                'name' => 'Elektronik & Gadget',
                'icon' => 'ti-cpu',
                'slug' => 'elektronik-gadget',
                'products' => [
                    [
                        'name' => 'Smartwatch Xiaomi Band 8 Pro',
                        'brand' => 'Xiaomi',
                        'price' => 999000,
                        'image' => 'https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=600&auto=format&fit=crop&q=80',
                        'gallery' => [
                            'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=600&auto=format&fit=crop&q=80',
                        ],
                        'summary' => 'Pelacak kesehatan pintar dengan layar AMOLED lebar dan GPS independen.',
                        'variations' => [
                            [
                                'name' => 'Warna',
                                'options' => [
                                    ['name' => 'Space Black', 'image' => 'https://images.unsplash.com/photo-1546868871-7041f2a55e12?w=600&auto=format&fit=crop&q=80'],
                                    ['name' => 'Light Gold', 'image' => 'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1?w=600&auto=format&fit=crop&q=80'],
                                ],
                            ],
                        ],
                        'variants' => [
                            ['combination' => ['Space Black'], 'price' => 999000, 'stock' => 50],
                            ['combination' => ['Light Gold'], 'price' => 1049000, 'stock' => 30],
                        ],
                    ],
                    [
                        'name' => 'TWS Anker Soundcore R50i',
                        'brand' => 'Anker',
                        'price' => 249000,
                        'image' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Earphone nirkabel dengan bass powerful dan daya tahan baterai 30 jam.',
                    ],
                ],
            ],
            [
                'name' => 'Fashion Pria',
                'icon' => 'ti-shirt',
                'slug' => 'fashion-pria',
                'products' => [
                    [
                        'name' => 'Kaos Oversize Oversized Heavyweight Cotton',
                        'brand' => 'Erigo',
                        'price' => 129000,
                        'image' => 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Kaos polos katun combed 20s tebal, nyaman, dan tidak mudah berserabut.',
                        'variations' => [
                            [
                                'name' => 'Ukuran',
                                'options' => [
                                    ['name' => 'M'],
                                    ['name' => 'L'],
                                    ['name' => 'XL'],
                                ],
                            ],
                            [
                                'name' => 'Warna',
                                'options' => [
                                    ['name' => 'Hitam'],
                                    ['name' => 'Putih'],
                                ],
                            ],
                        ],
                        'variants' => [
                            ['combination' => ['M', 'Hitam'], 'price' => 129000, 'stock' => 25],
                            ['combination' => ['L', 'Hitam'], 'price' => 129000, 'stock' => 30],
                            ['combination' => ['XL', 'Hitam'], 'price' => 134000, 'stock' => 15],
                            ['combination' => ['M', 'Putih'], 'price' => 129000, 'stock' => 20],
                            ['combination' => ['L', 'Putih'], 'price' => 129000, 'stock' => 25],
                            ['combination' => ['XL', 'Putih'], 'price' => 134000, 'stock' => 10],
                        ],
                    ],
                    [
                        'name' => 'Celana Chino Slim Fit Stretch Pria',
                        'brand' => 'Roughneck',
                        'price' => 189000,
                        'image' => 'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Celana chino kasual bahan melar elastis yang elastis untuk kerja dan harian.',
                    ],
                ],
            ],
            [
                'name' => 'Fashion Wanita',
                'icon' => 'ti-hanger',
                'slug' => 'fashion-wanita',
                'products' => [
                    [
                        'name' => 'Kemeja Blouse Linen Oversize Wanita',
                        'brand' => 'Cottonink',
                        'price' => 175000,
                        'image' => 'https://images.unsplash.com/photo-1598554747436-c9293d6a588f?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Blouse wanita kekinian berbahan linen rami adem dan menyerap keringat.',
                    ],
                    [
                        'name' => 'Dress Casual Minimalis Korean Style',
                        'brand' => 'ShopAtVelvet',
                        'price' => 249000,
                        'image' => 'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Gaun santai bergaya Korea cocok untuk nongkrong dan acara santai.',
                    ],
                ],
            ],
            [
                'name' => 'Handphone & Tablet',
                'icon' => 'ti-device-mobile',
                'slug' => 'handphone-tablet',
                'products' => [
                    [
                        'name' => 'Smartphone Samsung Galaxy A55 5G',
                        'brand' => 'Samsung',
                        'price' => 5999000,
                        'image' => 'https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Kamera 50MP OIS, IP67 tahan air dan debu, layar Super AMOLED 120Hz.',
                    ],
                    [
                        'name' => 'iPad 10th Gen 64GB Wi-Fi',
                        'brand' => 'Apple',
                        'price' => 6299000,
                        'image' => 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Layar Liquid Retina 10.9 inci dengan chip A14 Bionic yang tangguh.',
                    ],
                ],
            ],
            [
                'name' => 'Komputer & Laptop',
                'icon' => 'ti-device-laptop',
                'slug' => 'komputer-laptop',
                'products' => [
                    [
                        'name' => 'Laptop ASUS Vivobook Go 14',
                        'brand' => 'ASUS',
                        'price' => 6499000,
                        'image' => 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Laptop tipis dan ringan untuk produktivitas harian dan tugas kantor.',
                    ],
                    [
                        'name' => 'Monitor Gaming AOC 24 inchi 180Hz IPS',
                        'brand' => 'AOC',
                        'price' => 1650000,
                        'image' => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Monitor gaming kencang respons time 0.5ms dengan akurasi warna IPS tinggi.',
                    ],
                ],
            ],
            [
                'name' => 'Kecantikan & Skincare',
                'icon' => 'ti-sparkles',
                'slug' => 'kecantikan-skincare',
                'products' => [
                    [
                        'name' => 'Serum Somethinc Niacinamide + Moisture',
                        'brand' => 'Somethinc',
                        'price' => 115000,
                        'image' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Serum wajah pencerah meredakan bekas jerawat dan menjaga kelembaban kulit.',
                    ],
                    [
                        'name' => 'Sunscreen Skintific Ultra Light Silk SPF 50+',
                        'brand' => 'Skintific',
                        'price' => 99000,
                        'image' => 'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Tabir surya tekstur sehalus sutra, ringan dan tidak lengket di kulit.',
                    ],
                ],
            ],
            [
                'name' => 'Kesehatan & Perawatan',
                'icon' => 'ti-first-aid-kit',
                'slug' => 'kesehatan-perawatan',
                'products' => [
                    [
                        'name' => 'Omron Tensimeter Digital Lengan Atas',
                        'brand' => 'Omron',
                        'price' => 680000,
                        'image' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Alat pengukur tekanan darah digital akurat dengan indikator hipertensi.',
                    ],
                    [
                        'name' => 'Multivitamin Blackmores Multivitamins & Minerals',
                        'brand' => 'Blackmores',
                        'price' => 245000,
                        'image' => 'https://images.unsplash.com/photo-1471864190281-a93a3070b6de?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Suplemen kesehatan harian untuk menjaga stamina dan daya tahan tubuh.',
                    ],
                ],
            ],
            [
                'name' => 'Makanan & Minuman',
                'icon' => 'ti-building-store',
                'slug' => 'makanan-minuman',
                'products' => [
                    [
                        'name' => 'Kopi Bubuk Arabika Gayo Highland 500g',
                        'brand' => 'KopiKenangan',
                        'price' => 89000,
                        'image' => 'https://images.unsplash.com/photo-1559056199-641a0ac8b55e?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Biji kopi sangrai khas Aceh Gayo beraroma khas buah dan cokelat gurih.',
                    ],
                    [
                        'name' => 'Madu Murni Forest Honey 1000g',
                        'brand' => 'Uray',
                        'price' => 145000,
                        'image' => 'https://images.unsplash.com/photo-1587049352847-4a222e784d38?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Madu alami hutan liar tanpa campuran gula buatan atau pengawet.',
                    ],
                ],
            ],
            [
                'name' => 'Ibu & Bayi',
                'icon' => 'ti-baby-carriage',
                'slug' => 'ibu-bayi',
                'products' => [
                    [
                        'name' => 'Stroller Bayi Lipat Otomatis Cabin Size',
                        'brand' => 'Babyelle',
                        'price' => 1250000,
                        'image' => 'https://images.unsplash.com/photo-1591154669695-5f2a8d20c089?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Kereta dorong bayi yang dapat dilipat kecil praktis untuk travelling.',
                    ],
                    [
                        'name' => 'Sterilizer Botol Bayi UV & Dryer',
                        'brand' => 'Pigeon',
                        'price' => 890000,
                        'image' => 'https://images.unsplash.com/photo-1519689680058-324335c77eba?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Alat sterilisasi sinar UV untuk membunuh 99.9% kuman dan bakteri pada botol susu.',
                    ],
                ],
            ],
            [
                'name' => 'Rumah Tangga & Furnitur',
                'icon' => 'ti-armchair',
                'slug' => 'rumah-tangga-furnitur',
                'products' => [
                    [
                        'name' => 'Meja Kerja Minimalis Kayu Mahoni',
                        'brand' => 'IKEA',
                        'price' => 450000,
                        'image' => 'https://images.unsplash.com/photo-1518455027359-f3f8164ba6bd?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Meja komputer modern rangka besi kokoh dengan papan kayu tebal.',
                    ],
                    [
                        'name' => 'Air Purifier Sharp Plasmacluster FP-F30Y',
                        'brand' => 'Sharp',
                        'price' => 1399000,
                        'image' => 'https://images.unsplash.com/photo-1585771724684-38269d6639fd?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Pembersih udara dengan teknologi ion plasmacluster pembunuh virus dan bau.',
                    ],
                ],
            ],
            [
                'name' => 'Sepatu & Sandal',
                'icon' => 'ti-footprints',
                'slug' => 'sepatu-sandal',
                'products' => [
                    [
                        'name' => 'Sepatu Sneakers Ventela Public Low Black',
                        'brand' => 'Ventela',
                        'price' => 240000,
                        'image' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Sneakers kanvas lokal terlaris dengan sol empuk dan jahitan berkualitas.',
                    ],
                    [
                        'name' => 'Sepatu Lari Nike Revolution 6 Next Nature',
                        'brand' => 'Nike',
                        'price' => 799000,
                        'image' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Sepatu running ringan dengan bantalan empuk meredam benturan saat berlari.',
                    ],
                ],
            ],
            [
                'name' => 'Tas & Aksesoris',
                'icon' => 'ti-briefcase',
                'slug' => 'tas-aksesoris',
                'products' => [
                    [
                        'name' => 'Ransel Laptop Eiger Daily Pack 22L',
                        'brand' => 'Eiger',
                        'price' => 389000,
                        'image' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Tas punggung tahan air dengan kompartemen laptop 15.6 inci dan raincover.',
                    ],
                    [
                        'name' => 'Tas Selempang Sling Bag Minimalis',
                        'brand' => 'Consina',
                        'price' => 145000,
                        'image' => 'https://images.unsplash.com/photo-1622560480605-d83c853bc5c3?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Slingbag serbaguna cocok untuk tempat dompet, HP, dan barang pribadi kecil.',
                    ],
                ],
            ],
            [
                'name' => 'Jam Tangan',
                'icon' => 'ti-watch',
                'slug' => 'jam-tangan',
                'products' => [
                    [
                        'name' => 'Jam Tangan Casio G-Shock DW-5600',
                        'brand' => 'Casio',
                        'price' => 1190000,
                        'image' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Jam tangan ikonik anti guncangan dan tahan air hingga kedalaman 200 meter.',
                    ],
                    [
                        'name' => 'Jam Tangan Otomatis Alba Chronograph Steel',
                        'brand' => 'Seiko',
                        'price' => 1850000,
                        'image' => 'https://images.unsplash.com/photo-1524805444758-089113d48a6d?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Jam tangan pria rantai stainless steel elegan untuk acara formal.',
                    ],
                ],
            ],
            [
                'name' => 'Otomotif & Motor',
                'icon' => 'ti-steering-wheel',
                'slug' => 'otomotif-motor',
                'products' => [
                    [
                        'name' => 'Helm Full Face JPX Fox1 Cross Motor',
                        'brand' => 'JPX',
                        'price' => 650000,
                        'image' => 'https://images.unsplash.com/photo-1558981806-ec527fa84c39?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Helm motor ikonis SNI & DOT dengan busa pipi lembut dan ventilasi udara.',
                    ],
                    [
                        'name' => 'Intercom Bluetooth Helm FreedConn T-Max S',
                        'brand' => 'FreedConn',
                        'price' => 580000,
                        'image' => 'https://images.unsplash.com/photo-1568772585407-9361f9bf3a87?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Alat komunikasi nirkabel sesama touring motor jarak jangkau 1200 meter.',
                    ],
                ],
            ],
            [
                'name' => 'Olahraga & Outdoor',
                'icon' => 'ti-ball-football',
                'slug' => 'olahraga-outdoor',
                'products' => [
                    [
                        'name' => 'Matras Yoga TPE Non-Slip 6mm',
                        'brand' => 'Decathlon',
                        'price' => 165000,
                        'image' => 'https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Matras senam pilates dan yoga ramah lingkungan yang tidak licin.',
                    ],
                    [
                        'name' => 'Tenda Camping Kapasitas 4 Orang Waterproof',
                        'brand' => 'Consina',
                        'price' => 699000,
                        'image' => 'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Tenda double layer kokoh tahan air hujan deras dan angin kencang.',
                    ],
                ],
            ],
            [
                'name' => 'Mainan & Hobi',
                'icon' => 'ti-device-gamepad-2',
                'slug' => 'mainan-hobi',
                'products' => [
                    [
                        'name' => 'Stik Gamepad Wireless DualSense PS5',
                        'brand' => 'Sony',
                        'price' => 1129000,
                        'image' => 'https://images.unsplash.com/photo-1606813907291-d86efa9b94db?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Controller PS5 dengan haptic feedback presisi tinggi dan adaptive trigger.',
                    ],
                    [
                        'name' => 'Gundam HG 1/144 RX-78-2 Beyond Global',
                        'brand' => 'Bandai',
                        'price' => 280000,
                        'image' => 'https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Model kit robot rakitan buatan Jepang dengan artikulasi tubuh yang fleksibel.',
                    ],
                ],
            ],
            [
                'name' => 'Buku & Alat Tulis',
                'icon' => 'ti-book',
                'slug' => 'buku-alat-tulis',
                'products' => [
                    [
                        'name' => 'Buku Atomic Habits - James Clear',
                        'brand' => 'Gramedia',
                        'price' => 108000,
                        'image' => 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Buku bestseller internasional cara membangun kebiasaan baik dan membuang kebiasaan buruk.',
                    ],
                    [
                        'name' => 'Set Pulpen Gel Zebra Sarasa Clip 0.5mm 5 Warna',
                        'brand' => 'Zebra',
                        'price' => 75000,
                        'image' => 'https://images.unsplash.com/photo-1583485088034-697b5bc54ccd?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Pulpen tinta gel Jepang super lancar tidak mudah bocor atau menggumpal.',
                    ],
                ],
            ],
            [
                'name' => 'Dapur & Peralatan',
                'icon' => 'ti-soup',
                'slug' => 'dapur-peralatan',
                'products' => [
                    [
                        'name' => 'Air Fryer Mecoo Aesthetic 4.5 Liter 650W',
                        'brand' => 'Mecoo',
                        'price' => 699000,
                        'image' => 'https://images.unsplash.com/photo-1584269600464-37b1b58a9fe7?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Penggorengan tanpa minyak hemat listrik dengan lapisan granit anti lengket.',
                    ],
                    [
                        'name' => 'Wajan Frypan Teflon Anti Lengket 24cm',
                        'brand' => 'Tefal',
                        'price' => 189000,
                        'image' => 'https://images.unsplash.com/photo-1584992236310-6edddc08acff?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Wajan penggorengan kualitas tinggi bebas PFOA aman untuk masakan keluarga.',
                    ],
                ],
            ],
            [
                'name' => 'Kamera & Fotografi',
                'icon' => 'ti-camera',
                'slug' => 'kamera-fotografi',
                'products' => [
                    [
                        'name' => 'Kamera Action GoPro HERO12 Black',
                        'brand' => 'GoPro',
                        'price' => 6499000,
                        'image' => 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Kamera aksi 5.3K video dengan penstabil gambar HyperSmooth 6.0.',
                    ],
                    [
                        'name' => 'Tripod Kamera & HP Takara ECO-193A',
                        'brand' => 'Takara',
                        'price' => 135000,
                        'image' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Tripod ringan aluminium sudah termasuk holder HP dan tas penyimpanan.',
                    ],
                ],
            ],
            [
                'name' => 'Pet Shop & Peliharaan',
                'icon' => 'ti-bone',
                'slug' => 'pet-shop-peliharaan',
                'products' => [
                    [
                        'name' => 'Makanan Kucing Whiskas Adult Tuna 1.2kg',
                        'brand' => 'Whiskas',
                        'price' => 68000,
                        'image' => 'https://images.unsplash.com/photo-1589924691995-400dc9ecc119?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Makanan kering kucing dewasa nutrisi seimbang untuk bulu lebat dan sehat.',
                    ],
                    [
                        'name' => 'Pasir Kucing Bentonit Wangi Lavender 10L',
                        'brand' => 'PasirKucing',
                        'price' => 55000,
                        'image' => 'https://images.unsplash.com/photo-1548767797-d8c844163c4c?w=600&auto=format&fit=crop&q=80',
                        'summary' => 'Pasir gumpal wangi otomatis menyerap bau kotoran kucing dengan cepat.',
                    ],
                ],
            ],
        ];

        foreach ($categoriesData as $index => $catData) {
            // 1. Create or Update Category
            $category = Category::updateOrCreate(
                ['slug' => $catData['slug']],
                [
                    'name' => $catData['name'],
                    'icon' => $catData['icon'] ?? 'ti-category',
                    'order' => $index + 1,
                ]
            );

            // 2. Loop Products inside category
            foreach ($catData['products'] as $pIndex => $prodData) {
                // Ensure Brand exists
                $brandModel = null;
                if (! empty($prodData['brand'])) {
                    $brandModel = Brand::firstOrCreate(
                        ['name' => $prodData['brand']],
                        [
                            'slug' => Str::slug($prodData['brand']),
                            'is_active' => true,
                        ]
                    );
                }

                $sku = 'PRD-'.strtoupper(Str::random(6));

                $product = Product::updateOrCreate(
                    ['name' => $prodData['name']],
                    [
                        'slug' => Str::slug($prodData['name']),
                        'sku' => $sku,
                        'description' => $this->getLongDescriptionForProduct($catData['slug'], $prodData['name']),
                        'summary' => $prodData['summary'] ?? null,
                        'specifications' => $this->getSpecificationsForProduct($catData['slug'], $prodData['name']),
                        'size_chart' => $this->getSizeChartForProduct($catData['slug'], $prodData['name']),
                        'weight' => 500,
                        'length' => 15,
                        'width' => 15,
                        'height' => 10,
                        'active' => true,
                    ]
                );

                // Sync pivots
                $product->categories()->sync([$category->id]);
                if ($brandModel) {
                    $product->brands()->sync([$brandModel->id]);
                }

                // Master Price
                if (! $product->productPrice) {
                    $product->productPrice()->create([
                        'price' => $prodData['price'],
                        'cost' => round($prodData['price'] * 0.8),
                    ]);
                } else {
                    $product->productPrice()->update([
                        'price' => $prodData['price'],
                        'cost' => round($prodData['price'] * 0.8),
                    ]);
                }

                // Master Stock
                if (! $product->productStock) {
                    $product->productStock()->create([
                        'stock' => rand(20, 150),
                        'min_stock' => rand(2, 5),
                        'min_purchase' => 1,
                        'is_unlimited' => false,
                    ]);
                }

                // Main Image
                if ($product->images()->count() === 0) {
                    $product->images()->create([
                        'path' => $prodData['image'],
                        'is_main' => true,
                    ]);

                    if (isset($prodData['gallery']) && is_array($prodData['gallery'])) {
                        foreach ($prodData['gallery'] as $galleryImg) {
                            $product->images()->create([
                                'path' => $galleryImg,
                                'is_main' => false,
                            ]);
                        }
                    }
                }

                // Seed Variations & Variants if defined
                if (isset($prodData['variations']) && is_array($prodData['variations']) && $product->variations()->count() === 0) {
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

                            $optionMap[$vData['name'].':'.$optData['name']] = $option;
                        }
                    }

                    if (isset($prodData['variants']) && is_array($prodData['variants'])) {
                        foreach ($prodData['variants'] as $variantData) {
                            $variantSku = $product->sku.'-'.strtoupper(implode('-', array_map(function ($c) {
                                return substr(Str::slug($c), 0, 3);
                            }, $variantData['combination'])));

                            $attachedOptionIds = [];
                            $variantImage = null;

                            foreach ($variantData['combination'] as $combOptName) {
                                foreach ($optionMap as $key => $optionModel) {
                                    if (str_ends_with($key, ':'.$combOptName)) {
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

                            $variant->productPrice()->create([
                                'product_id' => $product->id,
                                'price' => $variantData['price'],
                                'cost' => round($variantData['price'] * 0.8),
                            ]);

                            $variant->productStock()->create([
                                'product_id' => $product->id,
                                'stock' => $variantData['stock'],
                                'min_stock' => rand(1, 3),
                                'min_purchase' => 1,
                                'is_unlimited' => false,
                            ]);

                            $variant->options()->attach($attachedOptionIds);
                        }
                    }
                }
            }
        }
    }

    private function getSpecificationsForProduct(string $categorySlug, string $productName): array
    {
        return [
            'Merek' => 'Original Brand',
            'Kondisi' => 'Baru',
            'Garansi' => '1 Tahun Resmi',
            'Negara Asal' => 'Indonesia',
            'Kualitas' => 'Original 100%',
        ];
    }

    private function getLongDescriptionForProduct(string $categorySlug, string $productName): string
    {
        return "<h2><strong>{$productName}</strong></h2>
        <p>{$productName} merupakan produk berkualitas tinggi yang dirancang untuk memberikan pengalaman terbaik dalam penggunaan harian Anda.</p>
        <h3>Keunggulan Utama</h3>
        <ul>
            <li>Bahan material premium dan tahan lama.</li>
            <li>Desain modern & stylish.</li>
            <li>Garansi resmi dan terjamin.</li>
        </ul>";
    }

    private function getSizeChartForProduct(string $categorySlug, string $productName): ?array
    {
        if (! str_contains($categorySlug, 'fashion') && ! str_contains($categorySlug, 'pakaian')) {
            return null;
        }

        return [
            'enabled' => true,
            'headers' => ['Ukuran', 'Lebar Dada (cm)', 'Panjang (cm)'],
            'rows' => [
                ['size' => 'M', 'values' => ['50', '70']],
                ['size' => 'L', 'values' => ['52', '72']],
                ['size' => 'XL', 'values' => ['54', '74']],
            ],
        ];
    }
}
