<?php

use App\Http\Controllers\Api\V1\Admin\AvailabilityController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\MenuItemController;
use App\Http\Controllers\Api\V1\Admin\ModifierController;
use App\Http\Controllers\Api\V1\Admin\ModifierOptionController;
use App\Http\Controllers\Api\V1\Admin\SoldoutController;
use App\Http\Controllers\Api\V1\CatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/catalog', [CatalogController::class, 'index']);

    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
        Route::apiResource('categories', CategoryController::class);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

        Route::apiResource('menu-items', MenuItemController::class);
        Route::post('menu-items/{item}/modifiers/{modifier}', [MenuItemController::class, 'attachModifier']);
        Route::delete('menu-items/{item}/modifiers/{modifier}', [MenuItemController::class, 'detachModifier']);

        Route::apiResource('modifiers', ModifierController::class);

        Route::post('modifiers/{modifier}/options', [ModifierOptionController::class, 'store']);
        Route::patch('modifiers/{modifier}/options/{option}', [ModifierOptionController::class, 'update']);
        Route::delete('modifiers/{modifier}/options/{option}', [ModifierOptionController::class, 'destroy']);

        Route::post('menu-items/{item}/availabilities', [AvailabilityController::class, 'store']);
        Route::delete('menu-items/{item}/availabilities/{availability}', [AvailabilityController::class, 'destroy']);

        Route::post('menu-items/{item}/soldout', [SoldoutController::class, 'store']);
        Route::delete('menu-items/{item}/soldout/{soldout}', [SoldoutController::class, 'destroy']);
    });
});
