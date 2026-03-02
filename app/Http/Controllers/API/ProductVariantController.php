<?php

namespace App\Http\Controllers\API;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Services\ImageService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\ProductVariantOption;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\BodyParameter;

class ProductVariantController extends Controller
{
    /**
     * Get a product variant
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[BodyParameter('product_id', description: 'Product ID.', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function get(Request $request)
    {
        try {
            $user = auth()->user();

            $product = Product::with('variants.options')->where('id', $request->input('product_id'))->where('shop_id', $user->id)->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                    'errors' => 'Product not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product found',
                'data' => $product
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new product variant
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[BodyParameter('product_id', description: 'Product ID.', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('product_attribute_value_ids', description: 'Product attribute value IDs.', type: 'array', example: ['123e4567-e89b-12d3-a456-426614174000', '123e4567-e89b-12d3-a456-426614174000', '123e4567-e89b-12d3-a456-426614174000'])]
    #[BodyParameter('price', description: 'Price.', type: 'numeric', example: 100)]
    #[BodyParameter('stock', description: 'Stock.', type: 'integer', example: 100)]
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|uuid',
                'product_attribute_value_ids' => 'required|array',
                'price' => 'required|numeric',
                'stock' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();

            $array_product_attribute_value_ids = $request->input('product_attribute_value_ids');

            $sku = '';
            $option_signature = '';
            $product = Product::where('id', $request->input('product_id'))->where('shop_id', $user->id)->first();

            $sku .= $product->slug.'-';

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                    'errors' => 'Product not found'
                ], 404);
            }

            $arrayProductAttributeValues = [];

            foreach ($array_product_attribute_value_ids as $product_attribute_value_id) {
                $product_attribute_value = ProductAttributeValue::with('attribute')->find($product_attribute_value_id);

                $arrayProductAttributeValues[$product_attribute_value_id] = $product_attribute_value;

                if (! $product_attribute_value) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product attribute value not found',
                        'errors' => 'Product attribute value not found'
                    ], 404);
                }
                // Check if this is the last element in the loop to determine if we should append '-' or not
                if ($product_attribute_value_id === end($array_product_attribute_value_ids)) {
                    $sku .= $product_attribute_value->code;
                    $option_signature .= $product_attribute_value->attribute->code.':'.$product_attribute_value->code.';';
                } else {
                    $sku .= $product_attribute_value->code.'-';
                    $option_signature .= $product_attribute_value->attribute->code.':'.$product_attribute_value->code.';';
                }
            }

            $checkUpdateProductVariant = ProductVariant::where('product_id', $product->id)->where('option_signature', $option_signature)->first();

            if ($checkUpdateProductVariant) {
                $result = $this->updateProductVariant($checkUpdateProductVariant, $request);

                return $result;
            }

            DB::beginTransaction();

            $insertProductVariant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $sku,
                'option_signature' => $option_signature,
                'price' => $request->input('price'),
                'stock' => $request->input('stock'),
            ]);

            $countProductVariantOptions = 0;
            foreach ($arrayProductAttributeValues as $product_attribute_value_id => $product_attribute_value) {
                $arrayInsert = [
                    'product_variant_id' => $insertProductVariant->id,
                    'product_attribute_id' => $product_attribute_value->attribute->id,
                    'product_attribute_value_id' => $product_attribute_value_id,
                ];

                $insertProductAttributeValueProductVariant = ProductVariantOption::create($arrayInsert);
                $countProductVariantOptions++;
            }

            if ($countProductVariantOptions !== count($arrayProductAttributeValues)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Error',
                    'errors' => 'Error inserting product variant options'
                ], 500);
            }

            DB::commit();
            $this->updateMinMaxPrice($product);
            return response()->json([
                'success' => true,
                'message' => 'Product variant created successfully',
                'data' => [
                    'product' => $product,
                    'product_variant' => $insertProductVariant,
                    'product_variant_options' => $countProductVariantOptions
                ]
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Update a product variant
     *
     * @param ProductVariant $productVariant
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProductVariant(ProductVariant $productVariant, Request $request)
    {
        try {

            $product_attribute_value_ids = $request->input('product_attribute_value_ids');
            $price = $request->input('price');
            $stock = $request->input('stock');

            DB::beginTransaction();
            $updateProductVariant = $productVariant->update([
                'price' => $price,
                'stock' => $stock,
            ]);

            DB::commit();

            $countProductVariantOptions = ProductVariantOption::where('product_variant_id', $productVariant->id)->count();

            $this->updateMinMaxPrice($productVariant->product);

            return response()->json([
                'success' => true,
                'message' => 'Product variant updated successfully',
                'data' => [
                    'product' => $productVariant->product,
                    'product_variant' => $productVariant,
                    'product_variant_options' => $countProductVariantOptions
                ]
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Add image to product variant
     *
     * @param Request $request     
     */
    #[BodyParameter('product_variant_id', description: 'Product variant ID.', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('image', description: 'Image.', type: 'string', example: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAOEAA')]
    public function addImageToProductVariant(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_variant_id' => 'required|uuid',
                'image' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $productVariant = ProductVariant::with('productImage')->where('id', $request->product_variant_id)->first();

            if (! $productVariant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product variant not found',
                ], 404);
            }

            $imageService = new ImageService();

            if (! $imageService->isValidBase64Image($request->image)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid base64 image for product variant image',
                ], 422);
            }

            $imagePath = $imageService->convertBase64ToImage($request->image, 'products', $productVariant->productImage->file_path ?? null);

            DB::beginTransaction();

            if ($imagePath) {
                $upsertProductVariantImage = ProductImage::updateOrCreate([
                    'id' => $productVariant->productImage->id ?? null,
                ], [
                    'product_id' => $productVariant->product_id,
                    'file_path' => $imagePath,
                ]);

                $updateProductVariant = $productVariant->update([
                    'product_image_id' => $upsertProductVariantImage->id,
                ]);

            }

            DB::commit();

            $result = ProductVariant::with('productImage')->where('id', $productVariant->id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Product variant image added successfully',
                'data' => $result,
            ]);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Add existing image to product variant
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[BodyParameter('product_variant_id', description: 'Product variant ID.', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('image_id', description: 'Image ID.', type: 'string', format: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function addExistingImageToProductVariant(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_variant_id' => 'required|uuid',
                'image_id' => 'required|uuid',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $productVariant = ProductVariant::with('productImage')->where('id', $request->product_variant_id)->first();

            if (! $productVariant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product variant not found',
                ], 404);
            }

            $image = ProductImage::find($request->image_id);

            if (! $image) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found',
                ], 404);
            }

            $updateProductVariant = $productVariant->update([
                'product_image_id' => $image->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Product variant image added successfully',
                'data' => $productVariant,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    private function updateMinMaxPrice(Product $product)
    {
        $productVariants = ProductVariant::where('product_id', $product->id)->get();

        $minPrice = $productVariants->min('price');
        $maxPrice = $productVariants->max('price');

        $product->min_price = $minPrice;
        $product->max_price = $maxPrice;
        $product->save();
    }

}
