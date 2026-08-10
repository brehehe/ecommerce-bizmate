<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SportgigsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Categories requested by user with images & official brand products
        $categoriesData = [
            [
                'name' => 'Raket',
                'slug' => 'raket',
                'icon' => 'ti-brand-sports',
                'image_url' => 'https://images.unsplash.com/photo-1613997141316-0a06283b0f59?w=800&auto=format&fit=crop&q=80',
                'products' => [
                    [
                        'name' => 'Raket Badminton Yonex Astrox 88D Pro 4U',
                        'brand' => 'Yonex',
                        'condition' => 'new',
                        'price' => 2450000,
                        'summary' => 'Raket badminton flagship Yonex 4U G5, raket serang bertenaga tinggi favorit pemain ganda.',
                        'image_url' => 'https://images.unsplash.com/photo-1613997141316-0a06283b0f59?w=800&auto=format&fit=crop&q=80',
                        'gallery_urls' => [
                            'https://images.unsplash.com/photo-1626248801379-51a0748a5f96?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                    [
                        'name' => 'Raket Badminton Li-Ning 3D Calibar 900B',
                        'brand' => 'Li-Ning',
                        'condition' => 'second',
                        'price' => 1350000,
                        'summary' => 'Raket seken mulus 92%, tidak ada retak frame/shock, terpasang senar BG66 Ultimax 28 lbs.',
                        'image_url' => 'https://images.unsplash.com/photo-1626248801379-51a0748a5f96?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'name' => 'Raket Tennis Wilson Pro Staff 97 v13 (Sewa)',
                        'brand' => 'Wilson',
                        'condition' => 'rent',
                        'price' => 85000,
                        'summary' => 'Persewaan raket tenis profesional edisi Roger Federer harian/per sesi main di platform SPORTGIGS.',
                        'image_url' => 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'name' => 'Raket Padel Babolat Counter Viper 3K',
                        'brand' => 'Babolat',
                        'condition' => 'new',
                        'price' => 3200000,
                        'summary' => 'Raket padel berbahan carbon 3K presisi tinggi untuk permainan kontrol dan serangan balik cepat.',
                        'image_url' => 'https://images.unsplash.com/photo-1622279457486-62dcc4a431d6?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
            [
                'name' => 'Sepatu',
                'slug' => 'sepatu',
                'icon' => 'ti-shoe',
                'image_url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&auto=format&fit=crop&q=80',
                'products' => [
                    [
                        'name' => 'Sepatu Lari Nike ZoomX Vaporfly NEXT% 2',
                        'brand' => 'Nike',
                        'condition' => 'new',
                        'price' => 3199000,
                        'summary' => 'Sepatu lari maraton pelat karbon super ringan dengan bantalan bertenaga ZoomX.',
                        'image_url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&auto=format&fit=crop&q=80',
                        'gallery_urls' => [
                            'https://images.unsplash.com/photo-1608231387042-66d1773070a5?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                    [
                        'name' => 'Sepatu Tenis Adidas Barricade Hard Court',
                        'brand' => 'Adidas',
                        'condition' => 'second',
                        'price' => 850000,
                        'summary' => 'Sepatu tenis seken original size 42, pemakaian 2 bulan, outsole tebal belum tergerus.',
                        'image_url' => 'https://images.unsplash.com/photo-1584735935682-2f2b69dff9d2?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'name' => 'Sepatu Futsal Specs Accelerator Lightspeed II (Sewa)',
                        'brand' => 'Specs',
                        'condition' => 'rent',
                        'price' => 35000,
                        'summary' => 'Layanan sewa sepatu futsal Specs lokal terlaris di SPORTGIGS, sterilisasi UV higienis.',
                        'image_url' => 'https://images.unsplash.com/photo-1511556532299-8f662fc26c06?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'name' => 'Sepatu Badminton Victor A970ACE Nitro',
                        'brand' => 'Victor',
                        'condition' => 'new',
                        'price' => 1780000,
                        'summary' => 'Sepatu badminton profesional bantalan EnergyMax 3.0 dengan perlindungan pergelangan kaki.',
                        'image_url' => 'https://images.unsplash.com/photo-1608231387042-66d1773070a5?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
            [
                'name' => 'Jersey',
                'slug' => 'jersey',
                'icon' => 'ti-shirt',
                'image_url' => 'https://images.unsplash.com/photo-1515523110800-9415d13b84a8?w=800&auto=format&fit=crop&q=80',
                'products' => [
                    [
                        'name' => 'Jersey Nike NBA Lakers LeBron James Authentic',
                        'brand' => 'Nike',
                        'condition' => 'new',
                        'price' => 1199000,
                        'summary' => 'Jersey basket official NBA Authentic Dri-FIT, nyaman, sejuk dan cepat menyerap keringat.',
                        'image_url' => 'https://images.unsplash.com/photo-1515523110800-9415d13b84a8?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'name' => 'Jersey Bola Adidas Real Madrid Home 2024/2025 Original',
                        'brand' => 'Adidas',
                        'condition' => 'second',
                        'price' => 450000,
                        'summary' => 'Jersey bola second original size M, kondisi 95% sangat terawat tanpa bobble atau sablon pecah.',
                        'image_url' => 'https://images.unsplash.com/photo-1577219491135-ce391730fb2c?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'name' => 'Jersey Tim Futsal Specs Tournament Set 12 Pcs (Sewa)',
                        'brand' => 'Specs',
                        'condition' => 'rent',
                        'price' => 150000,
                        'summary' => 'Sewa 1 set jersey tim futsal (12 stel baju + celana + nomor dada) di SPORTGIGS untuk turnamen.',
                        'image_url' => 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
            [
                'name' => 'Tas',
                'slug' => 'tas',
                'icon' => 'ti-briefcase',
                'image_url' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800&auto=format&fit=crop&q=80',
                'products' => [
                    [
                        'name' => 'Tas Raket Yonex Pro Racquet Bag 92231EX 9 Pcs',
                        'brand' => 'Yonex',
                        'condition' => 'new',
                        'price' => 1250000,
                        'summary' => 'Tas raket Yonex Tour Series 9 kompartemen termo penahan suhu panas luar ruangan.',
                        'image_url' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'name' => 'Tas Gym Nike Utility Power Duffle Bag 51L',
                        'brand' => 'Nike',
                        'condition' => 'second',
                        'price' => 390000,
                        'summary' => 'Tas gym/olahraga seken original, kompartemen sepatu terpisah, bahan kanvas tahan gesek.',
                        'image_url' => 'https://images.unsplash.com/photo-1622560480605-d83c853bc5c3?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'name' => 'Tas Raket Wilson Tour 12 Pack Tennis Bag (Sewa)',
                        'brand' => 'Wilson',
                        'condition' => 'rent',
                        'price' => 50000,
                        'summary' => 'Persewaan tas raket tenis kapasitas besar 12 raket untuk kejuaraan atau tur luar kota.',
                        'image_url' => 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
            [
                'name' => 'Lainnya',
                'slug' => 'lainnya',
                'icon' => 'ti-dots-diagonal',
                'image_url' => 'https://images.unsplash.com/photo-1626248801379-51a0748a5f96?w=800&auto=format&fit=crop&q=80',
                'products' => [
                    [
                        'name' => 'Shuttlecock Yonex Aerosensa 30 Feather (1 Dosin)',
                        'brand' => 'Yonex',
                        'condition' => 'new',
                        'price' => 385000,
                        'summary' => 'Kok badminton bulu angsa asli standar BWF untuk kejuaraan & turnamen resmi.',
                        'image_url' => 'https://images.unsplash.com/photo-1626248801379-51a0748a5f96?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'name' => 'Bola Voli Molten V5M5000 Competition',
                        'brand' => 'Molten',
                        'condition' => 'new',
                        'price' => 650000,
                        'summary' => 'Bola voli resmi PBVSI Flistatec teknologi kontrol stabilitas penerbangan udara.',
                        'image_url' => 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'name' => 'Nike Guard Lock Knee Support Pad Pair (Sewa)',
                        'brand' => 'Nike',
                        'condition' => 'rent',
                        'price' => 20000,
                        'summary' => 'Sewa pelindung siku/lutut olahraga elastis untuk pencegahan dan pemulihan cedera.',
                        'image_url' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
        ];

        foreach ($categoriesData as $index => $catData) {
            // Download category image
            $catImagePath = $this->downloadAndSaveImage($catData['image_url'], 'categories', 'cat_'.$catData['slug']);

            $category = Category::updateOrCreate(
                ['slug' => $catData['slug']],
                [
                    'name' => $catData['name'],
                    'icon' => $catData['icon'],
                    'image' => $catImagePath,
                    'order' => $index + 10,
                ]
            );

            foreach ($catData['products'] as $prodData) {
                // Create or get official brand
                $brandName = $prodData['brand'];
                $brand = Brand::firstOrCreate(
                    ['name' => $brandName],
                    [
                        'slug' => Str::slug($brandName),
                        'is_active' => true,
                    ]
                );

                $productImagePath = $this->downloadAndSaveImage($prodData['image_url'], 'products', 'prod_'.Str::slug($prodData['name']));
                $sku = strtoupper(substr($brandName, 0, 3)).'-'.strtoupper(Str::random(6));

                $product = Product::updateOrCreate(
                    ['slug' => Str::slug($prodData['name'])],
                    [
                        'name' => $prodData['name'],
                        'sku' => $sku,
                        'category_id' => $category->id,
                        'brand_id' => $brand->id,
                        'brand' => $brand->name,
                        'condition' => $prodData['condition'],
                        'summary' => $prodData['summary'],
                        'description' => $this->getLongDescription($prodData['name'], $brand->name, $prodData['condition']),
                        'specifications' => [
                            'Merek' => $brand->name,
                            'Kondisi' => match ($prodData['condition']) {
                                'new' => 'Baru (New)',
                                'second' => 'Bekas (Second)',
                                'rent' => 'Sewa (Rental)',
                                default => ucfirst($prodData['condition']),
                            },
                            'Garansi' => $prodData['condition'] === 'new' ? 'Garansi Resmi Manufacturer 1 Tahun' : 'Garansi Toko SPORTGIGS 7 Hari',
                            'Kualitas' => 'Original 100%',
                        ],
                        'weight' => 500,
                        'length' => 20,
                        'width' => 20,
                        'height' => 10,
                        'active' => true,
                        'image' => $productImagePath,
                    ]
                );

                // Sync pivot tables
                $product->categories()->sync([$category->id]);
                $product->brands()->sync([$brand->id]);

                // Update/Create Price
                $product->productPrice()->updateOrCreate(
                    [],
                    [
                        'price' => $prodData['price'],
                        'cost' => round($prodData['price'] * 0.75),
                    ]
                );

                // Update/Create Stock
                $product->productStock()->updateOrCreate(
                    [],
                    [
                        'stock' => rand(10, 60),
                        'min_stock' => 2,
                        'min_purchase' => 1,
                        'is_unlimited' => false,
                    ]
                );

                // Product Images
                if ($product->images()->count() === 0) {
                    $product->images()->create([
                        'path' => $productImagePath,
                        'is_main' => true,
                        'sort_order' => 0,
                    ]);

                    if (isset($prodData['gallery_urls']) && is_array($prodData['gallery_urls'])) {
                        foreach ($prodData['gallery_urls'] as $gIndex => $gUrl) {
                            $gPath = $this->downloadAndSaveImage($gUrl, 'products', 'prod_gal_'.Str::slug($prodData['name']));
                            $product->images()->create([
                                'path' => $gPath,
                                'is_main' => false,
                                'sort_order' => $gIndex + 1,
                            ]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Download an image from URL and save it to storage/app/public/{folder}.
     * Returns the relative storage path (/storage/{folder}/{filename}) or original URL if download fails.
     */
    private function downloadAndSaveImage(string $url, string $folder, string $filenamePrefix): string
    {
        try {
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            if (strlen($extension) > 4) {
                $extension = 'jpg';
            }

            $filename = $filenamePrefix.'_'.time().'_'.Str::random(6).'.'.$extension;
            $relativeStoragePath = "{$folder}/{$filename}";

            $response = Http::timeout(10)
                ->withOptions(['verify' => false])
                ->get($url);

            if ($response->successful() && strlen($response->body()) > 100) {
                Storage::disk('public')->put($relativeStoragePath, $response->body());

                return "/storage/{$relativeStoragePath}";
            }
        } catch (\Throwable $e) {
            // Silently fall back to original URL on network/connection failure
        }

        return $url;
    }

    private function getLongDescription(string $productName, string $brandName, string $condition): string
    {
        $conditionLabel = match ($condition) {
            'new' => 'Baru (New Condition)',
            'second' => 'Bekas / Seken Terawat (Second Condition)',
            'rent' => 'Persewaan / Rental di Platform SPORTGIGS',
            default => ucfirst($condition),
        };

        return "<h2><strong>{$productName}</strong></h2>
        <p>Produk resmi dari brand <strong>{$brandName}</strong> yang terdaftar di platform Jual-Beli & Sewa Perlengkapan Olahraga <strong>SPORTGIGS</strong>.</p>
        <p>Status Kondisi: <strong>{$conditionLabel}</strong></p>
        <h3>Jaminan SPORTGIGS Marketplace:</h3>
        <ul>
            <li>Produk 100% Original dari brand {$brandName}.</li>
            <li>Transaksi aman dengan perlindungan Garansi Pembeli SPORTGIGS.</li>
            <li>Verifikasi kondisi barang (Quality & Authenticity Check) sebelum transaksi diproses.</li>
        </ul>
        <p>Dapatkan perlengkapan olahraga terbaik Anda di <strong>SPORTGIGS Marketplace</strong>!</p>";
    }
}
