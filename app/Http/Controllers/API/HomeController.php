<?php

namespace App\Http\Controllers\API;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\ProductCategory;
use App\Models\ProductSubCategory;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class HomeController extends Controller
{
    /**
     * @unauthenticated
     */
    public function getProducts()
    {
        try {
            $products = Product::all();
            return response()->json([
                'status' => 'success',
                'message' => 'Products fetched successfully',
                'data' => $products
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Products not found',
                'data' => []
            ], 404);
        }
    }

    /**
     * @unauthenticated
     */
    public function getProductCategories()
    {
        try {
            $productCategories = ProductCategory::all();
            return response()->json([
                'status' => 'success',
                'message' => 'Product categories fetched successfully',
                'data' => $productCategories
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product categories not found',
                'data' => []
            ], 404);
        }
    }

    /**
     * @unauthenticated
     */
    public function getProductSubCategories($productCategorySlug)
    {
        try {
            $productCategory = ProductCategory::where('slug', $productCategorySlug)->first();
            if (! $productCategory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product category not found',
                    'data' => []
                ], 404);
            }
            $productSubCategories = $productCategory->productSubCategories;
            return response()->json([
                'status' => 'success',
                'message' => 'Product sub categories fetched successfully',
                'data' => $productSubCategories
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product sub categories not found',
                'data' => []
            ], 404);
        }
    }

    /**
     * @unauthenticated
     */


    public function getProductsFilter(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'price_sort' => 'string|in:asc,desc',
                'price_from' => 'numeric',
                'price_to' => 'numeric',
                'most_recent_sort' => 'boolean',
                'name_sort' => 'string|in:asc,desc',
                'category_slug' => 'string|max:255|exists:product_categories,slug',
                'sub_category_slug' => 'string|max:255|exists:product_sub_categories,slug',
                'keyword' => 'string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $products = Product::with('images');

            if ($request->has('category_slug')) {
                $productCategory = ProductCategory::where('slug', $request->category_slug)->first();
                if (! $productCategory) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Product category not found',
                        'data' => []
                    ], 404);
                }
                $products->where('product_category_id', $productCategory->id);
            }
            if ($request->has('sub_category_slug')) {
                $productSubCategory = ProductSubCategory::where('slug', $request->sub_category_slug)->first();
                if (! $productSubCategory) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Product sub category not found',
                        'data' => []
                    ], 404);
                }
                $products->where('product_sub_category_id', $productSubCategory->id);
            }

            if ($request->has('price_sort')) {
                $products->orderBy('price', $request->price_sort);
            }
            if ($request->has('price_from')) {
                $products->where('price', '>=', $request->price_from);
            }
            if ($request->has('price_to')) {
                $products->where('price', '<=', $request->price_to);
            }
            if ($request->has('most_recent_sort')) {
                $products->orderBy('created_at', 'desc');
            }
            if ($request->has('name_sort')) {
                $products->orderBy('title', $request->name_sort);
            }
            if ($request->has('keyword')) {
                $products->where('title', 'like', '%' . $request->keyword . '%')
                ->orWhere('description', 'like', '%' . $request->keyword . '%')
                ->orWhere('sku', 'like', '%' . $request->keyword . '%');
            }
            $products = $products->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Products fetched successfully',
                'data' => $products
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
                'data' => []
            ], 404);
        }
    }
}