<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShippingAddress;

class ProfileShippingController extends Controller
{
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

    public function store(Request $request)
    {
        try {
            $user = auth()->user();
            $user_id = $user->id;

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
                'countrry_id' => $request->countrry_id,
                // 'country_name' => $request->country_name,
                'state_id' => $request->state_id,
                // 'state_name' => $request->state_name,
                'city_id' => $request->city_id,
                // 'city_name' => $request->city_name,
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

    public function show(Request $request)
    {
        try {
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
}
