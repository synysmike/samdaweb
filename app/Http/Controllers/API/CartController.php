<?php

namespace App\Http\Controllers\API;

use App\Models\Cart;
use App\Models\Product;
use App\Models\CartItem;
use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Http\Controllers\Controller;
use App\Models\ProductVariantOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\BodyParameter;

class CartController extends Controller
{
    private const DEFAULT_CURRENCY = 'IDR';

    private const CART_ITEM_RELATIONS = [
        'items.product.images',
        'items.variant.productImage',
        'items.shop',
    ];

    /**
     * Get active cart
     *
     * Returns the authenticated user's active cart with items and summary.
     * If no active cart exists, returns an empty cart structure for the frontend.
     */
    public function getCart()
    {
        try {
            $user = auth()->user();
            $cart = $this->findActiveCart($user->id);

            if (! $cart) {
                return response()->json([
                    'success' => true,
                    'message' => 'No active cart found',
                    'data' => $this->emptyCartPayload($user->id),
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cart fetched successfully',
                'data' => $this->formatCartPayload($cart),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get cart',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Show cart by ID
     *
     * @bodyParam id uuid required The cart ID. Example: 123e4567-e89b-12d3-a456-426614174000
     */
    #[BodyParameter('id', description: 'The cart ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function showCart(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid|exists:carts,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $cart = $this->findUserCart(auth()->id(), $request->id);

            if (! $cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cart fetched successfully',
                'data' => $this->formatCartPayload($cart),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to show cart',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Create or update cart metadata
     *
     * Creates an active cart if the user does not have one.
     *
     * @bodyParam id uuid optional The cart ID (update only). Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam currency string optional Currency code. Example: IDR
     * @bodyParam notes string optional Cart notes. Example: Please gift wrap
     */
    #[BodyParameter('id', description: 'The cart ID. Omit to create or update the active cart.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('currency', description: 'Currency code.', type: 'string', example: 'IDR')]
    #[BodyParameter('notes', description: 'Cart notes.', type: 'string', example: 'Please gift wrap')]
    public function storeCart(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|uuid|exists:carts,id',
                'currency' => 'nullable|string|max:50',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = auth()->user();

            if ($request->filled('id')) {
                $cart = $this->findUserCart($user->id, $request->id);

                if (! $cart) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cart not found',
                    ], 404);
                }

                if ($cart->status !== 'active') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Only active carts can be updated',
                    ], 422);
                }

                $cart->update([
                    'currency' => $request->currency ?? $cart->currency,
                    'notes' => $request->has('notes') ? $request->notes : $cart->notes,
                ]);
            } else {
                $cart = $this->getOrCreateActiveCart($user->id, $request->currency);
                $cart->update([
                    'currency' => $request->currency ?? $cart->currency,
                    'notes' => $request->has('notes') ? $request->notes : $cart->notes,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cart saved successfully',
                'data' => $this->formatCartPayload($cart->fresh()->load(self::CART_ITEM_RELATIONS)),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save cart',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Add or update a cart item
     *
     * Adds a product variant to the cart or updates its quantity.
     * Unique per cart + variant (same variant updates quantity when sent again).
     *
     * @bodyParam id uuid optional Cart item ID (update by item id). Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam product_id uuid required Product ID. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam variant_id uuid required Product variant ID. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam quantity integer required Quantity (min 1). Example: 2
     */
    #[BodyParameter('id', description: 'Cart item ID. Use to update quantity of an existing line item.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('product_id', description: 'Product ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('variant_id', description: 'Product variant ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('quantity', description: 'Quantity.', type: 'integer', example: 1)]
    public function storeCartItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|uuid|exists:cart_items,id',
                'product_id' => 'required_without:id|uuid|exists:products,id',
                'variant_id' => 'required_without:id|uuid|exists:product_variants,id',
                'quantity' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = auth()->user();
            $cart = $this->getOrCreateActiveCart($user->id);

            DB::beginTransaction();

            if ($request->filled('id')) {
                $cartItem = CartItem::where('id', $request->id)
                    ->where('cart_id', $cart->id)
                    ->first();

                if (! $cartItem) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Cart item not found',
                    ], 404);
                }

                $variant = ProductVariant::find($cartItem->variant_id);
                $stockError = $this->validateStock($variant, $request->quantity);

                if ($stockError) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => $stockError,
                    ], 422);
                }

                $cartItem->update(['quantity' => $request->quantity]);
            } else {
                $product = Product::where('id', $request->product_id)
                    ->where('is_active', true)
                    ->where('is_visible', true)
                    ->first();

                if (! $product) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Product not found or not available',
                    ], 404);
                }

                $variant = ProductVariant::where('id', $request->variant_id)
                    ->where('product_id', $product->id)
                    ->first();

                if (! $variant) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Product variant not found for this product',
                    ], 404);
                }

                $stockError = $this->validateStock($variant, $request->quantity);

                if ($stockError) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => $stockError,
                    ], 422);
                }

                $existingItem = CartItem::where('cart_id', $cart->id)
                    ->where('variant_id', $variant->id)
                    ->first();

                $itemPayload = $this->buildCartItemPayload($cart, $product, $variant, $request->quantity);

                if ($existingItem) {
                    $existingItem->update($itemPayload);
                } else {
                    CartItem::create($itemPayload);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cart item saved successfully',
                'data' => $this->formatCartPayload($cart->fresh()->load(self::CART_ITEM_RELATIONS)),
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to save cart item',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single cart item
     *
     * @bodyParam id uuid required Cart item ID. Example: 123e4567-e89b-12d3-a456-426614174000
     */
    #[BodyParameter('id', description: 'Cart item ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function showCartItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid|exists:cart_items,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $cartItem = $this->findUserCartItem(auth()->id(), $request->id);

            if (! $cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found',
                ], 404);
            }

            $cartItem->load(['product.images', 'variant.productImage', 'shop']);

            return response()->json([
                'success' => true,
                'message' => 'Cart item fetched successfully',
                'data' => $cartItem,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to show cart item',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a cart item
     *
     * @bodyParam id uuid required Cart item ID. Example: 123e4567-e89b-12d3-a456-426614174000
     */
    #[BodyParameter('id', description: 'Cart item ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function deleteCartItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid|exists:cart_items,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = auth()->user();
            $cartItem = $this->findUserCartItem($user->id, $request->id);

            if (! $cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found',
                ], 404);
            }

            $cart = $cartItem->cart;
            $cartItem->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cart item removed successfully',
                'data' => $this->formatCartPayload($cart->fresh()->load(self::CART_ITEM_RELATIONS)),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove cart item',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear all items from the active cart
     *
     * @bodyParam id uuid optional Cart ID. Uses active cart when omitted.
     */
    #[BodyParameter('id', description: 'Cart ID. Omit to clear the active cart.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function clearCart(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|uuid|exists:carts,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = auth()->user();
            $cart = $request->filled('id')
                ? $this->findUserCart($user->id, $request->id)
                : $this->findActiveCart($user->id);

            if (! $cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart not found',
                ], 404);
            }

            $cart->items()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cart cleared successfully',
                'data' => $this->formatCartPayload($cart->fresh()->load(self::CART_ITEM_RELATIONS)),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cart',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a cart
     *
     * Only active carts owned by the user can be deleted.
     *
     * @bodyParam id uuid required Cart ID. Example: 123e4567-e89b-12d3-a456-426614174000
     */
    #[BodyParameter('id', description: 'Cart ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function deleteCart(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid|exists:carts,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $cart = $this->findUserCart(auth()->id(), $request->id);

            if (! $cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart not found',
                ], 404);
            }

            if ($cart->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only active carts can be deleted',
                ], 422);
            }

            $cart->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cart deleted successfully',
                'data' => null,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete cart',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    private function findActiveCart(string $userId): ?Cart
    {
        return Cart::with(self::CART_ITEM_RELATIONS)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }

    private function findUserCart(string $userId, string $cartId): ?Cart
    {
        return Cart::with(self::CART_ITEM_RELATIONS)
            ->where('id', $cartId)
            ->where('user_id', $userId)
            ->first();
    }

    private function findUserCartItem(string $userId, string $cartItemId): ?CartItem
    {
        return CartItem::where('id', $cartItemId)
            ->whereHas('cart', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->first();
    }

    private function getOrCreateActiveCart(string $userId, ?string $currency = null): Cart
    {
        $cart = Cart::where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if ($cart) {
            return $cart;
        }

        return Cart::create([
            'user_id' => $userId,
            'currency' => $currency ?? self::DEFAULT_CURRENCY,
            'status' => 'active',
        ]);
    }

    private function buildCartItemPayload(Cart $cart, Product $product, ProductVariant $variant, int $quantity): array
    {
        return [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'shop_id' => $product->shop_id,
            'quantity' => $quantity,
            'unit_price' => $variant->price,
            'currency' => $cart->currency ?? self::DEFAULT_CURRENCY,
            'title_snapshot' => $product->title,
            'sku_snapshot' => $variant->sku,
            'option_snapshot' => $this->buildOptionSnapshot($variant),
        ];
    }

    private function buildOptionSnapshot(ProductVariant $variant): array
    {
        return ProductVariantOption::with(['productAttribute', 'productAttributeValue'])
            ->where('product_variant_id', $variant->id)
            ->get()
            ->map(function ($option) {
                return [
                    'attribute_id' => $option->product_attribute_id,
                    'attribute_name' => $option->productAttribute?->name,
                    'value_id' => $option->product_attribute_value_id,
                    'value' => $option->productAttributeValue?->value,
                ];
            })
            ->values()
            ->all();
    }

    private function validateStock(?ProductVariant $variant, int $quantity): ?string
    {
        if (! $variant || $variant->stock === null) {
            return null;
        }

        if ($quantity > $variant->stock) {
            return 'Insufficient stock. Available: '.$variant->stock;
        }

        return null;
    }

    private function formatCartPayload(Cart $cart): array
    {
        $cart->loadMissing(self::CART_ITEM_RELATIONS);

        $items = $cart->items;
        $lineTotals = $items->map(fn (CartItem $item) => (float) $item->unit_price * $item->quantity);

        $cartData = $cart->toArray();
        $cartData['summary'] = [
            'item_count' => $items->count(),
            'total_quantity' => $items->sum('quantity'),
            'subtotal' => $lineTotals->sum(),
            'currency' => $cart->currency,
        ];

        return $cartData;
    }

    private function emptyCartPayload(string $userId): array
    {
        return [
            'id' => null,
            'user_id' => $userId,
            'currency' => self::DEFAULT_CURRENCY,
            'status' => 'active',
            'notes' => null,
            'items' => [],
            'summary' => [
                'item_count' => 0,
                'total_quantity' => 0,
                'subtotal' => 0,
                'currency' => self::DEFAULT_CURRENCY,
            ],
        ];
    }
}
