<?php

use App\Http\Controllers\Api\XenditWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/payment/xendit/callback', [XenditWebhookController::class, 'handleCallback']);
