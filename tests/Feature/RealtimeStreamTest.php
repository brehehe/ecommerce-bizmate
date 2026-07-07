<?php

use App\Models\Chat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Customer', 'guard_name' => 'web']);
});

test('broadcasting auth requires authentication', function (): void {
    $response = $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-user.1',
        'socket_id' => '1234.1234',
    ]);
    $response->assertStatus(403);
});

test('broadcasting auth allows user to authorize for their own notification channel', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Customer');

    $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
        'channel_name' => "private-user.{$user->id}",
        'socket_id' => '1234.1234',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['auth']);
});

test('broadcasting auth allows owner to authorize for their chat channel', function (): void {
    $user = User::factory()->create();
    $user->assignRole('Customer');

    $chat = Chat::create([
        'user_id' => $user->id,
        'subject' => 'Tanya Penjual',
    ]);

    $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
        'channel_name' => "private-chat.{$chat->id}",
        'socket_id' => '1234.1234',
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['auth']);
});

test('broadcasting auth denies access to other user chat channel', function (): void {
    $user1 = User::factory()->create();
    $user1->assignRole('Customer');

    $user2 = User::factory()->create();
    $user2->assignRole('Customer');

    $chat = Chat::create([
        'user_id' => $user1->id,
        'subject' => 'Tanya Penjual',
    ]);

    $response = $this->actingAs($user2)->postJson('/broadcasting/auth', [
        'channel_name' => "private-chat.{$chat->id}",
        'socket_id' => '1234.1234',
    ]);

    $response->assertStatus(403);
});
