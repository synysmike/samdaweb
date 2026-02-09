<?php

namespace App\Http\Controllers\API;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\ProductAttributeSet;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\BodyParameter;

class ProductAttributeSetController extends Controller
{
    /**
     * Get product attribute sets
     * 
     * This endpoint is used to get the product attribute sets for a product.
     */
    #[BodyParameter('product_id', description: 'The ID of the product.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function get(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|uuid',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            
            $product = Product::with('attributeSets.productAttribute.productAttributeValues')->where('id', $request->product_id)->where('shop_id', $user->id)->first();
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            if ($product->attributeSets->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No product attribute sets found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product attribute sets fetched successfully',
                'data' => $product->attributeSets
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get product attribute sets',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Store product attribute set
     * 
     * This endpoint is used to store a product attribute set. 
     */
    #[BodyParameter('product_id', description: 'The ID of the product.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('product_attribute_id', description: 'The ID of the product attribute.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|uuid',
                'product_attribute_id' => 'required|uuid',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();

            $product = Product::where('id', $request->product_id)->where('shop_id', $user->id)->first();
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $productAttributeSet = ProductAttributeSet::where('product_id', $request->product_id)->where('product_attribute_id', $request->product_attribute_id)->first();
            if ($productAttributeSet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product attribute set already exists',
                ], 400);
            }

            $productAttributeSet = ProductAttributeSet::create([
                'product_id' => $request->product_id,
                'product_attribute_id' => $request->product_attribute_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product attribute set stored successfully',
                'data' => $productAttributeSet
            ], 200);
        }catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store product attribute set',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Delete product attribute set
     * 
     * This endpoint is used to delete a product attribute set.
     */
    #[BodyParameter('product_id', description: 'The ID of the product.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('product_attribute_id', description: 'The ID of the product attribute.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function destroy(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|uuid',
                'product_attribute_id' => 'required|uuid',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();

            $product = Product::where('id', $request->product_id)->where('shop_id', $user->id)->first();
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $productAttributeSet = ProductAttributeSet::where('product_id', $request->product_id)->where('product_attribute_id', $request->product_attribute_id)->first();
            if (! $productAttributeSet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product attribute set not found',
                ], 404);
            }

            $productAttributeSet->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product attribute set deleted successfully',
            ], 200);

        }catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product attribute set',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
