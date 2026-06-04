<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Admin\CmsController;
use App\Http\Controllers\Admin\KomerceShipmentController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\RefundController as AdminRefundController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReturnController as AdminReturnController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CustomerAddressController;
use App\Http\Controllers\Kurir\KurirAuthController;
use App\Http\Controllers\Kurir\KurirDashboardController;
use App\Http\Controllers\Kurir\KurirDeliveryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/search', [StorefrontController::class, 'search'])->name('search');
Route::get('/flash-sale', [StorefrontController::class, 'flashSale'])->name('flash-sale');
Route::get('/produk-terlaris', [StorefrontController::class, 'produkTerlaris'])->name('produk-terlaris');
Route::get('/category/{category}', [StorefrontController::class, 'category'])->name('category');
Route::get('/products/{product:slug}', [StorefrontController::class, 'show'])->name('products.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate']);
    Route::post('/register', [StorefrontController::class, 'register'])->name('register');
});

// Forgot & Reset Password
Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])->name('password.update');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/bulk-update', [CartController::class, 'bulkUpdate'])->name('cart.bulk-update');
    Route::patch('/cart/{cartItem}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::post('/cart/select-vouchers', [CartController::class, 'selectVouchers'])->name('cart.select-vouchers');

    // Customer Profile Edit
    Route::get('/profile', [ProfileController::class, 'showCustomerProfile'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'updateCustomerProfile'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updateCustomerPassword'])->name('profile.password.update');
    Route::get('/profile/coin-history', [ProfileController::class, 'coinHistory'])->name('profile.coin-history');

    // Customer Bank Accounts
    Route::get('/profile/bank-accounts', [ProfileController::class, 'bankAccounts'])->name('profile.bank-accounts.index');
    Route::post('/profile/bank-accounts', [ProfileController::class, 'storeBankAccount'])->name('profile.bank-accounts.store');
    Route::put('/profile/bank-accounts/{bankAccount}', [ProfileController::class, 'updateBankAccount'])->name('profile.bank-accounts.update');
    Route::delete('/profile/bank-accounts/{bankAccount}', [ProfileController::class, 'destroyBankAccount'])->name('profile.bank-accounts.destroy');
    Route::post('/profile/bank-accounts/{bankAccount}/make-primary', [ProfileController::class, 'makePrimaryBankAccount'])->name('profile.bank-accounts.make-primary');

    // Customer Shipping Addresses
    Route::get('/profile/addresses', [CustomerAddressController::class, 'index'])->name('profile.addresses.index');
    Route::post('/profile/addresses', [CustomerAddressController::class, 'store'])->name('profile.addresses.store');
    Route::put('/profile/addresses/{address}', [CustomerAddressController::class, 'update'])->name('profile.addresses.update');
    Route::delete('/profile/addresses/{address}', [CustomerAddressController::class, 'destroy'])->name('profile.addresses.destroy');
    Route::post('/profile/addresses/{address}/make-primary', [CustomerAddressController::class, 'makePrimary'])->name('profile.addresses.make-primary');

    // Address Search & Reverse Geocode Proxy APIs
    Route::get('/api/addresses/search', [CustomerAddressController::class, 'searchApi'])->name('api.addresses.search');
    Route::get('/api/addresses/reverse', [CustomerAddressController::class, 'reverseApi'])->name('api.addresses.reverse');
    Route::get('/api/addresses/ip-location', [CustomerAddressController::class, 'ipLocation'])->name('api.addresses.ip-location');

    // Chat
    Route::get('/chats', [ChatController::class, 'index'])->name('chats.index');
    Route::post('/chats', [ChatController::class, 'createChat'])->name('chats.create');
    Route::get('/chats/{chat}/messages', [ChatController::class, 'messages'])->name('chats.messages');
    Route::post('/chats/{chat}/messages', [ChatController::class, 'store'])->name('chats.store');
    Route::delete('/chats/{chat}', [ChatController::class, 'destroy'])->name('chats.destroy');
    Route::delete('/chats/{chat}/messages/{message}', [ChatController::class, 'destroyMessage'])->name('chats.messages.destroy');

    // Checkout
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/checkout/apply-voucher', [CheckoutController::class, 'applyVoucher'])->name('checkout.apply-voucher');
    Route::post('/checkout/shipping-cost', [CheckoutController::class, 'shippingCost'])->name('checkout.shipping-cost');
    Route::get('/checkout/cities', [CheckoutController::class, 'cities'])->name('checkout.cities');
    Route::get('/checkout/international-destinations', [CheckoutController::class, 'internationalDestinations'])->name('checkout.international-destinations');
    Route::get('/checkout/komerce/search-destination', [CheckoutController::class, 'searchKomerceDestination'])->name('checkout.komerce.search-destination');

    // Transaction (Customer)
    Route::get('/transactions', [StorefrontController::class, 'transactionHistory'])->name('transactions.index');
    Route::get('/transactions/{transaction}', [StorefrontController::class, 'transactionDetail'])->name('transactions.show');
    Route::get('/transactions/{transaction}/print-invoice', [StorefrontController::class, 'printInvoice'])->name('transactions.print-invoice-customer');
    Route::post('/transactions/{transaction}/upload-proof', [CheckoutController::class, 'uploadProof'])->name('transactions.upload-proof');
    Route::post('/transactions/{transaction}/cancel', [StorefrontController::class, 'cancelTransaction'])->name('transactions.cancel');
    Route::post('/transactions/{transaction}/change-payment', [StorefrontController::class, 'changePaymentMethod'])->name('transactions.change-payment');
    Route::post('/transactions/{transaction}/complete', [StorefrontController::class, 'completeTransaction'])->name('transactions.complete');
    Route::post('/transactions/{transaction}/extend-auto-complete', [StorefrontController::class, 'extendAutoComplete'])->name('transactions.extend-auto-complete');
    Route::post('/transactions/{transaction}/review', [StorefrontController::class, 'submitReview'])->name('transactions.review');
    Route::post('/reviews/{review}/report', [StorefrontController::class, 'reportReview'])->name('reviews.report');
    Route::get('/transactions/{transaction}/komerce/track', [KomerceShipmentController::class, 'trackShipment'])->name('transactions.komerce.track');

    // Returns (Customer)
    Route::get('/returns', [ReturnController::class, 'index'])->name('returns.index');

    // Refunds (Customer)
    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::get('/refunds/{refundRequest}', [RefundController::class, 'show'])->name('refunds.show');
    Route::post('/transactions/{transaction}/refund-request', [RefundController::class, 'store'])->name('refunds.store');
    Route::post('/transactions/{transaction}/return', [ReturnController::class, 'store'])->name('returns.store');
    Route::post('/returns/{returnRequest}/tracking', [ReturnController::class, 'updateTracking'])->name('returns.tracking');

    // Notifications (Customer)
    Route::post('/notifications/{notification}/read', [StorefrontController::class, 'markNotificationAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [StorefrontController::class, 'markAllNotificationsAsRead'])->name('notifications.read-all');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'not_customer'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Settings
    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/tour-complete', [SettingController::class, 'completeTour'])->name('settings.tour-complete');

    // Profile
    Route::get('/profile', [ProfileController::class, 'showAdminProfile'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'updateAdminProfile'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updateAdminPassword'])->name('profile.password.update');

    // CMS Banners
    Route::get('/cms/banners', [CmsController::class, 'banners'])->name('cms.banners');
    Route::post('/cms/banners', [CmsController::class, 'updateBanners'])->name('cms.banners.update');

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
    Route::get('/master-data/admins/{user}/courier-history', [MasterDataController::class, 'courierHistory'])->name('master-data.admins.courier-history');

    Route::get('/master-data/customers', [MasterDataController::class, 'customers'])->name('master-data.customers');
    Route::post('/master-data/customers', [MasterDataController::class, 'storeCustomer'])->name('master-data.customers.store');
    Route::put('/master-data/customers/{user}', [MasterDataController::class, 'updateCustomer'])->name('master-data.customers.update');
    Route::delete('/master-data/customers/{user}', [MasterDataController::class, 'destroyCustomer'])->name('master-data.customers.destroy');
    Route::post('/master-data/customers/{user}/toggle-active', [MasterDataController::class, 'toggleActiveCustomer'])->name('master-data.customers.toggle-active');

    Route::get('/master-data/payment-methods', [MasterDataController::class, 'paymentMethods'])->name('master-data.payment-methods');
    Route::post('/master-data/payment-methods', [MasterDataController::class, 'storePaymentMethod'])->name('master-data.payment-methods.store');
    Route::put('/master-data/payment-methods/{paymentMethod}', [MasterDataController::class, 'updatePaymentMethod'])->name('master-data.payment-methods.update');
    Route::delete('/master-data/payment-methods/{paymentMethod}', [MasterDataController::class, 'destroyPaymentMethod'])->name('master-data.payment-methods.destroy');
    Route::post('/master-data/payment-methods/{paymentMethod}/toggle-active', [MasterDataController::class, 'toggleActivePaymentMethod'])->name('master-data.payment-methods.toggle-active');

    Route::get('/master-data/couriers', [MasterDataController::class, 'couriers'])->name('master-data.couriers');
    Route::post('/master-data/couriers', [MasterDataController::class, 'storeCourier'])->name('master-data.couriers.store');
    Route::put('/master-data/couriers/{courier}', [MasterDataController::class, 'updateCourier'])->name('master-data.couriers.update');
    Route::delete('/master-data/couriers/{courier}', [MasterDataController::class, 'destroyCourier'])->name('master-data.couriers.destroy');
    Route::post('/master-data/couriers/{courier}/toggle-active', [MasterDataController::class, 'toggleActiveCourier'])->name('master-data.couriers.toggle-active');

    Route::get('/master-data/brands', [MasterDataController::class, 'brands'])->name('master-data.brands');
    Route::post('/master-data/brands', [MasterDataController::class, 'storeBrand'])->name('master-data.brands.store');
    Route::put('/master-data/brands/{brand}', [MasterDataController::class, 'updateBrand'])->name('master-data.brands.update');
    Route::delete('/master-data/brands/{brand}', [MasterDataController::class, 'destroyBrand'])->name('master-data.brands.destroy');
    Route::post('/master-data/brands/{brand}/toggle-active', [MasterDataController::class, 'toggleActiveBrand'])->name('master-data.brands.toggle-active');

    Route::get('/master-data/roles', [MasterDataController::class, 'roles'])->name('master-data.roles');

    // Chat (Admin)
    Route::get('/chats', [AdminChatController::class, 'index'])->name('chats.index');
    Route::get('/chats/{chat}', [AdminChatController::class, 'show'])->name('chats.show');
    Route::get('/chats/{chat}/poll', [AdminChatController::class, 'pollMessages'])->name('chats.poll');
    Route::post('/chats/{chat}/reply', [AdminChatController::class, 'reply'])->name('chats.reply');
    Route::delete('/chats/{chat}', [AdminChatController::class, 'destroy'])->name('chats.destroy');
    Route::delete('/chats/{chat}/messages/{message}', [AdminChatController::class, 'destroyMessage'])->name('chats.messages.destroy');

    // Transactions (Admin)
    Route::get('/transactions', [AdminTransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/find-by-number/{number}', [AdminTransactionController::class, 'findByNumber'])->name('transactions.find-by-number');
    Route::get('/transactions/{transaction}', [AdminTransactionController::class, 'show'])->name('transactions.show');
    Route::post('/transactions/{transaction}/status', [AdminTransactionController::class, 'updateStatus'])->name('transactions.update-status');
    Route::post('/transactions/{transaction}/confirm-payment', [AdminTransactionController::class, 'confirmPayment'])->name('transactions.confirm-payment');
    Route::post('/transactions/{transaction}/reject-payment', [AdminTransactionController::class, 'rejectPayment'])->name('transactions.reject-payment');
    Route::post('/transactions/{transaction}/tracking', [AdminTransactionController::class, 'updateTracking'])->name('transactions.update-tracking');
    Route::post('/transactions/{transaction}/delivery-history', [AdminTransactionController::class, 'addDeliveryHistory'])->name('transactions.add-delivery-history');
    Route::post('/transactions/{transaction}/komerce/store', [KomerceShipmentController::class, 'storeShipment'])->name('transactions.komerce.store');
    Route::post('/transactions/{transaction}/komerce/pickup', [KomerceShipmentController::class, 'requestPickup'])->name('transactions.komerce.pickup');
    Route::get('/transactions/{transaction}/komerce/print', [KomerceShipmentController::class, 'printLabel'])->name('transactions.komerce.print');
    Route::post('/transactions/{transaction}/komerce/cancel', [KomerceShipmentController::class, 'cancelShipment'])->name('transactions.komerce.cancel');
    Route::get('/transactions/{transaction}/komerce/track', [KomerceShipmentController::class, 'trackShipment'])->name('transactions.komerce.track');
    Route::get('/transactions/{transaction}/komerce/detail', [KomerceShipmentController::class, 'getOrderDetail'])->name('transactions.komerce.detail');

    Route::post('/transactions/bulk-status', [AdminTransactionController::class, 'bulkStatus'])->name('transactions.bulk-status');
    Route::post('/transactions/bulk-tracking', [AdminTransactionController::class, 'bulkTracking'])->name('transactions.bulk-tracking');
    Route::get('/transactions/{transaction}/print-invoice', [AdminTransactionController::class, 'printInvoice'])->name('transactions.print-invoice');
    Route::get('/transactions/{transaction}/print-shipping-label', [AdminTransactionController::class, 'printShippingLabel'])->name('transactions.print-shipping-label');

    // Stock Movements
    Route::get('/stock-movements', [AdminTransactionController::class, 'stockMovements'])->name('stock-movements.index');

    // Returns (Admin)
    Route::get('/returns', [AdminReturnController::class, 'index'])->name('returns.index');

    // Refunds (Admin)
    Route::get('/refunds', [AdminRefundController::class, 'index'])->name('refunds.index');
    Route::get('/refunds/{refund}', [AdminRefundController::class, 'show'])->name('refunds.show');
    Route::post('/refunds/{refund}/approve', [AdminRefundController::class, 'approve'])->name('refunds.approve');
    Route::post('/refunds/{refund}/reject', [AdminRefundController::class, 'reject'])->name('refunds.reject');
    Route::post('/refunds/{refund}/complete', [AdminRefundController::class, 'completeRefund'])->name('refunds.complete');
    Route::get('/returns/{return}', [AdminReturnController::class, 'show'])->name('returns.show');
    Route::post('/returns/{return}/approve', [AdminReturnController::class, 'approve'])->name('returns.approve');
    Route::post('/returns/{return}/reject', [AdminReturnController::class, 'reject'])->name('returns.reject');
    Route::post('/returns/{return}/customer-tracking', [AdminReturnController::class, 'updateCustomerTracking'])->name('returns.customer-tracking');
    Route::post('/returns/{return}/confirm-receipt', [AdminReturnController::class, 'confirmReceipt'])->name('returns.confirm-receipt');
    Route::post('/returns/{return}/process-refund', [AdminReturnController::class, 'processRefund'])->name('returns.process-refund');
    Route::post('/returns/{return}/process-replacement', [AdminReturnController::class, 'processReplacement'])->name('returns.process-replacement');
    Route::post('/returns/{return}/replacement-tracking', [AdminReturnController::class, 'updateReplacementTracking'])->name('returns.replacement-tracking');
    Route::post('/returns/{return}/complete-refund', [AdminReturnController::class, 'completeRefund'])->name('returns.complete-refund');

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('/products', [ReportController::class, 'products'])->name('products');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/customers', [ReportController::class, 'customers'])->name('customers');
        Route::get('/stocks', [ReportController::class, 'stocks'])->name('stocks');
        Route::get('/pareto', [ReportController::class, 'pareto'])->name('pareto');
        Route::get('/couriers', [ReportController::class, 'couriers'])->name('couriers');
        Route::get('/reviews', [ReportController::class, 'reviews'])->name('reviews');
    });
});

Route::redirect('/admin', '/admin/dashboard');

// Kurir Toko Portal
Route::prefix('kurir')->name('kurir.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [KurirAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [KurirAuthController::class, 'authenticate']);
    });

    Route::middleware(['auth', 'is_kurir'])->group(function () {
        Route::post('/logout', [KurirAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [KurirDashboardController::class, 'index'])->name('dashboard');
        Route::get('/transactions/{transaction}', [KurirDashboardController::class, 'show'])->name('transactions.show');
        Route::get('/scan/{number}', [KurirDashboardController::class, 'scan'])->name('scan');
        Route::post('/transactions/{transaction}/update-status', [KurirDeliveryController::class, 'updateStatus'])->name('transactions.update-status');
    });
});

Route::redirect('/kurir', '/kurir/dashboard');
