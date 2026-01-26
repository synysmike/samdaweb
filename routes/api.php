<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ShopController;
use App\Http\Controllers\API\WorldController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\MasterPlanMembership;
use App\Http\Controllers\ProfileShippingController;
use App\Http\Controllers\API\ProductCategoryController;
use App\Http\Controllers\API\ProductSubCategoryController;

Route::group(['prefix' => 'v1', 'as' => 'api.'], function () {
    Route::group(['prefix' => 'auth', 'as' => 'auth.'], function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });


    Route::middleware('auth:sanctum')->group(function () {
        Route::group(['prefix' => 'world', 'as' => 'world.'], function () {
            Route::get('countries', [WorldController::class, 'countries'])->name('countries.get');
            Route::post('states', [WorldController::class, 'states'])->name('states.post');
            Route::post('cities', [WorldController::class, 'cities'])->name('cities.post');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('master')->name('master.')->group(function () {
            Route::get('membership-plans', [MasterPlanMembership::class, 'index'])->name('membership-plans.index');
        });

        Route::middleware('auth:sanctum')->prefix('settings')->name('settings.')->group(function () {
            Route::get('get-profile', [ProfileController::class, 'getProfile'])->name('profile.get');
            Route::post('update-profile', [ProfileController::class, 'updateProfile'])->name('profile.update');
            Route::post('location', [ProfileController::class, 'updateLocation'])->name('location.update');            

            // Shipping Address CRUD
            Route::get('shipping-address', [ProfileShippingController::class, 'index'])->name('shipping-address.index');
            Route::post('shipping-address/show', [ProfileShippingController::class, 'show'])->name('shipping-address.show');
            Route::post('shipping-address/store', [ProfileShippingController::class, 'store'])->name('shipping-address.store');            
            Route::post('shipping-address/delete', [ProfileShippingController::class, 'delete'])->name('shipping-address.delete');

            // Change Password
            Route::post('change-password', [ProfileController::class, 'changePassword'])->name('change-password.update');
            Route::post('delete-account', [ProfileController::class, 'deleteAccount'])->name('delete-account.delete');
        });

        Route::middleware('auth:sanctum')->prefix('shop')->name('shop.')->group(function () {
            Route::get('get-shop', [ShopController::class, 'getShop'])->name('shop.get');
            Route::post('store-shop', [ShopController::class, 'storeShop'])->name('shop.store');
        });

        Route::middleware('auth:sanctum')->prefix('product-category')->name('product-category.')->group(function () {
            Route::get('get-product-categories', [ProductCategoryController::class, 'getProductCategories'])->name('product-categories.get');
            Route::post('store-product-category', [ProductCategoryController::class, 'storeProductCategory'])->name('product-category.store');
            Route::post('delete-product-category', [ProductCategoryController::class, 'deleteProductCategory'])->name('product-category.delete');
        });

        Route::middleware('auth:sanctum')->prefix('product-sub-category')->name('product-sub-category.')->group(function () {
            Route::get('get-product-sub-categories', [ProductSubCategoryController::class, 'getProductSubCategories'])->name('product-sub-categories.get');
            Route::post('store-product-sub-category', [ProductSubCategoryController::class, 'storeProductSubCategory'])->name('product-sub-category.store');
            Route::post('delete-product-sub-category', [ProductSubCategoryController::class, 'deleteProductSubCategory'])->name('product-sub-category.delete');
        });

    });
});

