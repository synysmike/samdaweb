<?php

namespace App\Http\Controllers\API;

use App\Models\City;
use App\Models\State;
use App\Models\Country;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class WorldController extends Controller
{
    public function countries()
    {
        try {
            $countries = Country::all();
            return response()->json([
                'status' => 'success',
                'message' => 'Countries fetched successfully',
                'data' => $countries
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch countries',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function states(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'country_id' => 'required|exists:countries,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            $country_id = $request->country_id;
            $states = State::where('country_id', $country_id)->get();
            return response()->json([
                'status' => 'success',
                'message' => 'States fetched successfully',
                'data' => $states
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch states',
                'errors' => $th->getMessage()
            ], 500);
        }
    }

    public function cities(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'state_id' => 'required|exists:states,id',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            $state_id = $request->state_id;
            $cities = City::where('state_id', $state_id)->get();
            return response()->json([
                'status' => 'success',
                'message' => 'Cities fetched successfully',
                'data' => $cities
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch cities',
                'errors' => $th->getMessage()
            ], 500);
        }
    }
}
