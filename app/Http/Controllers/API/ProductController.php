<?php

namespace App\Http\Controllers\API;

use App\Models\Shop;
use App\Models\Product;
use Illuminate\Support\Str;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use App\Services\ImageService;
use App\Services\WorldService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function getProducts()
    {
        try {
            $user = auth()->user();
            $products = Product::with('category', 'subCategory','images')->where('shop_id', $user->id)->get();
            return response()->json([
                'success' => true,
                'message' => 'Products fetched successfully',
                'data' => $products
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get products',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function storeProduct(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|uuid',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'required|uuid',
                'sub_category_id' => 'required|uuid',
                'is_active' => 'nullable|boolean',
                'is_visible' => 'nullable|boolean',
                'stock' => 'required|integer|max:99',
                'sku' => 'nullable|string|max:50',
                'price' => 'required|numeric|min:0',
                'discount_price' => 'nullable|numeric',
                'country_id' => 'nullable|integer',
                'state_id' => 'nullable|integer',
                'city_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();

            $checkShop = Shop::where('id', $user->id)->first();
            if (! $checkShop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shop not found',
                ], 404);
            }

            $slug = Str::slug($request->title);
            $checkSlug = Product::where('slug', $slug)->first();
            if ($checkSlug) {
                $slug = $slug.'-'.Str::random(5);
            }

            $worldService = new WorldService();
            $country_name = NULL;
            $state_name = NULL;
            $city_name = NULL;
            
            if ($request->country_id) {
                $country = $worldService->getCountryById($request->country_id);
                $country_name = $country->name;
            }
            if ($request->state_id) {
                $state = $worldService->getStateById($request->state_id);
                $state_name = $state->name;
            }
            if ($request->city_id) {
                $city = $worldService->getCityById($request->city_id);
                $city_name = $city->name;
            }

            $product = Product::updateOrCreate([
                'id' => $request->id,
                'shop_id' => $user->id
            ], [
                'title' => $request->title,
                'slug' => $slug,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'sub_category_id' => $request->sub_category_id,
                'is_active' => $request->boolean('is_active') ?? false,
                'is_visible' => $request->boolean('is_visible') ?? false,
                'stock' => $request->stock,
                'sku' => $request->sku,
                'price' => $request->price,
                'discount_price' => $request->discount_price,
                'country_id' => $request->country_id,
                'country_name' => $country_name,
                'state_id' => $request->state_id,
                'state_name' => $state_name,
                'city_id' => $request->city_id,
                'city_name' => $city_name,
            ]);

            return response()->json([
                'success' => true,
                'message' => $product->wasRecentlyCreated ? 'Product stored successfully' : 'Product updated successfully',
                'data' => $product
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store product',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function storeProductImage(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|uuid',
                'product_id' => 'required|uuid',
                // @example Using base64 string
                'image' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            $product = Product::find($request->product_id);
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $productImage = NULL;
            $getProductImage = ProductImage::where('product_id', $product->id)->first();
            if ($getProductImage) {
                $productImage = $getProductImage->file_path;
            }

            $imageService = new ImageService();
            if (! $imageService->isValidBase64Image($request->image)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid base64 image for cover_image',
                ], 422);
            }

            $imagePath = $imageService->convertBase64ToImage($request->image, 'products', $productImage);

            if ($imagePath) {
                $productImage = ProductImage::updateOrCreate([
                    'id' => $request->id,
                    'product_id' => $product->id
                ], [
                    'file_path' => $imagePath,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $productImage->wasRecentlyCreated ? 'Product image stored successfully' : 'Product image updated successfully',
                'data' => $productImage
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store product image',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
