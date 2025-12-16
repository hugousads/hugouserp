<?php

use App\Http\Controllers\Api\V1\CustomersController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\OrdersController;
use App\Http\Controllers\Api\V1\POSController;
use App\Http\Controllers\Api\V1\ProductsController;
use App\Http\Controllers\Api\V1\WebhooksController;
use App\Http\Controllers\Internal\DiagnosticsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::get('/health', function () {
        return new \Illuminate\Http\JsonResponse([
            'success' => true,
            'message' => 'API is running',
            'version' => 'v1',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Internal diagnostics endpoint
    Route::prefix('internal')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/diagnostics', [DiagnosticsController::class, 'index']);
    });

    // Branch-scoped API routes with model binding and full middleware stack
    Route::prefix('branches/{branch}')
        ->middleware(['api-core', 'api-auth', 'api-branch', 'throttle:120,1'])
        ->scopeBindings()
        ->group(function () {
        // Load all branch-specific route files
        require __DIR__.'/api/branch/common.php';
        require __DIR__.'/api/branch/hrm.php';
        require __DIR__.'/api/branch/motorcycle.php';
        require __DIR__.'/api/branch/rental.php';
        require __DIR__.'/api/branch/spares.php';
        require __DIR__.'/api/branch/wood.php';

        // Authenticated POS session management routes (consolidated into branch scope)
        Route::prefix('pos')->group(function () {
            Route::get('/session', [POSController::class, 'getCurrentSession']);
            Route::post('/session/open', [POSController::class, 'openSession']);
            Route::post('/session/{session}/close', [POSController::class, 'closeSession']);
            Route::get('/session/{session}/report', [POSController::class, 'getSessionReport']);
        });
    });

    Route::middleware(['store.token', 'throttle:api'])->group(function () {
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductsController::class, 'index']);
            Route::post('/', [ProductsController::class, 'store']);
            Route::get('/external/{externalId}', [ProductsController::class, 'byExternalId']);
            Route::get('/{id}', [ProductsController::class, 'show']);
            Route::put('/{id}', [ProductsController::class, 'update']);
            Route::delete('/{id}', [ProductsController::class, 'destroy']);
        });

        Route::prefix('inventory')->group(function () {
            Route::get('/stock', [InventoryController::class, 'getStock']);
            Route::post('/update-stock', [InventoryController::class, 'updateStock']);
            Route::post('/bulk-update-stock', [InventoryController::class, 'bulkUpdateStock']);
            Route::get('/movements', [InventoryController::class, 'getMovements']);
        });

        Route::prefix('orders')->group(function () {
            Route::get('/', [OrdersController::class, 'index']);
            Route::post('/', [OrdersController::class, 'store']);
            Route::get('/external/{externalId}', [OrdersController::class, 'byExternalId']);
            Route::get('/{id}', [OrdersController::class, 'show']);
            Route::patch('/{id}/status', [OrdersController::class, 'updateStatus']);
        });

        Route::prefix('customers')->group(function () {
            Route::get('/', [CustomersController::class, 'index']);
            Route::post('/', [CustomersController::class, 'store']);
            Route::get('/email/{email}', [CustomersController::class, 'byEmail']);
            Route::get('/{id}', [CustomersController::class, 'show']);
            Route::put('/{id}', [CustomersController::class, 'update']);
            Route::delete('/{id}', [CustomersController::class, 'destroy']);
        });
    });

    Route::prefix('webhooks')->group(function () {
        Route::post('/shopify/{storeId}', [WebhooksController::class, 'handleShopify'])->name('webhooks.shopify');
        Route::post('/woocommerce/{storeId}', [WebhooksController::class, 'handleWooCommerce'])->name('webhooks.woocommerce');
        Route::post('/laravel/{storeId}', [WebhooksController::class, 'handleLaravel'])->name('webhooks.laravel');
    });

    // Auth routes (public routes + authenticated routes)
    Route::middleware(['api-core'])->group(function () {
        require __DIR__.'/api/auth.php';
    });

    // Notifications routes (requires api-core + api-auth)
    Route::middleware(['api-core', 'api-auth', 'impersonate'])->group(function () {
        require __DIR__.'/api/notifications.php';
    });

    // Admin routes (requires api-core + api-auth + impersonate)
    Route::middleware(['api-core', 'api-auth', 'impersonate'])->group(function () {
        require __DIR__.'/api/admin.php';
    });

});
