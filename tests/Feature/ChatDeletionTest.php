<?php

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer can delete their own chat thread', function () {
    $user = User::factory()->create();
    $chat = Chat::create([
        'user_id' => $user->id,
        'subject' => 'Tanya Penjual',
    ]);

    ChatMessage::create([
        'chat_id' => $chat->id,
        'sender_type' => 'user',
        'sender_id' => $user->id,
        'body' => 'Hello',
    ]);

    $response = $this->actingAs($user)->delete("/chats/{$chat->id}");

    $response->assertRedirect(route('chats.index'));
    $this->assertDatabaseMissing('chats', ['id' => $chat->id]);
    $this->assertDatabaseMissing('chat_messages', ['chat_id' => $chat->id]);
});

test('customer cannot delete another user\'s chat thread', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $chat = Chat::create([
        'user_id' => $user1->id,
        'subject' => 'Tanya Penjual',
    ]);

    $response = $this->actingAs($user2)->delete("/chats/{$chat->id}");

    $response->assertStatus(403);
    $this->assertDatabaseHas('chats', ['id' => $chat->id]);
});

test('customer can delete their own chat message', function () {
    $user = User::factory()->create();
    $chat = Chat::create([
        'user_id' => $user->id,
        'subject' => 'Tanya Penjual',
    ]);

    $message = ChatMessage::create([
        'chat_id' => $chat->id,
        'sender_type' => 'user',
        'sender_id' => $user->id,
        'body' => 'Hello',
    ]);

    $response = $this->actingAs($user)->delete("/chats/{$chat->id}/messages/{$message->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('chat_messages', ['id' => $message->id]);
});

test('customer cannot delete another user\'s chat message', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $chat = Chat::create([
        'user_id' => $user1->id,
        'subject' => 'Tanya Penjual',
    ]);

    $message = ChatMessage::create([
        'chat_id' => $chat->id,
        'sender_type' => 'user',
        'sender_id' => $user1->id,
        'body' => 'Hello',
    ]);

    $response = $this->actingAs($user2)->delete("/chats/{$chat->id}/messages/{$message->id}");

    $response->assertStatus(403);
    $this->assertDatabaseHas('chat_messages', ['id' => $message->id]);
});

test('admin can delete any chat thread', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    $chat = Chat::create([
        'user_id' => $user->id,
        'subject' => 'Tanya Penjual',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/chats/{$chat->id}");

    $response->assertRedirect(route('admin.chats.index'));
    $this->assertDatabaseMissing('chats', ['id' => $chat->id]);
});

test('admin can delete any chat message', function () {
    $admin = User::factory()->create();
    $user = User::factory()->create();
    $chat = Chat::create([
        'user_id' => $user->id,
        'subject' => 'Tanya Penjual',
    ]);
    $message = ChatMessage::create([
        'chat_id' => $chat->id,
        'sender_type' => 'user',
        'sender_id' => $user->id,
        'body' => 'Hello',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/chats/{$chat->id}/messages/{$message->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('chat_messages', ['id' => $message->id]);
});
