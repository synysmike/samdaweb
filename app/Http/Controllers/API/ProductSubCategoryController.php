<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ProductSubCategory;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ProductSubCategoryController extends Controller
{
    public function getProductSubCategories(Request $request)
    {
        try {
            // @var string $category_id
            $category_id = $request->category_id;
            
            $role = auth()->user()->roles->first()->name;
            $query = ProductSubCategory::with('category')
                ->when($role !== 'admin', function ($q) {
                    return $q->where('is_active', true);
                })
                ->when($request->has('category_id'), function ($q) use ($request) {
                    return $q->where('category_id', $request->category_id);
                })
                ->orderBy('name', 'asc');

            $subCategories = $query->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Product sub categories fetched successfully',
                'data' => $subCategories
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch product sub categories',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function showProductSubCategory(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $subCategory = ProductSubCategory::find($request->id);
            if (!$subCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product sub category not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Product sub category fetched successfully',
                'data' => $subCategory
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to show product sub category',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function storeProductSubCategory(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|string|max:255',
                'category_id' => 'required|string|max:255|exists:product_categories,id',
                'name' => 'required|string|max:255',
                'is_active' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $upsert = ProductSubCategory::updateOrCreate(
                ['id' => $request->id],
                [
                    'category_id' => $request->category_id,
                    'name' => $request->name,
                    'is_active' => $request->is_active ?? true,
                    'slug' => Str::slug($request->name)
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => $upsert->wasRecentlyCreated ? 'Product sub category created successfully' : 'Product sub category updated successfully',
                'data' => $upsert->load('category')
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create product sub category',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function deleteProductSubCategory(Request $request)
    {
        try {
            $role = auth()->user()->roles->first()->name;
            if ($role !== 'admin') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'id' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $subCategory = ProductSubCategory::find($request->id);
            if (!$subCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product sub category not found',
                ], 404);
            }

            $subCategory->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Product sub category deleted successfully',
                'data' => $subCategory
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete product sub category',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
