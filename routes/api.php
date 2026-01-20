<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\MasterPlanMembership;
use App\Http\Controllers\ProfileShippingController;

Route::group(['prefix' => 'v1', 'as' => 'api.'], function () {
    Route::group(['prefix' => 'auth', 'as' => 'auth.'], function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('master')->name('master.')->group(function () {
            Route::get('membership-plans', [MasterPlanMembership::class, 'index'])->name('membership-plans.index');
        });
        
        Route::middleware('auth:sanctum')->prefix('settings')->name('settings.')->group(function () {
            Route::get('edit-profile', [ProfileController::class, 'editProfile'])->name('profile.edit');
            Route::post('update-profile', [ProfileController::class, 'updateProfile'])->name('profile.update');
            Route::post('location', [ProfileController::class, 'updateLocation'])->name('location.update');
            Route::post('shipping-address', [ProfileShippingController::class, 'index'])->name('shipping-address.index');
            
            // Shipping Address CRUD
            Route::post('shipping-address/store', [ProfileShippingController::class, 'store'])->name('shipping-address.store');
            Route::post('shipping-address/show', [ProfileShippingController::class, 'show'])->name('shipping-address.show');
            Route::post('shipping-address/delete', [ProfileShippingController::class, 'delete'])->name('shipping-address.delete');

            // Change Password
            Route::post('change-password', [ProfileController::class, 'changePassword'])->name('change-password.update');            
            Route::post('delete-account', [ProfileController::class, 'deleteAccount'])->name('delete-account.delete');
        });

    });
});

