<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ProductAttribute;
use App\Http\Controllers\Controller;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\BodyParameter;

class ProductAttributeValueController extends Controller
{
    /**
     * Get all product attribute values
     *
     * This endpoint is used to get all product attribute values for a shop.
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $validator = Validator::make($request->all(), [
                'product_attribute_id' => 'required|uuid|exists:product_attributes,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $productAttributeValues = ProductAttributeValue::where('product_attribute_id', $request->product_attribute_id)->get();

            if ($productAttributeValues->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No product attribute values found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product attribute values fetched successfully',
                'data' => $productAttributeValues
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get product attribute values',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Show a product attribute value
     *
     * This endpoint is used to show a product attribute value.
     *
     * @bodyParam id uuid required The ID of the product attribute value. Example: 123e4567-e89b-12d3-a456-426614174000
     */
    #[BodyParameter('id', description: 'The ID of the product attribute value.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
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
            $productAttributeValue = ProductAttributeValue::whereHas('attribute', function ($query) use ($authUser) {
                $query->where('shop_id', $authUser->id);
            })->with('attribute')->find($request->id);

            if (! $productAttributeValue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product attribute value not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product attribute value fetched successfully',
                'data' => $productAttributeValue
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get product attribute value',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Store (upsert) a product attribute value
     *
     * This endpoint is used to create or update a product attribute value.
     *
     * @bodyParam id uuid optional The ID of the product attribute value. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam product_attribute_id uuid required The ID of the product attribute. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam value string required The value. Example: Red
     * @bodyParam code string optional The code. Example: red
     * @bodyParam is_active boolean optional Whether the value is active. Example: true
     * @bodyParam sort_order integer optional The sort order. Example: 1
     */
    #[BodyParameter('id', description: 'The ID of the product attribute value.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('product_attribute_id', description: 'The ID of the product attribute.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('value', description: 'The value.', type: 'string', example: 'Red')]
    #[BodyParameter('is_active', description: 'Whether the value is active.', type: 'boolean', example: true)]
    #[BodyParameter('sort_order', description: 'The sort order.', type: 'integer', example: 1)]
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|uuid',
                'product_attribute_id' => 'required|uuid|exists:product_attributes,id',
                'value' => 'required|string|max:255|unique:product_attribute_values,value,NULL,id,product_attribute_id,'.$request->product_attribute_id,
                'is_active' => 'nullable|boolean',
                'sort_order' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $authUser = auth()->user();

            // Ensure the attribute belongs to the user's shop
            $attribute = ProductAttribute::where('shop_id', $authUser->id)->find($request->product_attribute_id);
            if (! $attribute) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product attribute not found or does not belong to your shop',
                    'data' => []
                ], 404);
            }

            $code = Str::slug($request->value);
            $isActive = $request->has('is_active') ? (bool) $request->is_active : true;
            $sortOrder = $request->sort_order ?? 0;

            $upsertPayload = [
                'product_attribute_id' => $request->product_attribute_id,
                'value' => $request->value,
                'code' => $code,
                'is_active' => $isActive,
                'sort_order' => $sortOrder,
            ];

            $upsertData = $request->filled('id')
                ? ProductAttributeValue::updateOrCreate(['id' => $request->id], $upsertPayload)
                : ProductAttributeValue::create($upsertPayload);

            return response()->json([
                'success' => true,
                'message' => $upsertData->wasRecentlyCreated ? 'Product attribute value stored successfully' : 'Product attribute value updated successfully',
                'data' => $upsertData->load('attribute')
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store product attribute value',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a product attribute value
     *
     * This endpoint is used to delete a product attribute value.
     *
     * @bodyParam id uuid required The ID of the product attribute value. Example: 123e4567-e89b-12d3-a456-426614174000
     */
    #[BodyParameter('id', description: 'The ID of the product attribute value.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
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
            $productAttributeValue = ProductAttributeValue::whereHas('attribute', function ($query) use ($authUser) {
                $query->where('shop_id', $authUser->id);
            })->find($request->id);

            if (! $productAttributeValue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product attribute value not found',
                    'data' => []
                ], 404);
            }

            $productAttributeValue->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product attribute value deleted successfully',
                'data' => []
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product attribute value',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    private function getSortOrder($productAttributeId)
    {
        $lastValue = ProductAttributeValue::where('product_attribute_id', $productAttributeId)
            ->orderByDesc('sort_order')
            ->first();
        return $lastValue ? $lastValue->sort_order + 1 : 1;
    }
}
