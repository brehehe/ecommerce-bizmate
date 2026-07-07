<?php

use App\Models\Brand;
use App\Models\ChatSticker;
use App\Models\Courier;
use App\Models\PaymentMethod;
use App\Models\SocialMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup roles
    Role::create(['name' => 'Super Admin']);
    Role::create(['name' => 'Admin']);
    Role::create(['name' => 'Customer']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
});

test('admin can bulk delete admins', function () {
    $admin1 = User::factory()->create();
    $admin1->assignRole('Admin');

    $admin2 = User::factory()->create();
    $admin2->assignRole('Admin');

    $response = $this->actingAs($this->admin)->post(route('admin.master-data.admins.bulk-delete'), [
        'ids' => [$admin1->id],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseMissing('users', ['id' => $admin1->id]);
    $this->assertDatabaseHas('users', ['id' => $admin2->id]);
});

test('admin can bulk delete customers', function () {
    $customer1 = User::factory()->create();
    $customer1->assignRole('Customer');

    $customer2 = User::factory()->create();
    $customer2->assignRole('Customer');

    $response = $this->actingAs($this->admin)->post(route('admin.master-data.customers.bulk-delete'), [
        'ids' => [$customer1->id],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseMissing('users', ['id' => $customer1->id]);
    $this->assertDatabaseHas('users', ['id' => $customer2->id]);
});

test('admin can bulk delete payment methods', function () {
    $method1 = PaymentMethod::factory()->create(['name' => 'Method 1']);
    $method2 = PaymentMethod::factory()->create(['name' => 'Method 2']);

    $response = $this->actingAs($this->admin)->post(route('admin.master-data.payment-methods.bulk-delete'), [
        'ids' => [$method1->id],
    ]);

    $response->assertRedirect();
    $this->assertSoftDeleted('payment_methods', ['id' => $method1->id]);
    $this->assertDatabaseHas('payment_methods', ['id' => $method2->id]);
});

test('admin can bulk delete couriers', function () {
    $courier1 = Courier::factory()->create(['code' => 'courier1', 'name' => 'Courier 1']);
    $courier2 = Courier::factory()->create(['code' => 'courier2', 'name' => 'Courier 2']);

    $response = $this->actingAs($this->admin)->post(route('admin.master-data.couriers.bulk-delete'), [
        'ids' => [$courier1->id],
    ]);

    $response->assertRedirect();
    $this->assertSoftDeleted('couriers', ['id' => $courier1->id]);
    $this->assertDatabaseHas('couriers', ['id' => $courier2->id]);
});

test('admin can bulk delete brands', function () {
    $brand1 = Brand::create(['name' => 'Brand 1', 'slug' => 'brand-1']);
    $brand2 = Brand::create(['name' => 'Brand 2', 'slug' => 'brand-2']);

    $response = $this->actingAs($this->admin)->post(route('admin.master-data.brands.bulk-delete'), [
        'ids' => [$brand1->id],
    ]);

    $response->assertRedirect();
    $this->assertSoftDeleted('brands', ['id' => $brand1->id]);
    $this->assertDatabaseHas('brands', ['id' => $brand2->id]);
});

test('admin can bulk delete social media links', function () {
    $sm1 = SocialMedia::create([
        'platform' => 'instagram',
        'label' => 'Instagram 1',
        'url' => 'https://instagram.com/1',
        'icon' => 'ti-brand-instagram',
        'order' => 1,
    ]);
    $sm2 = SocialMedia::create([
        'platform' => 'instagram',
        'label' => 'Instagram 2',
        'url' => 'https://instagram.com/2',
        'icon' => 'ti-brand-instagram',
        'order' => 2,
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.master-data.social-media.bulk-delete'), [
        'ids' => [$sm1->id],
    ]);

    $response->assertRedirect();
    $this->assertSoftDeleted('social_media', ['id' => $sm1->id]);
    $this->assertDatabaseHas('social_media', ['id' => $sm2->id]);
});

test('admin can bulk delete stickers', function () {
    $st1 = ChatSticker::create([
        'name' => 'Sticker 1',
        'category' => 'Default',
        'image_path' => 'chat-stickers/sticker1.png',
        'order' => 1,
    ]);
    $st2 = ChatSticker::create([
        'name' => 'Sticker 2',
        'category' => 'Default',
        'image_path' => 'chat-stickers/sticker2.png',
        'order' => 2,
    ]);

    // Mock storage disk deletion
    Storage::fake('public');

    $response = $this->actingAs($this->admin)->post(route('admin.master-data.stickers.bulk-delete'), [
        'ids' => [$st1->id],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseMissing('chat_stickers', ['id' => $st1->id]);
    $this->assertDatabaseHas('chat_stickers', ['id' => $st2->id]);
});
