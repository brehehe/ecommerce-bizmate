<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/search', [StorefrontController::class, 'search'])->name('search');
Route::get('/products/{product:slug}', [StorefrontController::class, 'show'])->name('products.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate']);
    Route::post('/register', [StorefrontController::class, 'register'])->name('register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});

Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Settings
    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

    // Categories
    Route::post('/categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::apiResource('categories', CategoryController::class)->except(['show']);

    // Products
    Route::post('/products/{product}/toggle-active', [ProductController::class, 'toggleActive'])->name('products.toggle-active');
    Route::resource('products', ProductController::class)->except(['show']);

    // Promotions
    Route::post('/promotions/{promotion}/toggle-active', [PromotionController::class, 'toggleActive'])->name('promotions.toggle-active');
    Route::resource('promotions', PromotionController::class)->except(['show']);

    // Store Management (Bulk Edits)
    Route::get('/store/prices', [ProductController::class, 'managePrices'])->name('store.prices');
    Route::post('/store/prices', [ProductController::class, 'updatePrices'])->name('store.prices.update');
    Route::get('/store/stocks', [ProductController::class, 'manageStocks'])->name('store.stocks');
    Route::post('/store/stocks', [ProductController::class, 'updateStocks'])->name('store.stocks.update');
    Route::get('/store/shipping', [ProductController::class, 'manageShipping'])->name('store.shipping');
    Route::post('/store/shipping', [ProductController::class, 'updateShipping'])->name('store.shipping.update');

    // Master Data Management
    Route::get('/master-data/admins', [MasterDataController::class, 'admins'])->name('master-data.admins');
    Route::post('/master-data/admins', [MasterDataController::class, 'storeAdmin'])->name('master-data.admins.store');
    Route::put('/master-data/admins/{user}', [MasterDataController::class, 'updateAdmin'])->name('master-data.admins.update');
    Route::delete('/master-data/admins/{user}', [MasterDataController::class, 'destroyAdmin'])->name('master-data.admins.destroy');
    Route::post('/master-data/admins/{user}/toggle-active', [MasterDataController::class, 'toggleActiveAdmin'])->name('master-data.admins.toggle-active');

    Route::get('/master-data/customers', [MasterDataController::class, 'customers'])->name('master-data.customers');
    Route::post('/master-data/customers', [MasterDataController::class, 'storeCustomer'])->name('master-data.customers.store');
    Route::put('/master-data/customers/{user}', [MasterDataController::class, 'updateCustomer'])->name('master-data.customers.update');
    Route::delete('/master-data/customers/{user}', [MasterDataController::class, 'destroyCustomer'])->name('master-data.customers.destroy');
    Route::post('/master-data/customers/{user}/toggle-active', [MasterDataController::class, 'toggleActiveCustomer'])->name('master-data.customers.toggle-active');

    Route::get('/master-data/roles', [MasterDataController::class, 'roles'])->name('master-data.roles');
});

Route::redirect('/admin', '/admin/dashboard');
