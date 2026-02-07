<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Services\WorldService;
use App\Models\ShippingAddress;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Dedoc\Scramble\Attributes\BodyParameter;

class ProfileShippingController extends Controller
{
    /**
     * Get Shipping Addresses
     * 
     * This endpoint is used to get all shipping addresses of the authenticated user.
     */
    public function index()
    {
        try {
            $user = auth()->user();
            $shippingAddresses = ShippingAddress::where('user_id', $user->id)->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Shipping addresses fetched successfully',
                'data' => $shippingAddresses
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get shipping addresses',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Create or Update Shipping Address
     * 
     * This endpoint is used to create or update a shipping address of the authenticated user.
     */
    #[BodyParameter('id', description: 'Shipping address ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[BodyParameter('address_type', description: 'Shipping address type.', type: 'string', example: 'Home')]
    #[BodyParameter('address_title', description: 'Shipping address title.', type: 'string', example: 'Home')]
    #[BodyParameter('first_name', description: 'First name.', type: 'string', example: 'John')]
    #[BodyParameter('last_name', description: 'Last name.', type: 'string', example: 'Doe')]
    #[BodyParameter('email', description: 'Email address.', type: 'string', format: 'email', example: 'john.doe@example.com')]
    #[BodyParameter('phone_number', description: 'Phone number.', type: 'string', example: '081234567890')]
    #[BodyParameter('country_id', description: 'Country ID.', type: 'integer', example: 1)]
    #[BodyParameter('state_id', description: 'State ID.', type: 'integer', example: 1)]
    #[BodyParameter('city_id', description: 'City ID.', type: 'integer', example: 1)]
    #[BodyParameter('zip_code', description: 'Zip code.', type: 'string', example: '12345')]
    #[BodyParameter('address_description', description: 'Address description.', type: 'string', example: '123 Main St, Anytown, USA, 12345')]
    public function store(Request $request)
    {
        try {
            $user = auth()->user();
            $user_id = $user->id;
            
            $worldService = new WorldService();        

            $validator = Validator::make($request->all(), [
                'id' => 'nullable|uuid',
                'address_type' => 'required|string|max:255',
                'address_title' => 'required|string|max:255',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone_number' => 'required|string|max:255',
                'country_id' => 'nullable|integer',
                'state_id' => 'nullable|integer',
                'city_id' => 'nullable|integer',
                'zip_code' => 'nullable|string',                
                // @example Example : 123 Main St, Anytown, USA, 12345
                'address_description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $country_name = $worldService->getCountryById($request->country_id)->name;
            $state_name = $worldService->getStateById($request->state_id)->name;
            $city_name = $worldService->getCityById($request->city_id)->name;

            $shippingAddress = ShippingAddress::updateOrCreate([
                'id' => $request->id,
                'user_id' => $user_id,
            ], [
                'address_type' => $request->address_type,
                'address_title' => $request->address_title,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'country_id' => $request->country_id,
                'country_name' => $country_name,
                'state_id' => $request->state_id,
                'state_name' => $state_name,
                'city_id' => $request->city_id,
                'city_name' => $city_name,
                'zip_code' => $request->zip_code,
                'address_description' => $request->address_description,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $shippingAddress->wasRecentlyCreated ? 'Shipping address created successfully' : 'Shipping address updated successfully',
                'data' => $shippingAddress
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create shipping address',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Show Shipping Address
     * 
     * This endpoint is used to show a shipping address of the authenticated user.
     */
    #[BodyParameter('id', description: 'Shipping address ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function show(Request $request)
    {
        try {        
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $id = $request->id;
            $user = auth()->user();
            $user_id = $user->id;

            $shippingAddress = ShippingAddress::where('id', $id)->where('user_id', $user_id)->first();
            return response()->json([
                'status' => 'success',
                'message' => 'Shipping address fetched successfully',
                'data' => $shippingAddress
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get shipping address',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Shipping Address
     * 
     * This endpoint is used to delete a shipping address of the authenticated user.
     */
    #[BodyParameter('id', description: 'Shipping address ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function delete(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|uuid',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $id = $request->id;
            $user = auth()->user();
            $user_id = $user->id;

            $shippingAddress = ShippingAddress::where('id', $id)->where('user_id', $user_id)->first();
            if (!$shippingAddress) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Shipping address not found',
                ], 404);
            }

            $shippingAddress->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Shipping address deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete shipping address',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
