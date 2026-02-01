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
    public function getProductCategories()
    {
        try {
            $role = auth()->user()->roles->first()->name;
            $categories = ProductCategory::when($role !== 'admin', function ($query) {
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

            $category = ProductCategory::find($request->id);
            if (!$category) {
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

    public function storeProductCategory(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|string|max:255',
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
                'id' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category = ProductCategory::find($request->id);
            if (!$category) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product category not found',
                ], 404);
            }

            $subCategories = ProductSubCategory::where('category_id', $request->id)->get();
            if ($subCategories->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product sub categories found, please delete them first',
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
