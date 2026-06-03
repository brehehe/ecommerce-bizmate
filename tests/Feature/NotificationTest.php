<?php

use App\Mail\OrderStatusChanged;
use App\Models\Category;
use App\Models\CustomerAddress;
use App\Models\Notification;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('a notification is created when transaction status changes', function () {
    $customer = User::factory()->create();

    $category = Category::create([
        'name' => 'Test Cat',
        'slug' => 'test-cat',
        'icon' => 'ti-box',
    ]);

    $product = Product::create([
        'name' => 'Test Product',
        'slug' => 'test-product',
        'sku' => 'TEST-P1',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $address = CustomerAddress::create([
        'user_id' => $customer->id,
        'receiver_name' => 'Customer Test',
        'phone_number' => '08000000000',
        'label' => 'Rumah',
        'full_address' => 'Jl. Test 1',
        'is_primary' => true,
    ]);

    $paymentMethod = PaymentMethod::create([
        'name' => 'BCA',
        'type' => 'manual',
        'bank_name' => 'BCA',
        'account_number' => '9876543210',
        'account_name' => 'Toko Test',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    $transaction = Transaction::create([
        'transaction_number' => 'TRX-TEST-99999',
        'user_id' => $customer->id,
        'customer_address_id' => $address->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => 'belum_bayar',
        'subtotal' => 200000,
        'discount_amount' => 0,
        'shipping_fee' => 15000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'grand_total' => 215000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
    ]);

    // Update status to trigger boots observer
    $transaction->update(['status' => 'diproses']);

    // Assert a notification is created in database
    $this->assertDatabaseHas('notifications', [
        'user_id' => $customer->id,
        'type' => 'transaction_status',
        'is_read' => false,
    ]);

    $notification = Notification::where('user_id', $customer->id)->first();
    expect($notification->message)->toContain('diproses');
});

test('authenticated user can mark notification as read', function () {
    $user = User::factory()->create();
    $notification = Notification::create([
        'user_id' => $user->id,
        'title' => 'Pembaruan Status Pesanan',
        'message' => 'Pesanan Anda TRX-12345 kini sedang diproses.',
        'type' => 'transaction_status',
        'url' => '/transactions/1',
        'is_read' => false,
    ]);

    $response = $this->actingAs($user)
        ->post(route('notifications.read', $notification->id));

    $response->assertRedirect();
    expect($notification->fresh()->is_read)->toBeTrue();
});

test('authenticated user can mark all notifications as read', function () {
    $user = User::factory()->create();
    Notification::create([
        'user_id' => $user->id,
        'title' => 'Pembaruan Status Pesanan',
        'message' => 'Pesanan Anda TRX-12345 kini sedang diproses.',
        'type' => 'transaction_status',
        'url' => '/transactions/1',
        'is_read' => false,
    ]);
    Notification::create([
        'user_id' => $user->id,
        'title' => 'Pembaruan Status Pesanan',
        'message' => 'Pesanan Anda TRX-12345 kini sedang dikirim.',
        'type' => 'transaction_status',
        'url' => '/transactions/1',
        'is_read' => false,
    ]);

    $response = $this->actingAs($user)
        ->post(route('notifications.read-all'));

    $response->assertRedirect();
    expect(Notification::where('user_id', $user->id)->where('is_read', false)->count())->toBe(0);
});

test('user cannot mark another users notification as read', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $notification = Notification::create([
        'user_id' => $user1->id,
        'title' => 'Pembaruan Status Pesanan',
        'message' => 'Pesanan Anda TRX-12345 kini sedang diproses.',
        'type' => 'transaction_status',
        'url' => '/transactions/1',
        'is_read' => false,
    ]);

    $response = $this->actingAs($user2)
        ->post(route('notifications.read', $notification->id));

    $response->assertStatus(403);
    expect($notification->fresh()->is_read)->toBeFalse();
});

test('a notification is created for admins when transaction is created', function () {
    $customer = User::factory()->create();

    $category = Category::create([
        'name' => 'Test Cat',
        'slug' => 'test-cat',
        'icon' => 'ti-box',
    ]);

    $product = Product::create([
        'name' => 'Test Product',
        'slug' => 'test-product',
        'sku' => 'TEST-P1',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    $address = CustomerAddress::create([
        'user_id' => $customer->id,
        'receiver_name' => 'Customer Test',
        'phone_number' => '08000000000',
        'label' => 'Rumah',
        'full_address' => 'Jl. Test 1',
        'is_primary' => true,
    ]);

    $paymentMethod = PaymentMethod::create([
        'name' => 'BCA',
        'type' => 'manual',
        'bank_name' => 'BCA',
        'account_number' => '9876543210',
        'account_name' => 'Toko Test',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    $transaction = Transaction::create([
        'transaction_number' => 'TRX-TEST-99998',
        'user_id' => $customer->id,
        'customer_address_id' => $address->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => 'belum_bayar',
        'subtotal' => 200000,
        'discount_amount' => 0,
        'shipping_fee' => 15000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'grand_total' => 215000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
    ]);

    // Assert a notification for admin is created in database (user_id is null)
    $this->assertDatabaseHas('notifications', [
        'user_id' => null,
        'type' => 'new_order',
        'is_read' => false,
    ]);

    $notification = Notification::whereNull('user_id')->first();
    expect($notification->message)->toContain('TRX-TEST-99998');
});

test('a notification is created when product stock is low or empty', function () {
    $category = Category::create([
        'name' => 'Test Cat',
        'slug' => 'test-cat',
        'icon' => 'ti-box',
    ]);

    $product = Product::create([
        'name' => 'Test Product',
        'slug' => 'test-product',
        'sku' => 'TEST-P1',
        'category_id' => $category->id,
        'summary' => 'Summary',
        'description' => 'Description',
        'active' => true,
    ]);

    // Create a ProductStock with low stock
    $productStock = ProductStock::create([
        'product_id' => $product->id,
        'product_variant_id' => null,
        'stock' => 5,
        'min_stock' => 10,
        'min_purchase' => 1,
        'is_unlimited' => false,
    ]);

    // Assert a low stock notification is created
    $this->assertDatabaseHas('notifications', [
        'user_id' => null,
        'type' => 'low_stock',
        'is_read' => false,
    ]);

    $lowNotif = Notification::where('type', 'low_stock')->first();
    expect($lowNotif->message)->toContain('Test Product');
    expect($lowNotif->message)->toContain('menipis (sisa 5)');

    // Now update stock to 0
    $productStock->update(['stock' => 0]);

    // Assert an out of stock notification is created
    $this->assertDatabaseHas('notifications', [
        'user_id' => null,
        'type' => 'out_of_stock',
        'is_read' => false,
    ]);

    $outNotif = Notification::where('type', 'out_of_stock')->first();
    expect($outNotif->message)->toContain('Test Product');
    expect($outNotif->message)->toContain('telah habis (0)');
});

test('an email is sent to customer when transaction status changes', function () {
    Mail::fake();

    $customer = User::factory()->create(['email' => 'testcustomer@example.com']);

    $category = Category::create([
        'name' => 'Test Cat',
        'slug' => 'test-cat',
        'icon' => 'ti-box',
    ]);

    $address = CustomerAddress::create([
        'user_id' => $customer->id,
        'receiver_name' => 'Customer Test',
        'phone_number' => '08000000000',
        'label' => 'Rumah',
        'full_address' => 'Jl. Test 1',
        'is_primary' => true,
    ]);

    $paymentMethod = PaymentMethod::create([
        'name' => 'BCA',
        'type' => 'manual',
        'bank_name' => 'BCA',
        'account_number' => '9876543210',
        'account_name' => 'Toko Test',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    $transaction = Transaction::create([
        'transaction_number' => 'TRX-EMAIL-TEST-123',
        'user_id' => $customer->id,
        'customer_address_id' => $address->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => 'belum_bayar',
        'subtotal' => 200000,
        'discount_amount' => 0,
        'shipping_fee' => 15000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'grand_total' => 215000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
    ]);

    // Update status to trigger email sending
    $transaction->update(['status' => 'diproses']);

    Mail::assertQueued(OrderStatusChanged::class, function ($mail) use ($customer) {
        return $mail->hasTo($customer->email) && $mail->transaction->status === 'diproses';
    });
});

test('an email is sent to customer when transaction tracking number changes', function () {
    Mail::fake();

    $customer = User::factory()->create(['email' => 'testcustomer2@example.com']);

    $category = Category::create([
        'name' => 'Test Cat',
        'slug' => 'test-cat',
        'icon' => 'ti-box',
    ]);

    $address = CustomerAddress::create([
        'user_id' => $customer->id,
        'receiver_name' => 'Customer Test',
        'phone_number' => '08000000000',
        'label' => 'Rumah',
        'full_address' => 'Jl. Test 1',
        'is_primary' => true,
    ]);

    $paymentMethod = PaymentMethod::create([
        'name' => 'BCA',
        'type' => 'manual',
        'bank_name' => 'BCA',
        'account_number' => '9876543210',
        'account_name' => 'Toko Test',
        'is_active' => true,
        'admin_fee' => 0,
    ]);

    $transaction = Transaction::create([
        'transaction_number' => 'TRX-EMAIL-TEST-456',
        'user_id' => $customer->id,
        'customer_address_id' => $address->id,
        'payment_method_id' => $paymentMethod->id,
        'status' => 'diproses',
        'subtotal' => 200000,
        'discount_amount' => 0,
        'shipping_fee' => 15000,
        'shipping_discount' => 0,
        'admin_fee' => 0,
        'grand_total' => 215000,
        'shipping_courier' => 'jne',
        'shipping_service' => 'REG',
    ]);

    // Update tracking number to trigger email sending
    $transaction->update(['tracking_number' => 'RESI123456789']);

    Mail::assertQueued(OrderStatusChanged::class, function ($mail) use ($customer) {
        return $mail->hasTo($customer->email) && $mail->transaction->tracking_number === 'RESI123456789';
    });
});
