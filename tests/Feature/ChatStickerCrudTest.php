<?php

use App\Models\ChatSticker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

test('admin can view sticker list page', function () {
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)->get('/admin/master-data/stickers');

    $response->assertOk();
});

test('admin can create a sticker', function () {
    $admin = User::factory()->create();
    $image = UploadedFile::fake()->image('sticker.png', 100, 100);

    $response = $this->actingAs($admin)->post('/admin/master-data/stickers', [
        'name' => 'Halo Geng',
        'category' => 'Sapaan',
        'image' => $image,
        'is_active' => '1',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('chat_stickers', [
        'name' => 'Halo Geng',
        'category' => 'Sapaan',
        'is_active' => true,
    ]);
});

test('admin cannot create sticker without image', function () {
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)->post('/admin/master-data/stickers', [
        'name' => 'Tanpa Gambar',
    ]);

    $response->assertSessionHasErrors('image');
});

test('admin can update a sticker name', function () {
    $admin = User::factory()->create();
    $image = UploadedFile::fake()->image('sticker.png', 100, 100);
    $sticker = ChatSticker::create([
        'name' => 'Lama',
        'image_path' => 'stickers/old.png',
        'is_active' => true,
        'order' => 0,
    ]);

    $response = $this->actingAs($admin)->put("/admin/master-data/stickers/{$sticker->id}", [
        'name' => 'Baru',
        'category' => 'Ekspresi',
        'is_active' => '1',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('chat_stickers', [
        'id' => $sticker->id,
        'name' => 'Baru',
        'category' => 'Ekspresi',
    ]);
});

test('admin can toggle sticker active status', function () {
    $admin = User::factory()->create();
    $sticker = ChatSticker::create([
        'name' => 'Toggle Test',
        'image_path' => 'stickers/test.png',
        'is_active' => true,
        'order' => 0,
    ]);

    $response = $this->actingAs($admin)->post("/admin/master-data/stickers/{$sticker->id}/toggle-active");

    $response->assertRedirect();
    $this->assertDatabaseHas('chat_stickers', [
        'id' => $sticker->id,
        'is_active' => false,
    ]);
});

test('admin can delete a sticker', function () {
    $admin = User::factory()->create();
    $image = UploadedFile::fake()->image('sticker.png', 100, 100);
    $path = $image->store('stickers', 'public');
    $sticker = ChatSticker::create([
        'name' => 'Hapus',
        'image_path' => $path,
        'is_active' => true,
        'order' => 0,
    ]);

    $response = $this->actingAs($admin)->delete("/admin/master-data/stickers/{$sticker->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('chat_stickers', ['id' => $sticker->id]);
    Storage::disk('public')->assertMissing($path);
});
