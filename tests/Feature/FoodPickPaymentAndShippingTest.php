<?php

use App\Models\CartItem;
use App\Models\MembershipLevel;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\RestoProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.membership_enabled' => false]);
    config(['app.is_foodpick' => true]);
    config(['app.foodpick.allow_store_courier' => true]);

    if (! Role::where('name', 'Super Admin')->exists()) {
        Role::create(['name' => 'Super Admin']);
    }
    if (! Role::where('name', 'Seller')->exists()) {
        Role::create(['name' => 'Seller']);
    }
    if (! Role::where('name', 'Customer')->exists()) {
        Role::create(['name' => 'Customer']);
    }

    MembershipLevel::create([
        'name' => 'Bronze',
        'slug' => 'bronze',
        'min_spend' => 0,
        'min_transactions' => 0,
        'order' => 1,
        'is_active' => true,
    ]);

    $this->seller = User::create([
        'name' => 'Chef Jangkung',
        'email' => 'chef@sinjay.com',
        'password' => bcrypt('password'),
        'phone_number' => '081234567890',
        'is_seller' => true,
        'store_name' => 'Bebek Sinjay',
        'store_slug' => 'bebek-sinjay',
    ]);
    $this->seller->assignRole('Seller');

    $this->restoProfile = RestoProfile::create([
        'user_id' => $this->seller->id,
        'resto_name' => 'Bebek Sinjay',
        'culinary_category' => 'Aneka Nasi & Bebek',
        'slug' => 'bebek-sinjay',
        'address' => 'Jl. Soekarno Hatta No. 45, Malang',
        'latitude' => -7.940000,
        'longitude' => 112.610000,
        'is_verified' => true,
        'is_open_now' => true,
        'allow_pickup' => true,
        'allow_delivery' => true,
        'max_delivery_radius_km' => 10,
        'delivery_fee_type' => 'flat',
        'delivery_fee_base' => 12000,
        'delivery_free_min_order' => 100000,
        'payment_methods' => [
            'cod' => [
                'is_active' => true,
                'label' => 'Bayar di Kasir / COD',
                'description' => 'Bayar tunai saat pesanan diserahkan.',
            ],
            'bank_transfer' => [
                'is_active' => true,
                'label' => 'Transfer Bank Sinjay',
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_name' => 'Bebek Sinjay Pusat',
                'description' => 'Transfer ke rekening BCA kami.',
            ],
            'qris' => [
                'is_active' => true,
                'label' => 'QRIS Bebek Sinjay',
                'merchant_name' => 'BEBEK SINJAY MALANG',
                'description' => 'Scan QRIS untuk pembayaran instan.',
                'image_path' => '/images/qris-test.png',
            ],
        ],
    ]);

    PaymentMethod::create([
        'name' => 'Bayar di Tempat (COD / Kasir Resto)',
        'type' => 'manual',
        'bank_name' => 'COD',
        'account_name' => 'Kasir Resto',
        'is_active' => true,
    ]);
    PaymentMethod::create([
        'name' => 'Transfer Bank Resto',
        'type' => 'manual',
        'bank_name' => 'BCA',
        'account_name' => 'Pemilik Resto',
        'is_active' => true,
    ]);
    PaymentMethod::create([
        'name' => 'QRIS Resto',
        'type' => 'manual',
        'bank_name' => 'QRIS',
        'account_name' => 'Merchant QRIS Resto',
        'is_active' => true,
    ]);
});

test('resto profile resolves customized per-resto payment methods', function () {
    $resolved = $this->restoProfile->getResolvedPaymentMethods();

    expect(array_keys($resolved))->toContain('cod', 'bank_transfer', 'qris', 'waris');

    $qris = $resolved['qris'];
    expect($qris['label'])->toBe('QRIS Bebek Sinjay');
    expect($qris['merchant_name'])->toBe('BEBEK SINJAY MALANG');

    $bank = $resolved['bank_transfer'];
    expect($bank['bank_name'])->toBe('BCA');
    expect($bank['account_number'])->toBe('1234567890');
    expect($bank['account_name'])->toBe('Bebek Sinjay Pusat');

    $waris = $resolved['waris'];
    expect($waris['is_active'])->toBeFalse();
    expect($waris['qris_code'])->toBe('random');
});

test('resto profile update endpoint saves payment methods and delivery settings', function () {
    $this->actingAs($this->seller);

    $response = $this->put('/admin/resto-profile', [
        'resto_name' => 'Bebek Sinjay Updated',
        'culinary_category' => 'Aneka Nasi & Lauk',
        'address' => 'Jl. Ijen No. 10, Malang',
        'latitude' => -7.970000,
        'longitude' => 112.630000,
        'avg_prep_time_minutes' => 25,
        'max_delivery_radius_km' => 12,
        'delivery_fee_type' => 'per_km',
        'delivery_fee_base' => 3000,
        'delivery_free_min_order' => 150000,
        'allow_pickup' => true,
        'allow_delivery' => true,
        'is_open_now' => true,
        'payment_methods' => [
            'cod' => [
                'is_active' => false,
                'label' => 'Bayar di Tempat',
            ],
            'bank_transfer' => [
                'is_active' => true,
                'label' => 'Transfer Mandiri Resto',
                'bank_name' => 'Mandiri',
                'account_number' => '9876543210',
                'account_name' => 'Chef Jangkung Mandiri',
            ],
            'qris' => [
                'is_active' => true,
                'label' => 'QRIS Sinjay Mandiri',
                'merchant_name' => 'SINJAY RESTO',
            ],
            'waris' => [
                'is_active' => true,
                'label' => 'WARIS QRIS Bebek Sinjay',
                'api_url' => 'https://webapiqris.biz.id/api/v1',
                'api_key' => 'wrs_live_resto_custom_123',
                'webhook_secret' => 'whsec_resto_abc',
                'qris_code' => 'resto_sinjay_01',
                'admin_fee' => 1000,
            ],
        ],
    ]);

    $response->assertSessionHas('success');

    $this->restoProfile->refresh();
    expect($this->restoProfile->resto_name)->toBe('Bebek Sinjay Updated');
    expect($this->restoProfile->delivery_fee_type)->toBe('per_km');
    expect((float) $this->restoProfile->delivery_fee_base)->toBe(3000.0);
    expect((float) $this->restoProfile->delivery_free_min_order)->toBe(150000.0);
    expect($this->restoProfile->payment_methods['bank_transfer']['bank_name'])->toBe('Mandiri');
    expect($this->restoProfile->payment_methods['cod']['is_active'])->toBeFalse();
    expect($this->restoProfile->payment_methods['waris']['is_active'])->toBeTrue();
    expect($this->restoProfile->payment_methods['waris']['api_key'])->toBe('wrs_live_resto_custom_123');
    expect($this->restoProfile->payment_methods['waris']['qris_code'])->toBe('resto_sinjay_01');
});

test('super admin can update resto profile payment methods and fixed delivery fee type is normalized', function () {
    $superAdmin = User::factory()->create();
    $role = Role::firstOrCreate(['name' => 'Super Admin']);
    $superAdmin->assignRole($role);

    $this->actingAs($superAdmin);

    $response = $this->put('/admin/resto-profile', [
        'resto_id' => $this->restoProfile->id,
        'resto_name' => $this->restoProfile->resto_name,
        'culinary_category' => $this->restoProfile->culinary_category,
        'address' => $this->restoProfile->address,
        'latitude' => $this->restoProfile->latitude,
        'longitude' => $this->restoProfile->longitude,
        'avg_prep_time_minutes' => 20,
        'max_delivery_radius_km' => 10,
        'delivery_fee_type' => 'fixed', // Legacy/seeder value should normalize to 'flat'
        'delivery_fee_base' => 15000,
        'delivery_free_min_order' => 50000,
        'allow_pickup' => 'true', // string boolean from multipart/form-data
        'allow_delivery' => 'true',
        'is_open_now' => 'true',
        'payment_methods' => [
            'cod' => [
                'is_active' => 'true',
                'label' => 'Bayar Tunai di Kasir Resto',
                'description' => 'Siapkan uang pas.',
            ],
            'bank_transfer' => [
                'is_active' => 'true',
                'bank_name' => 'BCA',
                'account_number' => '5544332211',
                'account_name' => 'Rekening Resto Resmi',
                'label' => 'Transfer Bank BCA',
            ],
            'qris' => [
                'is_active' => 'false',
                'merchant_name' => 'QRIS RESTO RESMI',
            ],
        ],
    ]);

    $response->assertSessionHas('success');

    $this->restoProfile->refresh();
    expect($this->restoProfile->delivery_fee_type)->toBe('flat');
    expect($this->restoProfile->payment_methods['bank_transfer']['account_number'])->toBe('5544332211');
    expect($this->restoProfile->payment_methods['cod']['label'])->toBe('Bayar Tunai di Kasir Resto');
    expect($this->restoProfile->payment_methods['qris']['is_active'])->toBeFalse();

    // Verify GET endpoint returns resolved payment methods
    $viewResponse = $this->get('/admin/resto-profile?resto_id='.$this->restoProfile->id);
    $viewResponse->assertOk();
    $pageProps = $viewResponse->original->getData()['page']['props'];
    expect($pageProps['restoProfile']['payment_methods']['bank_transfer']['account_number'])->toBe('5544332211');
    expect($pageProps['restoProfile']['payment_methods']['qris']['is_active'])->toBeFalse();
});

test('shipping calculation falls back to base fee without error when guest customer coordinates are absent', function () {
    // Calling shipping cost calculation without GPS coordinates
    $response = $this->postJson('/checkout/shipping-cost', [
        'courier' => 'store_courier',
        'resto_id' => $this->seller->id,
        'destination' => 'Klojen, Malang',
        'weight' => 500,
    ]);

    $response->assertStatus(200);
    $data = $response->json('results.0');

    expect($data['code'])->toBe('store_courier');
    expect($data['costs'][0]['service'])->toBe('Resto Delivery');
    // Base fee is 12000 from resto profile
    expect((float) $data['costs'][0]['cost'][0]['value'])->toBe(12000.0);
});

test('shipping calculation grants free delivery when minimum order threshold is reached', function () {
    $product = Product::create([
        'user_id' => $this->seller->id,
        'name' => 'Bebek Jumbo Kenduri',
        'slug' => 'bebek-jumbo-kenduri',
        'sku' => 'SKU-BEBEK-JUMBO',
        'status' => 'published',
        'price' => 120000,
        'weight' => 1000,
    ]);
    ProductPrice::create([
        'product_id' => $product->id,
        'price' => 120000,
    ]);

    $this->actingAs($this->seller);

    CartItem::create([
        'user_id' => $this->seller->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $response = $this->postJson('/checkout/shipping-cost', [
        'courier' => 'store_courier',
        'resto_id' => $this->seller->id,
        'destination' => 'Malang',
        'weight' => 1000,
        'latitude' => -7.950000,
        'longitude' => 112.615000,
    ]);

    $response->assertStatus(200);
    $cost = $response->json('results.0.costs.0.cost.0');

    expect((float) $cost['value'])->toBe(0.0);
    expect($cost['note'])->toContain('Promo Gratis Ongkir Resto');
});

test('shipping cost calculation accepts numeric destination and area IDs without 422 string validation errors', function () {
    // Sending integer/number destination, dest_area_id, and biteship_area_id
    $response = $this->postJson('/checkout/shipping-cost', [
        'courier' => 'store_courier',
        'resto_id' => $this->seller->id,
        'destination' => 1422,
        'dest_area_id' => 1422,
        'biteship_area_id' => 5012,
        'weight' => 500,
    ]);

    $response->assertStatus(200);
    $data = $response->json('results.0');

    expect($data['code'])->toBe('store_courier');
    expect((float) $data['costs'][0]['cost'][0]['value'])->toBe(12000.0);
});
