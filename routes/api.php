<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware('auth.jwt')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    Route::post('refresh', [AuthController::class, 'refresh'])
        ->middleware('auth.jwt.refresh');
});

Route::middleware('auth.jwt')->group(function () {
    Route::apiResource('orders', OrderController::class);
    Route::get('orders/{order}/payments', [PaymentController::class, 'orderPayments']);

    Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show']);
});
