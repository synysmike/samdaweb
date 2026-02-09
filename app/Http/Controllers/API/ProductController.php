<?php

namespace App\Http\Controllers\API;

use App\Models\Shop;
use App\Models\Product;
use App\Models\TempProduct;
use Illuminate\Support\Str;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use App\Services\ImageService;
use App\Services\WorldService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\BodyParameter;

class ProductController extends Controller
{

    public function checkShopVerification()
    {
        try {
            $user = auth()->user();
            $checkShopExists = Shop::where('id', $user->id)->where('valid_verification', true)->first();
            if (! $checkShopExists) {
                return [
                    'success' => false,
                    'message' => 'Shop not found or not verified. Please create a shop first and wait for verification.',
                ];
            }
            return [
                'success' => true,
                'message' => 'Shop verified successfully',
            ];
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'message' => 'Failed to check shop verification',
                'errors' => $th->getMessage()
            ];
        }
    }
    /**
     * Get all products
     *
     * This endpoint is used to get all products for a shop.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProducts()
    {
        try {
            $user = auth()->user();

            $checkShopVerification = $this->checkShopVerification();
            if (! $checkShopVerification['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $checkShopVerification['message'],
                    'errors' => $checkShopVerification['errors'] ?? null
                ], 404);
            }

            $products = Product::with('category.parent', 'images')->where('shop_id', $user->id)->get();
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

    /**
     * Show a product
     *
     * This endpoint is used to show a product for a shop.
     *
     * @bodyParam id uuid required The ID of the product. Example: 123e4567-e89b-12d3-a456-426614174000
     * @return \Illuminate\Http\JsonResponse
     */
    #[BodyParameter('id', description: 'The ID of the product.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function showProduct(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $checkShopVerification = $this->checkShopVerification();
            if (! $checkShopVerification['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $checkShopVerification['message'],
                    'errors' => $checkShopVerification['errors'] ?? null
                ], 404);
            }

            $product = Product::with('category', 'subCategory', 'images')->find($request->id);
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product fetched successfully',
                'data' => $product
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to show product',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Store a product
     *
     * This endpoint is used to store a product for a shop.
     *
     * @bodyParam id uuid optional The ID of the product. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam title string required The title of the product. Example: Product Title
     * @return \Illuminate\Http\JsonResponse
     */
    #[BodyParameter('id', description: 'The ID of the product. If not provided, a new product will be created.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('title', description: 'The title of the product.', type: 'string', example: 'Product Title')]
    #[BodyParameter('description', description: 'The description of the product.', type: 'string', example: 'Product Description')]
    #[BodyParameter('category_id', description: 'The ID of the category.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('is_active', description: 'The status of the product.', type: 'boolean', example: true)]
    #[BodyParameter('is_visible', description: 'The visibility of the product.', type: 'boolean', example: true)]
    #[BodyParameter('country_id', description: 'The ID of the country.', type: 'integer', example: 1)]
    #[BodyParameter('state_id', description: 'The ID of the state.', type: 'integer', example: 1)]
    #[BodyParameter('city_id', description: 'The ID of the city.', type: 'integer', example: 1)]
    #[BodyParameter('min_price', description: 'The minimum price of the product.', type: 'numeric', example: 100)]
    #[BodyParameter('max_price', description: 'The maximum price of the product.', type: 'numeric', example: 100)]
    public function storeProduct(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|uuid',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'required|uuid',
                'is_active' => 'boolean',
                'is_visible' => 'boolean',
                'country_id' => 'nullable|integer',
                'state_id' => 'nullable|integer',
                'city_id' => 'nullable|integer',
                'min_price' => 'nullable|numeric',
                'max_price' => 'nullable|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();

            $checkShopVerification = $this->checkShopVerification();
            if (! $checkShopVerification['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $checkShopVerification['message'],
                    'errors' => $checkShopVerification['errors'] ?? null
                ], 403);
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
                'is_active' => $request->has('is_active') ? $request->is_active : true,
                'is_visible' => $request->has('is_visible') ? $request->is_visible : true,
                'country_id' => $request->country_id,
                'country_name' => $country_name,
                'state_id' => $request->state_id,
                'state_name' => $state_name,
                'city_id' => $request->city_id,
                'city_name' => $city_name,
                'min_price' => $request->min_price,
                'max_price' => $request->max_price,
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

    /**
     * Delete a product
     *
     * This endpoint is used to delete a product for a shop.
     *
     * @bodyParam id uuid required The ID of the product. Example: 123e4567-e89b-12d3-a456-426614174000
     * @return \Illuminate\Http\JsonResponse
     */
    #[BodyParameter('id', description: 'The ID of the product.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function deleteProduct(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $checkShopVerification = $this->checkShopVerification();
            if (! $checkShopVerification['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $checkShopVerification['message'],
                    'errors' => $checkShopVerification['errors'] ?? null
                ], 403);
            }

            $user = auth()->user();

            // Find product
            $product = Product::with('images')->where('id', $request->id)->where('shop_id', $user->id)->first();
            
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            // Back up to TempProduct
            $tempProductData = $product->toArray();
            unset($tempProductData['created_at'], $tempProductData['updated_at']); // optional, keep only what you want
            $tempProductData['id'] = $product->id; // explicitly set id

            // Copy images if any exist (optional, if you want to backup images too)
            // You can adapt as needed, this only backs up the product fields
            $tempProduct = TempProduct::create($tempProductData);

            // Now delete product
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted and backed up to temp_products.',
                'data' => $tempProduct
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
