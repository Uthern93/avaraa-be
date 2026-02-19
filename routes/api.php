<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InboundController;
use App\Http\Controllers\Api\ItemMasterController;
use App\Http\Controllers\Api\RackController;
use App\Http\Controllers\Api\DispatchRequestController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:api')->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // Dropdowns
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('warehouses', [WarehouseController::class, 'index']);
    Route::get('warehouses/{id}/layout', [WarehouseController::class, 'layout']);
    Route::get('racks', [RackController::class, 'index']);
    Route::get('racks/{rackId}/bins', [RackController::class, 'bins']);

    // Item Master
    Route::prefix('items')->group(function () {
        Route::get('/', [ItemMasterController::class, 'index']);
        Route::post('/', [ItemMasterController::class, 'store']);
        Route::get('/{id}', [ItemMasterController::class, 'show']);
        Route::post('/{id}', [ItemMasterController::class, 'update']);
        Route::delete('/{id}', [ItemMasterController::class, 'destroy']);
    });

    // Inbound Applications
    Route::prefix('inbound-applications')->group(function () {
        Route::get('/', [InboundController::class, 'index']);
        Route::post('/', [InboundController::class, 'store']);
        Route::get('/{id}', [InboundController::class, 'show']);
        Route::put('/{id}/verify', [InboundController::class, 'verify']);
        Route::put('/{id}/items/{itemId}/putaway', [InboundController::class, 'putaway']);
    });

    // Stock Movements (stored procedure report)
    Route::get('stock-movements', [StockMovementController::class, 'index']);

    // Dispatch Requests
    Route::prefix('dispatch-requests')->group(function () {
        Route::get('/', [DispatchRequestController::class, 'index']);
        Route::post('/', [DispatchRequestController::class, 'store']);
        Route::get('/{id}', [DispatchRequestController::class, 'show']);
        Route::put('/{id}/start-picking', [DispatchRequestController::class, 'startPicking']);
        Route::put('/{id}/complete-picking', [DispatchRequestController::class, 'completePicking']);
        Route::put('/{id}/start-packing', [DispatchRequestController::class, 'startPacking']);
        Route::put('/{id}/complete-packing', [DispatchRequestController::class, 'completePacking']);
        Route::post('/{id}/dispatch', [DispatchRequestController::class, 'dispatch']);
    });
});
