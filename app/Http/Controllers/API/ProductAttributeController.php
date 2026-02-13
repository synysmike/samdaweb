<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ProductAttribute;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\BodyParameter;

class ProductAttributeController extends Controller
{
    /**
     * Get all product attributes
     *
     * This endpoint is used to get all product attributes for a shop.
     *          
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $productAttributes = ProductAttribute::where('shop_id', $user->id)->get();
            if ($productAttributes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No product attributes found',
                    'data' => []
                ], 404);
            }
            return response()->json([
                'success' => true,
                'message' => 'Product attributes fetched successfully',
                'data' => $productAttributes
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get product attributes',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Show a product attribute
     *
     * This endpoint is used to show a product attribute for a shop.
     *
     * @bodyParam id uuid required The ID of the product attribute. Example: 123e4567-e89b-12d3-a456-426614174000
     * @return \Illuminate\Http\JsonResponse
     */
    #[BodyParameter('id', description: 'The ID of the product attribute.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function show(Request $request)
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

            $authUser = auth()->user();
            $productAttribute = ProductAttribute::where('shop_id', $authUser->id)->find($request->id);
            if (! $productAttribute) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product attribute not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product attribute fetched successfully',
                'data' => $productAttribute
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get product attribute',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Store (upsert) a product attribute
     *
     * This endpoint is used to create or update a product attribute for a shop. If the id is not provided, a new product attribute will be created. If the id is provided, the product attribute will be updated.
     *
     * @bodyParam id uuid optional The ID of the product attribute. If not provided, a new product attribute will be created. If provided, the product attribute will be updated. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam name string required The name of the product attribute. Example: Color
     * @return \Illuminate\Http\JsonResponse
     */
    #[BodyParameter('id', description: 'The ID of the product attribute. If not provided, a new product attribute will be created. If provided, the product attribute will be updated.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000', required: false)]
    #[BodyParameter('name', description: 'The name of the product attribute.', type: 'string', example: 'Color', required: true)]
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|uuid',
                'name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $authUser = auth()->user();
            $upsertData = ProductAttribute::updateOrCreate(
                [
                    'id' => $request->id,
                    'shop_id' => $authUser->id,
                ],
                [
                    'shop_id' => $authUser->id,
                    'name' => $request->name,
                    'code' => Str::slug($request->name),
                    'type' => 'select', // You may want to make this dynamic if needed
                    'is_active' => true,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => $upsertData->wasRecentlyCreated ? 'Product attribute stored successfully' : 'Product attribute updated successfully',
                'data' => $upsertData
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store product attribute',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a product attribute
     *
     * This endpoint is used to delete a product attribute for a shop.
     *
     * @bodyParam id uuid required The ID of the product attribute. Example: 123e4567-e89b-12d3-a456-426614174000
     * @return \Illuminate\Http\JsonResponse
     */
    #[BodyParameter('id', description: 'The ID of the product attribute.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function destroy(Request $request)
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

            $authUser = auth()->user();
            $productAttribute = ProductAttribute::where('shop_id', $authUser->id)->find($request->id);
            if (! $productAttribute) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product attribute not found',
                    'data' => []
                ], 404);
            }

            $productAttribute->delete();
            return response()->json([
                'success' => true,
                'message' => 'Product attribute deleted successfully',
                'data' => []
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product attribute',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
