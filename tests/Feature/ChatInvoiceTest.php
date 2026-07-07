<?php

use App\Models\Category;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createChatTestTransaction(): array
{
    $customer = User::factory()->create();
    $admin = User::factory()->create();

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

    $product->productPrice()->create(['price' => 100000, 'cost' => 60000]);

    ProductStock::create([
        'product_id' => $product->id,
        'stock' => 5,
        'is_unlimited' => false,
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
        'transaction_number' => 'TRX-TEST-00001',
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

    TransactionItem::create([
        'transaction_id' => $transaction->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'product_sku' => $product->sku,
        'quantity' => 2,
        'hpp' => 60000,
        'harga_jual' => 100000,
        'diskon_item' => 0,
        'harga_akhir' => 100000,
        'subtotal' => 200000,
    ]);

    TransactionPayment::create([
        'transaction_id' => $transaction->id,
        'payment_method_id' => $paymentMethod->id,
        'amount' => 215000,
        'status' => 'pending',
    ]);

    return [
        'transaction' => $transaction,
        'admin' => $admin,
        'customer' => $customer,
    ];
}

test('creating chat with transaction_id automatically sends invoice card', function () {
    ['transaction' => $transaction, 'customer' => $customer] = createChatTestTransaction();

    $response = $this->actingAs($customer)->postJson('/chats', [
        'subject' => 'Tanya Invoice',
        'transaction_id' => $transaction->id,
    ]);

    $response->assertOk();
    $data = $response->json();

    // Assert that a chat was created
    $this->assertDatabaseHas('chats', [
        'id' => $data['id'],
        'user_id' => $customer->id,
    ]);

    // Assert that the first message in this chat is the transaction card
    $chatMessage = ChatMessage::where('chat_id', $data['id'])->first();
    expect($chatMessage)->not->toBeNull();
    expect($chatMessage->body)->toStartWith('[TRANSACTION_CARD]');

    // Decode the transaction card body and check fields
    $cardJson = str_replace('[TRANSACTION_CARD]', '', $chatMessage->body);
    $card = json_decode($cardJson, true);

    expect($card['transaction_number'])->toBe($transaction->transaction_number);
    expect((float) $card['grand_total'])->toBe((float) $transaction->grand_total);
    expect($card['id'])->toBe($transaction->id);
});

test('subsequent createChat requests with same transaction_id does not duplicate invoice card', function () {
    ['transaction' => $transaction, 'customer' => $customer] = createChatTestTransaction();

    // First request
    $response1 = $this->actingAs($customer)->postJson('/chats', [
        'subject' => 'Tanya Invoice',
        'transaction_id' => $transaction->id,
    ]);
    $response1->assertOk();
    $chatId = $response1->json('id');

    expect(ChatMessage::where('chat_id', $chatId)->count())->toBe(1);

    // Second request
    $response2 = $this->actingAs($customer)->postJson('/chats', [
        'subject' => 'Tanya Invoice',
        'transaction_id' => $transaction->id,
    ]);
    $response2->assertOk();
    expect($response2->json('id'))->toBe($chatId);

    // Count messages should still be 1 (not duplicated)
    expect(ChatMessage::where('chat_id', $chatId)->count())->toBe(1);
});
