<?php

use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart/items', [CartController::class, 'store']);
    Route::patch('cart/items/{cartItem}', [CartController::class, 'update']);
    Route::delete('cart/items/{cartItem}', [CartController::class, 'destroy']);

    Route::post('checkout', [CheckoutController::class, 'store']);

    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/pay', [OrderController::class, 'processPayment']);
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
});
