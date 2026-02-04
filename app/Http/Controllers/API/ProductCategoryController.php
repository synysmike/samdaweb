<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ProductCategory;
use App\Models\ProductSubCategory;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;


class ProductCategoryController extends Controller
{
    /**
     * Get Product Categories
     * 
     * This endpoint is used to get all product categories.
     */
    public function getProductCategories()
    {
        try {
            $role = auth()->user()->roles->first()->name;
            $categories = ProductCategory::with('parent', 'children')
                ->when($role !== 'admin', function ($query) {
                    return $query->where('is_active', true);
                })
                ->orderBy('name', 'asc')
                ->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Product categories fetched successfully',
                'data' => $categories
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch product categories',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Show Product Category
     * 
     * This endpoint is used to show a product category.
     * 
     * @bodyParam id uuid Unique category ID. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
     */
    public function showProductCategory(Request $request)
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

            $category = ProductCategory::with('parent', 'children')->find($request->id);
            if (! $category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product category not found',
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Product category fetched successfully',
                'data' => $category
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to show product category',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Create or Update Product Category
     * 
     * This endpoint is used to manage product category data.
     * 
     * @bodyParam id uuid Unique category ID (optional). Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
     * @bodyParam name string Category name. Example: Electronics
     * @bodyParam is_active boolean Category active status. Example: true
     * @bodyParam parent_id uuid ID of the parent category if this is a sub-category. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
     */
    public function storeProductCategory(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|uuid',
                'name' => 'required|string|max:255',
                'is_active' => 'nullable|boolean',
                'parent_id' => 'nullable|uuid'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $upsert = ProductCategory::updateOrCreate([
                'id' => $request->id
            ], [
                'name' => $request->name,
                'is_active' => $request->is_active ?? true,
                'slug' => Str::slug($request->name)
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $upsert->wasRecentlyCreated ? 'Product category created successfully' : 'Product category updated successfully',
                'data' => $upsert
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create product category',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Product Category
     * 
     * This endpoint is used to delete a product category.
     * 
     * @bodyParam id uuid Unique category ID. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
     */
    public function deleteProductCategory(Request $request)
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
                'id' => 'required|uuid'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category = ProductCategory::with('parent', 'children')->find($request->id);
            if (! $category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product category not found',
                ], 404);
            }            

            if ($category->children->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product category has sub categories, please delete them first',
                ], 400);
            }

            if ($category->parent) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product category has a parent, please delete the parent first',
                ], 400);
            }

            $category->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Product category deleted successfully',
                'data' => $category
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete product category',
                'errors' => $th->getMessage()
            ], 500);
        }
    }


}
