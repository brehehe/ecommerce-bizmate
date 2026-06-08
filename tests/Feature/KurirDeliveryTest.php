<?php

use App\Mail\DeliveryArrived;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Kurir Toko', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Admin Toko', 'guard_name' => 'web']);

    Mail::fake();
});

function makeKurir(): User
{
    $kurir = User::factory()->create();
    $kurir->assignRole('Kurir Toko');

    return $kurir;
}

function makeStoreCourierTransaction(string $status = 'dikemas'): Transaction
{
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    return Transaction::factory()->create([
        'shipping_courier' => 'store_courier',
        'status' => $status,
        'booking_code' => 'ST-TRX-TEST-001',
        'user_id' => $customer->id,
    ]);
}

test('kurir can pickup a packaged order and saves courier_user_id', function () {
    $kurir = makeKurir();
    $trx = makeStoreCourierTransaction('dikemas');

    $this->actingAs($kurir)
        ->post("/kurir/transactions/{$trx->id}/update-status", ['action' => 'pickup'])
        ->assertRedirect();

    $fresh = $trx->fresh();
    expect($fresh->status)->toBe('out_for_pickup');
    expect($fresh->courier_user_id)->toBe($kurir->id);
});

test('kurir cannot pickup a packaged order if booking_code or tracking_number is empty', function () {
    $kurir = makeKurir();
    $trx = makeStoreCourierTransaction('dikemas');

    // Force clear booking code and tracking number
    $trx->updateQuietly([
        'booking_code' => null,
        'tracking_number' => null,
    ]);

    $this->actingAs($kurir)
        ->post("/kurir/transactions/{$trx->id}/update-status", ['action' => 'pickup'])
        ->assertSessionHas('error');

    expect($trx->fresh()->status)->toBe('dikemas');
});

test('kurir can start delivery and auto-generates resi', function () {
    $kurir = makeKurir();
    $trx = makeStoreCourierTransaction('out_for_pickup');

    $this->actingAs($kurir)
        ->post("/kurir/transactions/{$trx->id}/update-status", ['action' => 'delivering'])
        ->assertRedirect();

    $fresh = $trx->fresh();
    expect($fresh->status)->toBe('dikirim');
    expect($fresh->tracking_number)->toStartWith('RSI-');
});

test('existing resi is not overwritten when delivering', function () {
    $kurir = makeKurir();
    $trx = makeStoreCourierTransaction('out_for_pickup');
    $trx->update(['tracking_number' => 'EXISTING-RESI-123']);

    $this->actingAs($kurir)
        ->post("/kurir/transactions/{$trx->id}/update-status", ['action' => 'delivering'])
        ->assertRedirect();

    expect($trx->fresh()->tracking_number)->toBe('EXISTING-RESI-123');
});

test('kurir can mark as arrived and email is sent', function () {
    $kurir = makeKurir();
    $trx = makeStoreCourierTransaction('dikirim');

    $this->actingAs($kurir)
        ->post("/kurir/transactions/{$trx->id}/update-status", ['action' => 'arrived'])
        ->assertRedirect();

    $fresh = $trx->fresh();
    expect($fresh->delivery_arrived_at)->not->toBeNull();

    Mail::assertQueued(DeliveryArrived::class, function ($mail) use ($fresh) {
        return $mail->transaction->id === $fresh->id;
    });
});

test('kurir can mark as arrived with multiple proof images', function () {
    $kurir = makeKurir();
    $trx = makeStoreCourierTransaction('dikirim');

    Storage::fake('public');
    $file1 = UploadedFile::fake()->image('bukti1.jpg');
    $file2 = UploadedFile::fake()->image('bukti2.jpg');

    $this->actingAs($kurir)
        ->post("/kurir/transactions/{$trx->id}/update-status", [
            'action' => 'arrived',
            'delivery_photos' => [$file1, $file2],
        ])
        ->assertRedirect();

    $fresh = $trx->fresh();
    expect($fresh->delivery_arrived_at)->not->toBeNull();
    expect($fresh->delivery_photos)->toBeArray()->toHaveCount(2);

    Storage::disk('public')->assertExists($fresh->delivery_photos[0]);
    Storage::disk('public')->assertExists($fresh->delivery_photos[1]);
});

test('cannot perform action on non-store-courier transaction', function () {
    $kurir = makeKurir();
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $trx = Transaction::factory()->create([
        'shipping_courier' => 'jne',
        'status' => 'dikemas',
        'user_id' => $customer->id,
    ]);

    $this->actingAs($kurir)
        ->post("/kurir/transactions/{$trx->id}/update-status", ['action' => 'pickup'])
        ->assertSessionHas('error');
});

test('cannot perform action on completed transaction', function () {
    $kurir = makeKurir();
    $trx = makeStoreCourierTransaction('selesai');

    $this->actingAs($kurir)
        ->post("/kurir/transactions/{$trx->id}/update-status", ['action' => 'pickup'])
        ->assertSessionHas('error');
});

test('scan endpoint finds transaction by booking code', function () {
    $kurir = makeKurir();
    $trx = makeStoreCourierTransaction('dikemas');

    $this->actingAs($kurir)
        ->getJson("/kurir/scan/{$trx->booking_code}")
        ->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonPath('redirect_url', "/kurir/transactions/{$trx->id}");
});

test('scan endpoint returns 404 for unknown code', function () {
    $kurir = makeKurir();

    $this->actingAs($kurir)
        ->getJson('/kurir/scan/UNKNOWN-CODE-XYZ')
        ->assertNotFound()
        ->assertJson(['success' => false]);
});

test('booking code and resi are not generated when status is diproses', function () {
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $trx = Transaction::factory()->create([
        'shipping_courier' => 'store_courier',
        'status' => 'diproses',
        'booking_code' => null,
        'tracking_number' => null,
        'user_id' => $customer->id,
    ]);

    expect($trx->booking_code)->toBeNull();
    expect($trx->tracking_number)->toBeNull();
});

test('booking code and resi are auto-generated when status becomes dikemas', function () {
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $trx = Transaction::factory()->create([
        'shipping_courier' => 'store_courier',
        'status' => 'diproses',
        'booking_code' => null,
        'tracking_number' => null,
        'user_id' => $customer->id,
    ]);

    // Update status to dikemas (packed)
    $trx->update(['status' => 'dikemas']);

    expect($trx->booking_code)->not->toBeNull()->toStartWith('ST-');
    expect($trx->tracking_number)->not->toBeNull()->toStartWith('RSI-');
});

test('admin can access courier delivery history page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $kurir = makeKurir();

    $this->actingAs($admin)
        ->get("/admin/master-data/admins/{$kurir->id}/courier-history")
        ->assertOk();
});

test('non-admin cannot access courier history page', function () {
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $kurir = makeKurir();

    $this->actingAs($customer)
        ->get("/admin/master-data/admins/{$kurir->id}/courier-history")
        ->assertRedirect('/');
});

test('scanning an out_for_pickup order automatically transitions it to dikirim and assigns the courier', function () {
    $kurir = makeKurir();
    $trx = makeStoreCourierTransaction('out_for_pickup');

    $this->actingAs($kurir)
        ->getJson("/kurir/scan/{$trx->booking_code}")
        ->assertOk()
        ->assertJson(['success' => true]);

    $fresh = $trx->fresh();
    expect($fresh->status)->toBe('dikirim');
    expect($fresh->courier_user_id)->toBe($kurir->id);
    expect($fresh->tracking_number)->not->toBeNull()->toStartWith('RSI-');
});
