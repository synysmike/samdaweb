<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopController extends Controller
{
    public function getShop()
    {
        try {
            $user = auth()->user()->load('shop');
            if (! $user->shop) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Shop not found',
                ], 404);
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Shop retrieved successfully',
                'data' => $user->shop
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve shop',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function storeShop(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:25',
                'country_id' => 'nullable|string|max:255',
                'state_id' => 'nullable|string|max:255',
                'city_id' => 'nullable|string|max:255',
                'zip_code' => 'nullable|string|max:255',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();

            $upsert = Shop::updateOrCreate([
                'id' => $user->id
            ], [
                'name' => $request->name,
                'phone' => $request->phone,
                'country_id' => $request->country_id,
                'state_id' => $request->state_id,
                'city_id' => $request->city_id,
                'zip_code' => $request->zip_code,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $upsert->wasRecentlyCreated ? 'Shop created successfully' : 'Shop updated successfully',
                'data' => $upsert
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update shop',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
