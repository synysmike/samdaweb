<?php

namespace App\Http\Controllers\API;

use App\Models\Shop;
use Illuminate\Http\Request;
use App\Services\WorldService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\BodyParameter;

class ShopController extends Controller
{
    /**
     * Get Shop
     * 
     * This endpoint is used to get the shop of the authenticated user.
     */
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

    /**
     * Create or Update Shop
     * 
     * This endpoint is used to create or update the shop of the authenticated user.
     */
    #[BodyParameter('name', description: 'Shop name.', type: 'string', example: 'Shop Name')]
    #[BodyParameter('phone', description: 'Shop phone number.', type: 'string', example: '081234567890')]
    #[BodyParameter('country_id', description: 'Country ID.', type: 'integer', example: 1)]
    #[BodyParameter('state_id', description: 'State ID.', type: 'integer', example: 1)]
    #[BodyParameter('city_id', description: 'City ID.', type: 'integer', example: 1)]
    #[BodyParameter('zip_code', description: 'Zip code.', type: 'string', example: '12345')]
    #[BodyParameter('description', description: 'Shop description.', type: 'string', example: 'Shop description')]
    public function storeShop(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:25',
                'country_id' => 'nullable|exists:countries,id',
                'state_id' => 'nullable|exists:states,id',
                'city_id' => 'nullable|exists:cities,id',
                'zip_code' => 'nullable|string|max:10',
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

            $worldService = new WorldService();
            $country = $worldService->getCountryById($request->country_id);
            $state = $worldService->getStateById($request->state_id);
            $city = $worldService->getCityById($request->city_id);

            $upsert = Shop::updateOrCreate([
                'id' => $user->id
            ], [
                'name' => $request->name,
                'phone' => $request->phone,
                'country_id' => $request->country_id,
                'country_name' => $country->name,
                'state_id' => $request->state_id,
                'state_name' => $state->name,
                'city_id' => $request->city_id,
                'city_name' => $city->name,
                'zip_code' => $request->zip_code,
                'description' => $request->description,
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

    /**
     * Verify Shop
     * 
     * This endpoint is used to verify the shop of the authenticated user. Only admin can verify shop.
     */
    #[BodyParameter('id', description: 'The ID of the shop.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('valid_verification', description: 'The verification status of the shop.', type: 'boolean', example: true)]
    public function verify(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:shops,id',
                'valid_verification' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = auth()->user();
            $role = $user->roles->first();
            if ($role->name !== 'admin') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to verify shop',
                ], 403);
            }

            $shop = Shop::find($request->id);
            if (! $shop) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Shop not found',
                ], 404);
            }

            $shop->valid_verification = $request->valid_verification;
            $shop->valid_by = $user->id;
            $shop->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Shop verified successfully',
                'data' => $shop
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify shop',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
