<?php

use App\Models\Chat;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    $chat = Chat::find($chatId);
    if (! $chat) {
        return false;
    }

    return (string) $chat->user_id === (string) $user->id || ! $user->hasRole('Customer');
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (string) $user->id === (string) $userId;
});
