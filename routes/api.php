<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\PosApiController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes v1
|--------------------------------------------------------------------------
|
| Token-based (Sanctum) REST API. All responses JSON.
| Public: auth endpoints + webhooks. Everything else requires Bearer token.
|
*/

// Public webhooks (payment gateways) — signature verified, no auth
Route::prefix('webhooks')->group(function () {
    Route::post('/midtrans', [PaymentWebhookController::class, 'midtrans'])->name('webhooks.midtrans');
    Route::post('/xendit', [PaymentWebhookController::class, 'xendit'])->name('webhooks.xendit');
});

Route::prefix('v1')->group(function () {
    // Auth (public)
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('api.auth.login');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,10')->name('api.auth.register');
    });

    // Protected endpoints (Sanctum token required)
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        });

        // Master data
        Route::apiResource('products', ProductController::class)->names([
            'index' => 'api.products.index',
            'store' => 'api.products.store',
            'show' => 'api.products.show',
            'update' => 'api.products.update',
            'destroy' => 'api.products.destroy',
        ]);

        Route::apiResource('customers', CustomerController::class)->names([
            'index' => 'api.customers.index',
            'store' => 'api.customers.store',
            'show' => 'api.customers.show',
            'update' => 'api.customers.update',
            'destroy' => 'api.customers.destroy',
        ]);

        Route::apiResource('categories', CategoryController::class)->names([
            'index' => 'api.categories.index',
            'store' => 'api.categories.store',
            'show' => 'api.categories.show',
            'update' => 'api.categories.update',
            'destroy' => 'api.categories.destroy',
        ]);

        Route::apiResource('warehouses', WarehouseController::class)->names([
            'index' => 'api.warehouses.index',
            'store' => 'api.warehouses.store',
            'show' => 'api.warehouses.show',
            'update' => 'api.warehouses.update',
            'destroy' => 'api.warehouses.destroy',
        ]);

        Route::apiResource('suppliers', SupplierController::class)->names([
            'index' => 'api.suppliers.index',
            'store' => 'api.suppliers.store',
            'show' => 'api.suppliers.show',
            'update' => 'api.suppliers.update',
            'destroy' => 'api.suppliers.destroy',
        ]);

        // POS (mobile kasir)
        Route::prefix('pos')->group(function () {
            // Shift
            Route::get('/shift', [PosApiController::class, 'currentShift'])->name('api.pos.shift');
            Route::post('/shift/open', [PosApiController::class, 'openShift'])->name('api.pos.shift.open');
            Route::post('/shift/close', [PosApiController::class, 'closeShift'])->name('api.pos.shift.close');

            // Products & scan
            Route::get('/products', [PosApiController::class, 'products'])->name('api.pos.products');
            Route::post('/products/scan', [PosApiController::class, 'scan'])->name('api.pos.products.scan');

            // Cart
            Route::get('/cart', [PosApiController::class, 'cart'])->name('api.pos.cart');
            Route::post('/cart', [PosApiController::class, 'addToCart'])->name('api.pos.cart.add');
            Route::put('/cart/{cart}', [PosApiController::class, 'updateCart'])->name('api.pos.cart.update');
            Route::delete('/cart/{cart}', [PosApiController::class, 'removeFromCart'])->name('api.pos.cart.remove');
            Route::delete('/cart', [PosApiController::class, 'clearCart'])->name('api.pos.cart.clear');

            // Hold / resume
            Route::post('/hold', [PosApiController::class, 'holdCart'])->name('api.pos.hold');
            Route::get('/holds', [PosApiController::class, 'heldCarts'])->name('api.pos.holds');
            Route::post('/holds/{holdId}/resume', [PosApiController::class, 'resumeHold'])->name('api.pos.holds.resume');
            Route::delete('/holds/{holdId}', [PosApiController::class, 'deleteHold'])->name('api.pos.holds.delete');

            // Checkout & transactions
            Route::post('/checkout', [PosApiController::class, 'checkout'])->name('api.pos.checkout');
            Route::get('/transactions', [PosApiController::class, 'transactions'])->name('api.pos.transactions');
            Route::get('/transactions/{transaction}', [PosApiController::class, 'transactionDetail'])->name('api.pos.transactions.show');
        });
    });
});
