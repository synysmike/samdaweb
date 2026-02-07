<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\HomeController;
use App\Http\Controllers\API\ShopController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\API\WorldController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\API\MasterPlanMembership;
use App\Http\Controllers\ProfileShippingController;
use App\Http\Controllers\API\ProductCategoryController;
use App\Http\Controllers\API\ProductAttributeController;
use App\Http\Controllers\API\ProductSubCategoryController;

Route::group(['prefix' => 'v1', 'as' => 'api.'], function () {
    // Public routes
    Route::group(['prefix' => 'auth', 'as' => 'auth.'], function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });

    // public routes for frontend
    Route::group(['prefix' => 'frontend', 'as' => 'frontend.'], function () {        
        Route::post('products-filter', [HomeController::class, 'getProductsFilter'])->name('products.filter');
        Route::get('products/show/{productSlug}', [HomeController::class, 'getProduct'])->name('products.show');
        

        Route::get('product-categories', [HomeController::class, 'getProductCategories'])->name('product-categories.get');
        Route::get('product-sub-categories/{productCategorySlug}', [HomeController::class, 'getProductSubCategories'])->name('product-sub-categories.get');
    });    

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('master')->name('master.')->group(function () {
            Route::get('membership-plans', [MasterPlanMembership::class, 'index'])->name('membership-plans.index');
        });

        Route::prefix('world')->name('world.')->group(function () {
            Route::get('countries', [WorldController::class, 'countries'])->name('countries.get');
            Route::post('states', [WorldController::class, 'states'])->name('states.post');
            Route::post('cities', [WorldController::class, 'cities'])->name('cities.post');
        });

        Route::prefix('settings')->name('settings.')->group(function () {
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

        Route::prefix('shop')->name('shop.')->group(function () {
            Route::get('get-shop', [ShopController::class, 'getShop'])->name('shop.get');
            Route::post('store-shop', [ShopController::class, 'storeShop'])->name('shop.store');
        });

        Route::prefix('product')->name('product.')->group(function () {
            Route::get('get', [ProductController::class, 'getProducts'])->name('products.get');
            Route::post('show', [ProductController::class, 'showProduct'])->name('product.show');
            Route::post('store', [ProductController::class, 'storeProduct'])->name('product.store');
            Route::post('delete', [ProductController::class, 'deleteProduct'])->name('product.delete');
        });

        Route::prefix('product-attribute')->name('product-attribute.')->group(function () {
            Route::get('get', [ProductAttributeController::class, 'index'])->name('product-attribute.get');
            Route::post('show', [ProductAttributeController::class, 'show'])->name('product-attribute.show');
            Route::post('store', [ProductAttributeController::class, 'store'])->name('product-attribute.store');
            Route::post('delete', [ProductAttributeController::class, 'destroy'])->name('product-attribute.delete');
        });

        Route::prefix('product-image')->name('product-image.')->group(function () {
            Route::post('get', [ProductImageController::class, 'getProductImages'])->name('product-image.get');
            Route::post('store', [ProductImageController::class, 'store'])->name('product-image.store');
        });

        Route::prefix('product-category')->name('product-category.')->group(function () {
            Route::get('get', [ProductCategoryController::class, 'getProductCategories'])->name('product-cat    egories.get');
            Route::post('show', [ProductCategoryController::class, 'showProductCategory'])->name('product-category.show');
            Route::post('store', [ProductCategoryController::class, 'storeProductCategory'])->name('product-category.store');
            Route::post('delete', [ProductCategoryController::class, 'deleteProductCategory'])->name('product-category.delete');
        });        

        Route::group(['prefix' => 'wishlist', 'as' => 'wishlist.'], function () {
            Route::post('add', [WishlistController::class, 'add'])->name('add');
            Route::post('remove', [WishlistController::class, 'remove'])->name('remove');
            Route::post('show', [WishlistController::class, 'show'])->name('show');
        });

    });
});

