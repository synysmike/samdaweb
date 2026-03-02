<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\PathParameter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HomeController extends Controller
{   
    /**
     * Get all product categories
     * 
     * This endpoint is used to get all product categories.
     * @return \Illuminate\Http\JsonResponse
     * @unauthenticated
     */    
    public function getProductCategories()
    {
        try {
            $productCategories = ProductCategory::with('children')->whereHas('children')->get();
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
     * Get all product sub categories
     * 
     * This endpoint is used to get all product sub categories for a given product category slug.
     *
     * @param string $productCategorySlug
     * @return \Illuminate\Http\JsonResponse
     * @unauthenticated
     */
    #[PathParameter('productCategorySlug', description: 'The slug of the product category.', type: 'string', example: 'category-slug')]
    public function getProductSubCategories($productCategorySlug)
    {
        try {
            $productSubCategories = ProductCategory::where('slug', $productCategorySlug)->with('children')->first();
            if (! $productSubCategories) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product sub categories not found',
                    'data' => []
                ], 404);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Product sub categories fetched successfully',
                'data' => $productSubCategories->children
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
     * Get all products filter
     *
     * This endpoint is used to get all products filter.
     *
     * @bodyParam price_sort string optional The price sort. Example: asc
     * @bodyParam price_from numeric optional The price from. Example: 100
     * @bodyParam price_to numeric optional The price to. Example: 1000
     * @bodyParam most_recent_sort boolean optional The most recent sort. Example: true
     * @bodyParam name_sort string optional The name sort. Example: asc
     * @bodyParam category_slug string optional The category slug. Example: category-slug
     * @bodyParam sub_category_slug string optional The sub category slug. Example: sub-category-slug
     * @bodyParam keyword string optional The keyword. Example: keyword
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @unauthenticated
     */
    #[BodyParameter('price_sort', description: 'Price sort.', type: 'string', example: 'asc')]
    #[BodyParameter('price_from', description: 'Price from.', type: 'numeric', example: 100)]
    #[BodyParameter('price_to', description: 'Price to.', type: 'numeric', example: 1000)]
    #[BodyParameter('most_recent_sort', description: 'Most recent sort.', type: 'boolean', example: true)]
    #[BodyParameter('name_sort', description: 'Name sort.', type: 'string', example: 'asc')]
    #[BodyParameter('category_slug', description: 'Category slug.', type: 'string', example: 'category-slug')]
    #[BodyParameter('keyword', description: 'Keyword.', type: 'string', example: 'keyword')]
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
                'keyword' => 'string|max:255',
                'limit' => 'numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $products = Product::with('images', 'category', 'variants');

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
            if ($request->has('limit')) {
                $products->limit($request->limit);
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

    /**
     * Get a product
     *
     * This endpoint is used to get a product by its slug.
     *
     * @param string $productSlug
     * @return \Illuminate\Http\JsonResponse
     * @unauthenticated
     */
    #[PathParameter('productSlug', description: 'The slug of the product.', type: 'string', example: 'product-slug')]
    public function getProduct($productSlug)
    {
        try {
            $product = Product::with('images', 'category', 'variants')->where('slug', $productSlug)->first();
            if (! $product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found',
                    'data' => []
                ], 404);
            }            

            $moreProducts = Product::with('images', 'category', 'subCategory')->where('category_id', $product->category_id)->where('id', '!=', $product->id)->limit(4)->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Product fetched successfully',
                'data' => $product,
                'more_products' => $moreProducts
            ]);
        }catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
                'data' => []
            ], 404);
        }
    }
}