<?php

namespace App\Http\Controllers\API;

use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    public function add(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'product_id' => 'required|uuid|exists:products,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();

            // check if product already in wishlist
            $checkWishlistItem = WishlistItem::with('wishlist')
                    ->where('product_id', $request->product_id)
                    ->whereHas('wishlist', function ($query) use ($user) {
                        $query->where('user_id', $user->id);
                    })
                    ->first();

            if ($checkWishlistItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product already in wishlist',
                    'data' => $checkWishlistItem
                ], 400);
            }

            $wishlist = Wishlist::where('user_id', $user->id)->first();
            if (! $wishlist) {
                $wishlist = Wishlist::create([
                    'user_id' => $user->id,
                    'status' => 'active',
                    'last_seen_at' => now(),
                    'owner_type' => 'user',
                ]);
            }

            $wishlistItem = WishlistItem::create([
                'wishlist_id' => $wishlist->id,
                'product_id' => $request->product_id,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Product added to wishlist successfully',
                'data' => $wishlistItem
            ], 200);
        } catch (\Throwable $th) {            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add product to wishlist',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function show(Request $request)
    {
        try {
            $user = auth()->user();
            $wishlist = Wishlist::with(['wishlistItems' => function ($query) {
                $query->whereHas('product');
            }, 'wishlistItems.product'])
                ->where('user_id', $user->id)
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Wishlist fetched successfully',
                'data' => $wishlist,
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to show wishlist',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    public function remove(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id' => 'required|uuid|exists:products,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();

            $wishlist = Wishlist::where('user_id', $user->id)->first();

            DB::beginTransaction();
            $wishlistItem = WishlistItem::where('product_id', $request->product_id)
                                        ->where('wishlist_id', $wishlist->id)
                                        ->first();

            if (! $wishlistItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Product not found in wishlist',
                    'data' => []
                ], 404);
            }

            $wishlistItem->delete();

            $countWishlistItems = WishlistItem::where('wishlist_id', $wishlist->id)->count();
            if ($countWishlistItems == 0) {
                $wishlist->delete();
            }

            DB::commit();

            $wishlist = Wishlist::with('wishlistItems.product')
                    ->where('user_id', $user->id)
                    ->first();

            return response()->json([
                'status' => 'success',
                'message' => 'Product removed from wishlist successfully',
                'data' => $wishlist->wishlistItems ?? []
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove product from wishlist',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
