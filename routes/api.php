<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;

Route::group(['prefix' => 'v1', 'as' => 'api.'], function () {
    Route::group(['prefix' => 'auth', 'as' => 'auth.'], function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });
});

Route::group(['prefix' => 'v1/master', 'as' => 'api.master.'], function () {
    
});

