<?php

namespace App\Http\Controllers\API;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\ProductVariantOption;
use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\BodyParameter;

class ProductVariantController extends Controller
{
    public function get(Request $request)
    {

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
