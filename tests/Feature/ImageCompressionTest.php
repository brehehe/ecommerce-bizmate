<?php

use App\Helpers\ImageHelper;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
});

test('ImageHelper compresses jpeg/png images correctly', function () {
    // Create a mock image
    $uploadedFile = UploadedFile::fake()->image('test_avatar.jpg', 800, 600);
    $originalData = file_get_contents($uploadedFile->getRealPath());

    // Compress
    $compressedData = ImageHelper::compress($originalData, 'jpg', 75);

    // Assert compressed data has content and is smaller or valid
    expect($compressedData)->not->toBeNull();
});

test('ImageHelper compressAndStore stores file on public disk', function () {
    $uploadedFile = UploadedFile::fake()->image('avatar.png', 100, 100);

    $path = ImageHelper::compressAndStore($uploadedFile, 'avatars');

    expect($path)->toContain('avatars/');
    Storage::disk('public')->assertExists($path);
});

test('proof image validation blocks images over 2MB', function () {
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $paymentMethod = PaymentMethod::factory()->create(['name' => 'Transfer Bank']);
    $transaction = Transaction::factory()->create([
        'user_id' => $customer->id,
        'payment_method_id' => $paymentMethod->id,
        'grand_total' => 100000,
        'status' => 'belum_bayar',
    ]);

    // Create a fake image larger than 2MB (e.g. 3MB)
    $largeImage = UploadedFile::fake()->image('proof.jpg')->size(3000); // 3000KB

    $response = $this->actingAs($customer)
        ->from("/transactions/{$transaction->id}")
        ->post("/transactions/{$transaction->id}/upload-proof", [
            'proof_image' => $largeImage,
        ]);

    $response->assertRedirect("/transactions/{$transaction->id}");
    $response->assertSessionHasErrors(['proof_image']);
});

test('proof image validation accepts images under 2MB and stores compressed file', function () {
    $customer = User::factory()->create();
    $customer->assignRole('Customer');

    $paymentMethod = PaymentMethod::factory()->create(['name' => 'Transfer Bank']);
    $transaction = Transaction::factory()->create([
        'user_id' => $customer->id,
        'payment_method_id' => $paymentMethod->id,
        'grand_total' => 100000,
        'status' => 'belum_bayar',
    ]);

    // Create a fake image under 2MB (e.g., 500KB)
    $smallImage = UploadedFile::fake()->image('proof.jpg')->size(500); // 500KB

    $response = $this->actingAs($customer)
        ->from("/transactions/{$transaction->id}")
        ->post("/transactions/{$transaction->id}/upload-proof", [
            'proof_image' => $smallImage,
        ]);

    $response->assertRedirect("/transactions/{$transaction->id}");
    $response->assertSessionHasNoErrors();

    // Verify payment record and status update
    $transaction->refresh();
    $payment = $transaction->payment;
    expect($payment)->not->toBeNull();
    expect($payment->status)->toBe('pending');
    expect($payment->proof_image)->not->toBeNull();
    Storage::disk('public')->assertExists($payment->proof_image);
});
