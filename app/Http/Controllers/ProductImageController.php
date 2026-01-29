<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use App\Services\ImageService;
use Illuminate\Support\Facades\Validator;

class ProductImageController extends Controller
{
    public function getProductImages(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|uuid'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $productImages = ProductImage::where('product_id', $request->product_id)->get();
            return response()->json([
                'success' => true,
                'message' => 'Product images fetched successfully',
                'data' => $productImages
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get product images',
                'error' => $th->getMessage()
            ], 500);
        }
    }
    public function store(Request $request)
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
